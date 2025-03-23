<?php
// Start session to access user data
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Include database connection and PDF library
require_once '../config/database.php';
require_once '../vendor/autoload.php'; // Ensure this path is correct

// Get tax ID from URL parameter
$tax_id = isset($_GET['tax_id']) ? intval($_GET['tax_id']) : 0;

// Validate payment belongs to current user
$user_id = $_SESSION['user_id'];

// Query to fetch payment details - ensure we're only getting completed payments with valid amounts
$query = "SELECT p.payment_id, p.amount, p.payment_date, p.payment_method, p.transaction_ref, 
                 t.tax_name, t.tax_type, t.description, t.tax_year, t.due_date,
                 u.full_name, u.email, u.address, u.phone
          FROM payments p
          JOIN taxes t ON p.tax_id = t.tax_id
          JOIN users u ON p.user_id = u.user_id
          WHERE p.tax_id = :tax_id AND p.user_id = :user_id AND p.payment_status = 'completed'
          ORDER BY p.payment_date DESC LIMIT 1";

$stmt = $pdo->prepare($query);
$stmt->execute([
    'tax_id' => $tax_id,
    'user_id' => $user_id
]);

if ($stmt->rowCount() == 0) {
    die("Paiement non trouvé ou accès refusé.");
}

$payment = $stmt->fetch(PDO::FETCH_ASSOC);

// Ensure payment amount is properly formatted (convert to float)
$payment['amount'] = floatval($payment['amount']);

// Format date in a more readable format
$paymentDate = date('d/m/Y', strtotime($payment['payment_date']));
$dueDate = date('d/m/Y', strtotime($payment['due_date']));

// Generate PDF using TCPDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Système de Gestion des Taxes');
$pdf->SetTitle('Reçu de Paiement');
$pdf->SetSubject('Reçu de Paiement');
$pdf->SetKeywords('Reçu, Paiement, Taxes');

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(10, 10, 10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 10);

// Set font
$pdf->SetFont('helvetica', '', 10);

// Add a page
$pdf->AddPage();

// Generate QR code with payment info
$qrStyle = [
    'border' => 0,
    'vpadding' => 0,
    'hpadding' => 0,
    'fgcolor' => [0, 0, 0],
    'bgcolor' => false,
    'module_width' => 1,
    'module_height' => 1
];
$qrData = "ID: REC-{$payment['payment_id']}\n";
$qrData .= "Date: {$paymentDate}\n";
$qrData .= "Montant: {$payment['amount']} MAD\n";
$qrData .= "Référence: {$payment['transaction_ref']}";

// Format the amount with 2 decimal places and thousand separator
$formattedAmount = number_format($payment['amount'], 2, '.', ' ');

// Content for the PDF with modern styling
$html = <<<EOD
<style>
    body {
        font-family: Helvetica, Arial, sans-serif;
        line-height: 1.6;
        color: #333;
    }
    .container {
        width: 100%;
    }
    .header {
        background-color: #3498db;
        color: white;
        padding: 15px;
        text-align: center;
        border-radius: 8px 8px 0 0;
    }
    .header h1 {
        font-size: 20px;
        margin: 0;
        padding: 0;
        font-weight: 300;
        letter-spacing: 1px;
    }
    .header p {
        margin: 5px 0 0 0;
        font-size: 12px;
        opacity: 0.9;
    }
    .receipt-number {
        font-family: Courier, monospace;
        text-align: right;
        margin: 10px 0;
        font-size: 12px;
        color: #777;
    }
    .section {
        margin-bottom: 15px;
    }
    .section-title {
        font-size: 14px;
        color: #3498db;
        border-bottom: 1px solid #3498db;
        padding-bottom: 5px;
        margin-bottom: 10px;
        font-weight: bold;
    }
    .details-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
    }
    .details-table td {
        padding: 6px 8px;
        border-bottom: 1px solid #f0f0f0;
    }
    .details-table td:first-child {
        font-weight: bold;
        width: 40%;
        color: #555;
    }
    .amount-box {
        background-color: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 5px;
        padding: 8px;
        margin: 10px 0;
        text-align: center;
    }
    .amount-box .label {
        font-size: 12px;
        color: #555;
    }
    .amount-box .value {
        font-size: 18px;
        color: #3498db;
        font-weight: bold;
    }
    .footer {
        margin-top: 20px;
        padding-top: 10px;
        border-top: 1px dashed #ccc;
        font-size: 10px;
        color: #777;
        text-align: center;
    }
    .qr-container {
        text-align: center;
        margin: 10px 0;
    }
    .status-paid {
        position: absolute;
        top: 50%;
        left: 20%;
        transform: rotate(-45deg);
        font-size: 60px;
        color: rgba(39, 174, 96, 0.2);
        font-weight: bold;
        z-index: -1;
    }
    .two-column {
        width: 100%;
    }
    .two-column td {
        width: 50%;
        vertical-align: top;
        padding: 5px;
    }
</style>

<div class="container">
    <div class="header">
        <h1>REÇU DE PAIEMENT</h1>
        <p>Système de Gestion des Taxes</p>
    </div>
    
    <div class="receipt-number">
        No: REC-{$payment['payment_id']}
    </div>
    
    <div class="status-paid">PAYÉ</div>
    
    <table class="two-column">
        <tr>
            <td>
                <div class="section">
                    <div class="section-title">Information du Payeur</div>
                    <table class="details-table">
                        <tr>
                            <td>Nom</td>
                            <td>{$payment['full_name']}</td>
                        </tr>
                        <tr>
                            <td>Email</td>
                            <td>{$payment['email']}</td>
                        </tr>
                        <tr>
                            <td>Téléphone</td>
                            <td>{$payment['phone']}</td>
                        </tr>
                        <tr>
                            <td>Adresse</td>
                            <td>{$payment['address']}</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td>
                <div class="section">
                    <div class="section-title">Détails du Paiement</div>
                    <table class="details-table">
                        <tr>
                            <td>Date</td>
                            <td>{$paymentDate}</td>
                        </tr>
                        <tr>
                            <td>Transaction</td>
                            <td>{$payment['transaction_ref']}</td>
                        </tr>
                        <tr>
                            <td>Méthode</td>
                            <td>{$payment['payment_method']}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>
    
    <div class="section">
        <div class="section-title">Détails de la Taxe</div>
        <table class="details-table">
            <tr>
                <td>Nom de la Taxe</td>
                <td>{$payment['tax_name']}</td>
            </tr>
            <tr>
                <td>Description</td>
                <td>{$payment['description']}</td>
            </tr>
            <tr>
                <td>Année</td>
                <td>{$payment['tax_year']}</td>
            </tr>
            <tr>
                <td>Date d'Échéance</td>
                <td>{$dueDate}</td>
            </tr>
        </table>
    </div>
    
    <div class="amount-box">
        <div class="label">MONTANT TOTAL PAYÉ</div>
        <div class="value">{$formattedAmount} MAD</div>
    </div>
    
    <div class="qr-container">
        [QR_CODE]
    </div>
    
    <div class="footer">
        <p>Ce reçu a été généré électroniquement et ne nécessite pas de signature.</p>
        <p>Pour toute question, veuillez contacter notre équipe de support.</p>
    </div>
</div>
EOD;

// Replace the QR placeholder with actual QR code
$pdf->write2DBarcode($qrData, 'QRCODE,L', 80, 200, 40, 40, $qrStyle, 'N');

// Output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$pdf->Output('recu_paiement.pdf', 'I'); // 'I' for inline display, 'D' for download
?>