<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if tax_id is provided
if (!isset($_GET['tax_id']) || empty($_GET['tax_id'])) {
    $_SESSION['error'] = "Aucun ID de taxe spécifié.";
    header("Location: view-taxes.php");
    exit();
}

$tax_id = $_GET['tax_id'];

// Fetch complaints related to the specified tax_id
$complaints_query = "SELECT c.*, 
                    CASE 
                        WHEN c.status = 'Nouveau' THEN 'bg-danger'
                        WHEN c.status = 'En cours' THEN 'bg-warning'
                        WHEN c.status = 'Résolu' THEN 'bg-success'
                        ELSE 'bg-secondary'
                    END as status_class
                    FROM complaints c 
                    WHERE c.tax_id = ? AND c.user_id = ?
                    ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($complaints_query);
$stmt->execute([$tax_id, $user_id]);
$complaints_result = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réclamations pour la Taxe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include_once '../includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <h1 class="mb-4">Réclamations pour la Taxe #<?php echo htmlspecialchars($tax_id); ?></h1>
        
        <?php if (count($complaints_result) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Sujet</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints_result as $complaint): ?>
                            <tr>
                                <td>#<?php echo $complaint['complaint_id']; ?></td>
                                <td><?php echo htmlspecialchars($complaint['subject']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($complaint['created_at'])); ?></td>
                                <td><span class="badge <?php echo $complaint['status_class']; ?>"><?php echo $complaint['status']; ?></span></td>
                                <td>
                                    <a href="submit-complaint.php?view=<?php echo $complaint['complaint_id']; ?>" class="btn btn-sm btn-info">Détails</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-center">Aucune réclamation trouvée pour cette taxe.</p>
        <?php endif; ?>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>