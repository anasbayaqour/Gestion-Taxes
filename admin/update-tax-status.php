<?php
// Start session
session_start();
require "../includes/header.php";

// Check if user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "tax_management");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the user tax details if ID is provided in the URL
$user_tax = null;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $user_tax_id = $conn->real_escape_string($_GET['id']);
    $stmt = $conn->prepare("SELECT ut.*, u.username, u.full_name, t.tax_name, t.amount, t.due_date, t.tax_year 
                           FROM user_taxes ut 
                           INNER JOIN users u ON ut.user_id = u.user_id 
                           INNER JOIN taxes t ON ut.tax_id = t.tax_id 
                           WHERE ut.user_tax_id = ?");
    $stmt->bind_param("i", $user_tax_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_tax = $result->fetch_assoc();
    } else {
        $_SESSION['error'] = "Taxe utilisateur non trouvée!";
        header("Location: manage-taxes.php");
        exit();
    }
    $stmt->close();
}

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_status'])) {
        $user_tax_id = isset($_POST['user_tax_id']) ? $conn->real_escape_string($_POST['user_tax_id']) : '';
        $status = isset($_POST['status']) ? $conn->real_escape_string($_POST['status']) : '';
        
        // Check if we have valid data
        if (empty($user_tax_id) || empty($status)) {
            $_SESSION['error'] = "Données manquantes pour la mise à jour.";
            header("Location: manage-taxes.php");
            exit();
        }
        
        // Update tax status - WITHOUT the payment_date and payment_reference fields
        $stmt = $conn->prepare("UPDATE user_taxes SET status = ? WHERE user_tax_id = ?");
        $stmt->bind_param("si", $status, $user_tax_id);
        
        if ($stmt->execute()) {
            // If payment is marked as paid, create a payment record
            if ($status == 'paid') {
                // Get tax information
                $tax_query = $conn->prepare("SELECT ut.user_id, ut.tax_id, t.amount 
                                           FROM user_taxes ut 
                                           INNER JOIN taxes t ON ut.tax_id = t.tax_id 
                                           WHERE ut.user_tax_id = ?");
                $tax_query->bind_param("i", $user_tax_id);
                $tax_query->execute();
                $tax_info = $tax_query->get_result()->fetch_assoc();
                $tax_query->close();
                
                $payment_date = date('Y-m-d');
                $payment_reference = isset($_POST['payment_reference']) && !empty($_POST['payment_reference']) ? 
                    $conn->real_escape_string($_POST['payment_reference']) : 
                    'ADMIN-' . date('YmdHis');
                
                // Check if payments table exists and has the necessary columns
                try {
                    // Insert payment record
                    $payment_stmt = $conn->prepare("INSERT INTO payments (user_id, tax_id, amount, payment_date, payment_method, reference, status) 
                                                 VALUES (?, ?, ?, ?, 'administrateur', ?, 'completed')");
                    $payment_stmt->bind_param("iidss", $tax_info['user_id'], $tax_info['tax_id'], $tax_info['amount'], $payment_date, $payment_reference);
                    $payment_stmt->execute();
                    $payment_stmt->close();
                } catch (Exception $e) {
                    // Just log the error but continue (payments table might not exist)
                    error_log("Error creating payment record: " . $e->getMessage());
                }
                
                // Create notification for user
                try {
                    $notification_title = "Paiement confirmé";
                    $notification_message = "Votre paiement pour la taxe a été confirmé par l'administrateur.";
                    
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                    $notif_stmt->bind_param("iss", $tax_info['user_id'], $notification_title, $notification_message);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                } catch (Exception $e) {
                    // Just log the error but continue (notifications table might not exist)
                    error_log("Error creating notification: " . $e->getMessage());
                }
            }
            
            $_SESSION['success'] = "Statut de la taxe mis à jour avec succès!";
        } else {
            $_SESSION['error'] = "Erreur lors de la mise à jour: " . $stmt->error;
        }
        $stmt->close();
        
        header("Location: manage-taxes.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mise à jour du statut de taxe</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h4>Mise à jour du statut de la taxe</h4>
                    </div>
                    <div class="card-body">
                        <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                echo $_SESSION['error']; 
                                unset($_SESSION['error']);
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($user_tax): ?>
                            <div class="alert alert-info">
                                <p><strong>Utilisateur:</strong> <?php echo htmlspecialchars($user_tax['full_name']); ?></p>
                                <p><strong>Taxe:</strong> <?php echo htmlspecialchars($user_tax['tax_name']); ?> (<?php echo $user_tax['tax_year']; ?>)</p>
                                <p><strong>Montant:</strong> <?php echo number_format($user_tax['amount'], 2); ?> DH</p>
                                <p><strong>Échéance:</strong> <?php echo date('d/m/Y', strtotime($user_tax['due_date'])); ?></p>
                                <p><strong>Statut actuel:</strong> 
                                    <span class="badge <?php 
                                        echo ($user_tax['status'] == 'pending') ? 'badge-warning' : 
                                            (($user_tax['status'] == 'paid') ? 'badge-success' : 'badge-danger'); 
                                    ?>">
                                    <?php 
                                        echo ($user_tax['status'] == 'pending') ? 'En attente' : 
                                            (($user_tax['status'] == 'paid') ? 'Payée' : 'En retard'); 
                                    ?>
                                    </span>
                                </p>
                            </div>
                            
                            <form method="post" action="">
                                <input type="hidden" name="user_tax_id" value="<?php echo $user_tax['user_tax_id']; ?>">
                                
                                <div class="form-group">
                                    <label>Nouveau statut</label>
                                    <select name="status" class="form-control" required>
                                        <option value="pending" <?php echo ($user_tax['status'] == 'pending') ? 'selected' : ''; ?>>En attente</option>
                                        <option value="paid" <?php echo ($user_tax['status'] == 'paid') ? 'selected' : ''; ?>>Payée</option>
                                        <option value="overdue" <?php echo ($user_tax['status'] == 'overdue') ? 'selected' : ''; ?>>En retard</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Référence de paiement (optionnel)</label>
                                    <input type="text" name="payment_reference" class="form-control" placeholder="Laissez vide pour générer automatiquement">
                                    <small class="form-text text-muted">Si vous marquez comme payée, une référence sera générée automatiquement si non spécifiée.</small>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" name="update_status" class="btn btn-primary">Mettre à jour</button>
                                    <a href="manage-taxes.php" class="btn btn-secondary">Annuler</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                Aucune information de taxe trouvée. Veuillez sélectionner une taxe à mettre à jour.
                            </div>
                            <a href="manage-taxes.php" class="btn btn-primary">Retour à la gestion des taxes</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php
require "../includes/footer.php";
// Close connection
$conn->close();
?>