<?php 
// Include necessary files
require('C:/xampp/htdocs/sms/tcpdf/tcpdf.php'); // Adjust the path as needed
require('C:/xampp/htdocs/sms/PHPMailer-master/src/PHPMailer.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/Exception.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/SMTP.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include database connection
include('DB_connect.php');

session_start();
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Access sensitive information from environment variables
$smtp=$_ENV['SMTP'];
$mails=$_ENV['MAIL'];
$pass=$_ENV['PASS'];
$pass2=$_ENV['PASS2'];
$port=$_ENV['PORT'];

// Check session for student ID
if (!isset($_SESSION['id'])) {
    die('Student not logged in. Please log in to view the receipt.');
}

// Get payment ID and email from query string
if (isset($_POST['payment_id']) && isset($_POST['email'])) {
    $payment_id = (int)$_POST['payment_id'];
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
}

if ($payment_id <= 0 || !$email) {
    die('Invalid payment ID or email.');
}

// Define paths
$directory = 'C:/xampp r/htdocs/sms/receipts';
$filename = 'receipt_' . $payment_id . '_' . time() . '.pdf';
$filepath = $directory . '/' . $filename;

// Check and create directory if it doesn't exist
if (!is_dir($directory)) {
    if (!mkdir($directory, 0777, true)) {
        die('Failed to create directory.');
    }
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
$pdf->SetFillColor(255, 255, 255); 
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
            students.student_email,
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
            $studentEmail = $row['student_email'];
            $paymentDate = $row['payment_date'];
            $paymentMethod = $row['payment_method'];
            $totalAmount = $row['total_amount'];
            $paidAmount = $row['paid_amount'];
            $remainingAmount = $totalAmount - $paidAmount; // Calculating remaining amount
            $status = $row['status'];

            // QR Code generation
            $qrCodeContent = http_build_query([
                'student_id' => $studentId,
                'student_name' => $studentName,
                'student_email' => $studentEmail,
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
            $pdf->Cell(0, 10, 'Student Email: ' . htmlspecialchars($studentEmail), 0, 1, 'L');
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

// Save the PDF to a file

$pdf->Output($filepath, 'F'); // Save to file
// Send the PDF via email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP'];  // Use 'smtp.gmail.com'
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['MAIL'];  // Use your Gmail address
    $mail->Password   = $_ENV['PASS2']; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $_ENV['PORT'];  // Use port 587


    $mail->SMTPDebug = 3;
    if (!isset($studentEmail) || empty($studentEmail) || !isset($studentName) || empty($studentName)) {
        throw new Exception('Invalid email or student name.');
    }

    $mail->setFrom(htmlspecialchars($studentEmail), htmlspecialchars($studentName));
    $mail->addAddress($studentEmail);

    

    $mail->isHTML(true);
    $mail->Subject = 'Your Payment Receipt';
    $mail->Body = 'Please find attached your payment receipt.';
    $mail->addAttachment($filepath);

    $mail->send();
    echo 'Receipt has been sent to ' . htmlspecialchars($studentEmail);
} catch (Exception $e) {
    echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
}
?>
