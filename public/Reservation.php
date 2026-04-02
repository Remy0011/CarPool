<?php
$pdo = getDB();
$message = '';
$messageType = '';

// Handle reservation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['journey_id'])) {
    $journeyId = (int) $_POST['journey_id'];

    try {
        // Find the passenger record for the current user
        $stmt = $pdo->prepare('
            SELECT p.passengers_id
            FROM passengers p
            JOIN employees e ON p.employees_id = e.employees_id
            JOIN users u ON e.users_id = u.users_id
            WHERE u.user_email = ?
        ');
        $stmt->execute([$_SESSION['user_email']]);
        $passenger = $stmt->fetch();

        if (!$passenger) {
            $message = 'Vous n\'êtes pas enregistré comme passager.';
            $messageType = 'warning';
        } else {
            // Check if already reserved
            $check = $pdo->prepare('SELECT 1 FROM ASSO4 WHERE passengers_id = ? AND journeys_id = ?');
            $check->execute([$passenger['passengers_id'], $journeyId]);

            if ($check->fetch()) {
                $message = 'Vous avez déjà réservé ce trajet.';
                $messageType = 'info';
            } else {
                // Check available places
                $places = $pdo->prepare('
                    SELECT c.place_available,
                           (SELECT COUNT(*) FROM ASSO4 a WHERE a.journeys_id = ?) AS reserved
                    FROM CONDUIRE cd
                    JOIN conductors c ON cd.conductors_id = c.conductors_id
                    WHERE cd.journeys_id = ?
                ');
                $places->execute([$journeyId, $journeyId]);
                $placeInfo = $places->fetch();

                if (!$placeInfo || $placeInfo['reserved'] >= $placeInfo['place_available']) {
                    $message = 'Plus de places disponibles pour ce trajet.';
                    $messageType = 'danger';
                } else {
                    $insert = $pdo->prepare('INSERT INTO ASSO4 (passengers_id, journeys_id) VALUES (?, ?)');
                    $insert->execute([$passenger['passengers_id'], $journeyId]);
                    $message = 'Réservation effectuée avec succès !';
                    $messageType = 'success';
                }
            }
        }
    } catch (PDOException $e) {
        $message = 'Erreur lors de la réservation : ' . htmlspecialchars($e->getMessage());
        $messageType = 'danger';
    }
}

// Fetch available journeys with conductor info
$journeys = $pdo->query('
    SELECT journeys_id, start, final, travel_time, start_of_hours, end_of_hours,
           conducteur_nom, place_available
    FROM view_trajets_conducteurs
    ORDER BY start_of_hours ASC
')->fetchAll();

// Count current reservations per journey
$reservationCounts = [];
if ($journeys) {
    $ids = array_column($journeys, 'journeys_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $countStmt = $pdo->prepare("SELECT journeys_id, COUNT(*) AS cnt FROM ASSO4 WHERE journeys_id IN ($placeholders) GROUP BY journeys_id");
    $countStmt->execute($ids);
    foreach ($countStmt->fetchAll() as $row) {
        $reservationCounts[$row['journeys_id']] = $row['cnt'];
    }
}
?>

<div class="container my-4">
    <h2 class="mb-4">Réservation de trajets</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($journeys)): ?>
        <div class="alert alert-info">Aucun trajet disponible pour le moment.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Départ</th>
                        <th>Arrivée</th>
                        <th>Conducteur</th>
                        <th>Date/Heure départ</th>
                        <th>Durée</th>
                        <th>Places</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($journeys as $j):
                        $reserved = $reservationCounts[$j['journeys_id']] ?? 0;
                        $remaining = $j['place_available'] - $reserved;
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($j['start']) ?></td>
                            <td><?= htmlspecialchars($j['final']) ?></td>
                            <td><?= htmlspecialchars($j['conducteur_nom']) ?></td>
                            <td><?= htmlspecialchars($j['start_of_hours']) ?></td>
                            <td><?= htmlspecialchars($j['travel_time']) ?></td>
                            <td>
                                <span class="badge <?= $remaining > 0 ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $remaining ?> / <?= $j['place_available'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($remaining > 0): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="journey_id" value="<?= $j['journeys_id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Réserver</button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled>Complet</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
