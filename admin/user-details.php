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

// Get user ID from URL parameter
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch user details
$query = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Utilisateur non trouvé.");
}

$user = $result->fetch_assoc();
$stmt->close();

// Fetch user's associated data (e.g., taxes, payments, complaints)
$taxes_query = "SELECT t.*, ut.status FROM user_taxes ut JOIN taxes t ON ut.tax_id = t.tax_id WHERE ut.user_id = ?";
$taxes_stmt = $conn->prepare($taxes_query);
$taxes_stmt->bind_param("i", $user_id);
$taxes_stmt->execute();
$taxes_result = $taxes_stmt->get_result();

$payments_query = "SELECT p.*, t.tax_name FROM payments p JOIN taxes t ON p.tax_id = t.tax_id WHERE p.user_id = ?";
$payments_stmt = $conn->prepare($payments_query);
$payments_stmt->bind_param("i", $user_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();

$complaints_query = "SELECT * FROM complaints WHERE user_id = ?";
$complaints_stmt = $conn->prepare($complaints_query);
$complaints_stmt->bind_param("i", $user_id);
$complaints_stmt->execute();
$complaints_result = $complaints_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de l'utilisateur</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h2>Détails de l'utilisateur</h2>
        <div class="card mt-4">
            <div class="card-header">
                <h5>Informations personnelles</h5>
            </div>
            <div class="card-body">
                <p><strong>ID:</strong> <?php echo $user['user_id']; ?></p>
                <p><strong>Nom d'utilisateur:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Nom complet:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
                <p><strong>Rôle:</strong> <?php echo ucfirst($user['role']); ?></p>
                <p><strong>Adresse:</strong> <?php echo htmlspecialchars($user['address']); ?></p>
                <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
                <p><strong>Date de création:</strong> <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5>Taxes assignées</h5>
            </div>
            <div class="card-body">
                <?php if ($taxes_result->num_rows > 0): ?>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Nom de la taxe</th>
                                <th>Type</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Date d'échéance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($tax = $taxes_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tax['tax_name']); ?></td>
                                    <td><?php echo htmlspecialchars($tax['tax_type']); ?></td>
                                    <td><?php echo number_format($tax['amount'], 2) . ' DH'; ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo ($tax['status'] === 'pending') ? 'badge-warning' : 
                                                 (($tax['status'] === 'completed') ? 'badge-success' : 'badge-danger'); 
                                        ?>">
                                            <?php echo ucfirst($tax['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($tax['due_date'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucune taxe assignée.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5>Paiements</h5>
            </div>
            <div class="card-body">
                <?php if ($payments_result->num_rows > 0): ?>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Nom de la taxe</th>
                                <th>Montant</th>
                                <th>Date de paiement</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($payment = $payments_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['tax_name']); ?></td>
                                    <td><?php echo number_format($payment['amount'], 2) . ' DH'; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo ($payment['payment_status'] === 'completed') ? 'badge-success' : 
                                                 (($payment['payment_status'] === 'pending') ? 'badge-warning' : 'badge-danger'); 
                                        ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun paiement effectué.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5>Réclamations</h5>
            </div>
            <div class="card-body">
                <?php if ($complaints_result->num_rows > 0): ?>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Sujet</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($complaint = $complaints_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($complaint['subject']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo ($complaint['status'] === 'open') ? 'badge-danger' : 
                                                 (($complaint['status'] === 'in_progress') ? 'badge-warning' : 'badge-success'); 
                                        ?>">
                                            <?php echo ucfirst($complaint['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($complaint['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucune réclamation soumise.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-4">
            <a href="manage-users.php" class="btn btn-secondary">Retour</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php
// Close database connections
$taxes_stmt->close();
$payments_stmt->close();
$complaints_stmt->close();
$conn->close();
?>