<?php
// login.php - Page de connexion

require_once 'connexion.php';

$error = '';
$success = '';

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et nettoyer les données
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation des champs
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } else {
        // Vérifier les identifiants dans la base de données
        $sql = "SELECT id, nom, email, password FROM utilisateurs WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nom'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['logged_in'] = true;

            $success = 'Connexion réussie ! Redirection...';
            header('refresh:2;url=home.php');
        } else {
            $error = 'Email ou mot de passe incorrect';
        }
    }
}
?>

<body>
<div class="container">
    <h2>Connexion</h2>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                   required>
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit">Se connecter</button>
    </form>

    <div class="links">
        <a href="register.php">Créer un compte</a>
        <span class="separator">|</span>
        <a href="forgot_password.php">Mot de passe oublié ?</a>
    </div>
</div>
</body>
