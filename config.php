<?php
/**
 * Fichier de configuration de la base de données et de l'administration
 * À adapter avec vos informations de connexion o2switch.
 */

// Informations de connexion à la base de données MySQL
define('DB_HOST', 'localhost');
define('DB_NAME', 'nom_de_votre_base');     // À remplacer par le nom de la BDD créée sur cPanel
define('DB_USER', 'utilisateur_base');     // À remplacer par l'utilisateur de la BDD
define('DB_PASS', 'mot_de_passe_base');    // À remplacer par son mot de passe

// Mot de passe pour accéder à la modération des avis
define('ADMIN_PASSWORD', 'ChangerCeMotDePasse123!'); // À modifier impérativement !

/**
 * Fonction de connexion globale à la base de données via PDO
 */
function getDbConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // En production, ne pas afficher les détails de l'erreur de connexion pour la sécurité
        error_log("Erreur de connexion à la base de données : " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Impossible de se connecter à la base de données.']);
        exit;
    }
}
