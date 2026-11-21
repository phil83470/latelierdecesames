
<?php
require_once __DIR__ . '/config.php';

$erreur = '';
if (isset($_GET['erreur'])) {
    $erreur = htmlspecialchars(strip_tags($_GET['erreur']), ENT_QUOTES, 'UTF-8');
}

// Date minimale : aujourd'hui
$dateMin = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Séances en Visioconférence & Tarifs | <?= htmlspecialchars(ENTREPRISE_NOM) ?></title>
    <meta name="description" content="Réservez votre séance de soin énergétique et libération karmique en visioconférence avec L'Atelier de ces Âmes. Paiement sécurisé et accès direct à votre salle vidéo.">
    <link rel="stylesheet" href="style.css">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Service",
        "name": "Séances de Soins Énergétiques et Libération Karmique en Visioconférence",
        "provider": {
            "@type": "LocalBusiness",
            "name": "L'Atelier de ces Âmes",
            "url": "https://latelierdecesames.fr"
        }
    }
    </script>
</head>
<body>

<header class="site-header">
    <div class="header-container">
        <a href="tarifs-visio.php" class="brand-logo"><?= htmlspecialchars(ENTREPRISE_NOM) ?></a>
        <button class="burger-btn" id="burgerBtn" aria-label="Ouvrir le menu" aria-expanded="false">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <nav>
            <ul class="nav-links" id="navLinks">
                <li><a href="https://latelierdecesames.fr">Accueil</a></li>
                <li><a href="#tarifs">Tarifs Visio</a></li>
                <li><a href="#reservation">Réserver</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="container">
    <section class="page-intro" id="tarifs">
        <h1>Séances en Visioconférence</h1>
        <p>1. Choisissez votre séance &bull; 2. Réservez votre créneau &bull; 3. Réglez en ligne et accédez directement à votre espace vidéo privé.</p>
        <span class="tva-notice">TVA non applicable, art. 293 B du CGI</span>
    </section>

    <?php if ($erreur): ?>
        <div class="alert alert-error" role="alert"><?= $erreur ?></div>
    <?php endif; ?>

    <!-- Étape 1 : Choix du tarif -->
    <section class="pricing-grid" aria-label="Formules disponibles">
        <?php foreach ($FORMULES as $cle => $formule): ?>
            <article class="pricing-card <?= $cle === 'soin_karmique' ? 'selected' : '' ?>" data-formule="<?= $cle ?>">
                <div>
                    <h2><?= htmlspecialchars($formule['nom']) ?></h2>
                    <div class="price-tag"><?= htmlspecialchars($formule['prix_affiche']) ?></div>
                    <p><?= htmlspecialchars($formule['description']) ?></p>
                </div>
                <button type="button" class="btn btn-outline select-tarif-btn" data-formule="<?= $cle ?>">
                    Choisir cette séance
                </button>
            </article>
        <?php endforeach; ?>
    </section>

    <!-- Étape 2 : Formulaire de rendez-vous -->
    <section class="booking-section" id="reservation">
        <h2>Finaliser votre réservation</h2>
        <form action="paiement.php" method="POST">
            <!-- Protection anti-spam Honeypot -->
            <div class="hp-field" aria-hidden="true">
                <label for="website">Ne pas remplir ce champ</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="formule">Séance sélectionnée *</label>
                <select id="formule" name="formule" required>
                    <?php foreach ($FORMULES as $cle => $formule): ?>
                        <option value="<?= $cle ?>" <?= $cle === 'soin_karmique' ? 'selected' : '' ?>>
                            <?= htmlspecialchars($formule['nom']) ?> — <?= htmlspecialchars($formule['prix_affiche']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="date_rdv">Date du rendez-vous *</label>
                    <input type="date" id="date_rdv" name="date_rdv" min="<?= $dateMin ?>" required>
                </div>
                <div class="form-group">
                    <label for="heure_rdv">Heure du rendez-vous *</label>
                    <select id="heure_rdv" name="heure_rdv" required>
                        <option value="">-- Choisir un créneau --</option>
                        <option value="09:00">09:00</option>
                        <option value="10:00">10:00</option>
                        <option value="11:00">11:00</option>
                        <option value="14:00">14:00</option>
                        <option value="15:00">15:00</option>
                        <option value="16:00">16:00</option>
                        <option value="17:00">17:00</option>
                        <option value="18:00">18:00</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="nom">Votre nom complet *</label>
                    <input type="text" id="nom" name="nom" placeholder="Ex : Marie Laurent" required>
                </div>
                <div class="form-group">
                    <label for="email">Votre adresse e-mail *</label>
                    <input type="email" id="email" name="email" placeholder="marie.laurent@exemple.fr" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                Valider le créneau et passer au paiement sécurisé
            </button>
        </form>
    </section>
</main>

<script>
// Menu burger mobile
const burgerBtn = document.getElementById('burgerBtn');
const navLinks = document.getElementById('navLinks');
burgerBtn.addEventListener('click', () => {
    const expanded = burgerBtn.getAttribute('aria-expanded') === 'true';
    burgerBtn.setAttribute('aria-expanded', !expanded);
    navLinks.classList.toggle('active');
});

// Synchronisation clic sur carte tarif -> formulaire
const cards = document.querySelectorAll('.pricing-card');
const selectFormule = document.getElementById('formule');

document.querySelectorAll('.select-tarif-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const formule = btn.getAttribute('data-formule');
        selectFormule.value = formule;
        cards.forEach(c => c.classList.remove('selected'));
        btn.closest('.pricing-card').classList.add('selected');
        document.getElementById('reservation').scrollIntoView({ behavior: 'smooth' });
    });
});

selectFormule.addEventListener('change', () => {
    cards.forEach(c => {
        c.classList.toggle('selected', c.getAttribute('data-formule') === selectFormule.value);
    });
});
</script>

</body>
</html>