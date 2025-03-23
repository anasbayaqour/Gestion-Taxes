<?php
//view-taxes.php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Verify database connection
if (!$pdo) {
    die("Database connection not established.");
}

$user_id = $_SESSION['user_id'];

// Get user taxes
$taxes_query = "SELECT t.*, 
                CASE 
                    WHEN t.due_date < CURDATE() AND 
                         (SELECT COUNT(*) FROM payments p WHERE p.tax_id = t.tax_id AND p.user_id = ? AND p.payment_status = 'completed') = 0 
                    THEN 'bg-danger'
                    WHEN (SELECT COUNT(*) FROM payments p WHERE p.tax_id = t.tax_id AND p.user_id = ? AND p.payment_status = 'completed') > 0 
                    THEN 'bg-success'
                    WHEN t.due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                    THEN 'bg-warning'
                    ELSE 'bg-info'
                END as status_class,
                CASE 
                    WHEN (SELECT COUNT(*) FROM payments p WHERE p.tax_id = t.tax_id AND p.user_id = ? AND p.payment_status = 'completed') > 0 
                    THEN 'Payé'
                    WHEN t.due_date < CURDATE() 
                    THEN 'En retard'
                    ELSE 'À payer'
                END as status_text,
                (SELECT COUNT(*) FROM payments p WHERE p.tax_id = t.tax_id AND p.user_id = ? AND p.payment_status = 'completed') > 0 as is_paid
                FROM taxes t 
                LEFT JOIN user_taxes ut ON t.tax_id = ut.tax_id AND ut.user_id = ?
                WHERE ut.user_id IS NOT NULL OR t.tax_type = 'global'
                ORDER BY t.due_date ASC";

$stmt = $pdo->prepare($taxes_query);
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
$taxes_result = $stmt->fetchAll();

// Filter options
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Taxes - Portail Citoyen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <style>
        /* ===== GLOBAL STYLES ===== */
