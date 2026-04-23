<?php
date_default_timezone_set('Europe/Paris');
$pdo = getDB();
$message = '';
$messageType = '';
$editJourneyId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$confirmDeleteJourneyId = isset($_GET['confirm_delete']) ? (int) $_GET['confirm_delete'] : 0;

function findPassengerId(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare('
        SELECT p.passengers_id
        FROM passengers p
        JOIN users u ON p.users_id = u.users_id
        WHERE u.user_email = ?
    ');
    $stmt->execute([$email]);
    $passenger = $stmt->fetch();

    return $passenger ? (int) $passenger['passengers_id'] : null;
}

function findConductorId(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare('
        SELECT c.conductors_id
        FROM conductors c
        JOIN users u ON c.users_id = u.users_id
        WHERE u.user_email = ?
    ');
    $stmt->execute([$email]);
    $conductor = $stmt->fetch();

    return $conductor ? (int) $conductor['conductors_id'] : null;
}

function findUserId(PDO $pdo, string $email): ?int
{
    $stmt = $pdo->prepare('SELECT users_id FROM users WHERE user_email = ? LIMIT 1');
    $stmt->execute([$email]);
    $userId = $stmt->fetchColumn();

    return $userId !== false ? (int) $userId : null;
}

function ensurePassengerRole(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT passengers_id FROM passengers WHERE users_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $passengerId = $stmt->fetchColumn();

    if ($passengerId !== false) {
        return (int) $passengerId;
    }

    $insert = $pdo->prepare('INSERT INTO passengers (users_id) VALUES (?)');
    $insert->execute([$userId]);

    return (int) $pdo->lastInsertId();
}

function buildJourneyPayload(array $input): array
{
    $startLocation = trim($input['start'] ?? '');
    $finalLocation = trim($input['final'] ?? '');
    $durationInput = trim($input['travel_time'] ?? ''); // Format: HH:mm
    $startOfHours = trim($input['start_of_hours'] ?? ''); // Format: Y-m-d\TH:i from datetime-local
    $conductorsId = (int) ($input['conductors_id'] ?? 0);

    if ($startLocation === '' || $finalLocation === '' || $durationInput === '' || $startOfHours === '' || $conductorsId <= 0) {
        throw new InvalidArgumentException('Tous les champs sont obligatoires.');
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $durationInput)) {
        throw new InvalidArgumentException('La duree doit etre calculee automatiquement avant l enregistrement.');
    }

    // 1. Parse the Start Date (from datetime-local input)
    $startDate = DateTime::createFromFormat('Y-m-d\TH:i', $startOfHours);
    if (!$startDate) {
        throw new InvalidArgumentException('Format de date de départ invalide.');
    }

    $now = new DateTime('now');
    if ($startDate < $now) {
        throw new InvalidArgumentException('La date et l heure de depart doivent etre dans le futur.');
    }

    // 2. Parse Duration (travel_time) and calculate End Date
    // We expect HH:mm from the time input
    list($hours, $minutes) = explode(':', $durationInput);
    $interval = new DateInterval("PT{$hours}H{$minutes}M");

    $endDate = clone $startDate;
    $endDate->add($interval);

    return [
            'start'          => $startLocation,
            'final'          => $finalLocation,
            'travel_time'    => $durationInput, // Stores as "00:30"
            'start_of_hours' => $startDate->format('Y-m-d H:i:s'), // DB Format
            'end_of_hours'   => $endDate->format('Y-m-d H:i:s'),   // Calculated
            'conductors_id'  => $conductorsId,
    ];
}

function getJourneyCapacityAndReservations(PDO $pdo, int $journeyId): ?array
{
    $stmt = $pdo->prepare('
        SELECT c.place_available AS capacity, COUNT(a.passengers_id) AS reserved
        FROM CONDUIRE cd
        JOIN conductors c ON cd.conductors_id = c.conductors_id
        LEFT JOIN ASSO4 a ON a.journeys_id = cd.journeys_id
        WHERE cd.journeys_id = ?
        GROUP BY c.place_available
        LIMIT 1
    ');
    $stmt->execute([$journeyId]);
    $result = $stmt->fetch();

    if (!$result) {
        return null;
    }

    $capacity = max(0, (int) $result['capacity']);
    $reserved = max(0, (int) $result['reserved']);

    return [
        'capacity' => $capacity,
        'reserved' => $reserved,
        'remaining' => max(0, $capacity - $reserved),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'reserve';
    $currentUserId = findUserId($pdo, $_SESSION['user_email']);
    $currentPassengerRole = findPassengerId($pdo, $_SESSION['user_email']);

    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);

        if ($action === 'reserve' && isset($_POST['journey_id'])) {
            $journeyId = (int) $_POST['journey_id'];
            $passengerId = $currentPassengerRole;

            if ($currentUserId === null) {
                $message = 'Utilisateur introuvable.';
                $messageType = 'warning';
            } else {
                if ($passengerId === null) {
                    $passengerId = ensurePassengerRole($pdo, $currentUserId);
                }

                $check = $pdo->prepare('SELECT 1 FROM ASSO4 WHERE passengers_id = ? AND journeys_id = ?');
                $check->execute([$passengerId, $journeyId]);

                if ($check->fetch()) {
                    $message = 'Vous avez deja reserve ce trajet.';
                    $messageType = 'info';
                } else {
                    $placeInfo = getJourneyCapacityAndReservations($pdo, $journeyId);

                    if (!$placeInfo || $placeInfo['remaining'] <= 0) {
                        $message = 'Plus de places disponibles pour ce trajet.';
                        $messageType = 'danger';
                    } else {
                        $pdo->beginTransaction();

                        $insert = $pdo->prepare('INSERT INTO ASSO4 (passengers_id, journeys_id) VALUES (?, ?)');
                        $insert->execute([$passengerId, $journeyId]);

                        $pdo->commit();

                        $message = 'Reservation effectuee avec succes.';
                        $messageType = 'success';
                    }
                }
            }
        } elseif ($action === 'cancel' && isset($_POST['journey_id'])) {
            $journeyId = (int) $_POST['journey_id'];
            $passengerId = $currentPassengerRole;

            if ($passengerId === null) {
                $message = 'Vous n\'etes pas enregistre comme passager.';
                $messageType = 'warning';
            } else {
                $pdo->beginTransaction();

                $delete = $pdo->prepare('DELETE FROM ASSO4 WHERE passengers_id = ? AND journeys_id = ?');
                $delete->execute([$passengerId, $journeyId]);

                if ($delete->rowCount() > 0) {
                    $pdo->commit();

                    $message = 'Reservation annulee avec succes.';
                    $messageType = 'success';
                } else {
                    $pdo->rollBack();
                    $message = 'Aucune reservation a annuler.';
                    $messageType = 'info';
                }
            }
        } elseif ($action === 'create') {
            $payload = buildJourneyPayload($_POST);

            $pdo->beginTransaction();

            $createJourney = $pdo->prepare('
                INSERT INTO journeys (travel_time, start, final, start_of_hours, end_of_hours)
                VALUES (?, ?, ?, ?, ?)
            ');
            $createJourney->execute([
                    $payload['travel_time'],
                    $payload['start'],
                    $payload['final'],
                    $payload['start_of_hours'],
                    $payload['end_of_hours'],
            ]);

            $journeyId = (int) $pdo->lastInsertId();

            $linkConductor = $pdo->prepare('INSERT INTO CONDUIRE (conductors_id, journeys_id) VALUES (?, ?)');
            $linkConductor->execute([$payload['conductors_id'], $journeyId]);

            $pdo->commit();

            $message = 'Trajet cree avec succes.';
            $messageType = 'success';
        } elseif ($action === 'update' && isset($_POST['journey_id'])) {
            $journeyId = (int) $_POST['journey_id'];
            $payload = buildJourneyPayload($_POST);

            $pdo->beginTransaction();

            $updateJourney = $pdo->prepare('
                UPDATE journeys
                SET travel_time = ?, start = ?, final = ?, start_of_hours = ?, end_of_hours = ?
                WHERE journeys_id = ?
            ');
            $updateJourney->execute([
                    $payload['travel_time'],
                    $payload['start'],
                    $payload['final'],
                    $payload['start_of_hours'],
                    $payload['end_of_hours'],
                    $journeyId,
            ]);

            $updateLink = $pdo->prepare('UPDATE CONDUIRE SET conductors_id = ? WHERE journeys_id = ?');
            $updateLink->execute([$payload['conductors_id'], $journeyId]);

            $pdo->commit();

            $message = 'Trajet modifie avec succes.';
            $messageType = 'success';
            $editJourneyId = 0;
        } elseif ($action === 'delete' && isset($_POST['journey_id'])) {
            $journeyId = (int) $_POST['journey_id'];
            $deleteJourney = $pdo->prepare('DELETE FROM journeys WHERE journeys_id = ?');
            $deleteJourney->execute([$journeyId]);

            $message = 'Trajet supprime avec succes.';
            $messageType = 'warning';
            $editJourneyId = 0;
        }
    } catch (InvalidArgumentException $e) {
        $message = $e->getMessage();
        $messageType = 'warning';
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Reservation error: ' . $e->getMessage());
        $message = 'Une erreur technique est survenue. Reessayez plus tard.';
        $messageType = 'danger';
    }
}

$currentPassengerId = findPassengerId($pdo, $_SESSION['user_email']);
$currentConductorId = findConductorId($pdo, $_SESSION['user_email']);

$conductors = $pdo->query('
    SELECT c.conductors_id, c.place_available, u.user_name
    FROM conductors c
    JOIN users u ON c.users_id = u.users_id
    ORDER BY u.user_name ASC
')->fetchAll();

$journeys = $pdo->query('
    SELECT j.journeys_id, j.start, j.final, j.travel_time, j.start_of_hours, j.end_of_hours,
           u.user_name AS conducteur_nom, c.place_available, c.conductors_id
    FROM journeys j
    JOIN CONDUIRE cd ON j.journeys_id = cd.journeys_id
    JOIN conductors c ON cd.conductors_id = c.conductors_id
    JOIN users u ON c.users_id = u.users_id
    ORDER BY j.start_of_hours ASC
')->fetchAll();

$reservationCounts = [];
if ($journeys) {
    $ids = array_column($journeys, 'journeys_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $countStmt = $pdo->prepare("SELECT journeys_id, COUNT(*) AS cnt FROM ASSO4 WHERE journeys_id IN ($placeholders) GROUP BY journeys_id");
    $countStmt->execute($ids);
    foreach ($countStmt->fetchAll() as $row) {
        $reservationCounts[(int) $row['journeys_id']] = (int) $row['cnt'];
    }
}

$journeyForm = [
        'journey_id' => 0,
        'start' => '',
        'final' => '',
        'conductors_id' => $currentConductorId ?: ($conductors[0]['conductors_id'] ?? 0),
        'start_of_hours' => '',
        'travel_time' => '00:30',
];

$myReservations = [];
if ($currentPassengerId !== null && $journeys) {
    $reservedByMeStmt = $pdo->prepare('SELECT journeys_id FROM ASSO4 WHERE passengers_id = ?');
    $reservedByMeStmt->execute([$currentPassengerId]);

    foreach ($reservedByMeStmt->fetchAll() as $row) {
        $myReservations[(int) $row['journeys_id']] = true;
    }
}

foreach ($journeys as $journey) {
    if ((int) $journey['journeys_id'] === $editJourneyId) {
        $journeyForm = [
                'journey_id' => (int) $journey['journeys_id'],
                'start' => $journey['start'],
                'final' => $journey['final'],
                'conductors_id' => (int) $journey['conductors_id'],
                'start_of_hours' => date('Y-m-d\TH:i', strtotime($journey['start_of_hours'])),
                'travel_time' => substr($journey['travel_time'], 0, 5),
        ];
        break;
    }
}

$journeyPendingDelete = null;
foreach ($journeys as $journey) {
    if ((int) $journey['journeys_id'] === $confirmDeleteJourneyId) {
        $journeyPendingDelete = $journey;
        break;
    }
}

$showJourneyForm = $journeyForm['journey_id'] > 0 || (isset($_GET['show_form']) && $_GET['show_form'] === '1');
?>

<main class="container reservation-shell<?= $journeyPendingDelete ? ' reservation-shell-has-overlay' : '' ?>">
    <div class="reservation-header">
        <div>
            <h2 class="mb-2">Reservation de trajets</h2>
            <p class="reservation-subtitle mb-0">Creez, modifiez, supprimez et reservez des trajets depuis le meme tableau.</p>
        </div>
        <a class="btn btn-outline-secondary reservation-reset" href="index.php?page=Reservation&show_form=1">Nouveau trajet</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="alert alert-info">Tous les comptes peuvent reserver comme passager et gerer les trajets depuis cette page.</div>

    <?php if ($showJourneyForm): ?>
        <section class="journey-editor-card">
            <div class="journey-editor-head">
                <h3 class="mb-1"><?= $journeyForm['journey_id'] > 0 ? 'Modifier un trajet' : 'Creer un trajet' ?></h3>
                <p class="mb-0">Renseignez Depart, Arrivee et Date/Heure depart. La distance en miles et la duree sont calculees automatiquement.</p>
            </div>
            <form method="POST" class="journey-editor-grid">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="<?= $journeyForm['journey_id'] > 0 ? 'update' : 'create' ?>">
                <?php if ($journeyForm['journey_id'] > 0): ?>
                    <input type="hidden" name="journey_id" value="<?= $journeyForm['journey_id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="start">Depart</label>
                    <input class="form-control" id="start" name="start" type="text" value="<?= htmlspecialchars($journeyForm['start']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="final">Arrivee</label>
                    <input class="form-control" id="final" name="final" type="text" value="<?= htmlspecialchars($journeyForm['final']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="conductors_id">Conducteur</label>
                    <select class="form-select journey-select" id="conductors_id" name="conductors_id" required>
                        <?php foreach ($conductors as $conductor): ?>
                            <option value="<?= (int) $conductor['conductors_id'] ?>" <?= (int) $conductor['conductors_id'] === (int) $journeyForm['conductors_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($conductor['user_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="start_of_hours">Date/Heure depart</label>
                    <input class="form-control" id="start_of_hours" name="start_of_hours" type="datetime-local" value="<?= htmlspecialchars($journeyForm['start_of_hours']) ?>" min="<?= date('Y-m-d\TH:i') ?>" required>
                </div>

                <div class="form-group">
                    <label for="distance_miles">Distance estimee</label>
                    <input class="form-control" id="distance_miles" type="text" value="" placeholder="Calculee automatiquement" readonly>
                </div>

                <div class="form-group">
                    <label for="travel_time_display">Duree estimee</label>
                    <input class="form-control" id="travel_time_display" type="text" value="<?= htmlspecialchars($journeyForm['travel_time']) ?> h" readonly>
                    <input type="hidden" id="travel_time" name="travel_time" value="<?= htmlspecialchars($journeyForm['travel_time']) ?>">
                </div>

                <div class="journey-editor-actions">
                    <button type="submit" class="btn btn-primary" id="journey-submit"><?= $journeyForm['journey_id'] > 0 ? 'Modifier' : 'Creer' ?></button>
                    <a class="btn btn-outline-secondary" href="index.php?page=Reservation">Annuler</a>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($journeyPendingDelete): ?>
        <section class="reservation-confirm-wrap" aria-modal="true" role="dialog">
            <a class="reservation-confirm-backdrop" href="index.php?page=Reservation" aria-label="Fermer la confirmation"></a>
            <article class="welcome-card journey-welcome-card reservation-confirm-card">
                <div class="welcome-avatar journey-avatar reservation-confirm-avatar">!</div>
                <div class="journey-summary-top">
                    <div>
                        <h3 class="journey-card-title mb-1">Supprimer ce trajet ?</h3>
                        <p class="journey-card-subtitle mb-0">Cette action retirera definitivement le trajet de la liste des reservations.</p>
                    </div>
                    <span class="journey-availability is-full">Suppression</span>
                </div>

                <div class="journey-form-grid">
                    <div class="journey-field">
                        <label>Depart</label>
                        <div class="journey-field-box"><?= htmlspecialchars($journeyPendingDelete['start']) ?></div>
                    </div>
                    <div class="journey-field">
                        <label>Arrivee</label>
                        <div class="journey-field-box"><?= htmlspecialchars($journeyPendingDelete['final']) ?></div>
                    </div>
                    <div class="journey-field">
                        <label>Conducteur</label>
                        <div class="journey-field-box"><?= htmlspecialchars($journeyPendingDelete['conducteur_nom']) ?></div>
                    </div>
                    <div class="journey-field">
                        <label>Date/Heure depart</label>
                        <div class="journey-field-box"><?= date('d/m/Y H:i', strtotime($journeyPendingDelete['start_of_hours'])) ?></div>
                    </div>
                    <div class="journey-field">
                        <label>Duree</label>
                        <div class="journey-field-box"><?= htmlspecialchars(substr((string) $journeyPendingDelete['travel_time'], 0, 5)) ?> h</div>
                    </div>
                    <div class="journey-field">
                        <label>Places</label>
                        <?php
                        $pendingReserved = $reservationCounts[(int) $journeyPendingDelete['journeys_id']] ?? 0;
                        $pendingTotalPlaces = max(0, (int) $journeyPendingDelete['place_available']);
                        $pendingRemaining = max(0, $pendingTotalPlaces - $pendingReserved);
                        ?>
                        <div class="journey-field-box"><?= $pendingRemaining ?> / <?= $pendingTotalPlaces ?></div>
                    </div>
                </div>

                <div class="journey-summary-actions">
                    <form method="POST" class="inline-form">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="journey_id" value="<?= (int) $journeyPendingDelete['journeys_id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm reservation-confirm-delete">Confirmer la suppression</button>
                    </form>
                    <a class="btn btn-outline-secondary btn-sm" href="index.php?page=Reservation">Annuler</a>
                </div>
            </article>
        </section>
    <?php endif; ?>

    <?php if (empty($journeys)): ?>
        <div class="alert alert-info">Aucun trajet disponible pour le moment.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                <tr>
                    <th>Depart</th>
                    <th>Arrivee</th>
                    <th>Conducteur</th>
                    <th>Date/Heure depart</th>
                    <th>Duree</th>
                    <th>Places disponibles</th>
                    <th>Ma reservation</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($journeys as $j):
                    $reserved = $reservationCounts[(int) $j['journeys_id']] ?? 0;
                    $totalPlaces = max(0, (int) $j['place_available']);
                    $remaining = max(0, $totalPlaces - $reserved);
                    $isReservedByMe = !empty($myReservations[(int) $j['journeys_id']]);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($j['start']) ?></td>
                        <td><?= htmlspecialchars($j['final']) ?></td>
                        <td><?= htmlspecialchars($j['conducteur_nom']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($j['start_of_hours'])) ?></td>
                        <td><?= htmlspecialchars(substr($j['travel_time'], 0, 5)) ?> h</td>
                        <td>
                                <span class="badge <?= $remaining > 0 ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $remaining ?> / <?= $totalPlaces ?>
                                </span>
                        </td>
                        <td>
                            <?php if ($isReservedByMe): ?>
                                <span class="badge bg-primary">Reserve</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border">Non reserve</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="journey-action-stack">
                                <form method="POST" class="inline-form">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="action" value="<?= $isReservedByMe ? 'cancel' : 'reserve' ?>">
                                    <input type="hidden" name="journey_id" value="<?= (int) $j['journeys_id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $isReservedByMe ? 'btn-warning' : 'btn-success' ?>">
                                        <?= $isReservedByMe ? 'Annuler' : 'Reserver' ?>
                                    </button>
                                </form>
                                <a class="btn btn-outline-secondary btn-sm" href="index.php?page=Reservation&edit=<?= (int) $j['journeys_id'] ?>">Modifier</a>
                                <a class="btn btn-outline-danger btn-sm" href="index.php?page=Reservation&confirm_delete=<?= (int) $j['journeys_id'] ?>">Supprimer</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
<script>
(() => {
    const startField = document.getElementById('start');
    const finalField = document.getElementById('final');
    const startOfHoursField = document.getElementById('start_of_hours');
    const distanceField = document.getElementById('distance_miles');
    const durationDisplayField = document.getElementById('travel_time_display');
    const durationField = document.getElementById('travel_time');
    const submitButton = document.getElementById('journey-submit');

    if (!startField || !finalField || !startOfHoursField || !distanceField || !durationDisplayField || !durationField) {
        return;
    }

    let debounceTimer = null;

    function getCurrentLocalDateTime() {
        const now = new Date();
        const offset = now.getTimezoneOffset();
        return new Date(now.getTime() - offset * 60000).toISOString().slice(0, 16);
    }

    function syncMinDateTime() {
        const minDateTime = getCurrentLocalDateTime();
        startOfHoursField.min = minDateTime;

        if (startOfHoursField.value && startOfHoursField.value < minDateTime) {
            startOfHoursField.value = minDateTime;
        }
    }

    function setPendingState(message) {
        distanceField.value = message;
        durationDisplayField.value = message;
        durationField.value = '';
        if (submitButton) {
            submitButton.disabled = true;
        }
    }

    function setComputedState(distanceMiles, durationMinutes) {
        const roundedMiles = Math.round(distanceMiles * 10) / 10;
        const totalMinutes = Math.max(1, Math.round(durationMinutes));
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;
        const safeHours = String(hours).padStart(2, '0');
        const safeMinutes = String(minutes).padStart(2, '0');

        distanceField.value = `${roundedMiles} miles`;
        durationDisplayField.value = `${safeHours}:${safeMinutes} h`;
        durationField.value = `${safeHours}:${safeMinutes}`;
        if (submitButton) {
            submitButton.disabled = false;
        }
    }

    function fallbackCoordinates(place) {
        const lookup = {
            paris: [2.3522, 48.8566],
            lyon: [4.8357, 45.7640],
            marseille: [5.3698, 43.2965],
            nancy: [6.1844, 48.6921],
            metz: [6.1757, 49.1193],
            lille: [3.0573, 50.6292],
            strasbourg: [7.7521, 48.5734],
            toulouse: [1.4442, 43.6047],
            bordeaux: [-0.5792, 44.8378],
        };

        return lookup[String(place || '').trim().toLowerCase()] || null;
    }

    async function geocodePlace(place) {
        const url = new URL('https://nominatim.openstreetmap.org/search');
        url.searchParams.set('format', 'json');
        url.searchParams.set('limit', '1');
        url.searchParams.set('q', place);

        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Geocoding indisponible');
        }

        const results = await response.json();
        if (!Array.isArray(results) || results.length === 0) {
            throw new Error(`Lieu introuvable : ${place}`);
        }

        return [Number(results[0].lon), Number(results[0].lat)];
    }

    async function fetchRouteMetrics(startCoordinates, endCoordinates) {
        const url = new URL(`https://router.project-osrm.org/route/v1/driving/${startCoordinates[0]},${startCoordinates[1]};${endCoordinates[0]},${endCoordinates[1]}`);
        url.searchParams.set('overview', 'false');

        const response = await fetch(url.toString());
        if (!response.ok) {
            throw new Error('Calcul d itineraire indisponible');
        }

        const data = await response.json();
        const route = data?.routes?.[0];
        if (!route) {
            throw new Error('Itineraire introuvable');
        }

        return {
            distanceMiles: Number(route.distance) * 0.000621371,
            durationMinutes: Number(route.duration) / 60,
        };
    }

    async function calculateRoute() {
        const start = startField.value.trim();
        const final = finalField.value.trim();

        if (start === '' || final === '') {
            setPendingState('Renseignez les villes');
            return;
        }

        setPendingState('Calcul en cours...');

        try {
            let startCoordinates;
            let endCoordinates;

            try {
                [startCoordinates, endCoordinates] = await Promise.all([
                    geocodePlace(start),
                    geocodePlace(final),
                ]);
            } catch (error) {
                startCoordinates = fallbackCoordinates(start);
                endCoordinates = fallbackCoordinates(final);
                if (!startCoordinates || !endCoordinates) {
                    throw error;
                }
            }

            const metrics = await fetchRouteMetrics(startCoordinates, endCoordinates);
            setComputedState(metrics.distanceMiles, metrics.durationMinutes);
        } catch (error) {
            setPendingState('Calcul impossible');
        }
    }

    function queueCalculation() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(calculateRoute, 400);
    }

    startField.addEventListener('input', queueCalculation);
    finalField.addEventListener('input', queueCalculation);
    startOfHoursField.addEventListener('focus', syncMinDateTime);
    startOfHoursField.addEventListener('input', syncMinDateTime);

    syncMinDateTime();
    window.setInterval(syncMinDateTime, 30000);

    if (startField.value.trim() !== '' && finalField.value.trim() !== '') {
        calculateRoute();
    } else {
        setPendingState('Renseignez les villes');
    }
})();
</script>
