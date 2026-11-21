<?php
require_once __DIR__ . '/config.php';

$token = strip_tags(trim($_GET['token'] ?? ''));
$isHost = isset($_GET['host']) && $_GET['host'] === '1';

if (!$token) {
    header('Location: tarifs-visio.php?erreur=Lien+de+visioconference+invalide');
    exit;
}

$reservations = lireReservations();
$rdv = null;

foreach ($reservations as $res) {
    if ($res['token'] === $token && $res['statut'] === 'paye') {
        $rdv = $res;
        break;
    }
}

if (!$rdv) {
    header('Location: tarifs-visio.php?erreur=Reservation+introuvable+ou+non+payee');
    exit;
}

$maintenant = time();
// On autorise l'entrée 5 minutes avant l'heure officielle pour tester micro/caméra
$ouvertureSalle = $rdv['timestamp_debut'] - 300;
$finSalle       = $rdv['timestamp_fin'];

$etat = 'en_cours';
if ($maintenant < $ouvertureSalle) {
    $etat = 'trop_tot';
} elseif ($maintenant >= $finSalle) {
    $etat = 'termine';
}

$secondesRestantesAvantOuverture = max(0, $ouvertureSalle - $maintenant);
$secondesRestantesSeance = max(0, $finSalle - $maintenant);
$nomParticipant = $isHost ? ENTREPRISE_NOM : $rdv['client_nom'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salle de Visioconférence | <?= ENTREPRISE_NOM ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="style.css">
    <?php if ($etat === 'en_cours'): ?>
        <script src="https://meet.jit.si/external_api.js"></script>
    <?php endif; ?>
</head>
<body>

<header class="site-header">
    <div class="header-container">
        <span class="brand-logo"><?= ENTREPRISE_NOM ?> — Espace Visio</span>
        <span><?= htmlspecialchars($rdv['formule_nom']) ?></span>
    </div>
</header>

<main class="container">

    <?php if ($etat === 'trop_tot'): ?>
        <!-- ÉTAT 1 : LE CLIENT ARRIVE EN AVANCE -->
        <section class="booking-section waiting-box">
            <h2>Votre salle de visioconférence est réservée</h2>
            <p>Votre rendez-vous est prévu le <strong><?= date('d/m/Y', $rdv['timestamp_debut']) ?> à <?= htmlspecialchars($rdv['heure_rdv']) ?></strong>.</p>
            <p style="margin-top: 0.5rem; color: var(--text-muted);">La salle vidéo s'ouvrira automatiquement 5 minutes avant le début de la séance :</p>

            <div class="countdown-big" id="waitCountdown">--:--:--</div>

            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Gardez cette page ouverte, elle basculera automatiquement sur la vidéo dès l'ouverture.
            </p>
        </section>

        <script>
        let secAvantOuverture = <?= (int)$secondesRestantesAvantOuverture ?>;
        const waitEl = document.getElementById('waitCountdown');

        function formatDuree(totalSec) {
            const h = Math.floor(totalSec / 3600);
            const m = Math.floor((totalSec % 3600) / 60);
            const s = totalSec % 60;
            return [h, m, s].map(v => String(v).padStart(2, '0')).join(':');
        }

        waitEl.textContent = formatDuree(secAvantOuverture);

        const waitTimer = setInterval(() => {
            secAvantOuverture--;
            if (secAvantOuverture <= 0) {
                clearInterval(waitTimer);
                window.location.reload();
            } else {
                waitEl.textContent = formatDuree(secAvantOuverture);
            }
        }, 1000);
        </script>

    <?php elseif ($etat === 'en_cours'): ?>
        <!-- ÉTAT 2 : SÉANCE EN COURS AVEC COUPURE AUTOMATIQUE -->
        <div id="warningBanner" class="alert alert-warning" style="display: none;" role="alert">
            Attention : il reste moins de 3 minutes avant la coupure automatique de la séance.
        </div>

        <section class="visio-wrapper">
            <div class="visio-topbar">
                <div>
                    <strong>Participant :</strong> <?= htmlspecialchars($nomParticipant) ?>
                </div>
                <div class="timer-badge" id="sessionTimer" aria-live="polite">
                    Temps restant : --:--
                </div>
            </div>
            <div id="jitsi-container"></div>
        </section>

        <script>
        // 1. Initialisation de la salle Jitsi Meet intégrée
        const domain = 'meet.jit.si';
        const options = {
            roomName: <?= json_encode($rdv['room_name']) ?>,
            width: '100%',
            height: '100%',
            parentNode: document.querySelector('#jitsi-container'),
            lang: 'fr',
            userInfo: {
                displayName: <?= json_encode($nomParticipant) ?>
            },
            configOverwrite: {
                startWithAudioMuted: false,
                startWithVideoMuted: false,
                prejoinPageEnabled: true,
                disableDeepLinking: true
            },
            interfaceConfigOverwrite: {
                SHOW_JITSI_WATERMARK: false,
                SHOW_WATERMARK_FOR_GUESTS: false,
                TOOLBAR_BUTTONS: [
                    'microphone', 'camera', 'desktop', 'fullscreen',
                    'chat', 'raisehand', 'tileview', 'settings', 'hangup'
                ]
            }
        };

        const api = new JitsiMeetExternalAPI(domain, options);

        // Si le participant clique lui-même sur raccrocher
        api.addEventListener('videoConferenceLeft', () => {
            terminerSeance();
        });

        // 2. Compte à rebours strict et coupure automatique
        let secondesRestantes = <?= (int)$secondesRestantesSeance ?>;
        const timerBadge = document.getElementById('sessionTimer');
        const warningBanner = document.getElementById('warningBanner');

        function terminerSeance() {
            try {
                api.executeCommand('hangup');
                api.dispose();
            } catch (e) {}
            window.location.reload();
        }

        function majMinuteur() {
            if (secondesRestantes <= 0) {
                terminerSeance();
                return;
            }

            const min = Math.floor(secondesRestantes / 60);
            const sec = secondesRestantes % 60;
            timerBadge.textContent = 'Temps restant : ' + String(min).padStart(2, '0') + ':' + String(sec).padStart(2, '0');

            // Alerte visuelle 3 minutes avant la fin
            if (secondesRestantes <= 180) {
                timerBadge.classList.add('warning');
                warningBanner.style.display = 'block';
            }

            secondesRestantes--;
        }

        majMinuteur();
        setInterval(majMinuteur, 1000);
        </script>

    <?php else: ?>
        <!-- ÉTAT 3 : SÉANCE TERMINÉE ET COUPÉE -->
        <section class="booking-section waiting-box">
            <h2>Séance terminée</h2>
            <p style="margin: 1rem 0; color: var(--text-muted);">
                Le temps imparti pour cette session de visioconférence est écoulé et la communication a été automatiquement clôturée.
            </p>
            <a href="tarifs-visio.php" class="btn btn-primary" style="max-width: 320px;">
                Réserver une nouvelle séance
            </a>
        </section>
    <?php endif; ?>

</main>

</body>
</html>