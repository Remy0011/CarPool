<?php
if (!isset($_SESSION['user_name'], $_SESSION['user_email'])) {
    exit;
}

$name     = htmlspecialchars($_SESSION['user_name']);
$email    = htmlspecialchars($_SESSION['user_email']);
$initiale = mb_strtoupper(mb_substr($name, 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bienvenue</title>
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
            max-width: 420px;
            text-align: center;
        }

        .avatar {
            width: 64px; height: 64px;
            border-radius: 50%;
            background: #E6F1FB;
            color: #0C447C;
            font-size: 24px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
        }

        h1 { font-size: 22px; font-weight: 600; letter-spacing: -0.3px; margin-bottom: 6px; }
        .email { font-size: 13px; color: #6b6b6b; margin-bottom: 1.75rem; }

        .info-box {
            background: #f4f3ef;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            text-align: left;
            margin-bottom: 1.75rem;
        }
        .info-box p { font-size: 13px; color: #555; line-height: 1.6; }

        .logout-btn {
            display: inline-block;
            padding: 9px 24px;
            font-size: 13px;
            font-weight: 500;
            font-family: inherit;
            color: #1a1a1a;
            background: #fff;
            border: 1px solid #d8d6ce;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.15s, transform 0.1s;
        }
        .logout-btn:hover  { background: #f4f3ef; }
        .logout-btn:active { transform: scale(0.98); }
    </style>
</head>
<body>
<div class="card">
    <div class="avatar"><?= $initiale ?></div>
    <h1>Bienvenue, <?= $name ?> !</h1>
    <p class="email"><?= $email ?></p>
    <div class="info-box">
        <p>Vous êtes connecté au portail. Le contenu de votre application s'affichera ici.</p>
    </div>
    <a href="logout.php" class="logout-btn">Se déconnecter</a>
</div>
</body>
</html>