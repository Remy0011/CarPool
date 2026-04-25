<?php
$name = htmlspecialchars($_SESSION['user_name']);
$email = htmlspecialchars($_SESSION['user_email']);
$initiale = mb_strtoupper(mb_substr($name, 0, 1));

$pdo = getDB();
$journeys = $pdo->query('
    SELECT j.journeys_id, j.start, j.final, j.travel_time, j.start_of_hours, j.end_of_hours,
           conductor_user.user_name AS conductor_name, c.place_available
    FROM journeys j
    JOIN CONDUIRE cd ON j.journeys_id = cd.journeys_id
    JOIN conductors c ON cd.conductors_id = c.conductors_id
    JOIN users conductor_user ON c.users_id = conductor_user.users_id
    ORDER BY j.start_of_hours ASC
')->fetchAll(PDO::FETCH_ASSOC);

$reservationCounts = [];
if ($journeys) {
    $ids = array_column($journeys, 'journeys_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $countStmt = $pdo->prepare("SELECT journeys_id, COUNT(*) AS cnt FROM ASSO4 WHERE journeys_id IN ($placeholders) GROUP BY journeys_id");
    $countStmt->execute($ids);

    foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $reservationCounts[(int) $row['journeys_id']] = (int) $row['cnt'];
    }
}

$journeyTotal = count($journeys);
?>

<main class="container home-shell">
    <section class="home-hero">
        <div class="welcome-card home-main-welcome text-center">
            <div class="welcome-avatar">
                <?= $initiale ?>
            </div>
            <h2 class="fw-semibold mb-1">Bienvenue, <?= $name ?> !</h2>
            <p class="text-muted small mb-3"><?= $email ?></p>
            <div class="welcome-panel mb-0">
                <p class="small text-secondary mb-0">
                    Il y a <?= $journeyTotal ?> trajet<?= $journeyTotal > 1 ? 's' : '' ?> visible<?= $journeyTotal > 1 ? 's' : '' ?> sur la page d'accueil.
                    Chaque creation faite dans Reservation apparait ici sur sa propre carte.
                </p>
            </div>
        </div>
    </section>

    <section class="home-journeys">
        <div class="home-section-head">
            <div>
                <p class="map-eyebrow mb-2">Trajets disponibles</p>
                <h3 class="mb-1">Une carte par trajet</h3>
                <p class="reservation-subtitle mb-0">Les informations ci-dessous sont synchronisees avec la page Reservation.</p>
            </div>
            <a class="btn btn-outline-secondary" href="index.php?page=Reservation">Voir tous les trajets</a>
        </div>

        <?php if (empty($journeys)): ?>
            <div class="alert alert-info">Aucun trajet disponible pour le moment.</div>
        <?php else: ?>
            <div class="journey-card-grid">
                <?php foreach ($journeys as $index => $journey):
                    $reserved = $reservationCounts[(int) $journey['journeys_id']] ?? 0;
                    $remaining = max(0, (int) $journey['place_available']);
                    $totalPlaces = $remaining + $reserved;
                    $cardNumber = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                    $journeyInitial = mb_strtoupper(mb_substr($journey['start'], 0, 1));
                    ?>
                    <article class="welcome-card journey-welcome-card">
                        <div class="welcome-avatar journey-avatar">
                            <?= htmlspecialchars($journeyInitial) ?>
                        </div>
                        <div class="journey-summary-top">
                            <div>
                                <h4 class="journey-card-title mb-1">Trajet <?= $cardNumber ?></h4>
                                <p class="journey-card-subtitle mb-0">Chaque trajet cree dans Reservation est affiche ici avec son etat actuel.</p>
                            </div>
                            <span class="journey-availability <?= $remaining > 0 ? 'is-open' : 'is-full' ?>">
                                <?= $remaining > 0 ? $remaining . ' places dispo' : 'Complet' ?>
                            </span>
                        </div>

                        <div class="journey-form-grid">
                            <div class="journey-field">
                                <label>Depart</label>
                                <div class="journey-field-box"><?= htmlspecialchars($journey['start']) ?></div>
                            </div>
                            <div class="journey-field">
                                <label>Arrivee</label>
                                <div class="journey-field-box"><?= htmlspecialchars($journey['final']) ?></div>
                            </div>
                            <div class="journey-field">
                                <label>Conducteur</label>
                                <div class="journey-field-box"><?= htmlspecialchars($journey['conductor_name']) ?></div>
                            </div>
                            <div class="journey-field">
                                <label>Date/Heure depart</label>
                                <div class="journey-field-box"><?= date('d/m/Y H:i', strtotime($journey['start_of_hours'])) ?></div>
                            </div>
                            <div class="journey-field">
                                <label>Duree</label>
                                <div class="journey-field-box"><?= htmlspecialchars(substr((string) $journey['travel_time'], 0, 5)) ?> h</div>
                            </div>
                            <div class="journey-field">
                                <label>Places disponibles</label>
                                <div class="journey-field-box"><?= $remaining ?> / <?= $totalPlaces ?></div>
                            </div>
                        </div>

                        <div class="journey-summary-actions">
                            <a class="btn btn-primary btn-sm" href="index.php?page=Reservation">Voir les details</a>
                            <a class="btn btn-outline-secondary btn-sm journey-map-link" href="index.php?page=Map">Voir la carte</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
