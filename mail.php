<?php

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Access sensitive information from environment variables
$smtp=$_ENV['SMTP'];
$mails=$_ENV['MAIL'];
$pass=$_ENV['PASS'];
$pass2=$_ENV['PASS2'];
$port=$_ENV['PORT'];

require('C:/xampp/htdocs/sms/PHPMailer-master/src/PHPMailer.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/Exception.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/SMTP.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include('DB_connect.php');

session_start();
$userId = $_SESSION['user_id']; // Fetching user ID from session

function getAdminDetails($connect, $userId) {
    $query = "SELECT admin_email FROM admin_users WHERE admin_id = ?";
    $stmt = $connect->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row;
    } else {
        return null;
    }
}

function getsmtpDetails($connect) {
    $query = "SELECT student_id, student_email, student_contact_number1 FROM students";
    $result = $connect->query($query);
    
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    return $students;
}

function getOverduePayments($connect) {
    $currentDate = date('Y-m-d');
    $query = "SELECT payment_id, student_id, total_amount, due_date FROM deposit WHERE due_date < ? AND status = 'pending'";
    $stmt = $connect->prepare($query);
    $stmt->bind_param('s', $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    return $payments;
}

function sendReminder($connect, $payment, $student, $smtpDetails) {
    $email = $student['student_email'];
    $amount = $payment['total_amount'];
    $dueDate = $payment['due_date'];
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP'];  // Use 'smtp.gmail.com'
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL'];  // Use your Gmail address
        $mail->Password   = $_ENV['PASS']; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['PORT'];  // Use port 587
    

        $mail->setFrom($smtpDetails['admin_email'], 'Moses');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Payment Reminder';
        $mail->Body    = "Dear Student,<br><br>You have a payment of $amount due on $dueDate.<br>Please make sure to complete the payment before the due date to avoid any penalties.<br><br>Thank you.";
        $mail->AltBody = "Dear Student,\n\nYou have a payment of $amount due on $dueDate.\nPlease make sure to complete the payment before the due date to avoid any penalties.\n\nThank you.";

        $mail->send();
        echo "Reminder sent to $email for payment ID {$payment['payment_id']}<br>";
    } catch (Exception $e) {
        echo "Message could not be sent to $email. Mailer Error: {$mail->ErrorInfo}<br>";
    }
}

$smtpDetails = getSMTPDetails($connect, $userId);
if (!$smtpDetails) {
    die("SMTP details not found.");
}

$students = getStudentDetails($connect);
$payments = getOverduePayments($connect);

foreach ($payments as $payment) {
    foreach ($students as $student) {
        if ($student['student_id'] == $payment['student_id']) {
            sendReminder($connect, $payment, $student, $smtpDetails);
        }
    }
}

$connect->close();
?>

