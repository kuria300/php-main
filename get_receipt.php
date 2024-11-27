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
    die('Student or parent not logged in. Please log in to view the receipt.');
}

if (isset($_SESSION["id"]) && isset($_SESSION["role"])) {
    // Store user role for easier access

    $userId = $_SESSION["id"];
    $userRole = $_SESSION["role"];
    // Map roles to display names
    $roleNames = [
        "1" => "Admin",
        "2" => "Student",
        "3" => "Parent"
    ];
    // Determine role name based on the session
    $displayRole = $roleNames[$userRole] ?? "Parent";
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
$pdf->SetFillColor(255, 255, 255); 
$pdf->Rect(10, 10, 190, 10, 'F'); // Background rectangle for the title
$pdf->SetXY(10, 10); // Position for the title
$pdf->writeHTMLCell(0, 10, '', '', $title, 0, 1, 0, true, 'C', true);

$pdf->Ln(10);


// Check database connection and prepare SQL statements
if ($connect instanceof mysqli) {
    if ($displayRole === 'Parent') {

        $stmt = $connect->prepare("
        SELECT d.student_id 
        FROM deposit d 
        JOIN students s ON d.student_id = s.student_id 
        WHERE s.parent_id = ? AND d.payment_id = ?
    ");
    
    $stmt->bind_param("ii", $userId, $paymentId); 
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $studentId = $row['student_id'];
    }
        // For parents, ensure we check against the student's ID
        $stmt = $connect->prepare("
           SELECT 
        deposit.payment_id AS payment_id,
        students.student_name AS student_name,
        deposit.total_amount, 
        deposit.paid_amount,
        deposit.remaining_amount, 
        deposit.payment_method,
        deposit.payment_number,
        deposit.status,
        deposit.payment_date
    FROM 
        deposit
    JOIN 
        students ON deposit.student_id = students.student_id
    WHERE 
        deposit.payment_id = ? AND students.parent_id = ?
        ");
        $stmt->bind_param("ii", $paymentId, $userId);
    } else if ($displayRole === 'Student') {
        $studentId = $userId;
        // For students, we only check against their own ID
        $stmt = $connect->prepare("
            SELECT 
        deposit.payment_id AS payment_id,
        students.student_name AS student_name,
        deposit.total_amount, 
        deposit.paid_amount,
        deposit.remaining_amount, 
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
        $stmt->bind_param("ii", $paymentId, $studentId);
    } else{
        $stmt = $connect->prepare("
        SELECT d.student_id 
        FROM deposit d 
        JOIN students s ON d.student_id = s.student_id 
        WHERE d.payment_id = ?
    ");
    
    $stmt->bind_param("i", $paymentId); 
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $studentId = $row['student_id'];
    }
        // For admins, check against the payment ID without restrictions
        $stmt = $connect->prepare("
           SELECT 
        deposit.payment_id AS payment_id,
        students.student_name AS student_name,
        deposit.total_amount, 
        deposit.paid_amount,
        deposit.remaining_amount, 
        deposit.payment_method,
        deposit.payment_number,
        deposit.status,
        deposit.payment_date
    FROM 
        deposit
    JOIN 
        students ON deposit.student_id = students.student_id
    WHERE 
        deposit.payment_id = ?
        ");
        $stmt->bind_param("i", $paymentId);
    }

    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Extract data
            $row = $result->fetch_assoc();
            $studentName = $row['student_name'];
            $paymentDate = $row['payment_date'];
            $paymentMethod = $row['payment_method'];
            $totalAmount = $row['total_amount'];
            $paidAmount = $row['paid_amount'];
            $remainingAmount = $row['remaining_amount'];
            $status = $row['status'];

            // QR Code generation
            $qrCodeContent = http_build_query([
                'id' => $studentId,
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
            $pdf->Cell(0, 10, 'Total Amount: ' . htmlspecialchars(number_format($totalAmount, 2)), 0, 1, 'R');
            $pdf->Cell(0, 10, 'Paid Amount: ' . htmlspecialchars(number_format($paidAmount, 2)), 0, 1, 'R');
            $pdf->Cell(0, 10, 'Remaining Amount: ' . htmlspecialchars(number_format($remainingAmount, 2)), 0, 1, 'R');
            $pdf->Cell(0, 10, 'Status: ' . htmlspecialchars($status), 0, 1, 'L');

            // Payment History
            $pdf->Ln(10);
            $pdf->SetX(5);
            $pdf->Cell(0, 10, 'Payment History:', 0, 1, 'L');
            
            $pdf->SetFont('helvetica', 'B', 12);
            
            $pdf->SetX(5);
            
            // Define column widths
            $pdf->Cell(30, 10, 'Payment Date', 1, 0, 'C');
            $pdf->Cell(40, 10, 'Payment Method', 1, 0, 'C');
            $pdf->Cell(35, 10, 'Total Amount', 1, 0, 'C');
            $pdf->Cell(35, 10, 'Paid Amount', 1, 0, 'C');
            $pdf->Cell(20, 10, 'R/M', 1, 0, 'C');
            $pdf->Cell(41, 10, 'Payment Number', 1, 1, 'C');
            
            // Reset font for table rows
            $pdf->SetFont('helvetica', '', 12);
            
            // Fetch payment history
            $paymentStmt = $connect->prepare("
                SELECT payment_date, payment_method, total_amount, paid_amount, remaining_amount, payment_number
                FROM deposit
                WHERE student_id = ? 
            
            ");
            //AND payment_id != ?
            if ($paymentStmt) {
                $paymentStmt->bind_param('i', $studentId);
                $paymentStmt->execute();
                $paymentResult = $paymentStmt->get_result();
            
                while ($payment = $paymentResult->fetch_assoc()) {
                    $formatted_payment_date = date('Y-m-d', strtotime($payment['payment_date']));
                    $pdf->SetX(5);
                    $pdf->Cell(30, 10, htmlspecialchars($formatted_payment_date), 1);
                    $pdf->Cell(40, 10, htmlspecialchars($payment['payment_method']), 1);
                    $pdf->Cell(35, 10, htmlspecialchars(number_format($payment['total_amount'], 2)), 1);
                    $pdf->Cell(35, 10, htmlspecialchars(number_format($payment['paid_amount'], 2)), 1);
                    $pdf->Cell(20, 10, htmlspecialchars(number_format($payment['remaining_amount'], 2)), 1);
                    $pdf->Cell(41, 10, htmlspecialchars($payment['payment_number']), 1);
                    $pdf->Ln();
                }
                $schoolEmail = '';
                $emailStmt = $connect->prepare("SELECT school_email_address FROM settings");
                if ($emailStmt) {
                    $emailStmt->execute();
                    $emailResult = $emailStmt->get_result();
                    
                    if ($emailRow = $emailResult->fetch_assoc()) {
                        $schoolEmail = htmlspecialchars($emailRow['school_email_address']); // Safely escape the email
                    }
                    $emailStmt->close();
                }
                $pdf->Ln(20); // Add some space before the footer
                $pdf->SetFont('helvetica', 'I', 8);
                $pdf->Cell(0, 5, 'Thank you for your payment!', 0, 1, 'C');
                $pdf->Cell(0, 5, 'For any inquiries, contact us at ' . $schoolEmail, 0, 1, 'C');
                $pdf->Cell(0, 5, 'Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'C');
            
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