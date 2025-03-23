<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Redirect if already logged in
redirectIfLoggedIn();

$error = '';
$success = '';

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug CSRF token - you can remove these lines after fixing the issue
    error_log('Received CSRF token: ' . ($_POST['csrf_token'] ?? 'not set'));
    error_log('Session CSRF token: ' . ($_SESSION['csrf_token'] ?? 'not set'));
    
    // Generate a new CSRF token if one doesn't exist in the session
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Check if CSRF token exists and matches
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission.';
    } else {
        // Sanitize user inputs
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? ''; // Don't sanitize password before hashing
        $confirm_password = $_POST['confirm_password'] ?? '';
        $full_name = sanitizeInput($_POST['full_name'] ?? '');
        $role = sanitizeInput($_POST['role'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        
        // Check if terms checkbox is checked
        if (!isset($_POST['terms'])) {
            $error = 'Vous devez accepter les termes et conditions.';
        }
        // Validate inputs
        elseif (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($full_name)) {
            $error = 'Veuillez remplir tous les champs obligatoires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Veuillez entrer une adresse email valide.';
        } elseif (strlen($password) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($password !== $confirm_password) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (!in_array($role, ['utilisateur', 'administrateur'])) {
            $error = 'Rôle invalide.';
        } else {
            // Check if username or email already exists
            global $pdo;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $error = 'Ce nom d\'utilisateur ou cette adresse email est déjà utilisé(e).';
            } else {
                // Hash the password only for administrators
                if ($role === 'administrateur') {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                } else {
                    // Store password as is for regular users
                    $hashed_password = $password;
                }
                
                // Insert new user into database
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, full_name, role, address, phone)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                try {
                    $stmt->execute([$username, $email, $hashed_password, $full_name, $role, $address, $phone]);
                    $user_id = $pdo->lastInsertId();
                    
                    // Log registration action
                    logAction($user_id, 'Registration', 'New user registered');
                    
                    $success = 'Inscription réussie! Vous pouvez maintenant vous connecter.';
                    
                    // Redirect to login page after 2 seconds
                    header("Refresh: 2; URL=login.php");
                } catch (PDOException $e) {
                    $error = 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.';
                    // Log the error for debugging
                    error_log($e->getMessage());
                }
            }
        }
    }
}

// Generate CSRF token for the form
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Page title
$pageTitle = 'Inscription';

// Include header
include_once '../includes/header.php';
?>

<!-- Main Content -->
<main class="d-flex align-items-center min-vh-100 py-4">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-gradient-primary text-dark text-center py-4">
                        <h1 class="h3 mb-0">Créer un compte</h1>
                        <p class="mb-0">Rejoignez-nous pour gérer vos taxes en toute simplicité.</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
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

                        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="needs-validation" novalidate>
                            <!-- Use the directly generated CSRF token instead of calling a function -->
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                            <!-- Username -->
                            <div class="mb-4">
                                <label for="username" class="form-label">Nom d'utilisateur*</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer un nom d'utilisateur valide.
                                    </div>
                                </div>
                            </div>

                            <!-- Email -->
                            <div class="mb-4">
                                <label for="email" class="form-label">Email*</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer une adresse email valide.
                                    </div>
                                </div>
                            </div>

                            <!-- Full Name -->
                            <div class="mb-4">
                                <label for="full_name" class="form-label">Nom complet*</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer votre nom complet.
                                    </div>
                                </div>
                            </div>

                            <!-- Role -->
                            <div class="mb-4">
                                <label for="role" class="form-label">Type de compte*</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="utilisateur" <?php echo (isset($_POST['role']) && $_POST['role'] === 'utilisateur') ? 'selected' : ''; ?>>Utilisateur</option>
                                    <option value="administrateur" <?php echo (isset($_POST['role']) && $_POST['role'] === 'administrateur') ? 'selected' : ''; ?>>Administrateur</option>
                                </select>
                                <div class="invalid-feedback">
                                    Veuillez sélectionner un type de compte.
                                </div>
                            </div>

                            <!-- Address -->
                            <div class="mb-4">
                                <label for="address" class="form-label">Adresse</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                            </div>

                            <!-- Phone -->
                            <div class="mb-4">
                                <label for="phone" class="form-label">Téléphone</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="mb-4">
                                <label for="password" class="form-label">Mot de passe*</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                                    <div class="invalid-feedback">
                                        Le mot de passe doit contenir au moins 8 caractères.
                                    </div>
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe*</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                                    <div class="invalid-feedback">
                                        Les mots de passe ne correspondent pas.
                                    </div>
                                </div>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="mb-4 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">J'accepte les <a href="#">termes et conditions</a>*</label>
                                <div class="invalid-feedback">
                                    Vous devez accepter les termes et conditions.
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">S'inscrire</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center py-3">
                        <p class="mb-0">Vous avez déjà un compte? <a href="login.php" class="text-decoration-none">Se connecter</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Include Footer -->
<?php include_once '../includes/footer.php'; ?>

<!-- Bootstrap JS and dependencies -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
<!-- Custom JS -->
<script>
// Form validation using Bootstrap
(function () {
    'use strict';

    // Fetch the form to apply custom Bootstrap validation styles
    const form = document.querySelector('.needs-validation');
    
    // Password confirmation validation
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePassword() {
        if (password.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity("Les mots de passe ne correspondent pas.");
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    password.addEventListener('change', validatePassword);
    confirmPassword.addEventListener('keyup', validatePassword);

    // Handle form submission
    form.addEventListener('submit', function (event) {
        validatePassword();
        
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        form.classList.add('was-validated');
    }, false);
})();
</script>