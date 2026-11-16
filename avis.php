<?php
/**
 * API PHP pour la gestion des avis (lecture et soumission)
 */

header('Content-Type: application/json');
// Autoriser les requêtes CORS pour les tests locaux (à restreindre en production si besoin)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config.php';

$db = getDbConnection();
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Récupérer uniquement les avis approuvés
    try {
        $stmt = $db->query("SELECT prenom, message, note, date_creation FROM avis WHERE valide = 1 ORDER BY date_creation DESC");
        $avis = $stmt->fetchAll();
        
        echo json_encode([
            'status' => 'success',
            'data' => $avis
        ]);
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode([
            'status' => 'error',
            'message' => 'Erreur lors de la récupération des avis.'
        ]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Soumettre un nouvel avis
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }

    $prenom = trim($input['prenom'] ?? '');
    $message = trim($input['message'] ?? '');
    $note = filter_var($input['note'] ?? null, FILTER_VALIDATE_INT);

    // Validation des données
    if (empty($prenom) || empty($message) || $note === false || $note < 1 || $note > 5) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode([
            'status' => 'error',
            'message' => 'Veuillez remplir tous les champs correctement. La note doit être comprise entre 1 et 5.'
        ]);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO avis (prenom, message, note, valide, date_creation) VALUES (:prenom, :message, :note, 0, NOW())");
        $stmt->execute([
            ':prenom' => htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8'),
            ':message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
            ':note' => $note
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Votre avis a été soumis avec succès et est en attente de modération.'
        ]);
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode([
            'status' => 'error',
            'message' => 'Impossible d\'enregistrer l\'avis pour le moment.'
        ]);
    }
    exit;
}

// Si la méthode n'est ni GET ni POST
header('HTTP/1.1 405 Method Not Allowed');
echo json_encode([
    'status' => 'error',
    'message' => 'Méthode non autorisée.'
]);
