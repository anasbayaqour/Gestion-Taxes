<?php
//user/dashboard.php
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require user login
requireUser();

// Get current user data
$user = getUserById($_SESSION['user_id']);

// Get user's taxes
$taxes = getUserTaxes($_SESSION['user_id']);

// Count pending taxes
$pendingTaxes = 0;
$overdueAmount = 0;
foreach ($taxes as $tax) {
    if ($tax['status'] === 'pending') {
        $pendingTaxes++;
        
        // Check if overdue
        if (strtotime($tax['due_date']) < time()) {
            $overdueAmount += $tax['amount'];
        }
    }
}

// Get recent payments
global $pdo;
$stmt = $pdo->prepare("
    SELECT p.*, t.tax_name, t.tax_type 
    FROM payments p
    JOIN taxes t ON p.tax_id = t.tax_id
    WHERE p.user_id = ?
    ORDER BY p.payment_date DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recentPayments = $stmt->fetchAll();

// Get recent complaints
$stmt = $pdo->prepare("
    SELECT * FROM complaints
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 3
");
$stmt->execute([$_SESSION['user_id']]);
$recentComplaints = $stmt->fetchAll();

// Get notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE user_id = ? AND read_status = 0
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

// Page title
$pageTitle = 'Tableau de bord';

// Include header
include_once '../includes/header.php';
?>

<!-- Custom CSS for Modern Dashboard -->
<style>
    body {
        background-color: #f8f9fa;
    }
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    }
    .card-header {
        background-color: #fff;
        border-bottom: 1px solid #e0e0e0;
        font-weight: 600;
        padding: 1.25rem;
    }
    .card-body {
        padding: 1.5rem;
    }
    .display-4 {
        font-size: 2.5rem;
        font-weight: 600;
    }
    .progress {
        height: 8px;
        border-radius: 4px;
    }
    .table-responsive {
        border-radius: 12px;
        overflow: hidden;
    }
    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
    }
    .badge {
        font-size: 0.9rem;
        padding: 0.5rem 0.75rem;
    }
    .bg-primary { background-color: #4e73df !important; }
    .bg-success { background-color: #1cc88a !important; }
    .bg-warning { background-color: #f6c23e !important; }
    .bg-danger { background-color: #e74a3b !important; }
    .bg-secondary { background-color: #858796 !important; }
    .list-group-item {
        border: none;
        border-bottom: 1px solid #e0e0e0;
        padding: 1rem;
    }
    .list-group-item:last-child {
        border-bottom: none;
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2 class="fw-bold">Tableau de bord</h2>
            <p class="lead">Bienvenue, <?php echo htmlspecialchars($user['full_name']); ?>!</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <!-- Pending Taxes -->
        <div class="col-md-4">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Taxes en attente</h6>
                            <h2 class="display-4"><?php echo $pendingTaxes; ?></h2>
                        </div>
                        <i class="fas fa-file-invoice fa-3x"></i>
                    </div>
                    <a href="view-taxes.php" class="btn btn-outline-light btn-sm mt-3">Voir détails</a>
                </div>
            </div>
        </div>

        <!-- Overdue Amount -->
        <div class="col-md-4">
            <div class="card text-white bg-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Montant en retard</h6>
                            <h2 class="display-4"><?php echo formatCurrency($overdueAmount); ?></h2>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-3x"></i>
                    </div>
                    <a href="view-taxes.php?filter=overdue" class="btn btn-outline-light btn-sm mt-3">Payer maintenant</a>
                </div>
            </div>
        </div>

        <!-- Active Complaints -->
        <div class="col-md-4">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Réclamations actives</h6>
                            <h2 class="display-4"><?php echo count($recentComplaints); ?></h2>
                        </div>
                        <i class="fas fa-comments fa-3x"></i>
                    </div>
                    <a href="submit-complaint.php" class="btn btn-outline-light btn-sm mt-3">Nouvelle réclamation</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Payments and Notifications -->
    <div class="row mb-4">
        <!-- Recent Payments -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Derniers paiements</h5>
                    <a href="payment-history.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                </div>
                <div class="card-body">
                    <?php if (count($recentPayments) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Taxe</th>
                                        <th>Montant</th>
                                        <th>Statut</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentPayments as $payment): ?>
                                        <tr>
                                            <td><?php echo formatDate($payment['payment_date']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['tax_name']); ?></td>
                                            <td><?php echo formatCurrency($payment['amount']); ?></td>
                                            <td>
                                                <?php 
                                                $statusClass = '';
                                                switch ($payment['payment_status']) {
                                                    case 'completed':
                                                        $statusClass = 'badge bg-success';
                                                        break;
                                                    case 'pending':
                                                        $statusClass = 'badge bg-warning';
                                                        break;
                                                    case 'failed':
                                                        $statusClass = 'badge bg-danger';
                                                        break;
                                                }
                                                ?>
                                                <span class="<?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($payment['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($payment['receipt_path'])): ?>
                                                    <a href="<?php echo $payment['receipt_path']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                                        <i class="fas fa-file-pdf"></i> Reçu
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <p>Aucun paiement récent.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Notifications</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (count($notifications) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                                <li class="list-group-item">
                                    <h6><?php echo htmlspecialchars($notification['title']); ?></h6>
                                    <p class="mb-0 small text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <small class="text-muted"><?php echo formatDate($notification['created_at']); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <p>Aucune notification.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Complaints -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Mes réclamations</h5>
                    <a href="submit-complaint.php" class="btn btn-sm btn-outline-primary">Nouvelle</a>
                </div>
                <div class="card-body p-0">
                    <?php if (count($recentComplaints) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recentComplaints as $complaint): ?>
                                <li class="list-group-item">
                                    <h6><?php echo htmlspecialchars($complaint['subject']); ?></h6>
                                    <p class="mb-1 small">
                                        <?php 
                                        $statusClass = '';
                                        switch ($complaint['status']) {
                                            case 'open':
                                                $statusClass = 'badge bg-info';
                                                break;
                                            case 'in_progress':
                                                $statusClass = 'badge bg-warning';
                                                break;
                                            case 'resolved':
                                                $statusClass = 'badge bg-success';
                                                break;
                                            case 'closed':
                                                $statusClass = 'badge bg-secondary';
                                                break;
                                        }
                                        ?>
                                        <span class="<?php echo $statusClass; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                                        </span>
                                    </p>
                                    <small class="text-muted"><?php echo formatDate($complaint['created_at']); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <p>Aucune réclamation.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>