<?php
// Start session to access admin data
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../auth/login.php'); // Redirect to login page if not logged in
    exit();
}

// Include database connection
require_once '../config/database.php';

// Get admin data
$admin_id = $_SESSION['admin_id'];
$sql = "SELECT * FROM admins WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    // Admin not found (should not happen if session is valid)
    session_destroy();
    header('Location: ../auth/login.php?error=invalid_session');
    exit();
}

// Handle form submission for profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Validate form data
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);

        // Basic validation
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error_message = "First name, last name, and email are required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format.";
        } else {
            // Check if email already exists (for another admin)
            $check_email = "SELECT id FROM admins WHERE email = ? AND id != ?";
            $check_stmt = $pdo->prepare($check_email);
            $check_stmt->execute([$email, $admin_id]);

            if ($check_stmt->rowCount() > 0) {
                $error_message = "Email is already in use by another account.";
            } else {
                // Update admin profile
                $update_sql = "UPDATE admins SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?";
                $update_stmt = $pdo->prepare($update_sql);

                if ($update_stmt->execute([$first_name, $last_name, $email, $phone, $admin_id])) {
                    $success_message = "Profile updated successfully!";
                    
                    // Update admin data for display
                    $admin['first_name'] = $first_name;
                    $admin['last_name'] = $last_name;
                    $admin['email'] = $email;
                    $admin['phone'] = $phone;
                } else {
                    $error_message = "Failed to update profile. Please try again.";
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Handle password change
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate password data
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New password and confirmation do not match.";
        } elseif (strlen($new_password) < 10) {
            $error_message = "New password must be at least 10 characters long.";
        } elseif (!preg_match('/[A-Z]/', $new_password) || 
                  !preg_match('/[a-z]/', $new_password) || 
                  !preg_match('/[0-9]/', $new_password) || 
                  !preg_match('/[^A-Za-z0-9]/', $new_password)) {
            $error_message = "Password must include uppercase, lowercase, number, and special character.";
        } else {
            // Verify current password
            if (password_verify($current_password, $admin['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $update_sql = "UPDATE admins SET password = ?, updated_at = NOW() WHERE id = ?";
                $update_stmt = $pdo->prepare($update_sql);

                if ($update_stmt->execute([$hashed_password, $admin_id])) {
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Failed to update password. Please try again.";
                }
            } else {
                $error_message = "Current password is incorrect.";
            }
        }
    }
}

// Include header
include_once '../includes/admin-header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3">
            <?php include_once '../includes/admin-sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3>Admin Profile</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($admin['first_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($admin['last_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($admin['phone']); ?>">
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h3>Change Password</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-danger">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>