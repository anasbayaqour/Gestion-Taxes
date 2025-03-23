<?php
require_once 'includes/session.php';
require_once 'includes/functions.php';

// Page title
$pageTitle = 'Accueil';

// Include header
include_once 'includes/header.php';
?>

<div class="px-4 py-5 my-5 text-center">
    <h1 class="display-5 fw-bold">Gestion des Taxes</h1>
    <div class="col-lg-6 mx-auto">
        <p class="lead mb-4">
            Notre application vous permet de consulter, payer vos taxes en ligne et soumettre des réclamations facilement.
        </p>
        <?php if (!isLoggedIn()): ?>
            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                <a href="auth/login.php" class="btn btn-primary btn-lg px-4 gap-3">Connexion</a>
                <a href="auth/register.php" class="btn btn-outline-primary btn-lg px-4">Inscription</a>
            </div>
        <?php else: ?>
            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                <?php if (isAdmin()): ?>
                    <a href="admin/dashboard.php" class="btn btn-primary btn-lg px-4 gap-3">Tableau de bord administrateur</a>
                <?php else: ?>
                    <a href="user/dashboard.php" class="btn btn-primary btn-lg px-4 gap-3">Accéder à mon espace</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="container px-4 py-5" id="featured-services">
    <h2 class="pb-2 border-bottom">Nos services</h2>
    <div class="row g-4 py-5 row-cols-1 row-cols-lg-3">
        <div class="col">
            <div class="card h-100 shadow">
                <div class="card-body">
                    <div class="feature-icon d-inline-flex align-items-center justify-content-center text-bg-primary bg-gradient fs-2 mb-3 p-2 rounded">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3 class="fs-2">Consultation des taxes</h3>
                    <p>Consultez facilement toutes vos taxes disponibles, filtrez par année ou par type, et accédez à votre historique complet.</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 shadow">
                <div class="card-body">
                    <div class="feature-icon d-inline-flex align-items-center justify-content-center text-bg-primary bg-gradient fs-2 mb-3 p-2 rounded">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h3 class="fs-2">Paiement en ligne</h3>
                    <p>Payez vos taxes en ligne de manière sécurisée, recevez des reçus automatiques et gardez une trace de tous vos paiements.</p>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100 shadow">
                <div class="card-body">
                    <div class="feature-icon d-inline-flex align-items-center justify-content-center text-bg-primary bg-gradient fs-2 mb-3 p-2 rounded">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3 class="fs-2">Réclamations et support</h3>
                    <p>Soumettez facilement des réclamations, suivez leur statut et communiquez directement avec nos administrateurs.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container px-4 py-5 bg-light rounded-3">
    <div class="row align-items-center">
        <div class="col-lg-6">
            <h2>Comment ça marche?</h2>
            <ol class="list-group list-group-flush list-group-numbered mb-4">
                <li class="list-group-item bg-transparent">Créez votre compte en quelques clics</li>
                <li class="list-group-item bg-transparent">Consultez les taxes disponibles dans votre espace personnel</li>
                <li class="list-group-item bg-transparent">Effectuez vos paiements en ligne de manière sécurisée</li>
                <li class="list-group-item bg-transparent">Recevez un reçu de paiement automatique</li>
                <li class="list-group-item bg-transparent">Suivez l'historique de vos paiements à tout moment</li>
            </ol>
            <?php if (!isLoggedIn()): ?>
                <a href="auth/register.php" class="btn btn-primary">Créer un compte</a>
            <?php endif; ?>
        </div>
        <div class="col-lg-6">
            <div class="p-5 text-center">
                <img src="assets/img/image.png" alt="Tax Payment" class="img-fluid" style="max-height: 300px;">
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>