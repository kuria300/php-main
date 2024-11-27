<?php 
require('C:\xampp\htdocs\sms\tcpdf\tcpdf.php'); 

// Create new PDF document
$pdf = new TCPDF();

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Your Company');
$pdf->SetTitle('Student Receipt');
$pdf->SetSubject('Receipt');

// Add a page
$pdf->AddPage();

// Set font for the title
$pdf->SetFont('helvetica', 'B', 16);

// Title with black color
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, 'Student Receipt', 0, 1, 'C');

// Add spacing
$pdf->Ln(10);

// Example student data
$studentId = '12345'; // Example student ID
$studentName = 'John Doe';
$issueDate = date('Y-m-d');
$dueDate = date('Y-m-d', strtotime('+30 days'));
$totalAmount = '$500.00';
$paidAmount = '$300.00';
$remainingAmount = '$200.00';

// QR Code generation
$qrCodeContent = 'student_id=' . $studentId; // Encode student ID in QR code
$pdf->write2DBarcode($qrCodeContent, 'QRCODE,H', 160, 20, 40, 40, [], 'N'); // Position and size of QR code

// Set font for student details
$pdf->SetFont('helvetica', '', 12);

// Student details
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, 'Student Name: ' . $studentName, 0, 1, 'L');
$pdf->Cell(0, 10, 'Issue Date: ' . $issueDate, 0, 1, 'L');
$pdf->Cell(0, 10, 'Due Date: ' . $dueDate, 0, 1, 'L');
$pdf->Cell(0, 10, 'Total Amount: ' . $totalAmount, 0, 1, 'L');
$pdf->Cell(0, 10, 'Paid Amount: ' . $paidAmount, 0, 1, 'L');
$pdf->Cell(0, 10, 'Remaining Amount: ' . $remainingAmount, 0, 1, 'L');

// Payment History
$pdf->Ln(10);
$pdf->Cell(0, 10, 'Payment History:', 0, 1, 'L');
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(30, 10, 'Date', 1, 0, 'C');
$pdf->Cell(50, 10, 'Method', 1, 0, 'C');
$pdf->Cell(50, 10, 'Amount', 1, 1, 'C');
$pdf->SetFont('helvetica', '', 12);

// Example payment history data
$payments = [
    ['2024-01-01', 'Credit Card', '$150.00'],
    ['2024-02-01', 'Bank Transfer', '$150.00']
];

foreach ($payments as $payment) {
    $pdf->Cell(30, 10, $payment[0], 1);
    $pdf->Cell(50, 10, $payment[1], 1);
    $pdf->Cell(50, 10, $payment[2], 1);
    $pdf->Ln();
}

// Close and output PDF document
$pdf->Output('student_receipt.pdf', 'I'); // Output to browser

?>