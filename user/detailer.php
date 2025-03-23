<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if complaint ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: submit-complaint.php");
    exit();
}

$complaint_id = $_GET['id'];

// Get complaint details
$view_query = "SELECT c.*, 
              CASE 
                  WHEN c.status = 'Nouveau' THEN 'bg-danger'
                  WHEN c.status = 'En cours' THEN 'bg-warning'
                  WHEN c.status = 'Résolu' THEN 'bg-success'
                  ELSE 'bg-secondary'
              END as status_class
              FROM complaints c 
              WHERE c.complaint_id = ? AND c.user_id = ?";
$stmt = $pdo->prepare($view_query);
$stmt->execute([$complaint_id, $user_id]);
$view_complaint = $stmt->fetch();

// If complaint doesn't exist or doesn't belong to the user
if (!$view_complaint) {
    header("Location: submit-complaint.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la Réclamation - Portail Citoyen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include_once '../includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <h1 class="mb-4">Détails de la Réclamation</h1>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Réclamation #<?php echo $view_complaint['complaint_id']; ?></h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Référence:</strong> #<?php echo $view_complaint['complaint_id']; ?></p>
                        <p><strong>Sujet:</strong> <?php echo htmlspecialchars($view_complaint['subject']); ?></p>
                        <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($view_complaint['description'])); ?></p>
                        <p><strong>Date de Soumission:</strong> <?php echo date('d/m/Y', strtotime($view_complaint['created_at'])); ?></p>
                        <p><strong>Statut:</strong> <span class="badge <?php echo $view_complaint['status_class']; ?>"><?php echo $view_complaint['status']; ?></span></p>
                        <?php if ($view_complaint['attachment']): ?>
                            <p><strong>Pièce Jointe:</strong> <a href="../uploads/complaints/<?php echo $view_complaint['attachment']; ?>" target="_blank">Voir la pièce jointe</a></p>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <a href="submit-complaint.php" class="btn btn-secondary">Retour</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>