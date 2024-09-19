<?php
// Start output buffering and disable error reporting
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define paths
$directory = 'C:/xampp/htdocs/sms/receipts';
$filename = 'get_receipt_' . time() . '.pdf';
$filepath = $directory . '/' . $filename;

// Check and create directory if it doesn't exist
if (!is_dir($directory)) {
    if (!mkdir($directory, 0777, true)) {
        die('Failed to create directory.');
    }
}

// Include necessary files
require('C:\xampp\htdocs\sms\tcpdf\tcpdf.php'); // Adjust the path as needed
include('DB_connect.php'); // Include your database connection
// Start session
session_start();

// Check session for student ID
if (!isset($_SESSION['id'])) {
    die('Student not logged in. Please log in to view the receipt.');
}

// Get payment ID from query string
$paymentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($paymentId <= 0) {
    die('Invalid payment ID.');
}

// Create new PDF document
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('AutoReceipt System');
$pdf->SetTitle('AutoReceipt');
$pdf->SetSubject('Payment Receipt');

// Add a page
$pdf->AddPage();




// Title styling
$title = '<span style="color: #800080;">A</span>utoReceipt';
$pdf->SetFont('helvetica', 'B', 16);
$pdf->SetFillColor(255, 255, 255); // White background
$pdf->Rect(10, 10, 190, 10, 'F'); // Background rectangle for the title
$pdf->SetXY(10, 10); // Position for the title
$pdf->writeHTMLCell(0, 10, '', '', $title, 0, 1, 0, true, 'C', true);

$pdf->Ln(10);

// Get student ID from session
$studentId = $_SESSION['id'];

// Check database connection and prepare SQL statements
if ($connect instanceof mysqli) {
    $stmt = $connect->prepare("
        SELECT 
            deposit.payment_id AS payment_id,
            students.student_name AS student_name,
            deposit.total_amount, 
            deposit.paid_amount, 
            deposit.payment_method,
            deposit.payment_number,
            deposit.status,
            deposit.payment_date
        FROM 
            deposit
        JOIN 
            students ON deposit.student_id = students.student_id
        WHERE 
            deposit.payment_id = ? AND students.student_id = ?
    ");

    if ($stmt) {
        $stmt->bind_param('ii', $paymentId, $studentId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Extract data
            $studentName = $row['student_name'];
            $paymentDate = $row['payment_date'];
            $paymentMethod = $row['payment_method'];
            $totalAmount = $row['total_amount'];
            $paidAmount = $row['paid_amount'];
            $remainingAmount = $row['remaining_amount']; // Calculating remaining amount
            $status = $row['status'];

            // QR Code generation
            $qrCodeContent = http_build_query([
                'student_id' => $studentId,
                'student_name' => $studentName,
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'status' => $status
            ]); 
            $pdf->write2DBarcode($qrCodeContent, 'QRCODE,H', 160, 20, 40, 40, [], 'N'); 

            // Set font for student details
            $pdf->SetFont('helvetica', '', 12);

            // Student details
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 10, 'Student Name: ' . htmlspecialchars($studentName), 0, 1, 'L');
            $pdf->Cell(0, 10, 'Payment Date: ' . htmlspecialchars($paymentDate), 0, 1, 'L');
            $pdf->Cell(0, 10, 'Payment Method: ' . htmlspecialchars($paymentMethod), 0, 1, 'L');
            $pdf->Cell(0, 10, 'Total Amount: ' . htmlspecialchars($totalAmount), 0, 1, 'R');
            $pdf->Cell(0, 10, 'Paid Amount: ' . htmlspecialchars($paidAmount), 0, 1, 'R');
            $pdf->Cell(0, 10, 'Remaining Amount: ' . htmlspecialchars($remainingAmount), 0, 1, 'R');
            $pdf->Cell(0, 10, 'Status: ' . htmlspecialchars($status), 0, 1, 'L');

            // Payment History
            $pdf->Ln(10);
            $pdf->SetX(5);
            $pdf->Cell(0, 10, 'Payment History:', 0, 1, 'L');
            
            $pdf->SetFont('helvetica', 'B', 12);
            
            $pdf->SetX(5);
            
            // Define column widths
            $pdf->Cell(45, 10, 'Payment Date', 1, 0, 'C');
            $pdf->Cell(40, 10, 'Payment Method', 1, 0, 'C');
            $pdf->Cell(40, 10, 'Total Amount', 1, 0, 'C');
            $pdf->Cell(40, 10, 'Paid Amount', 1, 0, 'C');
            $pdf->Cell(37, 10, 'Payment Number', 1, 1, 'C');
            
            // Reset font for table rows
            $pdf->SetFont('helvetica', '', 12);
            
            // Fetch payment history
            $paymentStmt = $connect->prepare("
                SELECT payment_date, payment_method, total_amount, paid_amount, payment_number
                FROM deposit
                WHERE student_id = ?
            ");
            
            if ($paymentStmt) {
                $paymentStmt->bind_param('i', $studentId);
                $paymentStmt->execute();
                $paymentResult = $paymentStmt->get_result();
            
                while ($payment = $paymentResult->fetch_assoc()) {
                    $pdf->SetX(5);
                    $pdf->Cell(45, 10, htmlspecialchars($payment['payment_date']), 1);
                    $pdf->Cell(40, 10, htmlspecialchars($payment['payment_method']), 1);
                    $pdf->Cell(40, 10, htmlspecialchars($payment['total_amount']), 1);
                    $pdf->Cell(40, 10, htmlspecialchars($payment['paid_amount']), 1);
                    $pdf->Cell(37, 10, htmlspecialchars($payment['payment_number']), 1);
                    $pdf->Ln();
                }
            
                $paymentStmt->close();
            } else {
                $pdf->Cell(0, 10, 'Failed to prepare payment history statement.', 0, 1, 'C');
            }
        } else {
            $pdf->Cell(0, 10, 'No details found for this payment.', 0, 1, 'C');
        }
        $stmt->close();
    } else {
        $pdf->Cell(0, 10, 'Failed to prepare SQL statement.', 0, 1, 'C');
    }

    $connect->close();
} else {
    $pdf->Cell(0, 10, 'Database connection is not valid.', 0, 1, 'C');
}

// Clean previous output and output the PDF
ob_end_clean();
$pdf->Output($filepath, 'F'); // Save to file
$pdf->Output($filename, 'I'); // Output to browser
?>