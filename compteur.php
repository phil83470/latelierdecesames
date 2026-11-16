<?php
/**
 * API PHP pour le compteur de visites (respectueux du RGPD)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config.php';

// Durée de validité du cookie pour éviter de sur-compter (24 heures)
$cookie_name = 'visite_atelier_validee';
$cookie_expire = time() + 86400; 

$db = getDbConnection();

try {
    // 1. S'assurer que la ligne du compteur existe
    $check = $db->query("SELECT COUNT(*) FROM compteur")->fetchColumn();
    if ($check == 0) {
        $db->query("INSERT INTO compteur (visites) VALUES (0)");
    }

    // 2. Vérifier si l'utilisateur a déjà visité le site aujourd'hui
    $already_visited = false;
    if (isset($_COOKIE[$cookie_name])) {
        $already_visited = true;
    } else {
        // On définit le cookie pour les prochaines 24h
        // (Samesite lax et Secure en HTTPS si possible)
        setcookie($cookie_name, '1', [
            'expires' => $cookie_expire,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    // 3. Si c'est une nouvelle visite, on incrémente le compteur
    if (!$already_visited) {
        $db->query("UPDATE compteur SET visites = visites + 1");
    }

    // 4. Récupérer le nombre total de visites
    $visites = $db->query("SELECT visites FROM compteur LIMIT 1")->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'visites' => (int)$visites
    ]);

} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors du traitement du compteur de visites.'
    ]);
}
