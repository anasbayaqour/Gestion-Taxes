<?php
// Start session to access user data
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Function to mark notification as read
function markAsRead($notification_id, $user_id, $conn) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

// Handle marking notification as read
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $user_id = $_SESSION['user_id'];
    
    if (markAsRead($notification_id, $user_id, $conn)) {
        header('Location: notifications.php?success=1');
        exit();
    } else {
        $error = "Unable to mark notification as read.";
    }
}

// Handle marking all notifications as read
if (isset($_POST['mark_all_read'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    header('Location: notifications.php?success=all_read');
    exit();
}

// Fetch notifications for the current user
$user_id = $_SESSION['user_id'];
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total notifications count
$count_sql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_notifications = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_notifications / $limit);

// Get notifications with pagination
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$notifications = $stmt->get_result();

// Count unread notifications
$unread_sql = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_sql);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['unread'];

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3">
            <?php include_once 'includes/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">Notifications</h2>
                    <?php if ($unread_count > 0): ?>
                    <form method="post">
                        <button type="submit" name="mark_all_read" class="btn btn-outline-primary btn-sm">
                            Mark All as Read
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success">
                            <?php 
                            if ($_GET['success'] == 'all_read') {
                                echo "All notifications marked as read.";
                            } else {
                                echo "Notification updated successfully.";
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($notifications->num_rows > 0): ?>
                        <div class="list-group">
                            <?php while ($notification = $notifications->fetch_assoc()): ?>
                                <div class="list-group-item list-group-item-action <?php echo $notification['is_read'] ? '' : 'list-group-item-primary'; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h5>
                                        <small><?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    
                                    <?php if (!empty($notification['link'])): ?>
                                        <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="btn btn-sm btn-info mt-2">View Details</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($notification['is_read'] == 0): ?>
                                        <form method="post" class="mt-2">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" name="mark_read" class="btn btn-sm btn-outline-secondary">Mark as Read</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Notification pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="alert alert-info">
                            You have no notifications at this time.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';

// Close database connection
$conn->close();
?>