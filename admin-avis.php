<?php
/**
 * Espace d'administration et de modération des avis
 */

session_start();
require_once __DIR__ . '/config.php';

$db = getDbConnection();
$error = '';

// Gérer la connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $password = $_POST['password'] ?? '';
    if ($password === ADMIN_PASSWORD) {
        $_SESSION['admin_logged'] = true;
        header('Location: admin-avis.php');
        exit;
    } else {
        $error = 'Mot de passe incorrect.';
    }
}

// Gérer la déconnexion
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged']);
    session_destroy();
    header('Location: admin-avis.php');
    exit;
}

// Vérifier si l'administrateur est connecté
$is_logged = isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;

// Gérer les actions de modération si connecté
if ($is_logged && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['moderation_action'])) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $action = $_POST['moderation_action'];

    if ($id) {
        if ($action === 'approve') {
            $stmt = $db->prepare("UPDATE avis SET valide = 1 WHERE id = :id");
            $stmt->execute([':id' => $id]);
        } elseif ($action === 'reject') {
            $stmt = $db->prepare("UPDATE avis SET valide = 0 WHERE id = :id");
            $stmt->execute([':id' => $id]);
        } elseif ($action === 'delete') {
            $stmt = $db->prepare("DELETE FROM avis WHERE id = :id");
            $stmt->execute([':id' => $id]);
        }
    }
    header('Location: admin-avis.php');
    exit;
}