:root {
  --primary-color: #0d6efd;
  --primary-dark: #0a58ca;
  --secondary-color: #6c757d;
  --success-color: #28a745;
  --danger-color: #dc3545;
  --warning-color: #ffc107;
  --info-color: #17a2b8;
  --light-color: #f8f9fa;
  --dark-color: #343a40;
  --border-radius: 0.5rem;
  --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

body {
  font-family: 'Poppins', sans-serif;
  background-color: var(--light-color);
  color: var(--dark-color);
  line-height: 1.6;
}

/* ===== HEADER ===== */
.navbar {
  box-shadow: var(--box-shadow);
}

.navbar-brand {
  font-weight: 700;
  font-size: 1.5rem;
  color: var(--primary-color) !important;
}

.navbar-nav .nav-link {
  font-weight: 500;
  color: var(--dark-color);
  transition: all 0.3s ease;
}

.navbar-nav .nav-link:hover {
  color: var(--primary-color);
}

/* ===== TAX CARDS ===== */
.card {
  border: none;
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  margin-bottom: 1.5rem;
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

.card-header {
  background-color: white;
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
  font-weight: 600;
  padding: 1rem;
}

.card-body {
  padding: 1.5rem;
}

.card-footer {
  background-color: white;
  border-top: 1px solid rgba(0, 0, 0, 0.1);
  padding: 1rem;
}

.badge {
  font-size: 0.9rem;
  font-weight: 500;
  padding: 0.5rem 0.75rem;
  border-radius: 20px;
}

.badge.bg-success {
  background-color: var(--success-color) !important;
}

.badge.bg-danger {
  background-color: var(--danger-color) !important;
}

.badge.bg-warning {
  background-color: var(--warning-color) !important;
}

.badge.bg-info {
  background-color: var(--info-color) !important;
}

/* ===== BUTTONS ===== */
.btn {
  font-weight: 500;
  padding: 0.5rem 1rem;
  border-radius: var(--border-radius);
  transition: all 0.3s ease;
}

.btn-sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
}

.btn-info {
  background-color: var(--info-color);
  border-color: var(--info-color);
}

.btn-info:hover {
  background-color: #138496;
  border-color: #117a8b;
}

.btn-success {
  background-color: var(--success-color);
  border-color: var(--success-color);
}

.btn-success:hover {
  background-color: #218838;
  border-color: #1e7e34;
}

.btn-warning {
  background-color: var(--warning-color);
  border-color: var(--warning-color);
}

.btn-warning:hover {
  background-color: #e0a800;
  border-color: #d39e00;
}

.btn-secondary {
  background-color: var(--secondary-color);
  border-color: var(--secondary-color);
}

.btn-secondary:hover {
  background-color: #5a6268;
  border-color: #545b62;
}

/* ===== FILTER BUTTONS ===== */
.btn-group .btn {
  border-radius: var(--border-radius);
  margin-right: 0.5rem;
}

.btn-group .btn.active {
  background-color: var(--primary-color);
  color: white;
}

/* ===== TAX CALENDAR TABLE ===== */
.table {
  border-radius: var(--border-radius);
  overflow: hidden;
  box-shadow: var(--box-shadow);
}

.table thead th {
  background-color: var(--primary-color);
  color: white;
  font-weight: 600;
  border: none;
}

.table-striped tbody tr:nth-of-type(odd) {
  background-color: rgba(0, 0, 0, 0.03);
}

.table-hover tbody tr:hover {
  background-color: rgba(0, 0, 0, 0.05);
}

/* ===== ALERTS ===== */
.alert {
  border-radius: var(--border-radius);
  box-shadow: var(--box-shadow);
}

.alert-info {
  background-color: var(--info-color);
  color: white;
}

/* ===== ANIMATIONS ===== */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.fade-in {
  animation: fadeIn 0.5s ease-in;
}

/* ===== RESPONSIVE ADJUSTMENTS ===== */
@media (max-width: 767.98px) {
  .btn-group .btn {
    margin-bottom: 0.5rem;
  }

  .card {
    margin-bottom: 1rem;
  }
}
    </style>
    <?php include_once '../includes/header.php'; ?>
    
    <div class="container-fluid mt-4 fade-in">
        <h1 class="mb-4 text-center">Mes Taxes et Impôts</h1>
        
        <!-- Filter Options -->
        <div class="mb-4 text-center">
            <div class="btn-group">
                <a href="view-taxes.php?filter=all" class="btn btn-outline-primary <?php echo $filter == 'all' ? 'active' : ''; ?>">
                    Toutes
                </a>
                <a href="view-taxes.php?filter=unpaid" class="btn btn-outline-primary <?php echo $filter == 'unpaid' ? 'active' : ''; ?>">
                    À payer
                </a>
                <a href="view-taxes.php?filter=paid" class="btn btn-outline-primary <?php echo $filter == 'paid' ? 'active' : ''; ?>">
                    Payées
                </a>
                <a href="view-taxes.php?filter=overdue" class="btn btn-outline-primary <?php echo $filter == 'overdue' ? 'active' : ''; ?>">
                    En retard
                </a>
            </div>
        </div>
        
        <!-- Taxes List -->
        <div class="row">
            <?php 
            $has_taxes = false;
            foreach ($taxes_result as $tax): 
                if (
                    ($filter == 'paid' && !$tax['is_paid']) || 
                    ($filter == 'unpaid' && $tax['is_paid']) || 
                    ($filter == 'overdue' && $tax['status_text'] != 'En retard')
                ) {
                    continue;
                }
                $has_taxes = true;
            ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="m-0"><?php echo htmlspecialchars($tax['tax_name']); ?></h5>
                            <span class="badge <?php echo $tax['status_class']; ?>"><?php echo $tax['status_text']; ?></span>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Montant:</strong> <?php echo number_format($tax['amount'], 2, ',', ' '); ?> DH
                            </div>
                            <div class="mb-3">
                                <strong>Échéance:</strong> <?php echo date('d/m/Y', strtotime($tax['due_date'])); ?>
                            </div>
                            <div class="mb-3">
                                <strong>Type:</strong> <?php echo $tax['tax_type'] == 'global' ? 'Taxe municipale' : 'Taxe personnelle'; ?>
                            </div>
                            <div class="mb-3">
                                <strong>Description:</strong>
                                <p class="mb-0"><?php echo htmlspecialchars($tax['description']); ?></p>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between">
                                <a href="tax-detail.php?id=<?php echo $tax['tax_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-info-circle"></i> Détails
                                </a>
                                <?php if (!$tax['is_paid']): ?>
                                    <a href="make-payment.php?tax_id=<?php echo $tax['tax_id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-credit-card"></i> Payer
                                    </a>
                                <?php else: ?>
                                    <a href="receipt.php?tax_id=<?php echo $tax['tax_id']; ?>" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-file-invoice"></i> Reçu
                                    </a>
                                <?php endif; ?>
                                <a href="complaints.php?tax_id=<?php echo $tax['tax_id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-exclamation-circle"></i> Réclamation
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (!$has_taxes): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <?php 
                        if ($filter == 'all') {
                            echo "Vous n'avez actuellement aucune taxe à payer.";
                        } elseif ($filter == 'paid') {
                            echo "Vous n'avez pas encore payé de taxes.";
                        } elseif ($filter == 'unpaid') {
                            echo "Vous n'avez pas de taxes en attente de paiement.";
                        } else {
                            echo "Vous n'avez pas de taxes en retard de paiement.";
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Tax Calendar -->
        <div class="card mt-4">
            <div class="card-header">
                <h5>Calendrier des Échéances</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Taxe</th>
                                <th>Montant</th>
                                <th>Date d'échéance</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($taxes_result as $tax): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tax['tax_name']); ?></td>
                                    <td><?php echo number_format($tax['amount'], 2, ',', ' '); ?> DH</td>
                                    <td><?php echo date('d/m/Y', strtotime($tax['due_date'])); ?></td>
                                    <td><span class="badge <?php echo $tax['status_class']; ?>"><?php echo $tax['status_text']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>