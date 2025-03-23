<?php

// Include necessary files and initialize session
include '../includes/header.php';
include '../config/database.php';

// Check if a session is already active before starting one
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Handle form submission for updating settings
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Example: Update site title
    $site_title = $_POST['site_title'];
    $query = "UPDATE settings SET value = ? WHERE name = 'site_title'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $site_title);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = 'Settings updated successfully!';
    header('Location: settings.php');
    exit();
}

// Fetch current settings
$query = "SELECT * FROM settings";
$result = $conn->query($query);

if (!$result) {
    die("Query failed: " . $conn->error);
}

$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['name']] = $row['value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="../css/styles.css"> <!-- Adjusted path for CSS -->
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?> <!-- Adjusted path for sidebar -->

    <div class="container mt-4">
        <h1>Settings</h1>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="settings.php">
            <div class="form-group">
                <label for="site_title">Site Title</label>
                <input type="text" name="site_title" id="site_title" class="form-control" value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
        </form>
    </div>
</body>
</html>