<?php
//recevoir.php
// Start session to access user data
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Veuillez vous connecter pour accéder à cette page.";
    header('Location: ../index.php');
    exit();
}

// Include database connection
require_once '../config/database.php';

// Get tax ID from URL parameter with proper validation
$tax_id = isset($_GET['tax_id']) ? filter_var($_GET['tax_id'], FILTER_VALIDATE_INT) : 0;
if (!$tax_id) {
    $_SESSION['error'] = "Identifiant de taxe invalide.";
    header("Location: view-taxes.php");
    exit();
}

// Get logged-in user ID
$user_id = $_SESSION['user_id'];

try {
    // First check if a payment exists for this tax and user
    $check_query = "SELECT COUNT(*) FROM payments 
                    WHERE tax_id = :tax_id AND user_id = :user_id";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([
        'tax_id' => $tax_id,
        'user_id' => $user_id
    ]);
    
    $payment_exists = (int)$check_stmt->fetchColumn();
    
    if ($payment_exists === 0) {
        $_SESSION['error'] = "Aucun paiement trouvé pour cette taxe.";
        header("Location: view-taxes.php");
        exit();
    }
    
    // Modified query to directly join with taxes table to get the correct amount
    // This ensures we're getting the tax amount even if the payment record has issues
    $query = "SELECT p.payment_id, 
                    CASE WHEN p.amount <= 0 THEN t.amount ELSE p.amount END as amount, 
                    p.payment_date, p.payment_method, p.transaction_ref, p.payment_status,
                    t.tax_name, t.tax_type, t.description, t.tax_year, t.due_date,
                    u.full_name, u.email, u.address, u.phone
              FROM payments p
              JOIN taxes t ON p.tax_id = t.tax_id
              JOIN users u ON p.user_id = u.user_id
              WHERE p.tax_id = :tax_id AND p.user_id = :user_id
              ORDER BY p.payment_date DESC
              LIMIT 1";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'tax_id' => $tax_id,
        'user_id' => $user_id
    ]);
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Paiement non trouvé ou accès refusé.";
        header("Location: view-taxes.php");
        exit();
    }
    
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Double-check that we have a valid amount
    if ($payment['amount'] <= 0) {
        // Fallback: Get the amount directly from the tax table
        $tax_query = "SELECT amount FROM taxes WHERE tax_id = :tax_id";
        $tax_stmt = $pdo->prepare($tax_query);
        $tax_stmt->execute(['tax_id' => $tax_id]);
        $tax_amount = $tax_stmt->fetchColumn();
        
        if ($tax_amount > 0) {
            $payment['amount'] = $tax_amount;
        }
    }
} catch (PDOException $e) {
    // Log the error and show generic message
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Une erreur est survenue lors de la récupération du paiement.";
    header("Location: view-taxes.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu de Paiement #<?php echo htmlspecialchars($payment['payment_id']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background-color: #f9f9f9;
        }
        .receipt-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
        }
        .receipt-header h1 {
            color: #007bff;
        }
        .receipt-details, .payer-details {
            margin-bottom: 30px;
        }
        .table {
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            color: #666;
            font-size: 0.9em;
        }
        .action-buttons {
            text-align: center;
            margin: 30px 0;
        }
        .payment-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        @media print {
            .action-buttons, .no-print {
                display: none;
            }
            body {
                background-color: #fff;
            }
            .receipt-container {
                box-shadow: none;
                border: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3 no-print" role="alert">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-3 no-print" role="alert">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="receipt-container">
            <div class="receipt-header">
                <h1>Reçu de Paiement</h1>
                <p>Système de Gestion des Taxes</p>
            </div>
            
            <div class="receipt-details">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Reçu No:</strong> <?php echo sprintf('REC-%06d', $payment['payment_id']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Référence de Transaction:</strong> <?php echo htmlspecialchars($payment['transaction_ref']); ?></p>
                        <p><strong>Méthode de Paiement:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?></p>
                        <p>
                            <strong>Statut:</strong> 
                            <span class="payment-status status-<?php echo htmlspecialchars($payment['payment_status']); ?>">
                                <?php 
                                    $status_text = '';
                                    switch($payment['payment_status']) {
                                        case 'completed': $status_text = 'Complété'; break;
                                        case 'pending': $status_text = 'En attente'; break;
                                        case 'failed': $status_text = 'Échoué'; break;
                                        default: $status_text = ucfirst($payment['payment_status']);
                                    }
                                    echo htmlspecialchars($status_text);
                                ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60%;">Description</th>
                            <th style="width: 20%;">Année</th>
                            <th style="width: 20%;">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($payment['tax_name'] ?? 'Non spécifié'); ?><br>
                                <small><?php echo htmlspecialchars($payment['description'] ?? ''); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($payment['tax_year'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($payment['amount'], 2); ?> MAD</td>
                        </tr>
                        <tr>
                            <td colspan="2" class="text-end"><strong>Total</strong></td>
                            <td><strong><?php echo number_format($payment['amount'], 2); ?> MAD</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="payer-details">
                <h4>Informations du Payeur:</h4>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Nom:</strong> <?php echo htmlspecialchars($payment['full_name'] ?? 'Non spécifié'); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($payment['email'] ?? 'Non spécifié'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Adresse:</strong> <?php echo htmlspecialchars($payment['address'] ?? 'Non spécifié'); ?></p>
                        <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($payment['phone'] ?? 'Non spécifié'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="footer">
                <p>Ceci est un reçu généré électroniquement.</p>
                <p>Pour toute question, veuillez contacter notre équipe de support.</p>
                <p>Date d'émission: <?php echo date('d/m/Y H:i'); ?></p>
            </div>
            
            <div class="action-buttons no-print">
                <button class="btn btn-primary" onclick="window.print()">Imprimer le Reçu</button>
                <a href="../services/generate-pdf.php?tax_id=<?php echo $tax_id; ?>" class="btn btn-success">Télécharger PDF</a>
                <a href="view-taxes.php" class="btn btn-secondary">Retour</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>