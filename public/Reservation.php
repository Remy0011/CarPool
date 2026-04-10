<?php
date_default_timezone_set('Europe/Paris');
$pdo = getDB();
$message = '';
$messageType = '';
$editJourneyId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

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

    // 1. Parse the Start Date (from datetime-local input)
    $startDate = DateTime::createFromFormat('Y-m-d\TH:i', $startOfHours);
    if (!$startDate) {
        throw new InvalidArgumentException('Format de date de départ invalide.');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'reserve';

    try {
        if ($action === 'reserve' && isset($_POST['journey_id'])) {
            $journeyId = (int) $_POST['journey_id'];
            $passengerId = findPassengerId($pdo, $_SESSION['user_email']);

            if ($passengerId === null) {
                $message = 'Vous n\'etes pas enregistre comme passager.';
                $messageType = 'warning';
            } else {
                $check = $pdo->prepare('SELECT 1 FROM ASSO4 WHERE passengers_id = ? AND journeys_id = ?');
                $check->execute([$passengerId, $journeyId]);

                if ($check->fetch()) {
                    $message = 'Vous avez deja reserve ce trajet.';
                    $messageType = 'info';
                } else {
                    $places = $pdo->prepare('
                        SELECT c.place_available,
                               (SELECT COUNT(*) FROM ASSO4 a WHERE a.journeys_id = ?) AS reserved
                        FROM CONDUIRE cd
                        JOIN conductors c ON cd.conductors_id = c.conductors_id
                        WHERE cd.journeys_id = ?
                    ');
                    $places->execute([$journeyId, $journeyId]);
                    $placeInfo = $places->fetch();

                    if (!$placeInfo || (int) $placeInfo['reserved'] >= (int) $placeInfo['place_available']) {
                        $message = 'Plus de places disponibles pour ce trajet.';
                        $messageType = 'danger';
                    } else {
                        $pdo->beginTransaction();

                        $insert = $pdo->prepare('INSERT INTO ASSO4 (passengers_id, journeys_id) VALUES (?, ?)');
                        $insert->execute([$passengerId, $journeyId]);

                        // Get conductor_id for this journey
                        $getConductor = $pdo->prepare('SELECT conductors_id FROM CONDUIRE WHERE journeys_id = ?');
                        $getConductor->execute([$journeyId]);
                        $conductorInfo = $getConductor->fetch();

                        // Decrease available places by 1
                        $updatePlaces = $pdo->prepare('UPDATE conductors SET place_available = place_available - 1 WHERE conductors_id = ?');
                        $updatePlaces->execute([$conductorInfo['conductors_id']]);

                        $pdo->commit();

                        $message = 'Reservation effectuee avec succes.';
                        $messageType = 'success';
                    }
                }
            }
        } elseif ($action === 'cancel' && isset($_POST['journey_id'])) {
            $journeyId = (int) $_POST['journey_id'];
            $passengerId = findPassengerId($pdo, $_SESSION['user_email']);

            if ($passengerId === null) {
                $message = 'Vous n\'etes pas enregistre comme passager.';
                $messageType = 'warning';
            } else {
                $pdo->beginTransaction();

                $delete = $pdo->prepare('DELETE FROM ASSO4 WHERE passengers_id = ? AND journeys_id = ?');
                $delete->execute([$passengerId, $journeyId]);

                if ($delete->rowCount() > 0) {
                    // Get conductor_id for this journey
                    $getConductor = $pdo->prepare('SELECT conductors_id FROM CONDUIRE WHERE journeys_id = ?');
                    $getConductor->execute([$journeyId]);
                    $conductorInfo = $getConductor->fetch();

                    // Increase available places by 1
                    $updatePlaces = $pdo->prepare('UPDATE conductors SET place_available = place_available + 1 WHERE conductors_id = ?');
                    $updatePlaces->execute([$conductorInfo['conductors_id']]);

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

            // Set place_available to 4 for new journey
            $updatePlaces = $pdo->prepare('UPDATE conductors SET place_available = 4 WHERE conductors_id = ?');
            $updatePlaces->execute([$payload['conductors_id']]);

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
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = 'Erreur lors du traitement : ' . htmlspecialchars($e->getMessage());
        $messageType = 'danger';
    }
}

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
        'conductors_id' => $conductors[0]['conductors_id'] ?? 0,
        'start_of_hours' => '',
        'travel_time' => '00:30',
];

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
?>

<main class="container reservation-shell">
    <div class="reservation-header">
        <div>
            <h2 class="mb-2">Reservation de trajets</h2>
            <p class="reservation-subtitle mb-0">Creez, modifiez, supprimez et reservez des trajets depuis le meme tableau.</p>
        </div>
        <a class="btn btn-outline-secondary reservation-reset" href="index.php?page=Reservation">Nouveau trajet</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <section class="journey-editor-card">
        <div class="journey-editor-head">
            <h3 class="mb-1"><?= $journeyForm['journey_id'] > 0 ? 'Modifier un trajet' : 'Creer un trajet' ?></h3>
            <p class="mb-0">Renseignez Depart, Arrivee, Conducteur, Date/Heure depart et Duree. Les places seront automatiquement gerees (max 4).</p>
        </div>
        <form method="POST" class="journey-editor-grid">
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
                <input class="form-control" id="start_of_hours" name="start_of_hours" type="datetime-local" value="<?= htmlspecialchars($journeyForm['start_of_hours']) ?>" required>
            </div>

            <div class="form-group">
                <label for="travel_time">Duree</label>
                <input class="form-control" id="travel_time" name="travel_time" type="time" step="60" value="<?= htmlspecialchars($journeyForm['travel_time']) ?>" required>
            </div>

            <div class="journey-editor-actions">
                <button type="submit" class="btn btn-primary"><?= $journeyForm['journey_id'] > 0 ? 'Modifier' : 'Creer' ?></button>
                <?php if ($journeyForm['journey_id'] > 0): ?>
                    <a class="btn btn-outline-secondary" href="index.php?page=Reservation">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

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
                    <th>Places</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($journeys as $j):
                    $reserved = $reservationCounts[(int) $j['journeys_id']] ?? 0;
                    $remaining = (int) $j['place_available'] - $reserved;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($j['start']) ?></td>
                        <td><?= htmlspecialchars($j['final']) ?></td>
                        <td><?= htmlspecialchars($j['conducteur_nom']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($j['start_of_hours'])) ?></td>
                        <td><?= htmlspecialchars(substr($j['travel_time'], 0, 5)) ?> h</td>
                        <td>
                                <span class="badge <?= $remaining > 0 ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $remaining ?> / <?= (int) $j['place_available'] ?>
                                </span>
                        </td>
                        <td>
                            <div class="journey-action-stack">
                                <?php if ($remaining > 0): ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="reserve">
                                        <input type="hidden" name="journey_id" value="<?= (int) $j['journeys_id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Reserver</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled>Complet</button>
                                <?php endif; ?>

                                <a class="btn btn-outline-secondary btn-sm" href="index.php?page=Reservation&edit=<?= (int) $j['journeys_id'] ?>">Modifier</a>

                                <form method="POST" class="inline-form" onsubmit="return confirm('Supprimer ce trajet ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="journey_id" value="<?= (int) $j['journeys_id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>