<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Redirect if already logged in
redirectIfLoggedIn();

$error = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid form submission.';
    } else {
        // Sanitize user inputs
        $username = htmlspecialchars(trim($_POST['username']));
        $password = $_POST['password']; // Don't sanitize password before verification
        
        if (empty($username) || empty($password)) {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            // Connect to database
            try {
                $pdo = new PDO("mysql:host=localhost;dbname=tax_management", "root", ""); // Adjust with your credentials
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Check if user exists in database
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]); // Allow login with username or email
                $user = $stmt->fetch();
                
                // For now, since passwords are stored as plaintext in your DB
                if ($user && $password === $user['password']) {
                    // Password is correct, set up the session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    // Redirect based on role
                    if ($user['role'] === 'administrateur') {
                        header("Location: ../admin/dashboard.php");
                    } else {
                        header("Location: ../user/dashboard.php");
                    }
                    exit();
                } else {
                    $error = 'Nom d\'utilisateur ou mot de passe incorrect.';
                }
            } catch (PDOException $e) {
                $error = 'Erreur de connexion à la base de données: ' . $e->getMessage();
            }
        }
    }
}

// Page title
$pageTitle = 'Connexion';

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
                        <h1 class="h3 mb-0">Connexion</h1>
                        <p class="mb-0">Accédez à votre compte pour gérer vos taxes.</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <!-- Username or Email -->
                            <div class="mb-4">
                                <label for="username" class="form-label">Nom d'utilisateur ou Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer votre nom d'utilisateur ou email.
                                    </div>
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="mb-4">
                                <label for="password" class="form-label">Mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer votre mot de passe.
                                    </div>
                                </div>
                            </div>

                            <!-- Remember Me -->
                            <div class="mb-4 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Se souvenir de moi</label>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Se connecter</button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center py-3">
                        <p class="mb-0">Vous n'avez pas de compte? <a href="register.php" class="text-decoration-none">S'inscrire</a></p>
                        <p class="mb-0 mt-2"><a href="reset-password.php" class="text-decoration-none">Mot de passe oublié?</a></p>
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
// Example: Form validation using Bootstrap
(function () {
    'use strict';

    // Fetch the form to apply custom Bootstrap validation styles
    const form = document.querySelector('.needs-validation');

    form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    }, false);
})();
</script>