// Récupérer les avis si connecté
$avis_attente = [];
$avis_valides = [];
if ($is_logged) {
    try {
        $stmt = $db->query("SELECT * FROM avis WHERE valide = 0 ORDER BY date_creation DESC");
        $avis_attente = $stmt->fetchAll();

        $stmt = $db->query("SELECT * FROM avis WHERE valide = 1 ORDER BY date_creation DESC");
        $avis_valides = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = 'Impossible de charger les avis.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modération des Avis — L'Atelier de ces âmes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Raleway:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --nuit: #0b0d1a;
            --cosmos: #141729;
            --indigo: #3d4a8a;
            --lavande: #8b9fd4;
            --or: #c9a84c;
            --or-clair: #e8c96d;
            --texte: #d4cfe8;
            --texte-clair: #f0edf8;
            --gris-doux: #6b6880;
            --success: #2e7d32;
            --danger: #c62828;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Raleway', sans-serif;
            background-color: var(--nuit);
            color: var(--texte);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.6;
        }

        header {
            background-color: var(--cosmos);
            border-bottom: 1px solid rgba(201, 168, 76, 0.2);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        h1, h2, h3 {
            font-family: 'Cormorant Garamond', serif;
            color: var(--texte-clair);
            font-weight: 300;
        }

        h1 {
            font-size: 2rem;
            color: var(--or);
        }

        .container {
            max-width: 1000px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1.5rem;
            flex-grow: 1;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: 1px solid transparent;
            border-radius: 8px;
            font-family: 'Raleway', sans-serif;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-logout {
            background: transparent;
            border-color: rgba(201, 168, 76, 0.3);
            color: var(--or);
        }

        .btn-logout:hover {
            background: rgba(201, 168, 76, 0.1);
            border-color: var(--or);
        }

        .btn-approve {
            background: rgba(46, 125, 50, 0.15);
            border-color: var(--success);
            color: #81c784;
        }

        .btn-approve:hover {
            background: var(--success);
            color: white;
        }

        .btn-reject {
            background: rgba(198, 40, 40, 0.15);
            border-color: var(--danger);
            color: #e57373;
        }

        .btn-reject:hover {
            background: var(--danger);
            color: white;
        }

        .btn-delete {
            background: transparent;
            border-color: var(--gris-doux);
            color: var(--texte);
        }

        .btn-delete:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--texte-clair);
        }

        /* Formulaire de connexion */
        .login-box {
            max-width: 400px;
            margin: 8rem auto;
            background: var(--cosmos);
            border: 1px solid rgba(201, 168, 76, 0.2);
            border-radius: 16px;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .login-box h2 {
            font-size: 1.8rem;
            color: var(--or);
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: var(--or);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            background: var(--nuit);
            border: 1px solid rgba(201, 168, 76, 0.2);
            border-radius: 8px;
            color: var(--texte-clair);
            font-size: 1rem;
            outline: none;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: var(--or);
        }

        .btn-submit {
            width: 100%;
            background: var(--or);
            color: var(--nuit);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 0.8rem;
        }

        .btn-submit:hover {
            background: var(--or-clair);
            transform: translateY(-2px);
        }

        .alert-error {
            background: rgba(198, 40, 40, 0.15);
            border: 1px solid var(--danger);
            color: #e57373;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        /* Liste d'avis */
        .section-title {
            font-size: 1.6rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(201, 168, 76, 0.1);
            padding-bottom: 0.5rem;
            color: var(--or);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .avis-list {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .avis-card {
            background: var(--cosmos);
            border: 1px solid rgba(201, 168, 76, 0.15);
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .avis-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .avis-meta {
            display: flex;
            flex-direction: column;
        }

        .avis-author {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--texte-clair);
        }

        .avis-date {
            font-size: 0.8rem;
            color: var(--gris-doux);
        }

        .avis-stars {
            color: var(--or);
            font-size: 0.9rem;
        }

        .avis-body {
            font-style: italic;
            color: var(--texte);
            white-space: pre-wrap;
            font-size: 0.95rem;
        }

        .avis-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 1rem;
        }

        .empty-message {
            color: var(--gris-doux);
            font-style: italic;
            text-align: center;
            padding: 2rem;
            background: rgba(20, 23, 41, 0.5);
            border-radius: 12px;
            border: 1px dashed rgba(201, 168, 76, 0.1);
        }
    </style>
</head>
<body>

    <header>
        <h1>✦ Modération des Avis</h1>
        <?php if ($is_logged): ?>
            <a href="admin-avis.php?action=logout" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Se déconnecter
            </a>
        <?php endif; ?>
    </header>

    <div class="container">
        <?php if (!$is_logged): ?>
            <!-- Formulaire de Connexion -->
            <div class="login-box">
                <h2>Accès Sécurisé</h2>
                <?php if ($error): ?>
                    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form action="admin-avis.php" method="POST">
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" id="password" name="password" required placeholder="Saisir le mot de passe d'admin">
                    </div>
                    <button type="submit" name="login" class="btn btn-submit">Se connecter</button>
                </form>
            </div>
        <?php else: ?>
            
            <!-- Liste des avis EN ATTENTE -->
            <h2 class="section-title">
                <i class="fas fa-clock"></i> Avis en attente de validation
            </h2>
            <div class="avis-list">
                <?php if (empty($avis_attente)): ?>
                    <div class="empty-message">Aucun avis en attente de modération. Tout est à jour !</div>
                <?php else: ?>
                    <?php foreach ($avis_attente as $a): ?>
                        <div class="avis-card">
                            <div class="avis-header">
                                <div class="avis-meta">
                                    <span class="avis-author">✦ <?= htmlspecialchars($a['prenom']) ?></span>
                                    <span class="avis-date">Reçu le <?= date('d/m/Y à H:i', strtotime($a['date_creation'])) ?></span>
                                </div>
                                <div class="avis-stars">
                                    <?= str_repeat('<i class="fas fa-star"></i>', $a['note']) ?>
                                    <?= str_repeat('<i class="far fa-star"></i>', 5 - $a['note']) ?>
                                </div>
                            </div>
                            <p class="avis-body">"<?= htmlspecialchars($a['message']) ?>"</p>
                            <div class="avis-actions">
                                <form action="admin-avis.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="moderation_action" value="approve">
                                    <button type="submit" class="btn btn-approve">
                                        <i class="fas fa-check"></i> Approuver
                                    </button>
                                </form>
                                <form action="admin-avis.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="moderation_action" value="delete" onsubmit="return confirm('Supprimer définitivement cet avis ?');">
                                    <button type="submit" class="btn btn-reject">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Liste des avis APPROUVÉS -->
            <h2 class="section-title">
                <i class="fas fa-check-circle"></i> Avis publiés sur le site
            </h2>
            <div class="avis-list">
                <?php if (empty($avis_valides)): ?>
                    <div class="empty-message">Aucun avis publié pour le moment.</div>
                <?php else: ?>
                    <?php foreach ($avis_valides as $a): ?>
                        <div class="avis-card" style="border-color: rgba(46, 125, 50, 0.2);">
                            <div class="avis-header">
                                <div class="avis-meta">
                                    <span class="avis-author" style="color: var(--or);">✦ <?= htmlspecialchars($a['prenom']) ?></span>
                                    <span class="avis-date">Publié le <?= date('d/m/Y à H:i', strtotime($a['date_creation'])) ?></span>
                                </div>
                                <div class="avis-stars">
                                    <?= str_repeat('<i class="fas fa-star"></i>', $a['note']) ?>
                                    <?= str_repeat('<i class="far fa-star"></i>', 5 - $a['note']) ?>
                                </div>
                            </div>
                            <p class="avis-body">"<?= htmlspecialchars($a['message']) ?>"</p>
                            <div class="avis-actions">
                                <form action="admin-avis.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="moderation_action" value="reject">
                                    <button type="submit" class="btn btn-delete">
                                        <i class="fas fa-eye-slash"></i> Masquer
                                    </button>
                                </form>
                                <form action="admin-avis.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="moderation_action" value="delete" onsubmit="return confirm('Supprimer définitivement cet avis ?');">
                                    <button type="submit" class="btn btn-reject">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

</body>
</html>
