<?php
declare(strict_types=1);

$pdo = getDB();
$journeys = $pdo->query('
    SELECT j.journeys_id, j.start, j.final, j.start_of_hours, j.end_of_hours, j.travel_time,
           u.user_name AS conductor_name
    FROM journeys j
    JOIN CONDUIRE cd ON j.journeys_id = cd.journeys_id
    JOIN conductors c ON cd.conductors_id = c.conductors_id
    JOIN users u ON c.users_id = u.users_id
    ORDER BY j.start_of_hours ASC
')->fetchAll(PDO::FETCH_ASSOC);

$journeyOptions = array_map(
    static function (array $journey): array {
        return [
            'id' => (int) $journey['journeys_id'],
            'start' => (string) $journey['start'],
            'final' => (string) $journey['final'],
            'start_of_hours' => (string) $journey['start_of_hours'],
            'end_of_hours' => (string) $journey['end_of_hours'],
            'travel_time' => substr((string) $journey['travel_time'], 0, 5),
            'conductor_name' => (string) $journey['conductor_name'],
        ];
    },
    $journeys
);
?>
<main class="container map-shell">
    <section class="map-layout">
        <aside class="map-sidebar">
            <div class="map-sidebar-card">
                <p class="map-eyebrow">Suivi du trajet</p>
                <h2>Simulation de route</h2>
                <p class="map-copy">Choisissez un trajet pour afficher un itineraire bleu et faire avancer la voiture sur la carte.</p>

                <?php if (empty($journeyOptions)): ?>
                    <div class="alert alert-info mb-0">Aucun trajet disponible pour la carte.</div>
                <?php else: ?>
                    <div class="form-group">
                        <label for="journey-select">Trajet</label>
                        <select id="journey-select" class="form-select journey-select">
                            <?php foreach ($journeyOptions as $index => $journey): ?>
                                <option value="<?= (int) $journey['id'] ?>" <?= $index === 0 ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($journey['start'] . ' -> ' . $journey['final'] . ' (' . $journey['conductor_name'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <dl class="map-metrics">
                        <div>
                            <dt>Depart</dt>
                            <dd id="journey-start">-</dd>
                        </div>
                        <div>
                            <dt>Arrivee</dt>
                            <dd id="journey-final">-</dd>
                        </div>
                        <div>
                            <dt>Conducteur</dt>
                            <dd id="journey-conductor">-</dd>
                        </div>
                        <div>
                            <dt>Heure</dt>
                            <dd id="journey-start-time">-</dd>
                        </div>
                        <div>
                            <dt>Duree</dt>
                            <dd id="journey-duration">-</dd>
                        </div>
                        <div>
                            <dt>Arrivee prevue</dt>
                            <dd id="journey-end-time">-</dd>
                        </div>
                    </dl>

                    <div class="map-progress-card">
                        <div class="map-progress-head">
                            <span>Progression</span>
                            <strong id="journey-progress-label">0%</strong>
                        </div>
                        <div class="map-progress-track" aria-hidden="true">
                            <div id="journey-progress-bar" class="map-progress-bar"></div>
                        </div>
                    </div>

                    <div class="journey-action-stack map-controls">
                        <button id="route-play" type="button" class="btn btn-primary">Lancer</button>
                        <button id="route-reset" type="button" class="btn btn-outline-secondary">Revenir au depart</button>
                    </div>

                    <p id="map-status" class="map-status" role="status">Preparation de la carte...</p>
                <?php endif; ?>
            </div>
        </aside>

        <div class="map-stage">
            <div id="map" class="map-canvas"></div>
        </div>
    </section>
</main>

<script>
window.CARPOOL_JOURNEYS = <?= json_encode($journeyOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="/MapLibreLoader.js"></script>
