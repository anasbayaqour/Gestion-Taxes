<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Check if tax_id is provided
if (!isset($_GET['tax_id']) || empty($_GET['tax_id'])) {
    header("Location: view-taxes.php");
    exit();
}

$tax_id = $_GET['tax_id'];
$user_id = $_SESSION['user_id'];

// Check if the tax exists and is not already paid
$check_query = "SELECT t.*, COALESCE(p.payment_status, 'Non payé') as payment_status 
                FROM taxes t 
                LEFT JOIN payments p ON t.tax_id = p.tax_id AND p.user_id = :user_id 
                WHERE t.tax_id = :tax_id";
$stmt = $pdo->prepare($check_query);
$stmt->execute(['user_id' => $user_id, 'tax_id' => $tax_id]);
$tax = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tax) {
    // Tax not found
    $_SESSION['error'] = "Taxe non trouvée.";
    header("Location: view-taxes.php");
    exit();
}

if (isset($tax['payment_status']) && $tax['payment_status'] == 'completed') {
    // Tax already paid
    $_SESSION['error'] = "Cette taxe a déjà été payée.";
    header("Location: view-taxes.php");
    exit();
}

// Process payment form
$payment_error = '';
$payment_success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment_method = $_POST['payment_method'];
    $card_number = isset($_POST['card_number']) ? $_POST['card_number'] : null;
    $card_holder = isset($_POST['card_holder']) ? $_POST['card_holder'] : null;
    $card_expiry = isset($_POST['card_expiry']) ? $_POST['card_expiry'] : null;
    $card_cvv = isset($_POST['card_cvv']) ? $_POST['card_cvv'] : null;

    // Validate inputs based on payment method
    if ($payment_method == 'card') {
        if (empty($card_number) || empty($card_holder) || empty($card_expiry) || empty($card_cvv)) {
            $payment_error = "Veuillez remplir tous les champs de carte.";
        } else {
            // Basic validation for card number (16 digits)
            if (!preg_match('/^\d{16}$/', str_replace(' ', '', $card_number))) {
                $payment_error = "Numéro de carte invalide.";
            }
            // Basic validation for expiry date (MM/YY)
            elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $card_expiry)) {
                $payment_error = "Date d'expiration invalide.";
            }
            // Basic validation for CVV (3 digits)
            elseif (!preg_match('/^\d{3}$/', $card_cvv)) {
                $payment_error = "CVV invalide.";
            }
        }
    }

    // If validation passed, process payment
    if (empty($payment_error)) {
        // Generate unique reference
        $reference = 'PAY-' . time() . '-' . uniqid();

        // Insert payment record
        $insert_query = "INSERT INTO payments (user_id, tax_id, amount, payment_method, transaction_ref, payment_status, payment_date) 
                         VALUES (:user_id, :tax_id, :amount, :payment_method, :transaction_ref, 'completed', NOW())";

        $stmt = $pdo->prepare($insert_query);
        $params = [
            'user_id' => $user_id,
            'tax_id' => $tax_id,
            'amount' => isset($tax['amount']) ? $tax['amount'] : 0,
            'payment_method' => $payment_method,
            'transaction_ref' => $reference,
        ];

        if ($stmt->execute($params)) {
            $payment_id = $pdo->lastInsertId();
            $payment_success = "Paiement effectué avec succès! Votre référence de paiement est : " . $reference;

            // Redirect to receipt after 3 seconds
            header("refresh:3;url=../generate-pdf.php?payment_id=" . $payment_id);
        } else {
            $payment_error = "Erreur lors du traitement du paiement.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement de Taxe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .payment-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .payment-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .tax-details {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .payment-methods {
            margin-bottom: 20px;
        }
        .payment-form {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .credit-card-form {
            max-width: 400px;
            margin: 0 auto;
        }
        .credit-card {
            background: linear-gradient(135deg, #5b6467, #8b939a);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .credit-card .card-number {
            font-size: 1.5rem;
            letter-spacing: 3px;
            font-family: monospace;
        }
        .card-holder {
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .card-details {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include('../includes/header.php'); ?>
    
    <div class="container-fluid mt-4 payment-container">
        <div class="payment-header text-center">
            <h2><i class="fas fa-credit-card"></i> Paiement de Taxe</h2>
            <p>Complétez le formulaire ci-dessous pour effectuer votre paiement.</p>
        </div>
        
        <?php if($payment_error): ?>
            <div class="alert alert-danger"><?php echo $payment_error; ?></div>
        <?php endif; ?>
        
        <?php if($payment_success): ?>
            <div class="alert alert-success"><?php echo $payment_success; ?></div>
        <?php endif; ?>
        
        <!-- Tax Details Section -->
        <div class="tax-details">
            <h4>Détails de la Taxe</h4>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Nom:</strong> <?php echo isset($tax['tax_name']) ? htmlspecialchars($tax['tax_name']) : 'Non spécifié'; ?></p>
                    <p><strong>Type:</strong> <?php echo isset($tax['tax_type']) ? htmlspecialchars($tax['tax_type']) : 'Non spécifié'; ?></p>
                    <p><strong>Description:</strong> <?php echo isset($tax['description']) ? htmlspecialchars($tax['description']) : 'Non spécifié'; ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Montant à payer:</strong> <span class="text-danger fw-bold"><?php echo isset($tax['amount']) ? number_format($tax['amount'], 2, ',', ' ') : '0.00'; ?> DH</span></p>
                    <p><strong>Date d'échéance:</strong> <?php echo isset($tax['due_date']) ? date('d/m/Y', strtotime($tax['due_date'])) : 'Non spécifié'; ?></p>
                </div>
            </div>
        </div>
        
        <?php if(empty($payment_success)): ?>
            <!-- Payment Methods Section -->
            <div class="payment-methods">
                <h4>Mode de Paiement</h4>
                <form method="POST" action="" id="payment-form">
                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="payment_method" id="card" value="card" checked>
                            <label class="form-check-label" for="card"><i class="fas fa-credit-card"></i> Carte Bancaire</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="payment_method" id="paypal" value="paypal">
                            <label class="form-check-label" for="paypal"><i class="fab fa-paypal"></i> PayPal</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="payment_method" id="virement" value="virement">
                            <label class="form-check-label" for="virement"><i class="fas fa-university"></i> Virement Bancaire</label>
                        </div>
                    </div>
                    
                    <!-- Credit Card Form -->
                    <div id="card-form" class="payment-form">
                        <div class="credit-card-form">
                            <div class="credit-card">
                                <div class="mb-3">
                                    <div class="card-number" id="card-number-display">**** **** **** ****</div>
                                </div>
                                <div class="card-holder" id="card-holder-display">NOM DU TITULAIRE</div>
                                <div class="card-details">
                                    <div>
                                        <div class="small">Date d'expiration</div>
                                        <div id="expiry-display">MM/YY</div>
                                    </div>
                                    <div>
                                        <div class="small">CVV</div>
                                        <div id="cvv-display">***</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="card_number" class="form-label">Numéro de carte</label>
                                <input type="text" class="form-control" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                            </div>
                            <div class="mb-3">
                                <label for="card_holder" class="form-label">Nom du titulaire</label>
                                <input type="text" class="form-control" id="card_holder" name="card_holder" placeholder="Nom du titulaire">
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label for="card_expiry" class="form-label">Date d'expiration</label>
                                    <input type="text" class="form-control" id="card_expiry" name="card_expiry" placeholder="MM/YY" maxlength="5">
                                </div>
                                <div class="col-6 mb-3">
                                    <label for="card_cvv" class="form-label">CVV</label>
                                    <input type="text" class="form-control" id="card_cvv" name="card_cvv" placeholder="123" maxlength="3">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- PayPal Form -->
                    <div id="paypal-form" class="payment-form" style="display: none;">
                        <div class="text-center">
                            <img src="../assets/img/Paypal.png" alt="PayPal" style="max-width: 200px;">
                            <p class="mt-3">Vous serez redirigé vers PayPal pour effectuer votre paiement en toute sécurité.</p>
                        </div>
                    </div>
                    
                    <!-- Bank Transfer Form -->
                    <div id="virement-form" class="payment-form" style="display: none;">
                        <div class="alert alert-info">
                            <h5>Informations pour le virement bancaire</h5>
                            <p>Veuillez effectuer votre virement avec les informations suivantes:</p>
                            <ul>
                                <li><strong>Bénéficiaire:</strong> Administration des Taxes</li>
                                <li><strong>IBAN:</strong> FR76 1234 5678 9123 4567 8912 345</li>
                                <li><strong>BIC:</strong> ABCDEFGHIJK</li>
                                <li><strong>Référence:</strong> TAX-<?php echo $tax_id; ?></li>
                                <li><strong>Montant:</strong> <?php echo isset($tax['amount']) ? number_format($tax['amount'], 2, ',', ' ') : '0.00'; ?> DH</li>
                            </ul>
                            <p>Important: Votre paiement sera validé après réception du virement (délai 2-3 jours ouvrés).</p>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Procéder au Paiement</button>
                        <a href="view-taxes.php" class="btn btn-outline-secondary btn-lg ms-2">Annuler</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include('../includes/footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle payment methods
        document.querySelectorAll('input[name="payment_method"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                document.getElementById('card-form').style.display = 'none';
                document.getElementById('paypal-form').style.display = 'none';
                document.getElementById('virement-form').style.display = 'none';
                
                document.getElementById(this.value + '-form').style.display = 'block';
            });
        });
        
        // Credit card form live preview
        document.getElementById('card_number').addEventListener('input', function() {
            let value = this.value.replace(/\s/g, '').replace(/\D/g, '');
            if (value.length > 0) {
                value = value.match(/.{1,4}/g).join(' ');
            }
            this.value = value;
            document.getElementById('card-number-display').textContent = value || '**** **** **** ****';
        });
        
        document.getElementById('card_holder').addEventListener('input', function() {
            document.getElementById('card-holder-display').textContent = this.value.toUpperCase() || 'NOM DU TITULAIRE';
        });
        
        document.getElementById('card_expiry').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            
            if (value.length > 2) {
                value = value.substring(0, 2) + '/' + value.substring(2);
            }
            
            this.value = value.substring(0, 5);
            document.getElementById('expiry-display').textContent = value || 'MM/YY';
        });
        
        document.getElementById('card_cvv').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 3);
            document.getElementById('cvv-display').textContent = this.value.replace(/./g, '*');
        });
    </script>
</body>
</html>