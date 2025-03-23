<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Require admin login
requireAdmin();

// Get statistics
global $pdo;

// Count total users
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'utilisateur'");
$totalUsers = $stmt->fetchColumn();

// Count total taxes
$stmt = $pdo->query("SELECT COUNT(*) FROM taxes");
$totalTaxes = $stmt->fetchColumn();

// Calculate total payments
$stmt = $pdo->query("SELECT SUM(amount) FROM payments WHERE payment_status = 'completed'");
$totalPayments = $stmt->fetchColumn() ?: 0;

// Count pending complaints
$stmt = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('open', 'in_progress')");
$pendingComplaints = $stmt->fetchColumn();

// Count total complaints
$stmt = $pdo->query("SELECT COUNT(*) FROM complaints");
$totalComplaints = $stmt->fetchColumn();

// Recent payments
$stmt = $pdo->prepare("
    SELECT p.*, t.tax_name, u.username, u.full_name
    FROM payments p
    JOIN taxes t ON p.tax_id = t.tax_id
    JOIN users u ON p.user_id = u.user_id
    ORDER BY p.payment_date DESC
    LIMIT 10
");
$stmt->execute();
$recentPayments = $stmt->fetchAll();

// Recent complaints
$stmt = $pdo->prepare("
    SELECT c.*, u.username, u.full_name
    FROM complaints c
    JOIN users u ON c.user_id = u.user_id
    ORDER BY c.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentComplaints = $stmt->fetchAll();

// Get monthly payment statistics for chart (last 6 months)
$months = [];
$monthlyPayments = [];

for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[] = date('M Y', strtotime("-$i months"));
    
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total FROM payments 
        WHERE payment_status = 'completed' 
        AND payment_date BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $result = $stmt->fetch();
    $monthlyPayments[] = $result['total'] ?: 0;
}

// Page title
$pageTitle = 'Tableau de bord administrateur';

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
    .bg-info { background-color: #36b9cc !important; }
    .bg-warning { background-color: #f6c23e !important; }
    .bg-danger { background-color: #e74a3b !important; }
    .bg-secondary { background-color: #858796 !important; }
    .chart-container {
        position: relative;
        height: 300px;
    }
    
</style>


<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2 class="fw-bold">Tableau de bord administrateur</h2>
            <p class="lead">Bienvenue, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <!-- Total Users -->
        <div class="col-xl-3 col-md-6">
            <div class="card text-white bg-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Utilisateurs</h6>
                            <h2 class="display-4"><?php echo $totalUsers; ?></h2>
                        </div>
                        <i class="fas fa-users fa-3x"></i>
                    </div>
                    <a href="manage-users.php" class="btn btn-outline-light btn-sm mt-3">Gérer</a>
                </div>
            </div>
        </div>

        <!-- Total Payments -->
        <div class="col-xl-3 col-md-6">
            <div class="card text-white bg-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Total des paiements</h6>
                            <h2 class="display-4"><?php echo $totalPayments.' DH'; ?></h2>
                        </div>
                        <i class="fas fa-money-bill-wave fa-3x"></i>
                    </div>
                    <a href="view-payments.php" class="btn btn-outline-light btn-sm mt-3">Détails</a>
                </div>
            </div>
        </div>

        <!-- Total Taxes -->
        <div class="col-xl-3 col-md-6">
            <div class="card text-white bg-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Taxes actives</h6>
                            <h2 class="display-4"><?php echo $totalTaxes; ?></h2>
                        </div>
                        <i class="fas fa-file-invoice fa-3x"></i>
                    </div>
                    <a href="manage-taxes.php" class="btn btn-outline-light btn-sm mt-3">Gérer</a>
                </div>
            </div>
        </div>

        <!-- Pending Complaints -->
        <div class="col-xl-3 col-md-6">
            <div class="card text-white bg-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Réclamations en attente</h6>
                            <h2 class="display-4"><?php echo $pendingComplaints; ?></h2>
                        </div>
                        <i class="fas fa-exclamation-circle fa-3x"></i>
                    </div>
                    <a href="view-complaints.php" class="btn btn-outline-light btn-sm mt-3">Gérer</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Tables -->
    <div class="row">
        <!-- Monthly Payments Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="mb-0">Statistiques des paiements mensuels</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="paymentsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Complaint Status -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow h-100">
                <div class="card-header">
                    <h5 class="mb-0">Statut des réclamations</h5>
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $pdo->query("
                        SELECT status, COUNT(*) as count 
                        FROM complaints 
                        GROUP BY status
                    ");
                    $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                    
                    $statuses = [
                        'open' => ['label' => 'Ouvertes', 'color' => 'danger'],
                        'in_progress' => ['label' => 'En cours', 'color' => 'warning'],
                        'resolved' => ['label' => 'Résolues', 'color' => 'success'],
                        'closed' => ['label' => 'Fermées', 'color' => 'secondary']
                    ];
                    
                    foreach ($statuses as $status => $info) {
                        $count = isset($statusCounts[$status]) ? $statusCounts[$status] : 0;
                        $percentage = $totalComplaints > 0 ? round(($count / $totalComplaints) * 100) : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?php echo $info['label']; ?></span>
                                <span><?php echo $count; ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-<?php echo $info['color']; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Payments and Complaints -->
    <div class="row">
        <!-- Recent Payments -->
        <div class="col-md-6 mb-4">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Paiements récents</h5>
                    <a href="view-payments.php" class="btn btn-sm btn-primary">Voir tout</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Taxe</th>
                                    <th>Montant</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['tax_name']); ?></td>
                                    <td><?php echo formatCurrency($payment['amount']); ?></td>
                                    <td><?php echo formatDate($payment['payment_date']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentPayments)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3">Aucun paiement récent</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Complaints -->
        <div class="col-md-6 mb-4">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Réclamations récentes</h5>
                    <a href="view-complaints.php" class="btn btn-sm btn-primary">Voir tout</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Sujet</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentComplaints as $complaint): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($complaint['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($complaint['subject']); ?></td>
                                    <td>
                                        <?php 
                                        $statusLabels = [
                                            'open' => '<span class="badge bg-danger">Ouvert</span>',
                                            'in_progress' => '<span class="badge bg-warning">En cours</span>',
                                            'resolved' => '<span class="badge bg-success">Résolu</span>',
                                            'closed' => '<span class="badge bg-secondary">Fermé</span>'
                                        ];
                                        echo $statusLabels[$complaint['status']] ?? '';
                                        ?>
                                    </td>
                                    <td><?php echo formatDate($complaint['created_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($recentComplaints)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3">Aucune réclamation récente</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Initialize Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly payments chart
    var ctx = document.getElementById('paymentsChart').getContext('2d');
    var paymentsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Paiements mensuels',
                data: <?php echo json_encode($monthlyPayments); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString() + ' €';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw.toLocaleString() + ' €';
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>