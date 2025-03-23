<?php
// Include database connection
require_once __DIR__ . '/../config/database.php';

// Function to sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to hash passwords
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Function to verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Function to generate a unique transaction reference
function generateTransactionRef() {
    return 'TXN' . time() . rand(1000, 9999);
}

// Function to get user details by ID
function getUserById($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Function to get tax details by ID
function getTaxById($taxId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM taxes WHERE tax_id = ?");
    $stmt->execute([$taxId]);
    return $stmt->fetch();
}

// Function to get user taxes
function getUserTaxes($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT t.*, ut.status 
        FROM taxes t
        JOIN user_taxes ut ON t.tax_id = ut.tax_id
        WHERE ut.user_id = ?
        ORDER BY t.due_date ASC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// Function to get user payments
function getUserPayments($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.*, t.tax_name, t.tax_type 
        FROM payments p
        JOIN taxes t ON p.tax_id = t.tax_id
        WHERE p.user_id = ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// Function to get user complaints
function getUserComplaints($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM complaints
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// Function to create a notification
function createNotification($userId, $title, $message) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message)
        VALUES (?, ?, ?)
    ");
    return $stmt->execute([$userId, $title, $message]);
}

// Function to format date
function formatDate($date) {
    return date("d/m/Y", strtotime($date));
}

// Function to format currency
function formatCurrency($amount) {
    return number_format($amount, 2, ',', ' ') . ' €';
}

// Function to log system actions (for audit trail)
function logAction($userId, $action, $details) {
    // This could be implemented to log all important system actions
    // for security and audit purposes
    // For simplicity, we'll just log to a file
    $logFile = __DIR__ . '/../logs/system.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] User ID: $userId - $action - $details\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}
?>