<body>
<div class="navbar">
    <h1>Mon Application</h1>
    <a href="?logout=1">Déconnexion</a>
</div>

<div class="container">
    <div class="welcome-card">
        <h2>Bienvenue sur votre tableau de bord</h2>
        <p>Vous êtes maintenant connecté à votre espace personnel.</p>

        <div class="user-info">
            <h3>Informations utilisateur :</h3>
            <p><strong>Nom :</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
            <p><strong>Email :</strong> <?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
            <p><strong>ID :</strong> <?php echo htmlspecialchars($_SESSION['user_id']); ?></p>
        </div>
    </div>
</div>
</body>