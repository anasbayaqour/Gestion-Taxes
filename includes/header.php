<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Taxes</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <style>
        /* ===== GLOBAL STYLES ===== */
:root {
  --primary-color: #0d6efd;
  --secondary-color: #6c757d;
  --success-color: #198754;
  --danger-color: #dc3545;
  --warning-color: #ffc107;
  --info-color: #0dcaf0;
  --light-color: #f8f9fa;
  --dark-color: #212529;
  --primary-dark: #0a58ca;
  --border-radius: 0.375rem;
}

body {
  font-family: 'Poppins', 'Segoe UI', Roboto, sans-serif;
  background-color: #f5f8fa;
  color: #333;
  line-height: 1.6;
}

/* ===== HEADER & NAVIGATION ===== */
header {
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.navbar-brand {
  font-weight: 700;
  font-size: 1.4rem;
  letter-spacing: 0.5px;
}

.navbar-dark .navbar-nav .nav-link {
  color: rgba(255, 255, 255, 0.85);
  padding: 0.6rem 1rem;
  transition: all 0.3s ease;
}

.navbar-dark .navbar-nav .nav-link:hover {
  color: #ffffff;
  background-color: rgba(255, 255, 255, 0.1);
  border-radius: var(--border-radius);
}

.dropdown-menu {
  border: none;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.dropdown-item:hover {
  background-color: rgba(13, 110, 253, 0.1);
}

/* ===== MAIN CONTAINER ===== */
.container {
  max-width: 1200px;
}

main.container {
  min-height: calc(100vh - 150px);
}

/* ===== CARDS ===== */
.card {
  border: none;
  border-radius: var(--border-radius);
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  margin-bottom: 1.5rem;
}

.card:hover {
  transform: translateY(-3px);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.card-header {
  background-color: #fff;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  font-weight: 600;
  padding: 1rem 1.25rem;
}

.card-body {
  padding: 1.25rem;
}

/* ===== BUTTONS ===== */
.btn {
  font-weight: 500;
  padding: 0.5rem 1.2rem;
  border-radius: var(--border-radius);
  transition: all 0.3s ease;
}

.btn-primary {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
}

.btn-primary:hover {
  background-color: var(--primary-dark);
  border-color: var(--primary-dark);
}

.btn-outline-primary {
  color: var(--primary-color);
  border-color: var(--primary-color);
}

.btn-outline-primary:hover {
  background-color: var(--primary-color);
}

.btn-sm {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

/* ===== FORMS ===== */
.form-control {
  border: 1px solid #ced4da;
  border-radius: var(--border-radius);
  padding: 0.6rem 0.75rem;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-control:focus {
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.form-label {
  font-weight: 500;
  margin-bottom: 0.4rem;
}

.form-select {
  border: 1px solid #ced4da;
  border-radius: var(--border-radius);
  padding: 0.6rem 2rem 0.6rem 0.75rem;
  transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-select:focus {
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* ===== TABLES ===== */
.table {
  border-radius: var(--border-radius);
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  margin-bottom: 0;
}

.table thead th {
  background-color: #f8f9fa;
  border-bottom: 2px solid #dee2e6;
  color: #495057;
  font-weight: 600;
  vertical-align: middle;
}

.table-striped tbody tr:nth-of-type(odd) {
  background-color: rgba(0, 0, 0, 0.02);
}

.table-hover tbody tr:hover {
  background-color: rgba(13, 110, 253, 0.05);
}

.table td {
  vertical-align: middle;
}

/* ===== BADGES ===== */
.badge {
  padding: 0.35em 0.65em;
  font-weight: 600;
  border-radius: 30px;
}

/* ===== ALERTS ===== */
.alert {
  border: none;
  border-radius: var(--border-radius);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  margin-bottom: 1.5rem;
}

.alert-dismissible .btn-close {
  padding: 1rem;
}

/* ===== DASHBOARD ELEMENTS ===== */
.dashboard-stat {
  background: white;
  border-radius: var(--border-radius);
  padding: 1.5rem;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.dashboard-stat:hover {
  transform: translateY(-3px);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.dashboard-stat i {
  font-size: 2rem;
  margin-right: 1rem;
  color: var(--primary-color);
  background: rgba(13, 110, 253, 0.1);
  padding: 1rem;
  border-radius: 50%;
}

.dashboard-stat .stat-value {
  font-size: 1.8rem;
  font-weight: 700;
  margin-bottom: 0.3rem;
}

.dashboard-stat .stat-label {
  color: var(--secondary-color);
  font-size: 0.9rem;
}

/* ===== PROFILE ELEMENTS ===== */
.avatar-container {
  position: relative;
  display: inline-block;
}

.avatar-container img {
  object-fit: cover;
  border: 3px solid var(--primary-color);
}

/* ===== PAGINATION ===== */
.pagination {
  margin-top: 1rem;
  margin-bottom: 0;
}

.pagination .page-item .page-link {
  color: var(--primary-color);
  border: none;
  margin: 0 0.2rem;
  border-radius: var(--border-radius);
}

.pagination .page-item.active .page-link {
  background-color: var(--primary-color);
  color: white;
}

.pagination .page-item .page-link:focus {
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* ===== RESPONSIVE ADJUSTMENTS ===== */
@media (max-width: 767.98px) {
  .navbar-brand {
    font-size: 1.2rem;
  }
  
  .dashboard-stat {
    padding: 1rem;
  }
  
  .dashboard-stat i {
    font-size: 1.5rem;
    padding: 0.75rem;
  }
  
  .dashboard-stat .stat-value {
    font-size: 1.4rem;
  }
  
  .btn {
    padding: 0.4rem 0.8rem;
  }
  
  .card-header {
    padding: 0.75rem 1rem;
  }
  
  .card-body {
    padding: 1rem;
  }
}

/* ===== ADDITIONAL UTILITIES ===== */
.text-primary {
  color: var(--primary-color) !important;
}

.bg-light-primary {
  background-color: rgba(13, 110, 253, 0.1);
}

.border-primary {
  border-color: var(--primary-color) !important;
}

/* Custom background colors */
.bg-light-success {
  background-color: rgba(25, 135, 84, 0.1);
}

.bg-light-warning {
  background-color: rgba(255, 193, 7, 0.1);
}

.bg-light-danger {
  background-color: rgba(220, 53, 69, 0.1);
}

/* ===== MODALS ===== */
.modal-content {
  border: none;
  border-radius: var(--border-radius);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.modal-header {
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
  background-color: #f8f9fa;
}

.modal-footer {
  border-top: 1px solid rgba(0, 0, 0, 0.05);
}

/* ===== FOOTER ===== */
footer {
  background-color: #f8f9fa;
  border-top: 1px solid rgba(0, 0, 0, 0.05);
  padding: 1.5rem 0;
  margin-top: 2rem;
}

/* ===== CUSTOM ANIMATIONS ===== */
.fade-in {
  animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.slide-in {
  animation: slideIn 0.5s ease-out;
}

@keyframes slideIn {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

/* ===== LOADER ===== */
.loader {
  display: inline-block;
  width: 2rem;
  height: 2rem;
  border: 3px solid rgba(13, 110, 253, 0.3);
  border-radius: 50%;
  border-top-color: var(--primary-color);
  animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Print styles */
@media print {
  .no-print {
    display: none !important;
  }
  
  body {
    background-color: white;
  }
  
  .card {
    box-shadow: none;
    border: 1px solid #ddd;
  }
  
  .table {
    box-shadow: none;
  }
}
    </style>
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container-fluid">
                <a class="navbar-brand" href="/">Gestion des Taxes</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <?php if (isLoggedIn()): ?>
                            <?php if (isAdmin()): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="dashboard.php">Tableau de bord</a>
                                </li>
                                <li class="nav-item">
                                <a class="nav-link" href="reports.php" >
                                     Reports
                                </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="manage-taxes.php">Gérer les taxes</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="manage-users.php">Gérer les utilisateurs</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="view-complaints.php">Réclamations</a>
                                </li>
                            <?php else: ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="dashboard.php">Tableau de bord</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="view-taxes.php">Mes taxes</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="payment-history.php">Historique de paiements</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="submit-complaint.php">Soumettre une réclamation</a>
                                </li>
                            <?php endif; ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user"></i> <?php echo $_SESSION['username']; ?>
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item" href="profile.php">Mon profil</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/Gestion_Des_Taxes/user/logout.php">Déconnexion</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="auth/login.php">Connexion</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="register.php">Inscription</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main class="container py-4">
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['flash_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php 
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
            ?>
        <?php endif; ?>