<?php 
session_start(); 
require_once '../config/database.php';

// Check if user is logged in 
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if tax ID is provided 
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Aucun ID de taxe spécifié.";
    header("Location: view-taxes.php");
    exit();
}

$tax_id = $_GET['id'];

// Fetch tax details
$tax_query = "SELECT t.tax_id, t.tax_type, t.tax_name, t.description, t.amount, t.due_date, t.tax_year,
                     COALESCE(ut.status, 'pending') as payment_status
              FROM taxes t
              LEFT JOIN user_taxes ut ON t.tax_id = ut.tax_id AND ut.user_id = :user_id
              WHERE t.tax_id = :tax_id";
              
$stmt = $pdo->prepare($tax_query);
$stmt->execute(['user_id' => $user_id, 'tax_id' => $tax_id]);
$tax = $stmt->fetch();

if (!$tax) {
    $_SESSION['error'] = "Taxe non trouvée.";
    header("Location: view-taxes.php");
    exit();
}

// Get payment status text in French
$status_text = [
    'pending' => 'En attente',
    'paid' => 'Payé',
    'overdue' => 'En retard'
][$tax['payment_status']] ?? 'En attente';

// Get status class for badge
$status_class = [
    'pending' => 'bg-warning',
    'paid' => 'bg-success',
    'overdue' => 'bg-danger'
][$tax['payment_status']] ?? 'bg-warning';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la Taxe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include_once '../includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <h1 class="mb-4">Détails de la Taxe #<?php echo htmlspecialchars($tax['tax_id']); ?></h1>
        
        <div class="card">
            <div class="card-header">
                <h4>Informations sur la Taxe</h4>
            </div>
            <div class="card-body">
                <p><strong>Nom:</strong> <?php echo htmlspecialchars($tax['tax_name']); ?></p>
                <p><strong>Type:</strong> <?php echo htmlspecialchars($tax['tax_type']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($tax['description']); ?></p>
                <p><strong>Année:</strong> <?php echo htmlspecialchars($tax['tax_year']); ?></p>
                <p><strong>Montant:</strong> <?php echo number_format($tax['amount'], 2) . ' MAD'; ?></p>
                <p><strong>Date d'échéance:</strong> <?php echo date('d/m/Y', strtotime($tax['due_date'])); ?></p>
                <p><strong>Statut de paiement:</strong> 
                    <span class="badge <?php echo $status_class; ?>">
                        <?php echo htmlspecialchars($status_text); ?>
                    </span>
                </p>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="complaints.php?tax_id=<?php echo $tax['tax_id']; ?>" class="btn btn-primary">Faire une Réclamation</a>
            <?php if ($tax['payment_status'] !== 'paid'): ?>
                <a href="make-payment.php?tax_id=<?php echo $tax['tax_id']; ?>" class="btn btn-success">Payer Maintenant</a>
            <?php endif; ?>
            <a href="view-taxes.php" class="btn btn-secondary">Retour à la liste</a>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>