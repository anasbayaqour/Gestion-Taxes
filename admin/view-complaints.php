<?php
// admin/view-complaints.php
session_start();
require "../includes/header.php";
// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "tax_management");

// Vérifier la connexion
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Traiter les soumissions de formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ajouter une nouvelle réclamation
    if (isset($_POST['add_complaint'])) {
        // Récupérer les valeurs du formulaire avec des valeurs par défaut
        $subject = $conn->real_escape_string($_POST['subject'] ?? '');
        $description = $conn->real_escape_string($_POST['description'] ?? '');
        $message = $conn->real_escape_string($_POST['message'] ?? '');
        $user_id = $_SESSION['user_id'];
        
        // Vérifier que tous les champs requis sont remplis
        if (empty($subject) || empty($description) || empty($message)) {
            $error_message = "Tous les champs sont obligatoires!";
        } else {
            // Utiliser une requête préparée pour insérer la réclamation
            $stmt = $conn->prepare("INSERT INTO complaints (user_id, subject, description, status) VALUES (?, ?, ?, 'open')");
            $stmt->bind_param("iss", $user_id, $subject, $description);
            
            if ($stmt->execute()) {
                $complaint_id = $stmt->insert_id;
                
                // Ajouter le message à la table des messages
                $stmt_message = $conn->prepare("INSERT INTO messages (complaint_id, sender_id, content, read_status) VALUES (?, ?, ?, FALSE)");
                $stmt_message->bind_param("iis", $complaint_id, $user_id, $message);
                $stmt_message->execute();
                $stmt_message->close();
                
                $success_message = "Réclamation envoyée avec succès!";
            } else {
                $error_message = "Erreur: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    // Ajouter une réponse à une réclamation
    if (isset($_POST['add_reply'])) {
        $complaint_id = $conn->real_escape_string($_POST['complaint_id'] ?? '');
        $content = $conn->real_escape_string($_POST['content'] ?? '');
        $sender_id = $_SESSION['user_id'];
        
        // Vérifier si la réclamation existe et que l'utilisateur y a accès
        $check_query = "SELECT c.* FROM complaints c WHERE c.complaint_id = ?";
        if ($_SESSION['role'] !== 'administrateur') {
            $check_query .= " AND c.user_id = ?";
        }
        
        $stmt_check = $conn->prepare($check_query);
        if ($_SESSION['role'] !== 'administrateur') {
            $stmt_check->bind_param("ii", $complaint_id, $_SESSION['user_id']);
        } else {
            $stmt_check->bind_param("i", $complaint_id);
        }
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();
        
        if ($check_result->num_rows > 0) {
            // Insérer la réponse
            $stmt_reply = $conn->prepare("INSERT INTO messages (complaint_id, sender_id, content, read_status) VALUES (?, ?, ?, FALSE)");
            $stmt_reply->bind_param("iis", $complaint_id, $sender_id, $content);
            
            if ($stmt_reply->execute()) {
                // Mettre à jour le statut de la réclamation si l'admin répond
                if ($_SESSION['role'] === 'administrateur') {
                    $complaint = $check_result->fetch_assoc();
                    
                    if ($complaint['status'] === 'open') {
                        $update_status = "UPDATE complaints SET status = 'in_progress' WHERE complaint_id = ?";
                        $stmt_update = $conn->prepare($update_status);
                        $stmt_update->bind_param("i", $complaint_id);
                        $stmt_update->execute();
                        $stmt_update->close();
                    }
                    
                    // Créer une notification pour l'utilisateur
                    $user_id = $complaint['user_id'];
                    $notification_title = "Réponse à votre réclamation";
                    $notification_message = "Un administrateur a répondu à votre réclamation concernant '" . $conn->real_escape_string($complaint['subject']) . "'.";
                    
                    $stmt_notification = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                    $stmt_notification->bind_param("iss", $user_id, $notification_title, $notification_message);
                    $stmt_notification->execute();
                    $stmt_notification->close();
                }
                
                $success_message = "Réponse envoyée avec succès!";
            } else {
                $error_message = "Erreur: " . $stmt_reply->error;
            }
            $stmt_reply->close();
        } else {
            $error_message = "Réclamation non trouvée ou accès non autorisé!";
        }
        $stmt_check->close();
    }
    
    // Mettre à jour le statut de la réclamation
    if (isset($_POST['update_status']) && $_SESSION['role'] === 'administrateur') {
        $complaint_id = $conn->real_escape_string($_POST['complaint_id'] ?? '');
        $status = $conn->real_escape_string($_POST['update_status'] ?? '');
        
        // Vérifier que le statut est valide (pour éviter l'erreur de troncation)
        $valid_statuses = ['open', 'in_progress', 'resolved', 'closed'];
        if (in_array($status, $valid_statuses)) {
            // Mettre à jour le statut
            $stmt_update = $conn->prepare("UPDATE complaints SET status = ? WHERE complaint_id = ?");
            $stmt_update->bind_param("si", $status, $complaint_id);
            
            if ($stmt_update->execute()) {
                $success_message = "Statut mis à jour avec succès!";
                
                // Récupérer l'ID de l'utilisateur pour la notification
                $stmt_user = $conn->prepare("SELECT user_id, subject FROM complaints WHERE complaint_id = ?");
                $stmt_user->bind_param("i", $complaint_id);
                $stmt_user->execute();
                $user_result = $stmt_user->get_result();
                $complaint = $user_result->fetch_assoc();
                $stmt_user->close();
                
                // Créer une notification
                $user_id = $complaint['user_id'];
                $notification_title = "Mise à jour du statut de réclamation";
                
                // Traduire le statut pour l'affichage
                $status_display = '';
                switch($status) {
                    case 'open': $status_display = 'Ouvert'; break;
                    case 'in_progress': $status_display = 'En cours'; break;
                    case 'resolved': $status_display = 'Résolu'; break;
                    case 'closed': $status_display = 'Fermé'; break;
                }
                
                $notification_message = "Le statut de votre réclamation concernant '" . $conn->real_escape_string($complaint['subject']) . "' a été mis à jour à '$status_display'.";
                
                $stmt_notification = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                $stmt_notification->bind_param("iss", $user_id, $notification_title, $notification_message);
                $stmt_notification->execute();
                $stmt_notification->close();
            } else {
                $error_message = "Erreur: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $error_message = "Statut invalide!";
        }
    }
}

// Récupérer les réclamations pour l'affichage
$complaints_query = "SELECT c.*, u.username, u.full_name, COUNT(m.message_id) as message_count, 
                    EXISTS (
                        SELECT 1 
                        FROM messages m2 
                        WHERE m2.complaint_id = c.complaint_id 
                        AND m2.read_status = FALSE 
                        AND m2.sender_id != " . $_SESSION['user_id'] . "
                    ) as has_unread
                    FROM complaints c 
                    INNER JOIN users u ON c.user_id = u.user_id 
                    LEFT JOIN messages m ON c.complaint_id = m.complaint_id
                    WHERE 1=1";

if ($_SESSION['role'] !== 'administrateur') {
    // Les utilisateurs normaux ne peuvent voir que leurs propres réclamations
    $complaints_query .= " AND c.user_id = " . $_SESSION['user_id'];
}

$complaints_query .= " GROUP BY c.complaint_id ORDER BY 
                      CASE 
                          WHEN c.status = 'open' THEN 1
                          WHEN c.status = 'in_progress' THEN 2
                          WHEN c.status = 'resolved' THEN 3
                          WHEN c.status = 'closed' THEN 4
                      END, 
                      c.created_at DESC";

$complaints_result = $conn->query($complaints_query);

// Récupérer une réclamation spécifique pour la visualisation
$view_complaint = null;
$messages = [];
if (isset($_GET['view']) && !empty($_GET['view'])) {
    $complaint_id = $conn->real_escape_string($_GET['view']);
    
    // Récupérer les détails de la réclamation
    $view_query = "SELECT c.*, u.username, u.full_name FROM complaints c 
                  INNER JOIN users u ON c.user_id = u.user_id 
                  WHERE c.complaint_id = ?";
    
    if ($_SESSION['role'] !== 'administrateur') {
        $view_query .= " AND c.user_id = ?";
    }
    
    $stmt_view = $conn->prepare($view_query);
    if ($_SESSION['role'] !== 'administrateur') {
        $stmt_view->bind_param("ii", $complaint_id, $_SESSION['user_id']);
    } else {
        $stmt_view->bind_param("i", $complaint_id);
    }
    $stmt_view->execute();
    $view_result = $stmt_view->get_result();
    
    if ($view_result->num_rows > 0) {
        $view_complaint = $view_result->fetch_assoc();
        
        // Récupérer les messages pour cette réclamation
        $messages_query = "SELECT m.*, u.username, u.full_name, u.role 
                          FROM messages m 
                          INNER JOIN users u ON m.sender_id = u.user_id 
                          WHERE m.complaint_id = ? 
                          ORDER BY m.created_at";
        $stmt_messages = $conn->prepare($messages_query);
        $stmt_messages->bind_param("i", $complaint_id);
        $stmt_messages->execute();
        $messages_result = $stmt_messages->get_result();
        
        while ($message = $messages_result->fetch_assoc()) {
            $messages[] = $message;
        }
        $stmt_messages->close();
        
        // Marquer les messages comme lus si l'utilisateur les consulte
        $update_read = "UPDATE messages SET read_status = TRUE 
                       WHERE complaint_id = ? 
                       AND sender_id != ?";
        $stmt_update_read = $conn->prepare($update_read);
        $stmt_update_read->bind_param("ii", $complaint_id, $_SESSION['user_id']);
        $stmt_update_read->execute();
        $stmt_update_read->close();
    }
    $stmt_view->close();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Système de Gestion des Taxes - Réclamations</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        .message-container {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 15px;
            background-color: #f8f9fa;
        }
        .message {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
            position: relative;
        }
        .message-user {
            background-color: #DCF8C6;
            margin-left: 20px;
        }
        .message-admin {
            background-color: #F2F2F2;
            margin-right: 20px;
        }
        .message-timestamp {
            font-size: 0.7em;
            color: #777;
            position: absolute;
            bottom: 2px;
            right: 8px;
        }
        .status-open {
            background-color: #dc3545;
            color: white;
        }
        .status-in_progress {
            background-color: #ffc107;
            color: #212529;
        }
        .status-resolved {
            background-color: #28a745;
            color: white;
        }
        .status-closed {
            background-color: #6c757d;
            color: white;
        }
        .complaint-card {
            transition: all 0.3s ease;
        }
        .complaint-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .unread-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #dc3545;
            display: inline-block;
            margin-left: 5px;
        }
        .card-header.bg-primary {
            background-color: #4e73df !important;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <?php if(isset($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-white">Réclamations</h5>
                        <button type="button" class="btn btn-light btn-sm" data-toggle="modal" data-target="#newComplaintModal">
                            <i class="fas fa-plus"></i> Nouvelle
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group">
                            <?php if($complaints_result && $complaints_result->num_rows > 0): ?>
                                <?php while($complaint = $complaints_result->fetch_assoc()): ?>
                                    <a href="?view=<?php echo $complaint['complaint_id']; ?>" 
                                       class="list-group-item list-group-item-action complaint-card 
                                              <?php echo (isset($_GET['view']) && $_GET['view'] == $complaint['complaint_id']) ? 'bg-light' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($complaint['subject']); ?>
                                                <?php if($complaint['has_unread']): ?>
                                                    <span class="unread-indicator"></span>
                                                <?php endif; ?>
                                            </h6>
                                            <span class="badge badge-pill status-<?php echo $complaint['status']; ?>">
                                                <?php 
                                                    switch($complaint['status']) {
                                                        case 'open': echo 'Ouvert'; break;
                                                        case 'in_progress': echo 'En cours'; break;
                                                        case 'resolved': echo 'Résolu'; break;
                                                        case 'closed': echo 'Fermé'; break;
                                                        default: echo $complaint['status']; // Fallback
                                                    }
                                                ?>
                                            </span>
                                        </div>
                                        <small>
                                            <?php echo htmlspecialchars($complaint['full_name']); ?> • 
                                            <?php echo date('d/m/Y H:i', strtotime($complaint['created_at'])); ?> •
                                            <?php echo $complaint['message_count']; ?> message(s)
                                        </small>
                                    </a>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="list-group-item text-center">
                                    <p class="mb-0">Aucune réclamation trouvée</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <?php if($view_complaint): ?>
                    <div class="card">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5><?php echo htmlspecialchars($view_complaint['subject']); ?></h5>
                                <div>
                                    <span class="badge badge-pill status-<?php echo $view_complaint['status']; ?> mr-2">
                                        <?php 
                                            switch($view_complaint['status']) {
                                                case 'open': echo 'Ouvert'; break;
                                                case 'in_progress': echo 'En cours'; break;
                                                case 'resolved': echo 'Résolu'; break;
                                                case 'closed': echo 'Fermé'; break;
                                                default: echo $view_complaint['status']; // Fallback
                                            }
                                        ?>
                                    </span>
                                    <?php if($_SESSION['role'] === 'administrateur'): ?>
                                        <div class="dropdown d-inline">
                                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="statusDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                Statut ▼
                                            </button>
                                            <div class="dropdown-menu" aria-labelledby="statusDropdown">
                                                <form method="post" action="">
                                                    <input type="hidden" name="complaint_id" value="<?php echo $view_complaint['complaint_id']; ?>">
                                                    <button type="submit" name="update_status" value="open" class="dropdown-item">Ouvert</button>
                                                    <button type="submit" name="update_status" value="in_progress" class="dropdown-item">En cours</button>
                                                    <button type="submit" name="update_status" value="resolved" class="dropdown-item">Résolu</button>
                                                    <button type="submit" name="update_status" value="closed" class="dropdown-item">Fermé</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-2">
                                <small>
                                    Par <?php echo htmlspecialchars($view_complaint['full_name']); ?> • 
                                    Créé le <?php echo date('d/m/Y H:i', strtotime($view_complaint['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="message-container">
                                <?php foreach($messages as $message): ?>
                                    <div class="message 
                                           <?php echo ($_SESSION['user_id'] == $message['sender_id']) ? 'message-user' : 'message-admin'; ?>">
                                        <strong>
                                            <?php echo htmlspecialchars($message['full_name']); ?>
                                            <?php if($message['role'] === 'administrateur'): ?>
                                                <span class="badge badge-info">Admin</span>
                                            <?php endif; ?>
                                        </strong>
                                        <p><?php echo nl2br(htmlspecialchars($message['content'])); ?></p>
                                        <div class="message-timestamp">
                                            <?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if($view_complaint['status'] !== 'closed'): ?>
                                <form method="post" action="">
                                    <div class="form-group">
                                        <textarea class="form-control" name="content" rows="3" placeholder="Votre réponse..." required></textarea>
                                    </div>
                                    <input type="hidden" name="complaint_id" value="<?php echo $view_complaint['complaint_id']; ?>">
                                    <button type="submit" name="add_reply" class="btn btn-primary">Répondre</button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-secondary mt-3">
                                    Cette réclamation est fermée. Aucune nouvelle réponse n'est acceptée.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-comments fa-4x mb-3 text-muted"></i>
                            <h5>Sélectionnez une réclamation ou créez-en une nouvelle</h5>
                            <button class="btn btn-primary mt-3" data-toggle="modal" data-target="#newComplaintModal">
                                <i class="fas fa-plus"></i> Nouvelle réclamation
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- New Complaint Modal -->
    <div class="modal fade" id="newComplaintModal" tabindex="-1" role="dialog" aria-labelledby="newComplaintModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newComplaintModalLabel">Nouvelle réclamation</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="complaintSubject">Sujet:</label>
                            <input type="text" class="form-control" id="complaintSubject" name="subject" required>
                        </div>
                        <div class="form-group">
                            <label for="complaintDescription">Description:</label>
                            <textarea class="form-control" id="complaintDescription" name="description" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="complaintMessage">Message:</label>
                            <textarea class="form-control" id="complaintMessage" name="message" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                        <button type="submit" name="add_complaint" class="btn btn-primary">Envoyer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Scroll to bottom of message container when viewing a complaint
        $(document).ready(function() {
            var messageContainer = $('.message-container');
            if (messageContainer.length) {
                messageContainer.scrollTop(messageContainer[0].scrollHeight);
            }
            
            // Auto-hide alerts after 5 seconds
            $('.alert').delay(5000).fadeOut(500);
        });
    </script>
    <?php require "../includes/footer.php"; ?>
</body>
</html>