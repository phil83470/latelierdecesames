<?php

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tarifs-visio.php');
    exit;
}

// Vérification anti-spam Honeypot
if (!empty($_POST['website'])) {
    header('Location: tarifs-visio.php?erreur=Requete+invalide');
    exit;
}

// Nettoyage des entrées
$formuleCle = strip_tags(trim($_POST['formule'] ?? ''));
$dateRdv    = strip_tags(trim($_POST['date_rdv'] ?? ''));
$heureRdv   = strip_tags(trim($_POST['heure_rdv'] ?? ''));
$nom        = htmlspecialchars(strip_tags(trim($_POST['nom'] ?? '')), ENT_QUOTES, 'UTF-8');
$email      = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

if (!isset($FORMULES[$formuleCle]) || !$dateRdv || !$heureRdv || !$nom || !$email) {
    header('Location: tarifs-visio.php?erreur=Veuillez+remplir+tous+les+champs+correctement');
    exit;
}

// Vérification que la date/heure n'est pas passée
$timestampDebut = strtotime($dateRdv . ' ' . $heureRdv . ':00');
if ($timestampDebut === false || $timestampDebut < (time() - 300)) {
    header('Location: tarifs-visio.php?erreur=Ce+creneau+horaire+est+deja+passe');
    exit;
}

$formule = $FORMULES[$formuleCle];
$timestampFin = $timestampDebut + ($formule['duree_minutes'] * 60);

// Vérification disponibilité du créneau parmi les réservations déjà payées
$reservations = lireReservations();
foreach ($reservations as $res) {
    if ($res['statut'] === 'paye') {
        if ($timestampDebut < $res['timestamp_fin'] && $timestampFin > $res['timestamp_debut']) {
            header('Location: tarifs-visio.php?erreur=Ce+creneau+est+deja+reserve+choisissez+un+autre+horaire');
            exit;
        }
    }
}

// Génération d'un jeton unique cryptographique et d'un nom de salle Jitsi imprévisible
$token = bin2hex(random_bytes(24));
$roomName = 'AtelierDeCesAmes_' . bin2hex(random_bytes(12));

// Enregistrement en statut "en_attente"
$nouvelleReservation = [
    'token'           => $token,
    'room_name'       => $roomName,
    'formule_cle'     => $formuleCle,
    'formule_nom'     => $formule['nom'],
    'prix_affiche'    => $formule['prix_affiche'],
    'duree_minutes'   => $formule['duree_minutes'],
    'date_rdv'        => $dateRdv,
    'heure_rdv'       => $heureRdv,
    'timestamp_debut' => $timestampDebut,
    'timestamp_fin'   => $timestampFin,
    'client_nom'      => $nom,
    'client_email'    => $email,
    'statut'          => 'en_attente',
    'stripe_session'  => '',
    'cree_le'         => date('Y-m-d H:i:s')
];

$reservations[] = $nouvelleReservation;
sauvegarderReservations($reservations);

// Appel direct à l'API Stripe Checkout via cURL avec facturation automatique activée
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
$postFields = http_build_query([
    'mode' => 'payment',
    'customer_email' => $email,
    'invoice_creation[enabled]' => 'true',
    'success_url' => SITE_URL . '/confirmation.php?session_id={CHECKOUT_SESSION_ID}&token=' . $token,
    'cancel_url' => SITE_URL . '/tarifs-visio.php?erreur=Paiement+annule',
    'line_items[0][price_data][currency]' => 'eur',
    'line_items[0][price_data][product_data][name]' => $formule['nom'] . ' - RDV le ' . date('d/m/Y', $timestampDebut) . ' à ' . $heureRdv,
    'line_items[0][price_data][unit_amount]' => $formule['prix_centimes'],
    'line_items[0][quantity]' => 1,
    'metadata[token]' => $token
]);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$sessionData = json_decode($response, true);

if ($httpCode === 200 && !empty($sessionData['url'])) {
    header('Location: ' . $sessionData['url']);
    exit;
} else {
    header('Location: tarifs-visio.php?erreur=Erreur+de+connexion+au+module+de+paiement+Stripe');
    exit;
}