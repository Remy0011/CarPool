<?php
session_start();
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Veuillez remplir les deux champs.';
    } else {
        try {
            $pdo  = getDB();
            // Colonnes : user_name, user_email, user_password
            $stmt = $pdo->prepare('SELECT users_id, user_name, user_email, user_password FROM users WHERE user_email = ? LIMIT 1');
            $stmt->execute([$email]);

            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['user_password'])) {
                $_SESSION['user_name']  = $user['user_name'];
                $_SESSION['user_email'] = $user['user_email'];
                header('Location: index.php');
                exit;
            } else {
                $error = 'Email ou mot de passe invalide.';
            }
        } catch (PDOException $e) {
            $error = $e->getMessage(); // <- affiche l'erreur réelle
            // error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Connexion</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f3ef;
            font-family: 'Segoe UI', system-ui, sans-serif;
            color: #1a1a1a;
        }

        .card {
            background: #ffffff;
            border: 1px solid #e2e0d8;
            border-radius: 14px;
            padding: 2.5rem 2.25rem;
            width: 100%;
            max-width: 400px;
        }

        .card-header { margin-bottom: 2rem; }
        .card-header h1 { font-size: 22px; font-weight: 600; letter-spacing: -0.3px; margin-bottom: 4px; }
        .card-header p  { font-size: 13px; color: #6b6b6b; }

        .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 1rem; }
        label { font-size: 13px; font-weight: 500; color: #444; }

        input[type="email"],
        input[type="password"] {
            font-size: 14px;
            font-family: inherit;
            color: #1a1a1a;
            background: #fafaf8;
            border: 1px solid #d8d6ce;
            border-radius: 8px;
            padding: 9px 13px;
            width: 100%;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        input:focus {
            border-color: #378ADD;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(55,138,221,0.12);
        }

        .error-banner {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #fcebeb;
            border: 1px solid #f09595;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            color: #A32D2D;
            margin-bottom: 1rem;
        }
        .error-banner::before {
            content: '!';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px; height: 18px;
            background: #E24B4A;
            color: #fff;
            border-radius: 50%;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
        }

        button[type="submit"] {
            width: 100%;
            margin-top: 0.5rem;
            padding: 10px;
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            color: #fff;
            background: #1a1a1a;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
        }
        button[type="submit"]:hover  { background: #333; }
        button[type="submit"]:active { transform: scale(0.98); }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h1>Connexion</h1>
        <p>Entrez vos identifiants pour accéder au portail.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="error-banner"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
        <div class="form-group">
            <label for="email">Adresse email</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="vous@exemple.com" required autofocus />
        </div>
        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password"
                   placeholder="••••••••" required />
        </div>
        <button type="submit">Se connecter</button>
    </form>
</div>
</body>
</html>