<?php  
require('C:/xampp/htdocs/sms/tcpdf/tcpdf.php'); 
require('C:/xampp/htdocs/sms/PHPMailer-master/src/PHPMailer.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/Exception.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/SMTP.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if payment_id and email are provided
if (isset($_POST['payment_id']) && isset($_POST['email'])) {
    $payment_id = (int)$_POST['payment_id'];
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if ($payment_id <= 0 || !$email) {
        die('Invalid payment ID or email.');
    }

    // Include database connection
    include('DB_connect.php');

    // Fetch payment details
    $stmt = $connect->prepare("
        SELECT 
            deposit.payment_id, 
            students.student_name, 
            students.student_email,
            deposit.total_amount, 
            deposit.paid_amount, 
            deposit.payment_method, 
            deposit.payment_number, 
            deposit.status, 
            deposit.payment_date
        FROM deposit
        JOIN students ON deposit.student_id = students.student_id
        WHERE deposit.payment_id = ?
    ");
    
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();

    if ($payment) {
        // Create PDF
        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('AutoReceipt System');
        $pdf->SetTitle('AutoReceipt');
        $pdf->SetSubject('Payment Receipt');
        $pdf->AddPage();

        // Title styling
        $title = '<span style="color: #800080;">A</span>utoReceipt';
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetFillColor(255, 255, 255); // White background
        $pdf->Rect(10, 10, 190, 10, 'F'); // Background rectangle for the title
        $pdf->SetXY(10, 10); // Position for the title
        $pdf->writeHTMLCell(0, 10, '', '', $title, 0, 1, 0, true, 'C', true);

        $pdf->Ln(10);

        // Set font for student details
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(0, 0, 0);

        // Extract payment details
        $studentName = htmlspecialchars($payment['student_name']);
        $studentEmail = htmlspecialchars($payment['student_email']);
        $paymentDate = htmlspecialchars($payment['payment_date']);
        $paymentMethod = htmlspecialchars($payment['payment_method']);
        $totalAmount = htmlspecialchars($payment['total_amount']);
        $paidAmount = htmlspecialchars($payment['paid_amount']);
        $remainingAmount = $totalAmount - $paidAmount; // Calculating remaining amount
        $status = htmlspecialchars($payment['status']);

        // Add details to PDF
        $pdf->Cell(0, 10, 'Student Name: ' . $studentName, 0, 1, 'L');
        $pdf->Cell(0, 10, 'Student Email: ' . $studentEmail, 0, 1, 'L');
        $pdf->Cell(0, 10, 'Payment Date: ' . $paymentDate, 0, 1, 'L');
        $pdf->Cell(0, 10, 'Payment Method: ' . $paymentMethod, 0, 1, 'L');
        $pdf->Cell(0, 10, 'Total Amount: ' . $totalAmount, 0, 1, 'R');
        $pdf->Cell(0, 10, 'Paid Amount: ' . $paidAmount, 0, 1, 'R');
        $pdf->Cell(0, 10, 'Remaining Amount: ' . $remainingAmount, 0, 1, 'R');
        $pdf->Cell(0, 10, 'Status: ' . $status, 0, 1, 'L');

        // QR Code generation
        $qrCodeContent = http_build_query([
            'payment_id' => $payment_id,
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

        // Payment History
        $pdf->Ln(50);
       
        $pdf->Cell(0, 10, 'Payment History:', 0, 1, 'L');

        $pdf->Ln(10);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetX(5);
        $pdf->Cell(45, 10, 'Payment Date', 1, 0, 'C');
        $pdf->Cell(40, 10, 'Payment Method', 1, 0, 'C');
        $pdf->Cell(40, 10, 'Total Amount', 1, 0, 'C');
        $pdf->Cell(40, 10, 'Paid Amount', 1, 0, 'C');
        $pdf->Cell(37, 10, 'Payment Number', 1, 1, 'C');

        $pdf->SetFont('helvetica', '', 12);

        // Fetch payment history
        $paymentStmt = $connect->prepare("
            SELECT payment_date, payment_method, total_amount, paid_amount, payment_number
            FROM deposit
            WHERE student_id = (SELECT student_id FROM deposit WHERE payment_id = ?)
        ");
        
        $paymentStmt->bind_param('i', $payment_id);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();

        while ($paymentHistory = $paymentResult->fetch_assoc()) {
            $pdf->SetX(5);
            $pdf->Cell(45, 10, htmlspecialchars($paymentHistory['payment_date']), 1);
            $pdf->Cell(40, 10, htmlspecialchars($paymentHistory['payment_method']), 1);
            $pdf->Cell(40, 10, htmlspecialchars($paymentHistory['total_amount']), 1);
            $pdf->Cell(40, 10, htmlspecialchars($paymentHistory['paid_amount']), 1);
            $pdf->Cell(37, 10, htmlspecialchars($paymentHistory['payment_number']), 1);
            $pdf->Ln();
        }

        $paymentStmt->close();

        // Save PDF
        $pdfPath = __DIR__ . '/receipt_' . $payment_id . '.pdf';
        $pdf->Output($pdfPath, 'F');

        // Send email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'eugenekuria66@gmail.com';
            $mail->Password   = 'hamk nfql ozcj lpyo'; // Replace with actual password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom($payment['student_email'], $payment['student_name']);
            $mail->addAddress($payment['student_email']);

            $mail->isHTML(true);
            $mail->Subject = 'Your Payment Receipt';
            $mail->Body    = 'Please find attached your payment receipt.';
            $mail->addAttachment($pdfPath);

            $mail->send();
            echo 'Receipt has been sent to ' . htmlspecialchars($email);
        } catch (Exception $e) {
            echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
        }
    } else {
        echo 'Payment not found.';
    }

    $stmt->close();
    $connect->close();
} else {
    echo 'No payment ID or email specified.';
}
?>