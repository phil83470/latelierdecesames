<?php
// Démarre la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fuseau horaire France
date_default_timezone_set('Europe/Paris');

// === CONFIGURATION L'ATELIER DE CES ÂMES ===
define('SITE_URL', 'https://latelierdecesames.fr/visio-rdv'); // URL sans slash final
define('ADMIN_EMAIL', 'nathalieannesuzan@gmail.com');
define('ENTREPRISE_NOM', "L'Atelier de ces Âmes");

// Clé secrète Stripe (à renseigner uniquement dans config.php sur le serveur)
define('STRIPE_SECRET_KEY', 'VOTRE_CLE_SECRETE_STRIPE');

// Définition des 4 formules de tarifs.html (Prix en centimes pour Stripe)
$FORMULES = [
    'guidances' => [
        'nom' => 'Espace Guidances — Guidance par les cartes (1h)',
        'prix_centimes' => 5000,
        'prix_affiche' => '50 €',
        'duree_minutes' => 60,
        'description' => 'Lecture intuitive des cartes oracles ou de tarot, messages de votre âme et pistes de réflexion.'
    ],
    'karmique_rapide' => [
        'nom' => 'Espace Guérison Karmique — Rapide (30 min)',
        'prix_centimes' => 4000,
        'prix_affiche' => '40 €',
        'duree_minutes' => 30,
        'description' => 'Soin énergétique rapide et libération ciblée en visioconférence (½ heure).'
    ],
    'karmique_approfondi' => [
        'nom' => 'Espace Guérison Karmique — Approfondi (1h)',
        'prix_centimes' => 5000,
        'prix_affiche' => '50 €',
        'duree_minutes' => 60,
        'description' => 'Soin énergétique complet et libération karmique approfondie (1 heure).'
    ],
    'parcours_eveil' => [
        'nom' => "Parcours d'Éveil & Guérison — Formule Complète (2h)",
        'prix_centimes' => 12000,
        'prix_affiche' => '120 €',
        'duree_minutes' => 120,
        'description' => 'La formule tout-en-un : un soin approfondi d’1 heure + une guidance par les cartes (2 heures).'
    ]
];

// Fichier de stockage JSON
define('DATA_FILE', __DIR__ . '/data/reservations.json');

// Fonctions utilitaires de lecture/écriture JSON sécurisée
function lireReservations(): array {
    if (!file_exists(DATA_FILE)) {
        return [];
    }
    $contenu = file_get_contents(DATA_FILE);
    $data = json_decode($contenu, true);
    return is_array($data) ? $data : [];
}

function sauvegarderReservations(array $reservations): bool {
    return file_put_contents(
        DATA_FILE,
        json_encode($reservations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    ) !== false;
}
