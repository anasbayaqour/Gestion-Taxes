<?php
// Start session
session_start();
require "../includes/header.php";
// Check if user is logged in and is an administrator
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "tax_management");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add new user
    if (isset($_POST['add_user'])) {
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = $conn->real_escape_string($_POST['password']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $role = $conn->real_escape_string($_POST['role']);
        $address = $conn->real_escape_string($_POST['address']);
        $phone = $conn->real_escape_string($_POST['phone']);
        
        // Check if username or email already exists
        $check_query = "SELECT * FROM users WHERE username = '$username' OR email = '$email'";
        $result = $conn->query($check_query);
        
        if ($result->num_rows > 0) {
            $error_message = "Username or email already exists!";
        } else {
            // Insert new user
            $insert_query = "INSERT INTO users (username, email, password, full_name, role, address, phone) 
                            VALUES ('$username', '$email', '$password', '$full_name', '$role', '$address', '$phone')";
            
            if ($conn->query($insert_query) === TRUE) {
                $success_message = "User added successfully!";
            } else {
                $error_message = "Error: " . $conn->error;
            }
        }
    }
    
    // Update user
    if (isset($_POST['update_user'])) {
        $user_id = $conn->real_escape_string($_POST['user_id']);
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $role = $conn->real_escape_string($_POST['role']);
        $address = $conn->real_escape_string($_POST['address']);
        $phone = $conn->real_escape_string($_POST['phone']);
        
        // Update user info
        $update_query = "UPDATE users SET 
                        username = '$username', 
                        email = '$email', 
                        full_name = '$full_name', 
                        role = '$role', 
                        address = '$address', 
                        phone = '$phone' 
                        WHERE user_id = $user_id";
        
        if ($conn->query($update_query) === TRUE) {
            $success_message = "User updated successfully!";
        } else {
            $error_message = "Error: " . $conn->error;
        }
    }
    
    // Delete user
    if (isset($_POST['delete_user'])) {
        $user_id = $conn->real_escape_string($_POST['user_id']);
        
        // Check if user has associated data in other tables
        $check_payments = "SELECT COUNT(*) as count FROM payments WHERE user_id = $user_id";
        $check_complaints = "SELECT COUNT(*) as count FROM complaints WHERE user_id = $user_id";
        $check_taxes = "SELECT COUNT(*) as count FROM user_taxes WHERE user_id = $user_id";
        
        $payments_result = $conn->query($check_payments)->fetch_assoc();
        $complaints_result = $conn->query($check_complaints)->fetch_assoc();
        $taxes_result = $conn->query($check_taxes)->fetch_assoc();
        
        if ($payments_result['count'] > 0 || $complaints_result['count'] > 0 || $taxes_result['count'] > 0) {
            $error_message = "Cannot delete user with associated data. Please remove associated payments, complaints, and taxes first.";
        } else {
            // Delete user
            $delete_query = "DELETE FROM users WHERE user_id = $user_id";
            
            if ($conn->query($delete_query) === TRUE) {
                $success_message = "User deleted successfully!";
            } else {
                $error_message = "Error: " . $conn->error;
            }
        }
    }
}

// Get users for display
$users_query = "SELECT * FROM users ORDER BY created_at DESC";
$users_result = $conn->query($users_query);

// Get specific user for edit form
$edit_user = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $user_id = $conn->real_escape_string($_GET['edit']);
    $edit_query = "SELECT * FROM users WHERE user_id = $user_id";
    $edit_result = $conn->query($edit_query);
    
    if ($edit_result->num_rows > 0) {
        $edit_user = $edit_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <style>
        .btn-action {
            margin-right: 5px;
        }
    </style>
</head>
<body>
   
    
    <div class="container-fluid mt-4">
        <h2>Gestion des Utilisateurs</h2>
        
        <?php if(isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <?php echo ($edit_user) ? "Modifier Utilisateur" : "Ajouter un Utilisateur"; ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <?php if($edit_user): ?>
                                <input type="hidden" name="user_id" value="<?php echo $edit_user['user_id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label>Nom d'utilisateur</label>
                                <input type="text" name="username" class="form-control" value="<?php echo ($edit_user) ? $edit_user['username'] : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo ($edit_user) ? $edit_user['email'] : ''; ?>" required>
                            </div>
                            
                            <?php if(!$edit_user): ?>
                                <div class="form-group">
                                    <label>Mot de passe</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label>Nom complet</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo ($edit_user) ? $edit_user['full_name'] : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Rôle</label>
                                <select name="role" class="form-control" required>
                                    <option value="utilisateur" <?php echo ($edit_user && $edit_user['role'] == 'utilisateur') ? 'selected' : ''; ?>>Utilisateur</option>
                                    <option value="administrateur" <?php echo ($edit_user && $edit_user['role'] == 'administrateur') ? 'selected' : ''; ?>>Administrateur</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Adresse</label>
                                <input type="text" name="address" class="form-control" value="<?php echo ($edit_user) ? $edit_user['address'] : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Téléphone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo ($edit_user) ? $edit_user['phone'] : ''; ?>">
                            </div>
                            
                            <?php if($edit_user): ?>
                                <button type="submit" name="update_user" class="btn btn-primary">Mettre à jour</button>
                                <a href="manage-users.php" class="btn btn-secondary">Annuler</a>
                            <?php else: ?>
                                <button type="submit" name="add_user" class="btn btn-success">Ajouter</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        Liste des Utilisateurs
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom d'utilisateur</th>
                                        <th>Email</th>
                                        <th>Nom complet</th>
                                        <th>Rôle</th>
                                        <th>Date de création</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($users_result->num_rows > 0): ?>
                                        <?php while($user = $users_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $user['user_id']; ?></td>
                                                <td><?php echo $user['username']; ?></td>
                                                <td><?php echo $user['email']; ?></td>
                                                <td><?php echo $user['full_name']; ?></td>
                                                <td>
                                                    <span class="badge <?php echo ($user['role'] == 'administrateur') ? 'badge-primary' : 'badge-secondary'; ?>">
                                                        <?php echo $user['role']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <a href="manage-users.php?edit=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-primary btn-action">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="post" action="" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-sm btn-danger btn-action" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur?');">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                    <a href="user-details.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-info btn-action">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Aucun utilisateur trouvé</td>
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