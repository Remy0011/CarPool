<?php
$pdo = getDB();

$profileStmt = $pdo->prepare('
    SELECT u.users_id, u.user_name, u.user_email,
           c.conductors_id, c.place_available,
           p.passengers_id
    FROM users u
    LEFT JOIN conductors c ON c.users_id = u.users_id
    LEFT JOIN passengers p ON p.users_id = u.users_id
    WHERE u.user_email = ?
    LIMIT 1
');
$profileStmt->execute([$_SESSION['user_email']]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    ?>
    <main class="container py-4">
        <div class="alert alert-danger">Impossible de charger le profil depuis la base de donnees.</div>
    </main>
    <?php
    return;
}

$createdJourneysStmt = $pdo->prepare('
    SELECT COUNT(*) 
    FROM CONDUIRE cd
    JOIN conductors c ON c.conductors_id = cd.conductors_id
    WHERE c.users_id = ?
');
$createdJourneysStmt->execute([$profile['users_id']]);
$createdJourneys = (int) $createdJourneysStmt->fetchColumn();

$reservedJourneys = 0;
if (!empty($profile['passengers_id'])) {
    $reservedJourneysStmt = $pdo->prepare('SELECT COUNT(*) FROM ASSO4 WHERE passengers_id = ?');
    $reservedJourneysStmt->execute([$profile['passengers_id']]);
    $reservedJourneys = (int) $reservedJourneysStmt->fetchColumn();
}

$recentJourneysStmt = $pdo->prepare('
    SELECT j.start, j.final, j.start_of_hours, j.end_of_hours, j.travel_time
    FROM journeys j
    JOIN CONDUIRE cd ON cd.journeys_id = j.journeys_id
    JOIN conductors c ON c.conductors_id = cd.conductors_id
    WHERE c.users_id = ?
    ORDER BY j.start_of_hours ASC
    LIMIT 5
');
$recentJourneysStmt->execute([$profile['users_id']]);
$recentJourneys = $recentJourneysStmt->fetchAll(PDO::FETCH_ASSOC);

$initial = mb_strtoupper(mb_substr($profile['user_name'], 0, 1));
$roles = [];
if (!empty($profile['conductors_id'])) {
    $roles[] = 'Conducteur';
}
if (!empty($profile['passengers_id'])) {
    $roles[] = 'Passager';
}
if ($roles === []) {
    $roles[] = 'Utilisateur';
}
?>

<main class="container py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1">Profil</h1>
            <p class="text-muted mb-0">Informations chargees depuis la table `users` et vos relations metier.</p>
        </div>
        <a class="btn btn-primary" href="index.php?page=Parametre">Modifier mes parametres</a>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center">
                    <div class="rounded-circle bg-dark text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 88px; height: 88px; font-size: 2rem;">
                        <?= htmlspecialchars($initial) ?>
                    </div>
                    <h2 class="h4 mb-1"><?= htmlspecialchars($profile['user_name']) ?></h2>
                    <p class="text-muted mb-3"><?= htmlspecialchars($profile['user_email']) ?></p>
                    <p class="mb-3">
                        <?php foreach ($roles as $role): ?>
                            <span class="badge text-bg-secondary me-1"><?= htmlspecialchars($role) ?></span>
                        <?php endforeach; ?>
                    </p>
                    <div class="text-start">
                        <p class="mb-2"><strong>ID utilisateur :</strong> <?= (int) $profile['users_id'] ?></p>
                        <p class="mb-0">
                            <strong>Places disponibles :</strong>
                            <?= $profile['place_available'] !== null ? (int) $profile['place_available'] : 'Non applicable' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <p class="text-muted mb-2">Trajets crees</p>
                            <p class="display-6 mb-0"><?= $createdJourneys ?></p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <p class="text-muted mb-2">Reservations</p>
                            <p class="display-6 mb-0"><?= $reservedJourneys ?></p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <p class="text-muted mb-2">Statut conducteur</p>
                            <p class="display-6 mb-0"><?= !empty($profile['conductors_id']) ? 'Oui' : 'Non' ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Mes trajets recents</h2>
                        <a class="btn btn-outline-secondary btn-sm" href="index.php?page=Reservation">Voir Reservation</a>
                    </div>

                    <?php if (empty($recentJourneys)): ?>
                        <div class="alert alert-light border mb-0">Aucun trajet conducteur trouve pour ce profil.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Depart</th>
                                    <th>Arrivee</th>
                                    <th>Depart prevu</th>
                                    <th>Duree</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recentJourneys as $journey): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($journey['start']) ?></td>
                                        <td><?= htmlspecialchars($journey['final']) ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($journey['start_of_hours']))) ?></td>
                                        <td><?= htmlspecialchars(substr((string) $journey['travel_time'], 0, 5)) ?> h</td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
