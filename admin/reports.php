<?php
// Start session
session_start();
require "../includes/header.php";

// Check if user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrateur') {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "tax_management");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch report data
// Total taxes
$totalTaxesQuery = "SELECT COUNT(*) AS total_taxes FROM taxes";
$totalTaxesResult = $conn->query($totalTaxesQuery);
$totalTaxes = $totalTaxesResult->fetch_assoc()['total_taxes'];

// Total payments
$totalPaymentsQuery = "SELECT SUM(amount) AS total_payments FROM payments WHERE payment_status = 'completed'";
$totalPaymentsResult = $conn->query($totalPaymentsQuery);
$totalPayments = $totalPaymentsResult->fetch_assoc()['total_payments'] ?: 0;

// Total complaints
$totalComplaintsQuery = "SELECT COUNT(*) AS total_complaints FROM complaints";
$totalComplaintsResult = $conn->query($totalComplaintsQuery);
$totalComplaints = $totalComplaintsResult->fetch_assoc()['total_complaints'];

// Complaints by status
$complaintsByStatusQuery = "SELECT status, COUNT(*) AS count FROM complaints GROUP BY status";
$complaintsByStatusResult = $conn->query($complaintsByStatusQuery);
$complaintsByStatus = [];
while ($row = $complaintsByStatusResult->fetch_assoc()) {
    $complaintsByStatus[$row['status']] = $row['count'];
}

// Payments by month (last 6 months)
$paymentsByMonthQuery = "
    SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month, SUM(amount) AS total
    FROM payments
    WHERE payment_status = 'completed'
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
";
$paymentsByMonthResult = $conn->query($paymentsByMonthQuery);
$paymentsByMonth = [];
while ($row = $paymentsByMonthResult->fetch_assoc()) {
    $paymentsByMonth[$row['month']] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<style>
    /* General Styles */
    body {
        background-color: #f8f9fa;
        font-family: 'Arial', sans-serif;
    }

    .container {
        max-width: 1200px;
        margin: auto;
        padding: 20px;
    }

    h2 {
        color: #343a40;
        font-weight: bold;
        margin-bottom: 20px;
    }

    /* Card Styles */
    .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    .card-title {
        font-size: 1.25rem;
        font-weight: bold;
        color: #fff;
    }

    .card-body {
        padding: 20px;
    }

    .display-4 {
        font-size: 2.5rem;
        font-weight: bold;
        color: #fff;
    }

    /* Background Colors for Cards */
    .bg-primary {
        background-color: #4e73df !important;
    }

    .bg-success {
        background-color: #1cc88a !important;
    }

    .bg-warning {
        background-color: #f6c23e !important;
    }

    /* Chart Styles */
    canvas {
        max-width: 100%;
        height: auto !important;
    }

    /* Header Styles */
    .card-header {
        background-color: #fff;
        border-bottom: 1px solid #e3e6f0;
        padding: 15px 20px;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }

    .card-header h5 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: bold;
        color: #5a5c69;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .col-md-4, .col-md-6 {
            margin-bottom: 20px;
        }

        .display-4 {
            font-size: 2rem;
        }
    }
</style>
    <div class="container mt-4">
        <h2>Rapports</h2>
        <div class="row mt-4">
            <!-- Total Taxes -->
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Total des Taxes</h5>
                        <h2 class="display-4"><?php echo $totalTaxes; ?></h2>
                    </div>
                </div>
            </div>

            <!-- Total Payments -->
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Total des Paiements</h5>
                        <h2 class="display-4"><?php echo number_format($totalPayments, 2) . ' DH'; ?></h2>
                    </div>
                </div>
            </div>

            <!-- Total Complaints -->
            <div class="col-md-4">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Total des Réclamations</h5>
                        <h2 class="display-4"><?php echo $totalComplaints; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Complaints by Status -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Réclamations par Statut</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="complaintsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Payments by Month -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Paiements Mensuels (6 derniers mois)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="paymentsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Complaints by Status Chart
        const complaintsChartCtx = document.getElementById('complaintsChart').getContext('2d');
        const complaintsChart = new Chart(complaintsChartCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($complaintsByStatus)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($complaintsByStatus)); ?>,
                    backgroundColor: ['#f6c23e', '#e74a3b', '#1cc88a', '#858796']
                }]
            }
        });

        // Payments by Month Chart
        const paymentsChartCtx = document.getElementById('paymentsChart').getContext('2d');
        const paymentsChart = new Chart(paymentsChartCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($paymentsByMonth)); ?>,
                datasets: [{
                    label: 'Paiements (DH)',
                    data: <?php echo json_encode(array_values($paymentsByMonth)); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>

<?php
// Close connection
$conn->close();
?>