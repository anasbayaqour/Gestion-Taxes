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

// Get user-tax ID from URL parameter
$user_tax_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch user-tax details
$query = "SELECT ut.*, 
                 t.tax_name, t.tax_type, t.description, t.tax_year, t.due_date, t.amount,
                 u.full_name, u.email, u.phone
          FROM user_taxes ut
          INNER JOIN taxes t ON ut.tax_id = t.tax_id
          INNER JOIN users u ON ut.user_id = u.user_id
          WHERE ut.user_tax_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_tax_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Affectation de taxe non trouvée.");
}

$user_tax = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de l'Affectation de Taxe</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container-fluid mt-4">
        <h2>Détails de l'Affectation de Taxe</h2>
        <div class="card mt-4">
            <div class="card-header">
                <h5>Informations sur la Taxe</h5>
            </div>
            <div class="card-body">
                <p><strong>Nom de la Taxe:</strong> <?php echo htmlspecialchars($user_tax['tax_name']); ?></p>
                <p><strong>Type de Taxe:</strong> <?php echo htmlspecialchars($user_tax['tax_type']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($user_tax['description']); ?></p>
                <p><strong>Année:</strong> <?php echo htmlspecialchars($user_tax['tax_year']); ?></p>
                <p><strong>Date d'Échéance:</strong> <?php echo date('d/m/Y', strtotime($user_tax['due_date'])); ?></p>
                <p><strong>Montant:</strong> <?php echo number_format($user_tax['amount'], 2) . ' DH'; ?></p>
                <p><strong>Statut:</strong> 
                    <span class="badge <?php 
                        echo ($user_tax['status'] === 'pending') ? 'badge-warning' : 
                             (($user_tax['status'] === 'completed') ? 'badge-success' : 'badge-danger'); 
                    ?>">
                        <?php echo ucfirst($user_tax['status']); ?>
                    </span>
                </p>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5>Informations sur l'Utilisateur</h5>
            </div>
            <div class="card-body">
                <p><strong>Nom:</strong> <?php echo htmlspecialchars($user_tax['full_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user_tax['email']); ?></p>
                <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($user_tax['phone']); ?></p>
            </div>
        </div>

        <div class="mt-4">
            <a href="manage-taxes.php" class="btn btn-secondary">Retour</a>
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