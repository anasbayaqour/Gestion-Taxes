<?php
// Start session
session_start();
require "../includes/header.php";
// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "tax_management");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set default filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$tax_filter = isset($_GET['tax_id']) ? $_GET['tax_id'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Base query
$query = "SELECT p.*, u.username, u.full_name, t.tax_name, t.tax_type 
         FROM payments p
         INNER JOIN users u ON p.user_id = u.user_id
         INNER JOIN taxes t ON p.tax_id = t.tax_id
         WHERE 1=1";

// Apply filters
if ($_SESSION['role'] !== 'administrateur') {
    // Regular users can only see their own payments
    $query .= " AND p.user_id = " . $_SESSION['user_id'];
}

if (!empty($status_filter)) {
    $query .= " AND p.payment_status = '" . $conn->real_escape_string($status_filter) . "'";
}

if (!empty($date_from)) {
    $query .= " AND DATE(p.payment_date) >= '" . $conn->real_escape_string($date_from) . "'";
}

if (!empty($date_to)) {
    $query .= " AND DATE(p.payment_date) <= '" . $conn->real_escape_string($date_to) . "'";
}

if (!empty($tax_filter)) {
    $query .= " AND p.tax_id = " . $conn->real_escape_string($tax_filter);
}

if (!empty($search)) {
    $query .= " AND (u.username LIKE '%" . $conn->real_escape_string($search) . "%' 
                OR u.full_name LIKE '%" . $conn->real_escape_string($search) . "%'
                OR t.tax_name LIKE '%" . $conn->real_escape_string($search) . "%'
                OR p.transaction_ref LIKE '%" . $conn->real_escape_string($search) . "%')";
}

// Order by
$query .= " ORDER BY p.payment_date DESC";

// Execute query
$result = $conn->query($query);

// Get taxes for filter dropdown
$taxes_query = "SELECT tax_id, tax_name FROM taxes ORDER BY tax_name";
$taxes_result = $conn->query($taxes_query);
$taxes = [];
while ($tax = $taxes_result->fetch_assoc()) {
    $taxes[] = $tax;
}

// Process receipt download
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $payment_id = $conn->real_escape_string($_GET['download']);
    
    // Get receipt path
    $receipt_query = "SELECT receipt_path FROM payments WHERE payment_id = $payment_id";
    if ($_SESSION['role'] !== 'administrateur') {
        $receipt_query .= " AND user_id = " . $_SESSION['user_id'];
    }
    
    $receipt_result = $conn->query($receipt_query);
    
    if ($receipt_result->num_rows > 0) {
        $receipt = $receipt_result->fetch_assoc();
        $file_path = $receipt['receipt_path'];
        
        if (file_exists($file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . basename($file_path));
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            $error_message = "File not found!";
        }
    } else {
        $error_message = "Receipt not found or you don't have permission to download it!";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation des Paiements</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        .filter-card {
            margin-bottom: 20px;
        }
        .badge-pending {
            background-color: #f0ad4e;
        }
        .badge-completed {
            background-color: #5cb85c;
        }
        .badge-failed {
            background-color: #d9534f;
        }
    </style>
</head>
<body>
   
    
    <div class="container-fluid mt-4">
        <h2>Consultation des Paiements</h2>
        
        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="card filter-card">
            <div class="card-header">
                <h5 class="mb-0">Filtres de recherche</h5>
            </div>
            <div class="card-body">
                <form method="get" action="" class="row">
                    <div class="form-group col-md-3">
                        <label>Recherche</label>
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nom, référence...">
                    </div>
                    
                    <div class="form-group col-md-2">
                        <label>Statut</label>
                        <select name="status" class="form-control">
                            <option value="">Tous</option>
                            <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>En attente</option>
                            <option value="completed" <?php echo ($status_filter == 'completed') ? 'selected' : ''; ?>>Complété</option>
                            <option value="failed" <?php echo ($status_filter == 'failed') ? 'selected' : ''; ?>>Échoué</option>
                        </select>
                    </div>
                    
                    <div class="form-group col-md-2">
                        <label>Taxe</label>
                        <select name="tax_id" class="form-control">
                            <option value="">Toutes</option>
                            <?php foreach($taxes as $tax): ?>
                                <option value="<?php echo $tax['tax_id']; ?>" <?php echo ($tax_filter == $tax['tax_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tax['tax_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group col-md-2">
                        <label>Date de début</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="form-group col-md-2">
                        <label>Date de fin</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="form-group col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Liste des Paiements</h5>
                    <?php if ($_SESSION['role'] !== 'administrateur'): ?>
                        <a href="make-payment.php" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> Nouveau Paiement
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <?php if ($_SESSION['role'] === 'administrateur'): ?>
                                    <th>Utilisateur</th>
                                <?php endif; ?>
                                <th>Taxe</th>
                                <th>Montant</th>
                                <th>Date de paiement</th>
                                <th>Méthode</th>
                                <th>Référence</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($result->num_rows > 0): ?>
                                <?php while($payment = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $payment['payment_id']; ?></td>
                                        <?php if ($_SESSION['role'] === 'administrateur'): ?>
                                            <td><?php echo htmlspecialchars($payment['full_name']) . ' (' . htmlspecialchars($payment['username']) . ')'; ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <span title="<?php echo htmlspecialchars($payment['tax_type']); ?>">
                                                <?php echo htmlspecialchars($payment['tax_name']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($payment['amount'], 2) . ' DH'; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($payment['payment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['transaction_ref']); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo ($payment['payment_status'] == 'pending') ? 'badge-pending' : 
                                                    (($payment['payment_status'] == 'completed') ? 'badge-completed' : 'badge-failed'); 
                                            ?>">
                                                <?php 
                                                    echo ($payment['payment_status'] == 'pending') ? 'En attente' : 
                                                        (($payment['payment_status'] == 'completed') ? 'Complété' : 'Échoué'); 
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="payment-details.php?id=<?php echo $payment['payment_id']; ?>" class="btn btn-sm btn-info" title="Voir les détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if(!empty($payment['receipt_path'])): ?>
                                                <a href="view-payments.php?download=<?php echo $payment['payment_id']; ?>" class="btn btn-sm btn-secondary" title="Télécharger le reçu">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if($_SESSION['role'] === 'administrateur' && $payment['payment_status'] === 'pending'): ?>
                                                <a href="update-payment-status.php?id=<?php echo $payment['payment_id']; ?>" class="btn btn-sm btn-warning" title="Mettre à jour le statut">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($_SESSION['role'] === 'administrateur') ? '9' : '8'; ?>" class="text-center">Aucun paiement trouvé</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
// Close connection

require "../includes/footer.php";
$conn->close();
?>