<?php
session_start();
require_once "../config/database.php";

// Make sure $conn exists after including database.php
if (!isset($conn)) {
    // Create connection if not defined in the included file
    $servername = "localhost";
    $username = "root";  // Your database username
    $password = "";      // Your database password
    $dbname = "tax_management";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}

// Check if reset_token and reset_expiry columns exist, and add them if not
$checkColumnsResult = $conn->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
if ($checkColumnsResult->num_rows == 0) {
    // Add reset_token and reset_expiry columns
    $conn->query("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL, ADD COLUMN reset_expiry DATETIME DEFAULT NULL");
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on user role
    if ($_SESSION['role'] == 'administrateur') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit();
}

$step = isset($_GET['step']) ? $_GET['step'] : 'request';
$error = '';
$success = '';

// Step 1: Request password reset (enter email)
if ($step == 'request' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez entrer une adresse email valide.";
    } else {
        // Check if email exists in database
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 0) {
            $error = "Aucun compte associé à cette adresse email.";
        } else {
            // Generate token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token in database
            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?");
            $stmt->bind_param("sss", $token, $expiry, $email);
            $stmt->execute();

            // In a real application, send email with reset link
            // For this example, we'll just redirect to the token step
            $success = "Un lien de réinitialisation a été envoyé à votre adresse email (simulation). ";
            $success .= "<a href='reset-password.php?step=token&email=" . urlencode($email) . "&token=" . $token . "'>Cliquez ici pour continuer</a>";
        }
        $stmt->close();
    }
}

// Step 2: Verify token and allow new password entry
if ($step == 'token' && isset($_GET['email']) && isset($_GET['token'])) {
    $email = $_GET['email'];
    $token = $_GET['token'];

    // Verify token
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND reset_token = ? AND reset_expiry > NOW()");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        $error = "Le lien de réinitialisation est invalide ou a expiré.";
        $step = 'request'; // Go back to request step
    }
    $stmt->close();
}

// Step 3: Update password
if ($step == 'token' && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $email = $_POST['email'];
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($password) || empty($confirm_password)) {
        $error = "Veuillez remplir tous les champs.";
    } elseif ($password != $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } else {
        // Verify token again
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND reset_token = ? AND reset_expiry > NOW()");
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 0) {
            $error = "Le lien de réinitialisation est invalide ou a expiré.";
        } else {
            $stmt->close();

            // Hash new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Update password and clear token
            $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $email);

            if ($stmt->execute()) {
                $success = "Votre mot de passe a été réinitialisé avec succès. <a href='login.php'>Connectez-vous</a>";
                $step = 'complete';
            } else {
                $error = "Erreur lors de la réinitialisation du mot de passe.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .reset-container {
            max-width: 500px;
            margin: 100px auto;
        }
        .reset-card {
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .reset-header {
            background: linear-gradient(45deg, #007bff, #00bfff);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .reset-body {
            padding: 30px;
        }
        .form-label {
            font-weight: 500;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #004085;
        }
    </style>
</head>
<body>
<?php include('../includes/header.php'); ?>
    <div class="container-fluid reset-container">
        <div class="card reset-card">
            <div class="reset-header">
                <h3>Réinitialisation de mot de passe</h3>
            </div>
            <div class="card-body reset-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($step == 'request' && !$success): ?>
                    <p>Veuillez entrer votre adresse email pour réinitialiser votre mot de passe.</p>
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="request_reset" class="btn btn-primary">Envoyer</button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($step == 'token' && !$error): ?>
                    <p>Veuillez entrer votre nouveau mot de passe.</p>
                    <form method="POST" action="">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <div class="mb-4">
                            <label for="password" class="form-label">Nouveau mot de passe</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <small class="form-text text-muted">Au moins 8 caractères</small>
                        </div>
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="update_password" class="btn btn-primary">Mettre à jour</button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($step == 'complete'): ?>
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-primary">Retour à la page de connexion</a>
                    </div>
                <?php endif; ?>

                <?php if ($step == 'request'): ?>
                    <div class="text-center mt-3">
                        <a href="login.php">Retour à la page de connexion</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    <?php include('../includes/footer.php'); ?>
</body>
</html>