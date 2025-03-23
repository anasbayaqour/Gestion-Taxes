<!-- filepath: c:\laragon\www\Gestion_Des_Taxes\includes\admin-sidebar.php -->
<div class="list-group">
    <a href="dashboard.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    <a href="manage-taxes.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'manage-taxes.php' ? 'active' : ''; ?>">
        <i class="fas fa-file-invoice-dollar"></i> Manage Taxes
    </a>
    <a href="admin/manage-users.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'manage-users.php' ? 'active' : ''; ?>">
        <i class="fas fa-users"></i> Manage Users
    </a>
    <a href="admin/view-complaints.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'view-complaints.php' ? 'active' : ''; ?>">
        <i class="fas fa-comments"></i> View Complaints
    </a>

    <a href="settings.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
        <i class="fas fa-cogs"></i> Settings
    </a>
</div>