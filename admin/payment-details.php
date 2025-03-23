<?php
// Start session
session_start();
require "../includes/header.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "tax_management");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get payment ID from URL parameter
$payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate payment belongs to the current user or is accessible by the admin
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$query = "SELECT p.*, t.tax_name, t.tax_type, t.description, t.tax_year, t.due_date, 
                 u.full_name, u.username, u.email, u.phone
          FROM payments p
          INNER JOIN taxes t ON p.tax_id = t.tax_id
          INNER JOIN users u ON p.user_id = u.user_id
          WHERE p.payment_id = ?";

if ($role !== 'administrateur') {
    $query .= " AND p.user_id = ?";
}

$stmt = $conn->prepare($query);
if ($role === 'administrateur') {
    $stmt->bind_param("i", $payment_id);
} else {
    $stmt->bind_param("ii", $payment_id, $user_id);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Paiement non trouvé ou accès refusé.");
}

$payment = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Paiement</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container-fluid mt-4">
        <h2>Détails du Paiement</h2>
        <div class="card mt-4">
            <div class="card-header">
                <h5>Informations sur le Paiement</h5>
            </div>
            <div class="card-body">
                <p><strong>ID du Paiement:</strong> <?php echo $payment['payment_id']; ?></p>
                <p><strong>Nom de la Taxe:</strong> <?php echo htmlspecialchars($payment['tax_name']); ?></p>
                <p><strong>Type de Taxe:</strong> <?php echo htmlspecialchars($payment['tax_type']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($payment['description']); ?></p>
                <p><strong>Année:</strong> <?php echo htmlspecialchars($payment['tax_year']); ?></p>
                <p><strong>Date d'Échéance:</strong> <?php echo date('d/m/Y', strtotime($payment['due_date'])); ?></p>
                <p><strong>Montant:</strong> <?php echo number_format($payment['amount'], 2) . ' DH'; ?></p>
                <p><strong>Date de Paiement:</strong> <?php echo date('d/m/Y H:i', strtotime($payment['payment_date'])); ?></p>
                <p><strong>Méthode de Paiement:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?></p>
                <p><strong>Référence de Transaction:</strong> <?php echo htmlspecialchars($payment['transaction_ref']); ?></p>
                <p><strong>Statut:</strong> 
                    <span class="badge <?php 
                        echo ($payment['payment_status'] == 'pending') ? 'badge-warning' : 
                            (($payment['payment_status'] == 'completed') ? 'badge-success' : 'badge-danger'); 
                    ?>">
                        <?php 
                            echo ($payment['payment_status'] == 'pending') ? 'En attente' : 
                                (($payment['payment_status'] == 'completed') ? 'Complété' : 'Échoué'); 
                        ?>
                    </span>
                </p>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5>Informations sur l'Utilisateur</h5>
            </div>
            <div class="card-body">
                <p><strong>Nom:</strong> <?php echo htmlspecialchars($payment['full_name']); ?></p>
                <p><strong>Nom d'utilisateur:</strong> <?php echo htmlspecialchars($payment['username']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($payment['email']); ?></p>
                <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($payment['phone']); ?></p>
            </div>
        </div>

        <div class="mt-4">
            <a href="view-payments.php" class="btn btn-secondary">Retour</a>
            <?php if (!empty($payment['receipt_path']) && file_exists($payment['receipt_path'])): ?>
                <a href="view-payments.php?download=<?php echo $payment['payment_id']; ?>" class="btn btn-primary">Télécharger le Reçu</a>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php
// Close connection
$conn->close();
?>