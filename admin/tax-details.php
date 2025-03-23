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

// Get tax ID from URL parameter
$tax_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch tax details
$query = "SELECT t.*, 
                 COUNT(p.payment_id) AS total_payments, 
                 IFNULL(SUM(p.amount), 0) AS total_collected
          FROM taxes t
          LEFT JOIN payments p ON t.tax_id = p.tax_id
          WHERE t.tax_id = ?
          GROUP BY t.tax_id, t.tax_name, t.tax_type, t.description, t.tax_year, t.due_date, t.amount";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $tax_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Taxe non trouvée.");
}

$tax = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la Taxe</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container-fluid mt-4">
        <h2>Détails de la Taxe</h2>
        <div class="card mt-4">
            <div class="card-header">
                <h5>Informations sur la Taxe</h5>
            </div>
            <div class="card-body">
                <p><strong>ID de la Taxe:</strong> <?php echo $tax['tax_id']; ?></p>
                <p><strong>Nom de la Taxe:</strong> <?php echo htmlspecialchars($tax['tax_name']); ?></p>
                <p><strong>Type de Taxe:</strong> <?php echo htmlspecialchars($tax['tax_type']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($tax['description']); ?></p>
                <p><strong>Année:</strong> <?php echo htmlspecialchars($tax['tax_year']); ?></p>
                <p><strong>Date d'Échéance:</strong> <?php echo date('d/m/Y', strtotime($tax['due_date'])); ?></p>
                <p><strong>Montant:</strong> <?php echo number_format($tax['amount'], 2) . ' DH'; ?></p>
                <p><strong>Total des Paiements:</strong> <?php echo $tax['total_payments']; ?></p>
                <p><strong>Total Collecté:</strong> <?php echo number_format($tax['total_collected'], 2) . ' DH'; ?></p>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5>Utilisateurs Assignés</h5>
            </div>
            <div class="card-body">
                <?php
                // Fetch users assigned to this tax
                $users_query = "SELECT u.full_name, u.email, ut.status 
                                FROM user_taxes ut
                                INNER JOIN users u ON ut.user_id = u.user_id
                                WHERE ut.tax_id = ?";
                $users_stmt = $conn->prepare($users_query);
                $users_stmt->bind_param("i", $tax_id);
                $users_stmt->execute();
                $users_result = $users_stmt->get_result();

                if ($users_result->num_rows > 0): ?>
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo ($user['status'] === 'pending') ? 'badge-warning' : 
                                                 (($user['status'] === 'completed') ? 'badge-success' : 'badge-danger'); 
                                        ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun utilisateur assigné à cette taxe.</p>
                <?php endif; ?>
                <?php $users_stmt->close(); ?>
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