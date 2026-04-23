<?php
$pdo = getDB();

$successMessage = '';
$errorMessage = '';

$userStmt = $pdo->prepare('
    SELECT u.users_id, u.user_name, u.user_email, u.user_password,
           c.conductors_id, c.place_available,
           p.passengers_id
    FROM users u
    LEFT JOIN conductors c ON c.users_id = u.users_id
    LEFT JOIN passengers p ON p.users_id = u.users_id
    WHERE u.user_email = ?
    LIMIT 1
');
$userStmt->execute([$_SESSION['user_email']]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    ?>
    <main class="container py-4">
        <div class="alert alert-danger">Utilisateur introuvable dans la base de donnees.</div>
    </main>
    <?php
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_account') {
            $userName = trim($_POST['user_name'] ?? '');
            $userEmail = trim($_POST['user_email'] ?? '');

            if ($userName === '' || $userEmail === '') {
                throw new RuntimeException('Le nom et l\'email sont obligatoires.');
            }

            if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('L\'adresse email n\'est pas valide.');
            }

            $emailCheck = $pdo->prepare('SELECT users_id FROM users WHERE user_email = ? AND users_id <> ? LIMIT 1');
            $emailCheck->execute([$userEmail, $user['users_id']]);

            if ($emailCheck->fetch()) {
                throw new RuntimeException('Cette adresse email est deja utilisee.');
            }

            $updateUser = $pdo->prepare('UPDATE users SET user_name = ?, user_email = ? WHERE users_id = ?');
            $updateUser->execute([$userName, $userEmail, $user['users_id']]);

            $_SESSION['user_name'] = $userName;
            $_SESSION['user_email'] = $userEmail;
            $successMessage = 'Informations du compte mises a jour.';
        } elseif ($action === 'update_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                throw new RuntimeException('Tous les champs du mot de passe sont obligatoires.');
            }

            if (!password_verify($currentPassword, $user['user_password'])) {
                throw new RuntimeException('Le mot de passe actuel est incorrect.');
            }

            if (strlen($newPassword) < 6) {
                throw new RuntimeException('Le nouveau mot de passe doit contenir au moins 6 caracteres.');
            }

            if ($newPassword !== $confirmPassword) {
                throw new RuntimeException('La confirmation du mot de passe ne correspond pas.');
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePassword = $pdo->prepare('UPDATE users SET user_password = ? WHERE users_id = ?');
            $updatePassword->execute([$passwordHash, $user['users_id']]);

            $successMessage = 'Mot de passe mis a jour.';
        }

        $userStmt->execute([$_SESSION['user_email']]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$roleLabels = [];
if (!empty($user['conductors_id'])) {
    $roleLabels[] = 'Conducteur';
}
if (!empty($user['passengers_id'])) {
    $roleLabels[] = 'Passager';
}
if ($roleLabels === []) {
    $roleLabels[] = 'Utilisateur';
}
?>

<main class="container py-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h1 class="h2 mb-1">Parametres</h1>
            <p class="text-muted mb-0">Cette page est maintenant connectee a votre base de donnees.</p>
        </div>
        <a class="btn btn-outline-secondary" href="index.php?page=Profil">Voir le profil</a>
    </div>

    <?php if ($successMessage !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Resume du compte</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Nom</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($user['user_name']) ?></dd>

                        <dt class="col-sm-5">Email</dt>
                        <dd class="col-sm-7 text-break"><?= htmlspecialchars($user['user_email']) ?></dd>

                        <dt class="col-sm-5">Roles</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars(implode(', ', $roleLabels)) ?></dd>

                        <dt class="col-sm-5">Places</dt>
                        <dd class="col-sm-7">
                            <?= $user['place_available'] !== null ? (int) $user['place_available'] . ' disponible(s)' : 'Non conducteur' ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Modifier les informations</h2>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="update_account">

                        <div class="col-md-6">
                            <label for="user_name" class="form-label">Nom utilisateur</label>
                            <input
                                type="text"
                                class="form-control"
                                id="user_name"
                                name="user_name"
                                value="<?= htmlspecialchars($user['user_name']) ?>"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="user_email" class="form-label">Adresse email</label>
                            <input
                                type="email"
                                class="form-control"
                                id="user_email"
                                name="user_email"
                                value="<?= htmlspecialchars($user['user_email']) ?>"
                                required
                            >
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h2 class="h5 mb-3">Changer le mot de passe</h2>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="update_password">

                        <div class="col-12">
                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>

                        <div class="col-md-6">
                            <label for="new_password" class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>

                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirmation</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-dark">Mettre a jour le mot de passe</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
