<?php
session_start();
require_once 'config.php';
applySecurityHeaders();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
    } catch (RuntimeException $e) {
        $error = 'Requete invalide. Rechargez la page et recommencez.';
    }

    if ($error === '' && ($email === '' || $password === '')) {
        $error = 'Veuillez remplir les deux champs.';
    } elseif ($error === '') {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT users_id, user_name, user_email, user_password FROM users WHERE user_email = ? LIMIT 1');
            $stmt->execute([$email]);

            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['user_password'])) {
                session_regenerate_id(true);
                $_SESSION['user_name'] = $user['user_name'];
                $_SESSION['user_email'] = $user['user_email'];
                $_SESSION['show_loading_screen'] = true;
                header('Location: loading.php');
                exit;
            } else {
                $error = 'Email ou mot de passe invalide.';
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Une erreur technique est survenue. Reessayez plus tard.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="carpool.css">
</head>
<body class="login-page">
<div class="card">
    <div class="card-header">
        <img class="login-logo" src="img/CarPool détouré.png" alt="CarPool logo">
        <div class="login-title-row">
            <img class="title-icon" src="img/img_icons/user.png" alt="">
            <h1>Connexion</h1>
        </div>
        <p>Entrez vos identifiants pour accéder au portail.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="error-banner"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
        <?= csrfInput() ?>
        <div class="form-group">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="vous@exemple.com" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password"
                   placeholder="••••••••" required>
        </div>
        <button type="submit">Se connecter</button>
    </form>
</div>
</body>
</html>
