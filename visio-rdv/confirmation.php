<?php
require_once __DIR__ . '/config.php';

$sessionId = strip_tags(trim($_GET['session_id'] ?? ''));
$token     = strip_tags(trim($_GET['token'] ?? ''));

if (!$sessionId || !$token) {
    header('Location: tarifs-visio.php');
    exit;
}

// Vérification du paiement auprès de Stripe
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':'
]);
$response = curl_exec($ch);
curl_close($ch);

$sessionData = json_decode($response, true);

if (empty($sessionData['payment_status']) || $sessionData['payment_status'] !== 'paid') {
    header('Location: tarifs-visio.php?erreur=Le+paiement+n+a+pas+pu+etre+confirme');
    exit;
}

// Mise à jour de la réservation en statut "paye"
$reservations = lireReservations();
$reservationActive = null;
$dejaNotifie = false;

foreach ($reservations as &$res) {
    if ($res['token'] === $token) {
        if ($res['statut'] === 'paye') {
            $dejaNotifie = true;
        }
        $res['statut'] = 'paye';
        $res['stripe_session'] = $sessionId;
        $reservationActive = $res;
        break;
    }
}
unset($res);

if (!$reservationActive) {
    header('Location: tarifs-visio.php?erreur=Reservation+introuvable');
    exit;
}

sauvegarderReservations($reservations);

$lienVisioClient = SITE_URL . '/visio.php?token=' . urlencode($token);
$lienVisioAdmin  = SITE_URL . '/visio.php?token=' . urlencode($token) . '&host=1';
$dateFr = date('d/m/Y', $reservationActive['timestamp_debut']);

// Envoi des emails de confirmation (une seule fois)
if (!$dejaNotifie) {
    $headers = "From: " . ENTREPRISE_NOM . " <" . ADMIN_EMAIL . ">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // Email au client
    $sujetClient = "Confirmation de votre RDV Visio - " . ENTREPRISE_NOM;
    $msgClient = "Bonjour " . $reservationActive['client_nom'] . ",\n\n"
               . "Votre paiement de " . $reservationActive['prix_affiche'] . " est validé (TVA non applicable, art. 293 B du CGI).\n\n"
               . "Détails de votre séance :\n"
               . "- Prestation : " . $reservationActive['formule_nom'] . "\n"
               . "- Date : " . $dateFr . " à " . $reservationActive['heure_rdv'] . "\n"
               . "- Durée : " . $reservationActive['duree_minutes'] . " minutes\n\n"
               . "Accédez directement à votre salle de visioconférence le jour J via ce lien unique :\n"
               . $lienVisioClient . "\n\n"
               . "Note : La salle s'ouvrira automatiquement à l'heure du rendez-vous et se coupera à la fin du temps imparti.\n\n"
               . "Cordialement,\n" . ENTREPRISE_NOM;

    @mail($reservationActive['client_email'], $sujetClient, $msgClient, $headers);

    // Email pour toi (administrateur)
    $sujetAdmin = "[Nouveau RDV Visio Payé] " . $reservationActive['client_nom'] . " - " . $dateFr . " à " . $reservationActive['heure_rdv'];
    $msgAdmin = "Un client vient de réserver et payer une séance visio :\n\n"
              . "Client : " . $reservationActive['client_nom'] . " (" . $reservationActive['client_email'] . ")\n"
              . "Formule : " . $reservationActive['formule_nom'] . " (" . $reservationActive['prix_affiche'] . ")\n"
              . "Créneau : " . $dateFr . " à " . $reservationActive['heure_rdv'] . "\n\n"
              . "Ton lien d'accès direct à la salle :\n"
              . $lienVisioAdmin . "\n";

    @mail(ADMIN_EMAIL, $sujetAdmin, $msgAdmin, $headers);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réservation Confirmée | <?= ENTREPRISE_NOM ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-header">
    <div class="header-container">
        <a href="tarifs-visio.php" class="brand-logo"><?= ENTREPRISE_NOM ?></a>
    </div>
</header>

<main class="container">
    <section class="booking-section">
        <div class="alert alert-success">
            Paiement validé ! Votre rendez-vous est officiellement réservé.
        </div>

        <h2>Récapitulatif de votre séance</h2>
        <p style="margin-bottom: 1rem;">
            <strong>Formule :</strong> <?= htmlspecialchars($reservationActive['formule_nom']) ?><br>
            <strong>Date et heure :</strong> Le <?= $dateFr ?> à <?= htmlspecialchars($reservationActive['heure_rdv']) ?><br>
            <strong>Durée :</strong> <?= (int)$reservationActive['duree_minutes'] ?> minutes (coupure automatique en fin de séance)
        </p>

        <p style="margin-bottom: 1.5rem; color: var(--text-muted);">
            Un e-mail contenant votre lien d'accès vient d'être envoyé à <strong><?= htmlspecialchars($reservationActive['client_email']) ?></strong>. Vous pouvez également cliquer directement sur le bouton ci-dessous pour rejoindre la salle d'attente :
        </p>

        <a href="<?= htmlspecialchars($lienVisioClient) ?>" class="btn btn-primary">
            Accéder à la page de visioconférence
        </a>
    </section>
</main>

</body>
</html>