<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Set default filters
$year_filter = isset($_GET['year']) ? $_GET['year'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get available years for filter
$years_query = "SELECT DISTINCT YEAR(payment_date) as year FROM payments WHERE user_id = ? ORDER BY year DESC";
$stmt = $pdo->prepare($years_query);
$stmt->execute([$_SESSION['user_id']]);
$years_result = $stmt->fetchAll();

// Build query based on filters
$query = "SELECT p.*, t.tax_type, t.tax_name, t.tax_year 
          FROM payments p 
          JOIN taxes t ON p.tax_id = t.tax_id 
          WHERE p.user_id = ?";

$params = [$_SESSION['user_id']];

if (!empty($year_filter)) {
    $query .= " AND YEAR(p.payment_date) = ?";
    $params[] = $year_filter;
}

if (!empty($status_filter)) {
    $query .= " AND p.payment_status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY p.payment_date DESC";

// Prepare and execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$result = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Paiements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .status-paid {
            color: green;
            font-weight: bold;
        }
        .status-pending {
            color: orange;
            font-weight: bold;
        }
        .payment-card {
            margin-bottom: 20px;
            border-left: 5px solid;
            transition: transform 0.2s;
        }
        .payment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .payment-paid {
            border-left-color: green;
        }
        .payment-pending {
            border-left-color: orange;
        }
    </style>
</head>
<body>
    <?php include('../includes/header.php'); ?>
    
    <div class="container-fluid mt-4">
        <h2 class="mb-4"><i class="fas fa-history"></i> Historique des Paiements</h2>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="year" class="form-label">Année</label>
                    <select name="year" id="year" class="form-select">
                        <option value="">Toutes les années</option>
                        <?php foreach ($years_result as $year): ?>
                            <option value="<?php echo $year['year']; ?>" <?php echo ($year_filter == $year['year']) ? 'selected' : ''; ?>>
                                <?php echo $year['year']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Statut</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">Tous les statuts</option>
                        <option value="completed" <?php echo ($status_filter == 'completed') ? 'selected' : ''; ?>>Payé</option>
                        <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>En attente</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="payment-history.php" class="btn btn-secondary ms-2">Réinitialiser</a>
                </div>
            </form>
        </div>
        
        <!-- Payments Display -->
        <div class="row">
            <?php if (count($result) > 0): ?>
                <?php foreach ($result as $payment): ?>
                    <div class="col-md-6">
                        <div class="card payment-card <?php echo ($payment['payment_status'] == 'completed') ? 'payment-paid' : 'payment-pending'; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title"><?php echo htmlspecialchars($payment['tax_name']); ?> - <?php echo htmlspecialchars($payment['tax_year']); ?></h5>
                                    <span class="badge <?php echo ($payment['payment_status'] == 'completed') ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo htmlspecialchars($payment['payment_status'] == 'completed' ? 'Payé' : 'En attente'); ?>
                                    </span>
                                </div>
                                <p><strong>Référence taxe:</strong> <?php echo htmlspecialchars($payment['transaction_ref']); ?></p>
                                <p><strong>Montant:</strong> <?php echo number_format($payment['amount'], 2); ?> €</p>
                                <p><strong>Date de paiement:</strong> <?php echo date('d/m/Y H:i', strtotime($payment['payment_date'])); ?></p>
                                <p><strong>Méthode:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?></p>
                                
                                <?php if (!empty($payment['transaction_ref'])): ?>
                                    <p><strong>Référence de paiement:</strong> <?php echo htmlspecialchars($payment['transaction_ref']); ?></p>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <a href="../generate-pdf.php?payment_id=<?php echo $payment['payment_id']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                        <i class="fas fa-file-pdf"></i> Télécharger le reçu
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        Aucun paiement trouvé avec les critères sélectionnés.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include('../includes/footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>