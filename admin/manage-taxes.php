<?php
// Démarrer la session
session_start();
require "../includes/header.php";

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: login.php");
    exit();
}

// Connexion à la base de données
$conn = new mysqli("localhost", "root", "", "tax_management");

// Vérifier la connexion
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Traitement des soumissions de formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ajouter une nouvelle taxe
    if (isset($_POST['add_tax'])) {
        $tax_name = $conn->real_escape_string($_POST['tax_name']);
        $tax_type = $conn->real_escape_string($_POST['tax_type']);
        $description = $conn->real_escape_string($_POST['description']);
        $amount = $conn->real_escape_string($_POST['amount']);
        $due_date = $conn->real_escape_string($_POST['due_date']);
        $tax_year = $conn->real_escape_string($_POST['tax_year']);

        // Insérer la nouvelle taxe
        $stmt = $conn->prepare("INSERT INTO taxes (tax_name, tax_type, description, amount, due_date, tax_year) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $tax_name, $tax_type, $description, $amount, $due_date, $tax_year);

        if ($stmt->execute()) {
            $success_message = "Taxe ajoutée avec succès!";
        } else {
            $error_message = "Erreur: " . $stmt->error;
        }
        $stmt->close();
    }

    // Mettre à jour une taxe
    if (isset($_POST['update_tax'])) {
        $tax_id = $conn->real_escape_string($_POST['tax_id']);
        $tax_name = $conn->real_escape_string($_POST['tax_name']);
        $tax_type = $conn->real_escape_string($_POST['tax_type']);
        $description = $conn->real_escape_string($_POST['description']);
        $amount = $conn->real_escape_string($_POST['amount']);
        $due_date = $conn->real_escape_string($_POST['due_date']);
        $tax_year = $conn->real_escape_string($_POST['tax_year']);

        // Mettre à jour la taxe
        $stmt = $conn->prepare("UPDATE taxes SET tax_name = ?, tax_type = ?, description = ?, amount = ?, due_date = ?, tax_year = ? WHERE tax_id = ?");
        $stmt->bind_param("ssssssi", $tax_name, $tax_type, $description, $amount, $due_date, $tax_year, $tax_id);

        if ($stmt->execute()) {
            $success_message = "Taxe mise à jour avec succès!";
        } else {
            $error_message = "Erreur: " . $stmt->error;
        }
        $stmt->close();
    }

    // Supprimer une taxe
    if (isset($_POST['delete_tax'])) {
        $tax_id = $conn->real_escape_string($_POST['tax_id']);

        // Vérifier si la taxe est associée à des paiements ou des utilisateurs
        $check_payments = $conn->prepare("SELECT COUNT(*) as count FROM payments WHERE tax_id = ?");
        $check_payments->bind_param("i", $tax_id);
        $check_payments->execute();
        $payments_result = $check_payments->get_result()->fetch_assoc();
        $check_payments->close();

        $check_user_taxes = $conn->prepare("SELECT COUNT(*) as count FROM user_taxes WHERE tax_id = ?");
        $check_user_taxes->bind_param("i", $tax_id);
        $check_user_taxes->execute();
        $taxes_result = $check_user_taxes->get_result()->fetch_assoc();
        $check_user_taxes->close();

        if ($payments_result['count'] > 0 || $taxes_result['count'] > 0) {
            $error_message = "Impossible de supprimer cette taxe car elle est associée à des paiements ou à des utilisateurs.";
        } else {
            // Supprimer la taxe
            $stmt = $conn->prepare("DELETE FROM taxes WHERE tax_id = ?");
            $stmt->bind_param("i", $tax_id);

            if ($stmt->execute()) {
                $success_message = "Taxe supprimée avec succès!";
            } else {
                $error_message = "Erreur: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    // Assigner une taxe à un utilisateur
    if (isset($_POST['assign_tax'])) {
        $user_id = $conn->real_escape_string($_POST['user_id']);
        $tax_id = $conn->real_escape_string($_POST['tax_id']);

        // Vérifier si l'assignation existe déjà
        $stmt = $conn->prepare("SELECT * FROM user_taxes WHERE user_id = ? AND tax_id = ?");
        $stmt->bind_param("ii", $user_id, $tax_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        $stmt->close();

        if ($check_result->num_rows > 0) {
            $error_message = "Cette taxe est déjà assignée à cet utilisateur!";
        } else {
            // Insérer l'assignation
            $stmt = $conn->prepare("INSERT INTO user_taxes (user_id, tax_id, status) VALUES (?, ?, 'pending')");
            $stmt->bind_param("ii", $user_id, $tax_id);

            if ($stmt->execute()) {
                $success_message = "Taxe assignée avec succès!";

                // Créer une notification pour l'utilisateur
                $stmt = $conn->prepare("SELECT tax_name FROM taxes WHERE tax_id = ?");
                $stmt->bind_param("i", $tax_id);
                $stmt->execute();
                $tax_result = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $notification_title = "Nouvelle taxe assignée";
                $notification_message = "La taxe '" . $conn->real_escape_string($tax_result['tax_name']) . "' vous a été assignée. Veuillez procéder au paiement.";

                $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $user_id, $notification_title, $notification_message);
                $stmt->execute();
                $stmt->close();
            } else {
                $error_message = "Erreur: " . $stmt->error;
            }
        }
    }
}

// Récupérer les taxes pour affichage
$taxes_query = "SELECT * FROM taxes ORDER BY tax_year DESC, due_date";
$taxes_result = $conn->query($taxes_query);

// Récupérer les utilisateurs pour le menu déroulant d'assignation
$users_query = "SELECT user_id, username, full_name FROM users WHERE role = 'utilisateur' ORDER BY full_name";
$users_result = $conn->query($users_query);
$users = [];
while ($user = $users_result->fetch_assoc()) {
    $users[] = $user;
}

// Récupérer une taxe spécifique pour le formulaire de modification
$edit_tax = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $tax_id = $conn->real_escape_string($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM taxes WHERE tax_id = ?");
    $stmt->bind_param("i", $tax_id);
    $stmt->execute();
    $edit_result = $stmt->get_result();

    if ($edit_result->num_rows > 0) {
        $edit_tax = $edit_result->fetch_assoc();
    }
    $stmt->close();
}

// Récupérer les types de taxes pour le menu déroulant
$tax_types_query = "SELECT DISTINCT tax_type FROM taxes ORDER BY tax_type";
$tax_types_result = $conn->query($tax_types_query);
$tax_types = [];
while ($type = $tax_types_result->fetch_assoc()) {
    $tax_types[] = $type['tax_type'];
}

// Ajouter des types de taxes par défaut si aucun n'existe
if (count($tax_types) == 0) {
    $tax_types = [
        'رسوم إدارية',
        'ضريبة عقارية',
        'رسوم بلدية',
        'ضريبة سكنية',
        'ضريبة سياحية',
        'رسوم زراعية',
        'إيرادات'
    ];
}

// Handle direct delete requests
if (isset($_GET['direct_delete']) && !empty($_GET['direct_delete'])) {
    $tax_id = $conn->real_escape_string($_GET['direct_delete']);
    
    // Check for associated records
    $check_payments = $conn->prepare("SELECT COUNT(*) as count FROM payments WHERE tax_id = ?");
    $check_payments->bind_param("i", $tax_id);
    $check_payments->execute();
    $payments_result = $check_payments->get_result()->fetch_assoc();
    $check_payments->close();

    $check_user_taxes = $conn->prepare("SELECT COUNT(*) as count FROM user_taxes WHERE tax_id = ?");
    $check_user_taxes->bind_param("i", $tax_id);
    $check_user_taxes->execute();
    $taxes_result = $check_user_taxes->get_result()->fetch_assoc();
    $check_user_taxes->close();

    if ($payments_result['count'] > 0 || $taxes_result['count'] > 0) {
        $error_message = "Impossible de supprimer cette taxe car elle est associée à des paiements ou à des utilisateurs.";
    } else {
        $stmt = $conn->prepare("DELETE FROM taxes WHERE tax_id = ?");
        $stmt->bind_param("i", $tax_id);
        
        if ($stmt->execute()) {
            $success_message = "Taxe supprimée avec succès!";
        } else {
            $error_message = "Erreur: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Taxes</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            padding: 0;
            margin: 0;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            margin-bottom: 15px;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .btn-action {
            margin-right: 5px;
        }
        .badge {
            font-size: 0.9em;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .modal-content {
            border-radius: 10px;
        }

        .container-fluid {
            padding-left: 0;
            padding-right: 0;
            max-width: 100%;
        }

        .card-body {
            padding: 0.75rem;
        }

        .form-control, .form-select {
            padding: 0.375rem 0.5rem;
        }

        .mb-3 {
            margin-bottom: 0.75rem !important;
        }

        .table {
            margin-bottom: 0;
        }

        .table td, .table th {
            padding: 0.5rem;
        }

        .row {
            margin-left: 0;
            margin-right: 0;
        }

        .col-md-4, .col-md-8 {
            padding-left: 10px;
            padding-right: 10px;
        }

        h2.mb-4 {
            margin-bottom: 0.75rem !important;
            padding-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <h2 class="mb-4">Gestion des Taxes</h2>

        <!-- Success and Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <!-- Tax Form -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <?php echo ($edit_tax) ? "Modifier la Taxe" : "Ajouter une Taxe"; ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <?php if ($edit_tax): ?>
                                <input type="hidden" name="tax_id" value="<?php echo $edit_tax['tax_id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Nom de la taxe</label>
                                <input type="text" name="tax_name" class="form-control" value="<?php echo ($edit_tax) ? $edit_tax['tax_name'] : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Type de taxe</label>
                                <select name="tax_type" class="form-select" required>
                                    <option value="">Sélectionner un type</option>
                                    <?php foreach ($tax_types as $type): ?>
                                        <option value="<?php echo $type; ?>" <?php echo ($edit_tax && $edit_tax['tax_type'] == $type) ? 'selected' : ''; ?>><?php echo $type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"><?php echo ($edit_tax) ? $edit_tax['description'] : ''; ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Montant (DH)</label>
                                <input type="number" name="amount" step="0.01" class="form-control" value="<?php echo ($edit_tax) ? $edit_tax['amount'] : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Date d'échéance</label>
                                <input type="date" name="due_date" class="form-control" value="<?php echo ($edit_tax) ? $edit_tax['due_date'] : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Année fiscale</label>
                                <select name="tax_year" class="form-select" required>
                                    <?php for ($year = date('Y') + 1; $year >= 2020; $year--): ?>
                                        <option value="<?php echo $year; ?>" <?php echo ($edit_tax && $edit_tax['tax_year'] == $year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <?php if ($edit_tax): ?>
                                <button type="submit" name="update_tax" class="btn btn-primary">Mettre à jour</button>
                                <a href="manage-taxes.php" class="btn btn-secondary">Annuler</a>
                            <?php else: ?>
                                <button type="submit" name="add_tax" class="btn btn-success">Ajouter</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Assign Tax Form -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        Assigner une Taxe à un Utilisateur
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="mb-3">
                                <label class="form-label">Utilisateur</label>
                                <select name="user_id" class="form-select" required>
                                    <option value="">Sélectionner un utilisateur</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>"><?php echo $user['full_name'] . ' (' . $user['username'] . ')'; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Taxe</label>
                                <select name="tax_id" class="form-select" required>
                                    <option value="">Sélectionner une taxe</option>
                                    <?php
                                    $taxes_result->data_seek(0);
                                    while ($tax = $taxes_result->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $tax['tax_id']; ?>"><?php echo $tax['tax_name'] . ' (' . $tax['tax_year'] . ')'; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <button type="submit" name="assign_tax" class="btn btn-info">Assigner</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <!-- Taxes List -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        Liste des Taxes
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Type</th>
                                        <th>Montant</th>
                                        <th>Échéance</th>
                                        <th>Année</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $taxes_result->data_seek(0);
                                    if ($taxes_result->num_rows > 0):
                                    ?>
                                        <?php while ($tax = $taxes_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $tax['tax_id']; ?></td>
                                                <td><?php echo htmlspecialchars($tax['tax_name']); ?></td>
                                                <td><?php echo htmlspecialchars($tax['tax_type']); ?></td>
                                                <td><?php echo number_format($tax['amount'], 2) . ' DH'; ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($tax['due_date'])); ?></td>
                                                <td><?php echo $tax['tax_year']; ?></td>
                                                <td>
                                                    <a href="manage-taxes.php?edit=<?php echo $tax['tax_id']; ?>" class="btn btn-sm btn-primary btn-action">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <!-- Improved delete button with direct JavaScript confirmation -->
                                                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $tax['tax_id']; ?>)" class="btn btn-sm btn-danger btn-action">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                    <a href="tax-details.php?id=<?php echo $tax['tax_id']; ?>" class="btn btn-sm btn-info btn-action">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Aucune taxe trouvée</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Assigned Taxes -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        Taxes Assignées
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <?php
                            $assigned_query = "SELECT ut.*, u.username, u.full_name, t.tax_name, t.amount, t.due_date 
                                              FROM user_taxes ut 
                                              INNER JOIN users u ON ut.user_id = u.user_id 
                                              INNER JOIN taxes t ON ut.tax_id = t.tax_id 
                                              ORDER BY ut.status, t.due_date";
                            $assigned_result = $conn->query($assigned_query);
                            ?>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Utilisateur</th>
                                        <th>Taxe</th>
                                        <th>Montant</th>
                                        <th>Échéance</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($assigned_result->num_rows > 0): ?>
                                        <?php while ($assigned = $assigned_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $assigned['user_tax_id']; ?></td>
                                                <td><?php echo htmlspecialchars($assigned['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($assigned['tax_name']); ?></td>
                                                <td><?php echo number_format($assigned['amount'], 2) . ' DH'; ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($assigned['due_date'])); ?></td>
                                                <td>
                                                    <span class="badge <?php
                                                        echo ($assigned['status'] == 'pending') ? 'bg-warning' :
                                                            (($assigned['status'] == 'paid') ? 'bg-success' : 'bg-danger');
                                                    ?>">
                                                        <?php
                                                            echo ($assigned['status'] == 'pending') ? 'En attente' :
                                                                (($assigned['status'] == 'paid') ? 'Payée' : 'En retard');
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="user-tax-details.php?id=<?php echo $assigned['user_tax_id']; ?>" class="btn btn-sm btn-info btn-action">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="update-tax-status.php?id=<?php echo $assigned['user_tax_id']; ?>" class="btn btn-sm btn-warning btn-action">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Aucune taxe assignée</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Delete confirmation script -->
    <script>
    function confirmDelete(taxId) {
        if (confirm('Êtes-vous sûr de vouloir supprimer cette taxe?')) {
            // Create and submit a form programmatically
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'manage-taxes.php';
            
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'tax_id';
            input.value = taxId;
            form.appendChild(input);
            
            var submitButton = document.createElement('input');
            submitButton.type = 'hidden';
            submitButton.name = 'delete_tax';
            submitButton.value = '1';
            form.appendChild(submitButton);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>

<?php
require "../includes/footer.php";
// Fermer la connexion
$conn->close();
?>