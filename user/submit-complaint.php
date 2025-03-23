<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's previous complaints
$complaints_query = "SELECT c.*, 
                    CASE 
                        WHEN c.status = 'Nouveau' THEN 'bg-danger'
                        WHEN c.status = 'En cours' THEN 'bg-warning'
                        WHEN c.status = 'Résolu' THEN 'bg-success'
                        ELSE 'bg-secondary'
                    END as status_class
                    FROM complaints c 
                    WHERE c.user_id = ? 
                    ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($complaints_query);
$stmt->execute([$user_id]);
$complaints_result = $stmt->fetchAll();

// Get user taxes for the complaint form
$taxes_query = "SELECT t.*
                FROM taxes t 
                LEFT JOIN payments p ON t.tax_id = p.tax_id AND p.user_id = ?
                WHERE 1=1
                ORDER BY t.due_date DESC";

$stmt = $pdo->prepare($taxes_query);
$stmt->execute([$user_id]);
$taxes_result = $stmt->fetchAll();

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sujet = trim($_POST['sujet']);
    $description = trim($_POST['description']);
    $tax_id = !empty($_POST['tax_id']) ? $_POST['tax_id'] : null;
    
    // Validate inputs
    if (empty($sujet)) {
        $error_message = "Veuillez entrer un sujet pour votre réclamation.";
    } elseif (empty($description)) {
        $error_message = "Veuillez décrire votre réclamation.";
    } else {
        // Handle file upload if present
        $piece_jointe = null;
        $upload_success = true;
        
        if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['size'] > 0) {
            $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($_FILES['piece_jointe']['type'], $allowed_types)) {
                $error_message = "Type de fichier non autorisé. Seuls les PDF, JPEG et PNG sont acceptés.";
                $upload_success = false;
            } elseif ($_FILES['piece_jointe']['size'] > $max_size) {
                $error_message = "La pièce jointe est trop volumineuse. Maximum 5 MB.";
                $upload_success = false;
            } else {
                // Create upload directory if it doesn't exist
                $upload_dir = '../uploads/complaints/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $filename = uniqid() . '_' . basename($_FILES['piece_jointe']['name']);
                $target_file = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $target_file)) {
                    $piece_jointe = $filename;
                } else {
                    $error_message = "Erreur lors du téléchargement du fichier.";
                    $upload_success = false;
                }
            }
        }
        
        if ($upload_success) {
            try {
                // Let's try to use the DEFAULT value from the database instead of specifying it
                if ($tax_id) {
                    $insert_query = "INSERT INTO complaints (user_id, tax_id, subject, description, attachment) 
                                    VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($insert_query);
                    $stmt->execute([$user_id, $tax_id, $sujet, $description, $piece_jointe]);
                } else {
                    $insert_query = "INSERT INTO complaints (user_id, subject, description, attachment) 
                                    VALUES (?, ?, ?, ?)";
                    $stmt = $pdo->prepare($insert_query);
                    $stmt->execute([$user_id, $sujet, $description, $piece_jointe]);
                }
                
                if ($stmt->rowCount() > 0) {
                    $success_message = "Votre réclamation a été soumise avec succès. Nous la traiterons dans les plus brefs délais.";
                    // Clear form after successful submission
                    $_POST = array();
                } else {
                    $error_message = "Erreur lors de la soumission de la réclamation.";
                }
            } catch (PDOException $e) {
                $error_message = "Erreur de base de données: " . $e->getMessage();
            }
        }
    }
}

// Handle viewing a specific complaint
$view_complaint = null;
if (isset($_GET['view'])) {
    $complaint_id = $_GET['view'];
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
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réclamations - Portail Citoyen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include_once '../includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <h1 class="mb-4">Gestion des Réclamations</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Mes Réclamations</h5>
                    </div>
                    <div class="card-body">
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
                                                <!-- Change this line in the table -->
                                                <td>
                                                    <a href="detailer.php?id=<?php echo $complaint['complaint_id']; ?>" class="btn btn-sm btn-info">Détails</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Vous n'avez pas encore soumis de réclamation.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Nouvelle Réclamation</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="sujet" class="form-label">Sujet</label>
                                <input type="text" class="form-control" id="sujet" name="sujet" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tax_id" class="form-label">Taxe concernée (optionnel)</label>
                                <select class="form-control" id="tax_id" name="tax_id">
                                    <option value="">Sélectionner une taxe</option>
                                    <?php foreach ($taxes_result as $tax): ?>
                                        <option value="<?php echo $tax['tax_id']; ?>">
                                            <?php echo htmlspecialchars($tax['tax_name'] . ' - ' . $tax['amount'] . ' €'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="piece_jointe" class="form-label">Pièce jointe (PDF, JPEG, PNG - 5MB max)</label>
                                <input type="file" class="form-control" id="piece_jointe" name="piece_jointe">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Soumettre</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($view_complaint): ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Détails de la Réclamation</h5>
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
                            <a href="submit-complaint.php" class="btn btn-secondary">Retour</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>