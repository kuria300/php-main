<?php
session_start();
 include('DB_connect.php');
 require('C:\xampp\htdocs\sms\tcpdf\tcpdf.php');

 include('res/functions.php');
 
if (!isset($_SESSION["role"])) {
    header("Location: Admin.php");
    exit;
}

if (isset($_SESSION["id"]) && isset($_SESSION["role"])) {
    // Store user role for easier access

    $userId = $_SESSION["id"];
    $userRole = $_SESSION["role"];
    $adminType = $_SESSION["admin_type"] ?? '';
    $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
    $text_size = isset($_COOKIE['text_size']) ? $_COOKIE['text_size'] : 'medium';
    // Map roles to display names
    $roleNames = [
        "1" => "Admin",
        "2" => "Student",
        "3" => "Parent"
    ];
    // Determine role name based on the session
    $displayRole = $roleNames[$userRole] ?? "Parent";
}

if ($displayRole === 'Student') {
    $stmt = $connect->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_remain'])) {
    // Initialize an array to collect error messages
    $errors = [];

    // Retrieve and sanitize input data
    $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
    $partial_amount = isset($_POST['partial_amount']) ? floatval($_POST['partial_amount']) : 0.0;
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    $payment_number = isset($_POST['mpesa_number']) ? $_POST['mpesa_number'] : '';

    // Validate inputs
    if ($payment_id <= 0 || $partial_amount <= 0) {
        $errors[] = "Invalid Payment ID or amount.";
    }
    if ($payment_method === 'cash') {
        $payment_number ="Cash Payment";
    }
    if ($payment_method === 'mpesa' && empty($mpesa_number)) {
        $errors[] = "M-Pesa number is required.";
    }

    if ($payment_method === 'credit_card') {
       $payment_number ="Credit Card Payment";
    }

    // If there are validation errors, redirect with error messages
    if (!empty($errors)) {
        $errorMessages = urlencode(implode('; ', $errors)); // Use semicolon to separate messages
        header('Location: deposit.php?msg=error&errors=' . $errorMessages);
        exit();
    }

    // Check if the payment ID is valid
    $stmt = $connect->prepare("SELECT total_amount, paid_amount, remaining_amount FROM deposit WHERE payment_id = ?");
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $errors[] = "Invalid payment ID.";
    } else {
        $row = $result->fetch_assoc();
        $current_remaining = $row['remaining_amount'];
        $total = $row['total_amount'];

        // Check if the partial amount exceeds the remaining balance
        if ($partial_amount > $current_remaining) {
            $errors[] = "Amount exceeds the remaining balance.";
        }
    
        // If there are validation errors, redirect with error messages
        if (!empty($errors)) {
            $errorMessages = urlencode(implode('; ', $errors)); // Use semicolon to separate messages
            header('Location: deposit.php?msg=error&errors=' . $errorMessages);
            exit();
        }

        // Update the payment record
        $new_remaining = $current_remaining - $partial_amount;
        $new_paid = $row['paid_amount'] + $partial_amount;

        if ($row['paid_amount'] > $total) {
            $errors[] = "Amount exceeds the total.";
        }

        $new_status = ($new_remaining <= 0) ? 'paid' : 'pending';

        $updateStmt = $connect->prepare("UPDATE deposit SET paid_amount = ?, remaining_amount = ?, status= ?, payment_method = ?, payment_number = ? WHERE payment_id = ?");
        $updateStmt->bind_param('disssi', $new_paid, $new_remaining,$new_status,$payment_method, $payment_number, $payment_id);
        $updateStmt->execute();

        if ($updateStmt->affected_rows > 0) {
            header('Location: deposit.php?msg=partial');
            exit();
        } else {
            $errors[] = "Error processing payment.";
        }
        $updateStmt->close();
    }

    // If there are errors during the update, redirect with error messages
    if (!empty($errors)) {
        $errorMessages = urlencode(implode('; ', $errors)); // Use semicolon to separate messages
        header('Location: deposit.php?msg=error&errors=' . $errorMessages);
        exit();
    }
}
$due_date = '';

// Calculate the due date only when the form is being loaded
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $payment_date = date('Y-m-d H:i:s');
    // Calculate the due date as 10 days after the payment date
    $due_date = date('Y-m-d', strtotime($payment_date .' -10 days'));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_payment'])) {
    include('DB_connect.php'); // Ensure this file initializes $connect

    $id = $_SESSION['id']; // Assuming this is used somewhere else for filtering
    
    $student_id = intval($_POST['student_name']);
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    $paid_amount = isset($_POST['paid_amount']) ? floatval($_POST['paid_amount']) : 0;
    $remaining_amount = isset($_POST['remaining_amount']) ? floatval($_POST['remaining_amount']) : 0;
    $status = ''; // Default to 'pending'

    $parent_id= isset($_POST['parent_id']);
    $payment_date = date('Y-m-d H:i:s');

    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : '';
   
    if ($paid_amount >= $total_amount) {
        $status = 'Paid';
    } elseif ($paid_amount > 0) {
        $status = 'Pending';
    } else {
        $status = 'Unpaid';
    }
    // Basic validation
    $errorMessages = '';
    $errors = [];
    if (empty($payment_method)) {
        $errors[] = 'Payment method is required.';
    }

    if ($total_amount <= 0) {
        $errors[] = 'payable amount must be greater than zero.';
    }

    if ($paid_amount < 0) {
        $errors[] = 'Paid amount cannot be negative.';
    }

    if ($paid_amount > $total_amount) {
        $errors[] = 'Paid amount cannot be greater than the total amount.';
    }

    // Or however you get the parent_id from the form
$fetch_parent_query = "SELECT p.parent_name
FROM students s
JOIN parents p ON s.parent_id = p.parent_id
WHERE s.student_id = ?;";
$parent_stmt = $connect->prepare($fetch_parent_query);
$parent_stmt->bind_param('i', $id);
$parent_stmt->execute();
$parent_result = $parent_stmt->get_result();

if ($parent_row = $parent_result->fetch_assoc()) {
    $parent_name = $parent_row['parent_name'];
} else {
    $parent_name = ''; // Handle as needed
}
$parent_stmt->close();
    

    if (!empty($errors)) {
        // Convert errors array to a query string format
        $errorMessages = urlencode(implode('; ', $errors)); // Use semicolon to separate messages
    
        header('Location: deposit.php?msg=error&errors=' . $errorMessages); // Redirect with query parameters
        exit();
    }else {
       
        $message = 'Payment processed successfully.';
    }


    // Handle different payment methods
    if ($payment_method === 'mpesa') {
        $mpesa_number = $_POST['mpesa_number'];
        if (empty($mpesa_number)) {
            echo '<p>M-Pesa number is required.</p>';
            exit;
        }

        // Call M-Pesa API function
        $result = callMpesaApi($mpesa_number, $total_amount);
        if (!$result) {
            echo '<p>M-Pesa payment failed.</p>';
            exit;
        }
        

    } else if ($payment_method === 'credit_card') {
       
        $payment_number = 'Credit card Payment'; 

    } else if ($payment_method === 'cash') {
        if ($paid_amount <= 0) {
            echo '<p>Cash amount must be greater than zero.</p>';
            exit;
        }
        $payment_number = 'Cash Payment'; // Placeholder for cash payments

    } else {
        echo '<p>Invalid payment method.</p>';
        exit;
    }

$student_query = "SELECT parent_id FROM students WHERE student_id = ?";
$stmt = $connect->prepare($student_query);
$stmt->bind_param('i',  $id);
$stmt->execute();
$stmt->bind_result($parents_id);
$stmt->fetch();
$stmt->close();

$fetch_parent_query = "SELECT parent_name FROM parents WHERE parent_id = ?";
$parent_stmt = $connect->prepare($fetch_parent_query);
$parent_stmt->bind_param('i', $id);
$parent_stmt->execute();
$parent_row = $parent_stmt->get_result()->fetch_assoc();
$parent_name = $parent_row ? $parent_row['parent_name'] : '';

if ($displayRole === 'Student' || $displayRole === 'Admin') {
    $parentIdToBind = $parents_id; // Use the parent's ID fetched from the students table
} else {
    $parentIdToBind = $id; // Assuming $id is the student_id in this context
}



    // Insert payment details into the database
    $query = " INSERT INTO deposit (student_id, payment_number, due_date, total_amount, paid_amount, payment_method, status, payment_date, parent_id, parent_name, remaining_amount)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";

            if ($stmt = $connect->prepare($query)) {
                $stmt->bind_param("isssssssss", $student_id, $payment_number, $due_date, $total_amount, $paid_amount, $payment_method, $status, $parentIdToBind, $parent_name, $remaining_amount);

                if ($stmt->execute()) {
                    // Redirect to the same page with a success message
                    header('Location: deposit.php?msg=success');
                    exit();
    } else {
        // Print error if execution fails
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    } else {
        echo '<p>Failed to prepare the SQL statement.</p>';
    }

}

$admin_query = "SELECT admin_name, admin_email FROM admin_users WHERE admin_id = ?"; // Use the appropriate admin_id
$admin_stmt = $connect->prepare($admin_query);

$admin_stmt->bind_param('i', $userId);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();

if ($admin_row = $admin_result->fetch_assoc()) {
    $admin_name = $admin_row['admin_name'];
    $admin_email = $admin_row['admin_email'];
} else {
    $admin_name = 'Default Admin'; // Fallback if no admin found
    $admin_email = 'admin@example.com'; // Fallback email
}

$admin_stmt->close();


require('C:/xampp/htdocs/sms/PHPMailer-master/src/PHPMailer.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/Exception.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/SMTP.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$error = '';

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
        deposit.payment_date,
        parents.parent_name, 
        parents.parent_email
    FROM deposit
    JOIN students ON deposit.student_id = students.student_id
    LEFT JOIN parents ON students.parent_id = parents.parent_id
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
        $pdf->Ln(30); // Add some space before the footer
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 5, 'Thank you for your payment!', 0, 1, 'C');
        $pdf->Cell(0, 5, 'For any inquiries, contact us at ' . $schoolEmail, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Page ' . $pdf->getAliasNumPage() . ' of ' . $pdf->getAliasNbPages(), 0, 0, 'C');
    

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

            if ($displayRole === 'Parent') {
                // Parent receives the email from the student
                $mail->setFrom($payment['parent_email'], $payment['parent_name']);
                $mail->addAddress($payment['parent_email'], $payment['parent_name']); // Send to parent
            } else if ($displayRole === 'Student') {
                // Student receives their own email
                $mail->setFrom($payment['student_email'], $payment['student_name']);
                $mail->addAddress($payment['student_email'], $payment['student_name']); // Send to student
            }else if ($displayRole === 'Admin') {
                // Student receives their own email
                $mail->setFrom($admin_email, $admin_name);
                $mail->addAddress($payment['student_email'], $payment['student_name']); // Send to student
                if (!empty($payment['parent_email'])) {
                    $mail->addAddress($payment['parent_email'], $payment['parent_name']); // Send to parent
                }
            }

            $mail->isHTML(true);
            $mail->Subject = 'Your Payment Receipt';
            $mail->Body    = 'Please find attached your payment receipt.';
            $mail->addAttachment($pdfPath);

            $mail->send();
            $message = 'Receipt has been sent to ' . htmlspecialchars($email);
            if (@$mail->send()) {
                header('Location: deposit.php?msg=send');
                exit(); // Ensure no further code is executed after the redirect
            } else {
                // Optionally log the error without displaying it
                file_put_contents('email_errors.log', date('Y-m-d H:i:s') . ' - ' . $mail->ErrorInfo . PHP_EOL, FILE_APPEND);
            }
        } catch (Exception $e) {
            file_put_contents('email_errors.log', date('Y-m-d H:i:s') . ' - ' . $mail->ErrorInfo . PHP_EOL, FILE_APPEND);
        
        }
    } else {
        $error = 'Payment not found.';
    }

    $stmt->close();
   
} 


// Example function to call M-Pesa API
function callMpesaApi($mpesa_number, $total_amount) {
    $url = "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";
    $access_token = getAccessToken();

    $shortcode = 174379;  // Replace with your shortcode
    $lipa_na_mpesa_online_shortcode_key = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
    $password = base64_encode($shortcode . $lipa_na_mpesa_online_shortcode_key . date('YmdHis'));
    

    /*"Amount" => $total_amount, // Change this to the desired amount
        "PartyA" => $mpesa_number, // Use the provided M-Pesa number
        "PartyB" => $shortcode,
        "PhoneNumber" => $mpesa_number, // Use the provided M-Pesa number*/

    $data = [
        "BusinessShortCode" => $shortcode,
        "Password" => $password,
        "Timestamp" => date('YmdHis'),
        "TransactionType" => "CustomerPayBillOnline",
        "Amount" => 1, // Change this to the desired amount$paid_amount
        "PartyA"=> 254768863372,//$mpesa_number
        "PartyB"=> 174379,
        "PhoneNumber"=> 254768863372,   //"PhoneNumber" => $mpesa_number,
        "CallBackURL" => "https://d9a6-102-0-5-84.ngrok-free.app/sms/daraja.php",
        "AccountReference" => "AutoReceipt",
        "TransactionDesc" => "Payment for Fees"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    

    // Decode the response
    $response_data = json_decode($response, true);
    
    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON decode error: " . json_last_error_msg() . "\n";
        return false;
    }

    // Check the response code
    if (isset($response_data['ResponseCode']) && $response_data['ResponseCode'] == '0') {
        return true; // Payment was successful
    } else {
        echo "Error: " . ($response_data['errorMessage'] ?? 'Unknown error') . "\n";
        return false; // Payment failed
    }
    curl_close($ch);
}

function processCreditCardPayment($number, $expiry, $cvc, $amount) {
    // Implement the actual API call to the credit card gateway
    // For now, return true as a placeholder
    return true;
}

function getAccessToken() {
    $consumer_key = ' Rm1drpxRb854CYa2YKqY4GjIvZ6ki2ltUYGgR3K4zWswBycK';
    $consumer_secret = 'kEjI9s29S43LS6KG0URrkJo6Pb9iCvdlVxA8Wc8ln5Qp0E1MxPAs7KYfl6NxgX9c';
    $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $headers = [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/json; charset=utf8'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERPWD, $consumer_key . ':' . $consumer_secret);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response_data = json_decode($response, true);

    return isset($response_data['access_token']) ? $response_data['access_token'] : '';

    curl_close($ch);
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment'])) {
   
    // Retrieve and sanitize inputs
    $payment_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    $paid_amount = isset($_POST['paid_amount']) ? floatval($_POST['paid_amount']) : 0;
    $remaining_amount = isset($_POST['remaining_amount']) ? floatval($_POST['remaining_amount']) : 0;
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';

    // Basic validation
    $errors = [];
    if ($total_amount <= 0) {
        $errors[] = 'Total amount must be greater than zero.';
    }

    if ($paid_amount < 0) {
        $errors[] = 'Paid amount cannot be negative.';
    }

    if ($paid_amount > $total_amount) {
        $errors[] = 'Paid amount cannot be greater than the total amount.';
    }

    if (!empty($errors)) {
        // Handle errors
        $errorMessages = urlencode(implode('; ', $errors));
        header('Location: deposit.php?msg=error&errors=' . $errorMessages);
        exit();
    } else {
        // Prepare SQL UPDATE statement
        $query = "
            UPDATE deposit 
            SET total_amount = ?, paid_amount = ?, remaining_amount = ?, payment_method = ?, status = ?
            WHERE payment_id = ?
        ";

        if ($stmt = $connect->prepare($query)) {
            // Bind parameters
            $stmt->bind_param("ddsssi", $total_amount, $paid_amount, $remaining_amount, $payment_method, $status, $payment_id);

            if ($stmt->execute()) {
                // Redirect to the same page with a success message
                header('Location: deposit.php?msg=update');
                exit();
            } else {
                echo "Error: " . $stmt->error;
            }

            // Close the statement
            $stmt->close();
        } else {
            echo "Error preparing statement: " . $connect->error;
        }
    }
}

// Fetch user preferences
  
$settingsQuery = "SELECT * FROM settings LIMIT 1";
$settingsResult = $connect->query($settingsQuery);

// Check if the query was successful
if ($settingsResult) {
    // Fetch the settings as an associative array
    $settings = $settingsResult->fetch_assoc();

    // Check if settings were retrieved
    if ($settings) {
        // Safely access the settings array
        $systemName = htmlspecialchars($settings['system_name']);
        
    } else {
        // Handle case when no settings are found
        $systemName = 'AutoReceipt';  // Fallback value
        
        // Optionally log or display a message
        error_log("No settings found in the database.");
    }
} else {
    // Handle query failure
    $systemName = 'AutoReceipt';  // Fallback value
   
    // Optionally log or display a message
    error_log("Query failed: " . $connect->error);
}
$contact_number = '';
$student_id = $_SESSION['id'];

if ($connect instanceof mysqli) {
    // Fetch the contact number for the given student ID
    $query = 'SELECT student_contact_number1 FROM students WHERE student_id = ?';
    $stmt = $connect->prepare($query);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($contact_number);
    $stmt->fetch();
    $stmt->close();

    $query = 'SELECT c.course_id, c.course_name, c.course_fee 
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = ?';
$stmt = $connect->prepare($query);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();

// Store courses and fees in an array
$courses = [];
while ($row = $result->fetch_assoc()) {
$courses[] = $row;
}
  
    if ($connect instanceof mysqli) {
        // Fetch the due date for the student
        $query = 'SELECT due_date FROM deposit WHERE student_id = ? ORDER BY payment_date DESC LIMIT 1';
        $stmt = $connect->prepare($query);
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($due_date);
        $stmt->fetch();
        $stmt->close();
    }

}
$sql = "SELECT p.*, s.student_name FROM deposit p
        JOIN students s ON p.student_id = s.student_id";
$result = $connect->query($sql);

// Handle search functionality if a query is provided
$searchQuery = '';
if (isset($_GET['query'])) {
    $searchQuery = $_GET['query'];

    $sql = "SELECT p.*, s.student_name FROM deposit p
            JOIN students s ON p.student_id = s.student_id
            WHERE 
                p.payment_number LIKE ? OR 
                p.parent_name LIKE ? OR 
                p.payment_method LIKE ? OR 
                p.status LIKE ? OR 
                p.payment_date LIKE ? OR 
                p.due_date LIKE ?";

    $stmt = $connect->prepare($sql);
    $likeQuery = "%" . $searchQuery . "%";
    $stmt->bind_param("ssssss", $likeQuery, $likeQuery, $likeQuery, $likeQuery, $likeQuery, $likeQuery);
    $stmt->execute();
    $result = $stmt->get_result();
}
$query = "";
$imageField = "";

if ($userRole === "1") { // Admin
    $query = "SELECT * FROM admin_users WHERE admin_id = ?";
    $imageField = 'admin_image';
} elseif ($userRole === "2") { // Student
    $query = "SELECT * FROM students WHERE student_id = ?";
    $imageField = 'student_image';
} else { // Parent
    $query = "SELECT * FROM parents WHERE parent_id = ?";
    $imageField = 'parent_image';
}

if ($stmt = $connect->prepare($query)) {
    $stmt->bind_param("i", $userId); // "i" for integer type
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc(); // Fetch associative array
    } else {
        $admin = null; // Handle user not found case
    }
    $stmt->close();
}
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_POST['delete_deposit'])) {
    // Retrieve the payment ID from POST data
    $payment_id = isset($_POST['payment_id']) ? $_POST['payment_id'] : '';

    // Validate the payment ID
    if (!is_numeric($payment_id)) {
        die("Invalid payment ID.");
    }

    // Prepare SQL statement to delete the deposit record
    $stmt = $connect->prepare("DELETE FROM deposit WHERE payment_id = ?");
    $stmt->bind_param("i", $payment_id);

    // Execute the statement
    if ($stmt->execute()) {
        // Redirect or provide success feedback
        header('Location: deposit.php?msg=delete');
        exit();
    } else {
        // Handle SQL execution error
        echo "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
    }

    // Close the statement
    $stmt->close();
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fees Payment</title>
    <link rel="icon" href="logo2.png">
    <link id="theme-style" rel="stylesheet" href="css/<?= htmlspecialchars($theme); ?>.css">
    <link id="text-size-style" rel="stylesheet" href="css/<?= htmlspecialchars($text_size); ?>.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link rel="stylesheet" href="css/dashboard1.css">
</head>
<body>
    <div class="grid-container"> 
        <!--start header-->
        <header class="header">
            <div class="menu-icon" onclick="openSideBar()">
                <span class="material-symbols-outlined">menu</span>
            </div>
            <div class="header-left">
                <form class="d-flex ms-auto" method="GET" action="search_results.php">
                    <div class="input-group my-lg-0">
                        <input 
                        type="text"
                         name="query"
                        class="form-control"
                        placeholder="search for..."
                        aria-label="search"
                        aria-describedby="button-addon2"
                        />
                        <button class="btn btn-success" type="submit" id="button-addon2"><i class="bi bi-search"></i></button>
                    </div>
                </form>
            </div>
            <div class="header-right">
                <ul class="navbar-nav mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="upload/<?php echo htmlspecialchars($admin[$imageField] ?? 'default.jpg'); ?>" class="rounded-circle" name="image" alt="Profile Image" style="width: 48px; height: 48px; object-fit: cover;">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                        <?php if ($displayRole === 'Admin'): ?>
                            <li><a class="dropdown-item text-muted" href="settings.php">Settings</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="viewuser.php">User Information</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php" onclick="confirmLogout(event)">Log Out</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </header>
        <!--end header-->
    
        <!--sidetag start-->
        <aside class="sidebar">
            <div class="sidebar-title">
            <div class="sidebar-brand">
                <span class="material"> <bold class="change-color"><?php echo $systemName; ?></bold></span>
            </div>
                <span class="material-symbols-outlined" onclick="closeSideBar()">close</span>
            </div>
            <ul class="sidebar-list">
                <li class="sidebar-list-item">
                    <a href="dashboard.php" class="nav-link px-3 active">
                        <span class="material-symbols-outlined">dashboard</span> Dashboard
                    </a>
                </li>
                
                <li class="sidebar-list-item">
                    <a class="nav-link px-3 mt-3 sidebar-link active" 
                    data-bs-toggle="collapse" 
                    href="#collapseExample" 
                    role="button"
                    aria-expanded="false" 
                    aria-controls="collapseExample">
                        <span class="material-symbols-outlined">account_balance_wallet</span> Fees Manager
                        <span class="right-icon ms-2"><i class="bi bi-chevron-down"></i></span>
                    </a>
                </li>
                <div class="collapse" id="collapseExample">
                    <div>
                        <ul class="navbar-nav ps-3">
                            <?php if ($displayRole === 'Admin'): ?>
                                <li class="sidebar-list-item">
                                    <a href="Student.php" class="nav-link px-3">
                                    <span class="material-symbols-outlined">person_add</span>
                                        <span>New Admission</span>
                                    </a>
                                </li>
                                <li class="sidebar-list-item">
                                    <a href="Student.php" class="nav-link px-3">
                                    <span class="material-symbols-outlined">search</span>
                                        <span>Search Admission</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li class="sidebar-list-item">
                                <a href="deposit.php" class="nav-link px-3">
                                <span class="material-symbols-outlined">payments</span>
                                    <span>Deposit Fees</span>
                                </a>
                            </li>
                            <li class="sidebar-list-item">
                                <a href="deposit.php" class="nav-link px-3">
                                <span class="material-symbols-outlined">receipt</span>
                                    <span>Generate Invoices</span>
                                </a>
                            </li>
                            <?php if ($displayRole === 'Admin'): ?>
                                <li class="sidebar-list-item">
                                    <a href="course.php" class="nav-link px-3">
                                    <span class="material-symbols-outlined">print</span>
                                        <span>Manage Fees</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <?php if ($displayRole === 'Admin' || $displayRole === 'Student'): ?>
                <li class="sidebar-list-item">
    <a class="nav-link px-3 mt-3 sidebar-link active" 
       data-bs-toggle="collapse" 
       href="#collapseReports" 
       role="button"
       aria-expanded="false" 
       aria-controls="collapseReports">
        <span class="material-symbols-outlined">admin_panel_settings</span> Management
        <span class="right-icon ms-2"><i class="bi bi-chevron-down"></i></span>
    </a>
</li>
<?php endif; ?>
<div class="collapse" id="collapseReports">
    <div>
        <ul class="navbar-nav ps-3">
           
                <?php if ($displayRole === 'Admin'): ?>
                    <li class="sidebar-list-item">
                        <a href="parents.php" class="nav-link px-3">
                        <span class="material-symbols-outlined">people</span>
                            <span>Manage Parents</span>
                        </a>
                    </li>
                    <li class="sidebar-list-item">
                        <a href="course.php" class="nav-link px-3">
                        <span class="material-symbols-outlined">class</span>
                            <span>Manage Courses</span>
                        </a>
                    </li>
                    <?php if ($adminType === 'master'): ?>
                    <li class="sidebar-list-item">
                        <a href="studententry.php" class="nav-link px-3">
                        <span class="material-symbols-outlined">admin_panel_settings</span>
                            <span>Manage Users</span>
                        </a>
                    </li>
                <?php endif; ?>
                    <li class="sidebar-list-item">
                        <a href="notify.php" class="nav-link px-3">
                        <span class="material-symbols-outlined">notifications</span>
                            <span>Reminders</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($displayRole === 'Student' || $displayRole === 'Admin'): ?>
                    <li class="sidebar-list-item">
                        <a href="addcourse.php" class="nav-link px-3">
                        <span class="material-symbols-outlined">add_circle</span>
                            <span>Add Course</span>
                        </a>
                    </li>
                <?php endif; ?>
        </ul>
    </div>
</div>
                <li class="sidebar-list-item">
                    <a class="nav-link px-3 mt-3 sidebar-link active" 
                    data-bs-toggle="collapse" 
                    href="#collapsePayments" 
                    role="button"
                    aria-expanded="false" 
                    aria-controls="collapsePayments">
                        <span class="material-symbols-outlined">payments</span>  Payments
                        <span class="right-icon ms-2"><i class="bi bi-chevron-down"></i></span>
                    </a>
                </li>
                <div class="collapse" id="collapsePayments">
                    <div>
                        <ul class="navbar-nav ps-3">
                            <li class="sidebar-list-item">
                                <a href="payment.php" class="nav-link px-3">
                                <span class="material-symbols-outlined">history</span>
                                    <span>Payments History</span>
                                </a>
                            </li>
                           
                        </ul>
                    </div>
                </div>
                <li class="sidebar-list-item">
                    <a href="grades.php" class="nav-link px-3 mt-3 active">
                        <span class="material-symbols-outlined">grade</span> Grades
                    </a>
                </li>
                <li class="sidebar-list-item">
                    <a href="attendance.php" class="nav-link px-3 mt-3 active">
                        <span class="material-symbols-outlined">calendar_today</span> Attendance
                    </a>
                </li>
                <li class="sidebar-list-item">
                    <a href="noticeboard.php" class="nav-link px-3 mt-3 active">
                        <span class="material-symbols-outlined">announcement</span> NoticeBoard
                    </a>
                </li>
                <?php if ($displayRole === 'Admin'|| $displayRole === 'Student'): ?>
                <li class="sidebar-list-item">
                    <a href="academicyears.php" class="nav-link px-3 mt-3 active">
                        <span class="material-symbols-outlined">school</span> Academic Years
                    </a>
                </li>
                <?php endif; ?>
                <li class="sidebar-list-item">
                    <a href="profile.php" class="nav-link px-3 mt-3 active">
                        <span class="material-symbols-outlined">person</span> Update Profile
                    </a>
                </li>
                <li class="sidebar-list-item">
                    <a href="updatepass.php" class="nav-link px-3 mt-3 active">
                        <span class="material-symbols-outlined">lock</span> Update Password
                    </a>
                </li>
                <li class="sidebar-list-item">
                    <a href="logout.php" class="nav-link px-3 mt-3 active" onclick="confirmLogout(event)">
                        <span class="material-symbols-outlined">logout</span> Log Out
                    </a>
                </li>
            </ul>
            <div class="sb-sidenav-footer">
                <div class="small">Logged in as:<span class="px-1"><?php echo htmlspecialchars($displayRole); ?></span></div>
            </div>
        </aside>
        <!--sidetag end-->
        
        <!--main-->
        <?php if ($displayRole === 'Parent'): ?>
            <main class="main-container">
        <?php 
        if (isset($_GET['action'])) {
            if ($_GET['action'] == 'add') {
                ?>
                <h1 class="mt-2 head-update">Payments and Invoices</h1>
                <ol class="breadcrumb mb-4 small">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active"><a href="payment.php">Payments and Invoices</a></li>
                    <li class="breadcrumb-item active">Add fees</li>
                </ol>
                <div class="row">
                    <div class="col-md-12">
                        <?php
                        if (!empty($error)) {
                            // Convert the error array to a string
                            $errorMessages = '<ul class="list-unstyled">';
                            foreach ($error as $err) {
                                $errorMessages .= '<li>' . htmlspecialchars($err) . '</li>';
                            }
                            $errorMessages .= '</ul>';
                        
                            // Display the alert with error messages
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                               . $errorMessages .
                               '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                               . '</div>';
                        }
                        ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <span class="material-symbols-outlined text-bold">manage_accounts</span> Add New Fees
                            </div>
                            <div class="card-body">
                            
                             
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="main-footer px-3">
                    <div class="pull-right hidden-xs"> 
                        Copyright © 2024-2025 <a href="#">AutoReceipt system</a>. All rights reserved  
                    </div>
                </footer>
                <?php
            } else if ($_GET['action'] == 'edit') {
                if (isset($_GET['id'])) {
                    ?>
                    <h1 class="mt-2 head-update">Payments and Invoices</h1>
                    <ol class="breadcrumb mb-4 small"  style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                        <li class="breadcrumb-item"><a href="dashboard.php"  style="color: #f8f9fa;">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="payment.php"  style="color: #f8f9fa;">Payment and Invoices</a></li>
                        <li class="breadcrumb-item active">Edit Invoice</li>
                    </ol>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header">
                                <?php if (isset($errors) && !empty($errors)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php foreach ($errors as $error): ?>
                                            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?><br>
                                        <?php endforeach; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($message) && !empty($message) && empty($errors)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                     <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>
                                    <span class="material-symbols-outlined">manage_accounts</span>Student Edit Form
                                </div>
                                <div class="card-body">
                                </div>
                            </div>
                        </div>
                    </div>
                    <footer class="main-footer px-3">
                            <div class="pull-right hidden-xs"> 
                            <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>
                            </div>
                        </footer>
                    <?php
                }
            }
        } else {
            ?>
            <h1 class="mt-2 head-update">Payments and Invoices</h1>
            <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
                <li class="breadcrumb-item active">Payment and Invoices</li>
            </ol>
            <?php
            if (isset($_GET['msg'])) {
                if ($_GET['msg'] == 'add') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Fees Successfully paid
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'success') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> Payment processed successfully
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
                }
                if ($_GET['msg'] === 'error' && isset($_GET['errors'])) {
                    // Decode and display error messages
                    $errors = urldecode($_GET['errors']);
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                    echo '<i class="bi bi-exclamation-circle"></i> ' . htmlspecialchars($errors);
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    echo '</div>';
                } 
                if ($_GET['msg'] == 'delete') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Payment deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'partial') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Partial payment was successful
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'update') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> payment was Updated successfully
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
            }
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <span class="material-symbols-outlined">manage_accounts</span> Payments
                        </div>
                        <div class="col-md-6 d-flex justify-content-end align-items-center">
                            <!-- Search Bar -->
                            <div class="mb-0 me-3">
                                <input type="text" id="searchBar" class="form-control" placeholder="Search Invoices..." onkeyup="searchInvoices()">
                            </div>
                            <!-- Button to trigger modal -->
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#payFeesModal">
                                Pay Fees
                            </button>
                        </div>
                        <!-- Modal -->
                        <?php
                        include('DB_connect.php');

                        $student_query = "SELECT parent_id FROM students WHERE student_id = ?";
                        $stmt = $connect->prepare($student_query);
                        $stmt->bind_param('i', $userId);
                        $stmt->execute();
                        $stmt->bind_result($parent_id);
                        $stmt->fetch();
                        $stmt->close();
                        ?>

                        <div class="modal fade" id="payFeesModal" tabindex="-1" aria-labelledby="payFeesModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="payFeesModalLabel">Choose Payment Method</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                    <div class="mb-3">
                                        <!-- Form to add fees -->
                                        <form action="deposit.php" method="post">
                                            <label for="payment_method">Payment Method:</label>
                                                <select id="payment_method" name="payment_method" onchange="togglePaymentFields()">
                                                <option value="cash" class="text-muted">Choose Payment</option>
                                                    <option value="cash">Cash</option>
                                                    <option value="mpesa">M-Pesa</option>
                                                    <option value="credit_card">Credit Card</option>
                                                </select><br><br>
                                            <div class="modal-footer">
                                               <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                            
                                        </form>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="emailReceiptModal" tabindex="-1" aria-labelledby="emailReceiptModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="emailReceiptModalLabel">Send Receipt via Email</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form action="deposit.php" method="post">
                                    <input type="hidden" id="emailReceiptPaymentId" name="payment_id">
                                    <div class="mb-3">
                                        <label for="emailAddress" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="emailAddress" name="email" required>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary">Send Receipt</button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
                <div class="modal fade" id="partialPaymentModal" tabindex="-1" aria-labelledby="partialPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="partialPaymentModalLabel">Make Partial Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Form to make partial payment -->
                <form action="deposit.php" method="post">
                    <div class="mb-3">
                        <label for="paymentId" class="form-label">Payment ID</label>
                        <input type="text" class="form-control" id="paymentId" name="payment_id" required>
                    </div>
                    <div class="mb-3">
                        <label for="partialAmount" class="form-label">Amount to Pay</label>
                        <input type="number" class="form-control" id="partialAmount" name="partial_amount" min="1" required>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success" name="pay_remain">Submit Payment</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
                <div class="table-responsive">
                    <div class="card-body">
                        <!-- Invoices Section -->
                        <div class="invoices-section mt-1 mb-2">
                            <h3>My Payments</h3>
                            <?php
            // Re-open connection
            include('DB_connect.php');

            // Check if student_id is set in the session
            if (isset($_SESSION['id'])) {
                $student_id = $_SESSION['id'];
                $items_per_page = 5; // Number of items per page
                $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $offset = ($current_page - 1) * $items_per_page;

                if ($connect instanceof mysqli) {
                   
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
                                deposit.payment_date,
                                deposit.due_date
                            FROM 
                                deposit
                            JOIN 
                                students ON deposit.student_id = students.student_id
                            WHERE 
                                students.parent_id = ?
                            LIMIT ? OFFSET ?
                        ");
                        $stmt->bind_param("iii", $student_id, $items_per_page, $offset);

                    if ($stmt) {
                        $stmt->execute();
                        $result = $stmt->get_result();


                        if ($result->num_rows > 0) {
                            echo '<table class="table table-bordered" id="payTable">';
                            echo '<thead><tr><th>Student Name</th><th>Payment Date</th><th>Due Date</th><th>Total Amount</th><th>Paid Amount</th><th>Remaining Amount</th><th>Payment Method</th><th>Payment Number</th><th>Status</th><th>Action</th></tr></thead><tbody>';
                            while ($row = $result->fetch_assoc()) {
                                
                                echo '<tr>';
                               
                                echo '<td>' . htmlspecialchars($row['student_name']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['payment_date']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['due_date']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['total_amount']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['paid_amount']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['remaining_amount']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['payment_method']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['payment_number']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['status']) . '</td>';
                                echo '<td>
                                    <div class="btn-group" role="group" aria-label="Actions">
                                        <button type="button" class="btn btn-success btn-sm me-3 btn-custom-radius" data-bs-target="#viewReceiptModal" data-id="' . htmlspecialchars($row['payment_id']) . '">
                                             Invoice
                                        </button>
                                         <a href="download_receipt.php?payment_id=' . htmlspecialchars($row['payment_id']) .'" class="btn btn-primary btn-sm me-3 btn-custom-radius" target="_blank">
                                            Download Receipt
                                        </a>
                                         <button type="button" class="btn btn-success btn-sm btn-custom-radius" data-bs-toggle="modal" data-bs-target="#emailReceiptModal" data-id="' . htmlspecialchars($row['payment_id']) . '">
                                            Email Receipt
                                        </button>
                                         <button type="button" class="btn btn-warning btn-sm ms-3 btn-custom-radius" data-bs-toggle="modal" data-bs-target="#partialPaymentModal" data-id="' . htmlspecialchars($row['payment_id']) . '">
                                            Pay Remaining
                                        </button>
                                    </div>
                                </td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';

                            // Get total number of records
                            $stmt_total = $connect->prepare("
                             SELECT COUNT(*) AS total_records 
                    FROM deposit 
                    JOIN students ON deposit.student_id = students.student_id
                    WHERE students.parent_id = ?
                            ");
                            if ($stmt_total) {
                                $stmt_total->bind_param("i", $student_id);
                                $stmt_total->execute();
                                $result_total = $stmt_total->get_result();
                                $total_records = $result_total->fetch_assoc()['total_records'];
                                $total_pages = ceil($total_records / $items_per_page);

                                echo '<nav aria-label="Page navigation"><ul class="pagination">';
                                
                                // Previous button
                                if ($current_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . ($current_page - 1) . '">Previous</a></li>';
                                } else {
                                    echo '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
                                }
                                
                                // Page numbers
                                for ($page = 1; $page <= $total_pages; $page++) {
                                    if ($page == $current_page) {
                                        echo '<li class="page-item active"><span class="page-link">' . $page . '</span></li>';
                                    } else {
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $page . '">' . $page . '</a></li>';
                                    }
                                }
                                
                                // Next button
                                if ($current_page < $total_pages) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . ($current_page + 1) . '">Next</a></li>';
                                } else {
                                    echo '<li class="page-item disabled"><span class="page-link">Next</span></li>';
                                }
                                
                                echo '</ul></nav>';
                            } else {
                                echo '<p>Failed to get total number of records.</p>';
                            }
                        } else {
                            echo '<p>No invoices found.</p>';
                        }
                        $stmt->close();
                    } else {
                        echo '<p>Failed to prepare SQL statement for invoices.</p>';
                    }
                } else {
                    echo '<p>Database connection is not valid.</p>';
                }

                $connect->close();
            } else {
                echo '<p>Student ID not found in session.</p>';
            }
            ?>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="main-footer px-3">
                            <div class="pull-right hidden-xs"> 
                            <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>
                            </div>
                        </footer>
            </div>
        </main>
        <?php
        }
        ?>
        <!--main-->
    </div>
    <?php endif; ?>

        <?php if ($displayRole === 'Student'): ?>
        <main class="main-container">

        <?php 
        if (isset($_GET['action'])) {
            if ($_GET['action'] == 'add') {
                ?>
                <h1 class="mt-2 head-update">Payments and Invoices</h1>
                <ol class="breadcrumb mb-4 small">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active"><a href="payment.php">Payments and Invoices</a></li>
                    <li class="breadcrumb-item active">Add fees</li>
                </ol>
                <div class="row">
                    <div class="col-md-12">
                        <?php
                        if (!empty($error)) {
                            // Convert the error array to a string
                            $errorMessages = '<ul class="list-unstyled">';
                            foreach ($error as $err) {
                                $errorMessages .= '<li>' . htmlspecialchars($err) . '</li>';
                            }
                            $errorMessages .= '</ul>';
                        
                            // Display the alert with error messages
                            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                               . $errorMessages .
                               '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                               . '</div>';
                        }
                        ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <span class="material-symbols-outlined text-bold">manage_accounts</span> Add New Fees
                            </div>
                            <div class="card-body">
                            
                             
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="main-footer px-3">
                    <div class="pull-right hidden-xs"> 
                        Copyright © 2024-2025 <a href="#">AutoReceipt system</a>. All rights reserved  
                    </div>
                </footer>
                <?php
            } else if ($_GET['action'] == 'edit') {
                if (isset($_GET['id'])) {
                    ?>
                    <h1 class="mt-2 head-update">Payments and Invoices</h1>
                    <ol class="breadcrumb mb-4 small"  style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                        <li class="breadcrumb-item"><a href="dashboard.php"  style="color: #f8f9fa;">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="payment.php"  style="color: #f8f9fa;">Payment and Invoices</a></li>
                        <li class="breadcrumb-item active">Edit Invoice</li>
                    </ol>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header">
                                <?php if (isset($errors) && !empty($errors)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php foreach ($errors as $error): ?>
                                            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?><br>
                                        <?php endforeach; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($message) && !empty($message) && empty($errors)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                     <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>
                                    <span class="material-symbols-outlined">manage_accounts</span>Student Edit Form
                                </div>
                                <div class="card-body">
                                </div>
                            </div>
                        </div>
                    </div>
                    <footer class="main-footer px-3">
                            <div class="pull-right hidden-xs"> 
                            <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>
                            </div>
                        </footer>
                    <?php
                }
            }
        } else {
            ?>
            <h1 class="mt-2 head-update">Payments and Invoices</h1>
            <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
                <li class="breadcrumb-item active">Payment and Invoices</li>
            </ol>
            <?php
            if (isset($_GET['msg'])) {
                if ($_GET['msg'] == 'add') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Fees Successfully paid
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'success') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> Payment processed successfully
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
                }
                if ($_GET['msg'] === 'error' && isset($_GET['errors'])) {
                    // Decode and display error messages
                    $errors = urldecode($_GET['errors']);
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                    echo '<i class="bi bi-exclamation-circle"></i> ' . htmlspecialchars($errors);
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    echo '</div>';
                } 
                if ($_GET['msg'] == 'delete') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Payment deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'partial') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Partial payment was successful
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'update') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> payment was Updated successfully
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if($_GET['msg'] == 'send'){
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> email sent successfully
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
                }
            }
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <span class="material-symbols-outlined">manage_accounts</span> Payments
                        </div>
                        <div class="col-md-6 d-flex justify-content-end align-items-center">
                            <!-- Search Bar -->
                            <div class="mb-0 me-3">
                                <input type="text" id="searchBar" class="form-control" placeholder="Search Invoices..." onkeyup="searchInvoices()">
                            </div>
                            <!-- Button to trigger modal -->
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#payFeesModal">
                                Pay Fees
                            </button>
                        </div>
                        <!-- Modal -->
                        <?php
                                               
                        include('DB_connect.php');

                        $student_query = "SELECT parent_id FROM students WHERE student_id = ?";
                        $stmt = $connect->prepare($student_query);
                        $stmt->bind_param('i',  $userId);
                        $stmt->execute();
                        $stmt->bind_result($parent_id);
                        $stmt->fetch();
                        $stmt->close();

                        ?>

                        <div class="modal fade" id="payFeesModal" tabindex="-1" aria-labelledby="payFeesModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="payFeesModalLabel">Choose Payment Method</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                     <div class="mb-3">
                                        <!-- Form to add fees -->
                                        <form action="deposit.php" method="post">
                                            <label for="payment_method">Payment Method:</label>
                                                <select id="payment_method" name="payment_method" onchange="togglePaymentFields()">
                                                <option value="cash" class="text-muted">Choose Payment</option>
                                                    <option value="cash">Cash</option>
                                                    <option value="mpesa">M-Pesa</option>
                                                    <option value="credit_card">Credit Card</option>
                                                </select><br><br>
                                            <div class="modal-footer">
                                           
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        
                                            </div>
                                            
                                        </form>  
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="emailReceiptModal" tabindex="-1" aria-labelledby="emailReceiptModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="emailReceiptModalLabel">Send Receipt via Email</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form action="deposit.php" method="post">
                                    <input type="hidden" id="emailReceiptPaymentId" name="payment_id">
                                    <div class="mb-3">
                                        <label for="emailAddress" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="emailAddress" name="email" required>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary">Send Receipt</button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
                <div class="modal fade" id="partialPaymentModal" tabindex="-1" aria-labelledby="partialPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="partialPaymentModalLabel">Make Partial Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Form to make partial payment -->
                <form action="deposit.php" method="post">
                    <div class="mb-3">
                        <label for="paymentId" class="form-label">Payment ID</label>
                        <input type="text" class="form-control" id="paymentId" name="payment_id" required>
                    </div>
                    <label for="payment_method">Payment Method:</label>
                                                <select id="payment_method" name="payment_method" onchange="togglePaymentFields()">
                                                
                                                    <option value="cash">Cash</option>
                                                    <option value="mpesa">M-Pesa</option>
                                                    <option value="credit_card">Credit Card</option>
                                                </select><br><br>
                  <div id="mpesaField" class="mb-3" style="display: none;">
                    <label for="mpesaNumber" class="form-label">M-Pesa Number</label>
                    <input type="text" class="form-control" id="mpesaNumber" name="mpesa_number">
                </div>
                    <div class="mb-3">
                        <label for="partialAmount" class="form-label">Amount to Pay</label>
                        <input type="number" class="form-control" id="partialAmount" name="partial_amount" min="1" required>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success" name="pay_remain">Submit Payment</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
                <div class="table-responsive">
                    <div class="card-body">
                        <!-- Invoices Section -->
                        <div class="invoices-section mt-1 mb-2">
                            <h3>My Payments</h3>
                            <?php
            // Re-open connection
            include('DB_connect.php');

            // Check if student_id is set in the session
            if (isset($_SESSION['id'])) {
                $student_id = $_SESSION['id'];
                $items_per_page = 4; // Number of items per page
                $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $offset = ($current_page - 1) * $items_per_page;

                if ($connect instanceof mysqli) {
                    // Prepare SQL query with filter for logged-in student
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
                        deposit.payment_date,
                        deposit.due_date
                      FROM 
                        deposit
                      JOIN 
                        students ON deposit.student_id = students.student_id
                      WHERE 
                        students.student_id = ?
                      LIMIT ? OFFSET ?;
                    ");
                    if ($stmt) {
                        $stmt->bind_param("iii", $student_id, $items_per_page, $offset);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            echo '<table class="table table-bordered" id="payTable">';
                            echo '<thead><tr><th>Student Name</th><th>Payment Date</th><th>Due Date</th><th>Total Amount</th><th>Paid Amount</th><th>Remaining Amount</th><th>Payment Method</th><th>Payment Number</th><th>Status</th><th>Action</th></tr></thead><tbody>';
                            while ($row = $result->fetch_assoc()) {
                                
                                echo '<tr>';
                               
                                echo '<td>' . htmlspecialchars($row['student_name']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['payment_date']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['due_date']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['total_amount']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['paid_amount']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['remaining_amount']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['payment_method']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['payment_number']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['status']) . '</td>';
                                echo '<td>
                                    <div class="btn-group" role="group" aria-label="Actions">
                                        <button type="button" class="btn btn-success btn-sm me-3 btn-custom-radius" data-bs-target="#viewReceiptModal" data-id="' . htmlspecialchars($row['payment_id']) . '">
                                             Invoice
                                        </button>
                                         <a href="download_receipt.php?payment_id=' . htmlspecialchars($row['payment_id']) .'" class="btn btn-primary btn-sm me-3 btn-custom-radius" target="_blank">
                                            Download Receipt
                                        </a>
                                         <button type="button" class="btn btn-success btn-sm btn-custom-radius" data-bs-toggle="modal" data-bs-target="#emailReceiptModal" data-id="' . htmlspecialchars($row['payment_id']) . '">
                                            Email Receipt
                                        </button>
                                         <button type="button" class="btn btn-warning btn-sm ms-3 btn-custom-radius" data-bs-toggle="modal" data-bs-target="#partialPaymentModal" data-id="' . htmlspecialchars($row['payment_id']) . '">
                                            Pay Remaining
                                        </button>
                                    </div>
                                </td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';

                            // Get total number of records
                            $stmt_total = $connect->prepare("
                              SELECT COUNT(*) AS total_records
                              FROM deposit
                              JOIN students ON deposit.student_id = students.student_id
                              WHERE students.student_id = ?
                            ");
                            if ($stmt_total) {
                                $stmt_total->bind_param("i", $student_id);
                                $stmt_total->execute();
                                $result_total = $stmt_total->get_result();
                                $total_records = $result_total->fetch_assoc()['total_records'];
                                $total_pages = ceil($total_records / $items_per_page);

                                echo '<nav aria-label="Page navigation"><ul class="pagination">';
                                
                                // Previous button
                                if ($current_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . ($current_page - 1) . '">Previous</a></li>';
                                } else {
                                    echo '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
                                }
                                
                                // Page numbers
                                for ($page = 1; $page <= $total_pages; $page++) {
                                    if ($page == $current_page) {
                                        echo '<li class="page-item active"><span class="page-link">' . $page . '</span></li>';
                                    } else {
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $page . '">' . $page . '</a></li>';
                                    }
                                }
                                
                                // Next button
                                if ($current_page < $total_pages) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . ($current_page + 1) . '">Next</a></li>';
                                } else {
                                    echo '<li class="page-item disabled"><span class="page-link">Next</span></li>';
                                }
                                
                                echo '</ul></nav>';
                            } else {
                                echo '<p>Failed to get total number of records.</p>';
                            }
                        } else {
                            echo '<p>No invoices found.</p>';
                        }
                        $stmt->close();
                    } else {
                        echo '<p>Failed to prepare SQL statement for invoices.</p>';
                    }
                } else {
                    echo '<p>Database connection is not valid.</p>';
                }

                $connect->close();
            } else {
                echo '<p>Student ID not found in session.</p>';
            }
            ?>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="main-footer px-3">
                            <div class="pull-right hidden-xs"> 
                            <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>
                            </div>
                        </footer>
            </div>
        </main>
        <?php
        }
        ?>
        <!--main-->
    </div>
    <?php endif; ?>
    <?php if ($displayRole === 'Admin'): ?>
        <main class="main-container">
            <?php 
            if (isset($_GET['action'])) {
                if ($_GET['action'] == 'add') {
                    ?>
                    <h1 class="mt-2 head-update">Payments and Invoices</h1>
                    <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                        <li class="breadcrumb-item"><a href="dashboard.php"  style="color: #f8f9fa;">Dashboard</a></li>
                        <li class="breadcrumb-item active"><a href="payment.php"  style="color: #f8f9fa;">Payments and Invoices</a></li>
                        <li class="breadcrumb-item active">View Invoices</li>
                    </ol>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header">
                                <?php if (isset($errors) && !empty($errors)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php foreach ($errors as $error): ?>
                                            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?><br>
                                        <?php endforeach; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($message) && !empty($message) && empty($errors)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                     <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php endif; ?>
                                    <span class="material-symbols-outlined">manage_accounts</span>Admin Invoice Management
                                </div>
                                <div class="card-body">
                                    <!-- Admin-specific functionalities -->
                                    <div class="table-responsive">
                                        <div class="card-body">
                                            <div class="invoices-section mt-1 mb-2">
                                                <h3>All Invoices</h3>
                                                <?php
                                                include('DB_connect.php');
                                                if ($connect instanceof mysqli) {
                                                    $items_per_page = 10; // Admin might see more items per page
                                                    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                                    $offset = ($current_page - 1) * $items_per_page;

                                                    $stmt = $connect->prepare("
                                                      SELECT 
                                                        deposit.payment_id AS payment_id,
                                                        students.student_name AS student_name,
                                                        deposit.total_amount, 
                                                        deposit.paid_amount, 
                                                        deposit.remaining_amount,
                                                        deposit.payment_method,
                                                        students.student_contact_number1 AS payment_number,
                                                        deposit.status,
                                                        deposit.payment_date,
                                                        deposit.due_date
                                                      FROM 
                                                        deposit
                                                      JOIN 
                                                        students ON deposit.student_id = students.student_id
                                                      LIMIT ? OFFSET ?;
                                                    ");
                                                    if ($stmt) {
                                                        $stmt->bind_param("ii", $items_per_page, $offset);
                                                        $stmt->execute();
                                                        $result = $stmt->get_result();

                                                        if ($result->num_rows > 0) {
                                                            echo '<table class="table table-bordered" id="payTable">';
                                                            echo '<thead><tr><th>ID</th><th>Student Name</th><th>Payment Date</th><th>Due Date</th><th>Total Amount</th><th>Paid Amount</th><th>Remaining Amount</th><th>Payment Method</th><th>Payment Number</th><th>Status</th><th>Action</th></tr></thead><tbody>';
                                                            while ($row = $result->fetch_assoc()) {
                                                                $remainingAmount = $row['total_amount'] - $row['paid_amount'];
                                                                echo '<tr>';
                                                                echo '<td>' . htmlspecialchars($row['payment_id']) . '</td>';
                                                                echo '<td>' . htmlspecialchars($row['student_name']) . '</td>';
                                                                echo '<td>' . htmlspecialchars($row['payment_date']) . '</td>';
                                                                echo '<td>' . htmlspecialchars($row['due_date']) . '</td>';
                                                                echo '<td>' . htmlspecialchars($row['total_amount']) . '</td>';
                                                                echo '<td>' . htmlspecialchars($row['paid_amount']) . '</td>';
                                                                echo '<td>' . htmlspecialchars($row['remaining_amount']) . '</td>';
                                                                echo '<td>' . htmlspecialchars($row['payment_method']) . '</td>';
                                                                echo '<td>' . htmlspecialchars($row['payment_number']) . '</td>';
                                                                echo '<td>' . htmlspecialchars($row['status']) . '</td>';
                                                                echo '<td>
                                                                    <div class="btn-group" role="group" aria-label="Actions">
                                                                        <button type="button" class="btn btn-info btn-sm" data-bs-target="#viewReceiptModal" data-id="' . htmlspecialchars($row['payment_id']) . '">
                                                                             View Details
                                                                        </button>
                                                                         
                                                                    </div>
                                                                </td>';
                                                                echo '</tr>';
                                                            }
                                                            echo '</tbody></table>';

                                                            $stmt_total = $connect->prepare("
                                                              SELECT COUNT(*) AS total_records
                                                              FROM deposit
                                                              JOIN students ON deposit.student_id = students.student_id
                                                            ");
                                                            if ($stmt_total) {
                                                                $stmt_total->execute();
                                                                $result_total = $stmt_total->get_result();
                                                                $total_records = $result_total->fetch_assoc()['total_records'];
                                                                $total_pages = ceil($total_records / $items_per_page);

                                                                echo '<nav aria-label="Page navigation"><ul class="pagination">';
                                                                
                                                                if ($current_page > 1) {
                                                                    echo '<li class="page-item"><a class="page-link" href="?page=' . ($current_page - 1) . '">Previous</a></li>';
                                                                } else {
                                                                    echo '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
                                                                }
                                                                
                                                                for ($page = 1; $page <= $total_pages; $page++) {
                                                                    if ($page == $current_page) {
                                                                        echo '<li class="page-item active"><span class="page-link">' . $page . '</span></li>';
                                                                    } else {
                                                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $page . '">' . $page . '</a></li>';
                                                                    }
                                                                }
                                                                
                                                                if ($current_page < $total_pages) {
                                                                    echo '<li class="page-item"><a class="page-link" href="?page=' . ($current_page + 1) . '">Next</a></li>';
                                                                } else {
                                                                    echo '<li class="page-item disabled"><span class="page-link">Next</span></li>';
                                                                }
                                                                
                                                                echo '</ul></nav>';
                                                            } else {
                                                                echo '<p>Failed to get total number of records.</p>';
                                                            }
                                                        } else {
                                                            echo '<p>No invoices found.</p>';
                                                        }
                                                        $stmt->close();
                                                    } else {
                                                        echo '<p>Failed to prepare SQL statement for invoices.</p>';
                                                    }
                                                } else {
                                                    echo '<p>Database connection is not valid.</p>';
                                                }

                                                $connect->close();
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                }else if ($_GET['action'] == 'edit') {
                    if (isset($_GET['id'])) {
                        ?>
                        
                        <h1 class="mt-2 head-update">Edit Fees</h1>
                        <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                            <li class="breadcrumb-item"><a href="dashboard.php"  style="color: #f8f9fa;" >Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="Deposit.php"   style="color: #f8f9fa;">All Payments</a></li>
                            <li class="breadcrumb-item active">Edit Fees</li>
                        </ol>
                        <?php 
                            $payment_id = intval($_GET['id']);

                            // Fetch the student's data from the database
                            $query = "SELECT d.*, s.student_name
                                        FROM deposit d
                                        JOIN students s ON d.student_id = s.student_id
                                        WHERE d.payment_id = ?";
                            $stmt = $connect->prepare($query);
                            $stmt->bind_param('i', $payment_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                    
                            if ($result->num_rows > 0) {
                                $student = $result->fetch_assoc();
                            } else {
                                $errors[] = 'No record found for the provided ID.';
                            }
                    
                            $stmt->close();
                        ?>
                       <div class="card">
    <div class="card-header">
        <div class="row">
            <div class="card-header">
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($message) && !empty($message) && empty($errors)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <span class="material-symbols-outlined">manage_accounts</span>Edit Fees
            </div>
            <div class="card-body">
                <form action="deposit.php" method="post" onsubmit="return validateForm();">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($student['payment_id']); ?>">

                    <div class="mb-3">
                        <label for="studentName" class="form-label">Student Name</label>
                        <input type="text" class="form-control" id="studentName" name="student_name" value="<?php echo htmlspecialchars($student['student_name']); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="totalAmount" class="form-label">Total Amount</label>
                        <input type="number" class="form-control" id="totalAmount" name="total_amount" value="<?php echo htmlspecialchars($student['total_amount']); ?>" min="0" required>
                    </div>

                    <div class="mb-3">
                        <label for="paidAmount" class="form-label">Paid Amount</label>
                        <input type="number" class="form-control" id="paidAmount" name="paid_amount" value="<?php echo htmlspecialchars($student['paid_amount']); ?>" min="0" required oninput="updateRemaining()">
                    </div>

                    <div class="mb-3">
                        <label for="remainingAmount" class="form-label">Remaining Amount</label>
                        <input type="number" class="form-control" id="remainingAmount" name="remaining_amount" value="<?php echo htmlspecialchars($student['remaining_amount']); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="paymentMethod" class="form-label">Payment Method</label>
                        <select class="form-select" id="paymentMethod" name="payment_method" required>
                            <option value="cash" <?php echo $student['payment_method'] == 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="mpesa" <?php echo $student['payment_method'] == 'mpesa' ? 'selected' : ''; ?>>M-Pesa</option>
                            <option value="credit_card" <?php echo $student['payment_method'] == 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="Unpaid" <?php echo $student['status'] == 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                            <option value="Pending" <?php echo $student['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Paid" <?php echo $student['status'] == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <button type="submit" class="btn btn-success" name="update_payment">Update Payment</button>
                        <a href="Deposit.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
                        <footer class="main-footer px-3">
                            <div class="pull-right hidden-xs"> 
                            <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>
                            </div>
                        </footer>
                        <?php
                    }
                }
            } else {
                ?>
                <h1 class="mt-2 head-update">Payments and Invoices</h1>
                <ol class="breadcrumb mb-4 small"  style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                    <li class="breadcrumb-item"><a href="dashboard.php"  style="color: #f8f9fa;">Dashboard</a></li>
                    <li class="breadcrumb-item active">Payments and Invoices</li>
                </ol>
                <?php
            if (isset($_GET['msg'])) {
                if ($_GET['msg'] == 'add') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Fees Successfully paid
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'success') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> Payment processed successfully
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
                }
                if ($_GET['msg'] === 'error' && isset($_GET['errors'])) {
                    // Decode and display error messages
                    $errors = urldecode($_GET['errors']);
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                    echo '<i class="bi bi-exclamation-circle"></i> ' . htmlspecialchars($errors);
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    echo '</div>';
                } 
                if ($_GET['msg'] == 'delete') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Payment deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'partial') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Partial payment was successful
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'update') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> payment was Updated successfully
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
            }
                ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <span class="material-symbols-outlined">manage_accounts</span> Payments
                            </div>
                            <div class="col-md-6 d-flex justify-content-end align-items-center">
                                <!-- Search Bar -->
                                <form id="searchForm" method="GET" class="d-inline">
    <div class="mb-0 me-3">
        <input type="text" id="searchBar" name="query" class="form-control" placeholder="Search Payments..." onkeyup="searchInvoices()">
    </div>
</form>
                                <!-- Button to trigger modal -->
                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#payFeesModal">
                                    Add Fees
                                </button>
                            </div>
                            <!-- Modal -->
                             <?php include('DB_connect.php');

if ($displayRole === 'Admin') {
    // Prepare query to select all parent IDs
    $student_query = "SELECT parent_id FROM parents";
    $stmt = $connect->prepare($student_query);
    // Execute the query
    $stmt->execute();
    // Bind result
    $stmt->bind_result($parent_id);
 
    // Close the statement
    $stmt->close();
   
}
?>
                            <div class="modal fade" id="payFeesModal" tabindex="-1" aria-labelledby="payFeesModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="payFeesModalLabel">Add New Fees</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <!-- Form to add fees -->
                                            <form action="deposit.php" method="post">
                                            <div class="mb-3">
                        <label for="parentSelect" class="form-label">Select Parent</label>
                        <select id="parentSelect" class="form-select" name="parent_id" required>
                        <?php
        include('DB_connect.php');

        // Prepare the query to fetch all parents
        $parent_query = "SELECT parent_id, parent_name FROM parents ORDER BY parent_name";
        $parent_stmt = $connect->prepare($parent_query);

        // Check if the statement was prepared correctly
        if ($parent_stmt) {
            $parent_stmt->execute();
            $parent_result = $parent_stmt->get_result();

            // Check if there are any parents
            if ($parent_result->num_rows > 0) {
                // Iterate over each parent
                while ($parentRow = $parent_result->fetch_assoc()) {
                    // Display each parent as an option
                    echo '<option value="' . htmlspecialchars($parentRow['parent_id']) . '">' . htmlspecialchars($parentRow['parent_name']) . '</option>';
                }
            } else {
                echo '<option value="">No parents found</option>';
            }

            $parent_stmt->close();
        } else {
            echo '<option value="">Error preparing statement.</option>';
        }

        $connect->close();
        ?>
                        </select>
                    </div>     
                                            <div class="mb-3">
    <label for="studentSelect" class="form-label">Select Student</label>
    <select id="studentSelect" class="form-select" name="student_name" required>
        <option value="">Select a parent first</option>
    </select>
</div>
<div class="mb-3">
    <label for="studentNumber" class="form-label">Admission Number</label>
    <select id="studentNumber" class="form-select" name="student_number" required>
        <option value="">Select a student</option>
    </select>
</div>
<div class="mb-3">
    <label for="courseSelect" class="form-label">Select Courses:</label>
    <select id="courseSelect" class="form-select" name="course_id">
        <!-- Options will be populated dynamically -->
    </select>
</div>

<div class="mb-3">
    <label for="totalAmount" class="form-label">Total Payable Amount:</label>
    <input type="number" class="form-control" id="totalAmount" name="total_amount" min="50" required readonly>
</div>
                                                <div class="mb-3">
                                                    <label for="paidAmount" class="form-label">Paid Amount:</label>
                                                    <input type="number" class="form-control" id="paidAmount" name="paid_amount" min="0" required>
                                                </div>
                                                <div class="mb-3">
        <label for="remainingAmount" class="form-label">Remaining Amount</label>
        <input type="number" class="form-control" id="remainingAmount" name="remaining_amount" readonly>
    </div>
                                            <div class="mb-3">
                                                <label for="dueDate" class="form-label">Due Date</label>
                                                <input type="text" class="form-control" id="dueDate" name="due_date" value="<?php echo htmlspecialchars($due_date); ?>" readonly>
                                            </div>
                                            <label for="payment_method">Payment Method:</label>
                                                <select id="payment_method" name="payment_method" required>
                                                <option value="cash" class="text-muted">Choose Payment</option>
                                                    <option value="cash">Cash</option>
                                                    <option value="mpesa">M-Pesa</option>
                                                    <option value="credit_card">Credit Card</option>
                                                </select><br><br>
                                                <div class="mb-3">
                                                    <label for="status" class="form-label">Status</label>
                                                    <div class="custom-select-wrapper">
                                                        <div class="custom-select">
                                                            <div class="selected">Select Status</div>
                                                            <div class="dropdown-menu">
                                                                <div class="dropdown-item btn-danger" data-value="Unpaid">Unpaid</div>
                                                                <div class="dropdown-item btn-warning" data-value="Pending">Pending</div>
                                                                <div class="dropdown-item btn-success" data-value="Paid">Paid</div>
                                                            </div>
                                                            <select id="status" name="status">
                                                                <option value="Unpaid">Unpaid</option>
                                                                <option value="Pending">Pending</option>
                                                                <option value="Paid">Paid</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <button type="submit" class="btn btn-success" name="submit_payment2">Submit Payment</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal fade" id="emailReceiptModal" tabindex="-1" aria-labelledby="emailReceiptModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="emailReceiptModalLabel">Send Receipt via Email</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form action="deposit.php" method="post">
                                    <input type="hidden" id="emailReceiptPaymentId" name="payment_id">
                                    <div class="mb-3">
                                        <label for="emailAddress" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="emailAddress" name="email" required>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-primary">Send Receipt</button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <div class="card-body">
                                <div class="invoices-section mt-1 mb-2">
                                    <h3>All Payments</h3>
                                    <?php
include('DB_connect.php');

if ($connect instanceof mysqli) {
    $items_per_page = 10; // Items per page
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($current_page - 1) * $items_per_page;

    // Handle search query
    $searchQuery = '';
    if (isset($_GET['query'])) {
        $searchQuery = $_GET['query'];
    }

    // Prepare the SQL statement based on whether a search query is present
    if ($searchQuery !== '') {
        $sql = "SELECT p.*, s.student_name FROM deposit p
                JOIN students s ON p.student_id = s.student_id
                WHERE 
                    p.payment_number LIKE ? OR 
                    s.student_name LIKE ? OR 
                    p.payment_method LIKE ? OR 
                    p.status LIKE ? OR 
                    p.payment_date LIKE ? OR 
                    p.due_date LIKE ?
                LIMIT ? OFFSET ?";
        
        $stmt = $connect->prepare($sql);
        $likeQuery = "%" . $searchQuery . "%";
        $stmt->bind_param("ssssssii", $likeQuery, $likeQuery, $likeQuery, $likeQuery, $likeQuery, $likeQuery, $items_per_page, $offset);
    } else {
        $sql = "SELECT p.*, s.student_name FROM deposit p
                JOIN students s ON p.student_id = s.student_id
                LIMIT ? OFFSET ?";
        
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("ii", $items_per_page, $offset);
    }

    // Execute the statement
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();

        echo '<table class="table table-bordered" id="payTable">';
        echo '<thead><tr><th>ID</th><th>Student Name</th><th>Payment Date</th><th>Total Amount</th><th>Paid Amount</th><th>Remaining Amount</th><th>Payment Method</th><th>Payment Number</th><th>Status</th><th>Action</th></tr></thead>';
        echo '<tbody>';
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['payment_id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['student_name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['payment_date']) . '</td>';
                echo '<td>' . htmlspecialchars($row['total_amount']) . '</td>';
                echo '<td>' . htmlspecialchars($row['paid_amount']) . '</td>';
                echo '<td>' . htmlspecialchars($row['remaining_amount']) . '</td>';
                echo '<td>' . htmlspecialchars($row['payment_method']) . '</td>';
                echo '<td>' . htmlspecialchars($row['payment_number']) . '</td>';
                echo '<td>' . htmlspecialchars($row['status']) . '</td>';
                echo '<td>
                    <div class="btn-group" role="group" aria-label="Actions">
                        <button type="button" class="btn btn-primary btn-sm me-3" data-bs-target="#viewReceiptModal" data-id="' . htmlspecialchars($row['payment_id']) . '">Invoice</button>
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#emailReceiptModal" data-id="' . htmlspecialchars($row['payment_id']) . '">Email Receipt</button>
                        <a href="deposit.php?action=edit&id=' . htmlspecialchars($row['payment_id']) . '" class="btn btn-warning btn-sm ms-2 me-2"><i class="bi bi-pencil"></i>Edit</a>
                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deletePaymentModal' . htmlspecialchars($row['payment_id']) . '"><i class="bi bi-trash"></i>Delete</button>
                        <div class="modal fade" id="deletePaymentModal' . htmlspecialchars($row['payment_id']) . '" tabindex="-1" aria-labelledby="deletePaymentModalLabel' . htmlspecialchars($row['payment_id']) . '" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="deletePaymentModalLabel' . htmlspecialchars($row['payment_id']) . '">Confirm Delete</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">Are you sure you want to delete this payment?</div>
                                    <div class="modal-footer">
                                        <form action="deposit.php?action=delete" method="post">
                                            <input type="hidden" name="payment_id" value="' . htmlspecialchars($row['payment_id']) . '">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="delete_deposit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="10">No results found.</td></tr>'; // Adjust column span as needed
        }

        echo '</tbody></table>';

        // Pagination Logic
        $stmt_total = $connect->prepare("SELECT COUNT(*) AS total_records FROM deposit");
        if ($stmt_total) {
            $stmt_total->execute();
            $result_total = $stmt_total->get_result();
            $total_records = $result_total->fetch_assoc()['total_records'];
            $total_pages = ceil($total_records / $items_per_page);

            echo '<nav aria-label="Page navigation"><ul class="pagination">';
            
            if ($current_page > 1) {
                echo '<li class="page-item"><a class="page-link" href="?page=' . ($current_page - 1) . '&query=' . urlencode($searchQuery) . '">Previous</a></li>';
            } else {
                echo '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
            }
            
            for ($page = 1; $page <= $total_pages; $page++) {
                if ($page == $current_page) {
                    echo '<li class="page-item active"><span class="page-link">' . $page . '</span></li>';
                } else {
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $page . '&query=' . urlencode($searchQuery) . '">' . $page . '</a></li>';
                }
            }
            
            if ($current_page < $total_pages) {
                echo '<li class="page-item"><a class="page-link" href="?page=' . ($current_page + 1) . '&query=' . urlencode($searchQuery) . '">Next</a></li>';
            } else {
                echo '<li class="page-item disabled"><span class="page-link">Next</span></li>';
            }
            
            echo '</ul></nav>';
        } else {
            echo '<p>Failed to get total number of records.</p>';
        }

        $stmt->close();
    } else {
        echo '<p>Failed to prepare SQL statement for payments.</p>';
    }
} else {
    echo '<p>Database connection is not valid.</p>';
}

$connect->close();
?>
</div>
  </div>
    </div>
       </div>
       </div>

                <footer class="main-footer px-3">
                            <div class="pull-right hidden-xs"> 
                            <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>
                            </div>
                     </footer>
                <?php
            }
            ?>
           
        </main>
    <?php endif; ?>

    <script>
   function searchInvoices() {
    // Submit the form
    document.getElementById("searchForm").submit();
}
 function togglePaymentP(){

    const paymentMethod = document.getElementById('payment_method').value;
    const mpesaField = document.getElementById('mpesaField');

    if (paymentMethod === 'mpesa') {
        mpesaField.style.display = 'block';
    } else {
        mpesaField.style.display = 'none';
    }
}
     function togglePay(){
            const paymentMethod = document.getElementById('payment_method').value;
           
            if (paymentMethod === 'mpesa') {
                // Redirect to M-Pesa integration page
                window.location.href = 'daraja.php'; // Replace with your actual URL
            } else if (paymentMethod === 'credit_card') {
                // Redirect to Credit Card payment page
                window.location.href = 'creditpayment.php'; // Replace with your actual Credit Card URL
            }else if (paymentMethod === 'cash'){
                window.location.href = 'cash.php';
            }
        }
        function fillFormData() {
    // Simulating fetched data from QR code (replace this with actual parsing if needed)
    const qrData = document.getElementById('scannerInput').value.split(';');
    const dataObj = {};

    qrData.forEach(item => {
        const [key, value] = item.split(':');
        if (key && value) {
            dataObj[key.trim()] = value.trim();
        }
    });

    // Autofill the form fields
    document.getElementById('studentSelect').value = dataObj['student_id'] || '';
    document.getElementById('admissionNumber').value = dataObj['admission_number'] || '';
    document.getElementById('parentSelect').value = dataObj['parent_id'] || '';
    document.getElementById('courseSelect').value = dataObj['course_id'] || '';
    document.getElementById('totalAmount').value = dataObj['course_fee'] || '';

    // Optionally, update the dropdowns if needed (you can populate student and parent dropdowns based on IDs)
    // Example:
    // populateStudentDropdown(dataObj['student_id']);
    // populateParentDropdown(dataObj['parent_id']);

}


          function updateRemaining() {
        const totalAmount = parseFloat(document.getElementById('totalAmount').value) || 0;
        const paidAmount = parseFloat(document.getElementById('paidAmount').value) || 0;
        const remainingAmount = Math.max(0, totalAmount - paidAmount);
        document.getElementById('remainingAmount').value = remainingAmount;
    }

    function validateForm() {
        const totalAmount = parseFloat(document.getElementById('totalAmount').value) || 0;
        const paidAmount = parseFloat(document.getElementById('paidAmount').value) || 0;

        if (paidAmount > totalAmount) {
            alert('Paid amount cannot exceed the total amount.');
            return false; // Prevent form submission
        }

        return true; // Allow form submission
    }
        document.addEventListener('DOMContentLoaded', function() {
    var partialPaymentModal = document.getElementById('partialPaymentModal');
    partialPaymentModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; // Button that triggered the modal
        var paymentId = button.getAttribute('data-id'); // Extract payment ID from data-id attribute
        // var partialAmount = button.getAttribute('data-amount');  Extract payment amount from data-amount attribute
        
        // Update the form fields with the payment ID and amount
        var paymentIdInput = partialPaymentModal.querySelector('#paymentId');
        var partialAmountInput = partialPaymentModal.querySelector('#partialAmount');
        
        paymentIdInput.value = paymentId;
        partialAmountInput.value = partialAmount;
    });
});
      document.addEventListener('DOMContentLoaded', function() {
    // Event handler for when a parent is selected
    document.getElementById('parentSelect').addEventListener('change', function() {
        var parentId = this.value;
        var studentSelect = document.getElementById('studentSelect');

        if (parentId) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'validate2.php?parent_id=' + encodeURIComponent(parentId), true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    studentSelect.innerHTML = xhr.responseText;
                } else {
                    studentSelect.innerHTML = '<option value="">Error fetching students</option>';
                }
            };
            xhr.send();
        } else {
            studentSelect.innerHTML = '<option value="">Select a parent first</option>';
        }
    });

    // Event handler for when a student is selected
    document.getElementById('studentSelect').addEventListener('change', function() {
        var studentId = this.value;
        var studentNumberSelect = document.getElementById('studentNumber');
        var courseSelect = document.getElementById('courseSelect');
        var mpesaNumberInput = document.getElementById('mpesaNumber');
        var totalAmountInput = document.getElementById('totalAmount');

        if (studentId) {
            // Fetch student numbers
            var xhrStudentNumber = new XMLHttpRequest();
            xhrStudentNumber.open('GET', 'validate3.php?student_id=' + encodeURIComponent(studentId), true);
            xhrStudentNumber.onload = function() {
                if (xhrStudentNumber.status === 200) {
                    studentNumberSelect.innerHTML = xhrStudentNumber.responseText;
                } else {
                    studentNumberSelect.innerHTML = '<option value="">Error fetching admission number</option>';
                }
            };
            xhrStudentNumber.send();

            // Fetch courses and fees
            var xhrCourseDetails = new XMLHttpRequest();
            xhrCourseDetails.open('GET', 'fetchCourseDetails.php?student_id=' + encodeURIComponent(studentId), true);
            xhrCourseDetails.onload = function() {
                if (xhrCourseDetails.status === 200) {
                    courseSelect.innerHTML = xhrCourseDetails.responseText;
                    calculateTotalAmount();
                } else {
                    courseSelect.innerHTML = '<option value="">Error fetching courses</option>';
                }
            };
            xhrCourseDetails.send();

            // Fetch payment numbers
            var xhrPaymentNumber = new XMLHttpRequest();
            xhrPaymentNumber.open('GET', 'fetchPaymentNumber.php?student_id=' + encodeURIComponent(studentId), true);
            xhrPaymentNumber.onload = function() {
                if (xhrPaymentNumber.status === 200) {
                    mpesaNumberInput.value = xhrPaymentNumber.responseText.trim();
                } else {
                    mpesaNumberInput.value = '';
                }
            };
            xhrPaymentNumber.send();
        } else {
            studentNumberSelect.innerHTML = '<option value="">Select a student</option>';
            courseSelect.innerHTML = '';
            mpesaNumberInput.value = '';
            totalAmountInput.value = '';
        }
    });

    // Event handler for when courses are selected
    document.getElementById('courseSelect').addEventListener('change', function() {
        calculateTotalAmount();
    });

    // Function to calculate the total amount based on selected courses
    function calculateTotalAmount() {
        var courseSelect = document.getElementById('courseSelect');
        var totalAmountInput = document.getElementById('totalAmount');
        var totalAmount = 0;

        Array.from(courseSelect.selectedOptions).forEach(function(option) {
            var fee = parseFloat(option.dataset.fee) || 0;
            totalAmount += fee;
        });

        totalAmountInput.value = totalAmount.toFixed(2);
    }
});


        document.addEventListener('DOMContentLoaded', function () {
    var payFeesModal = new bootstrap.Modal(document.getElementById('payFeesModal'));
    var courseSelect = document.getElementById('courseSelect');
    var totalAmountInput = document.getElementById('totalAmount');
    var paidAmountInput = document.getElementById('paidAmount');
    var remainingAmountInput = document.getElementById('remainingAmount');

    // Function to update the amounts
    function updateAmounts() {
        var selectedOption = courseSelect.options[courseSelect.selectedIndex];
        var courseFee = parseFloat(selectedOption.getAttribute('data-fee')) || 0;
        var paidAmount = parseFloat(paidAmountInput.value) || 0;
        var remainingAmount = courseFee - paidAmount;

        // Set remaining amount and total amount
        remainingAmountInput.value = remainingAmount > 0 ? remainingAmount : 0;
        totalAmountInput.value = courseFee; // Always show the full course fee as total amount
    }

    // Event listener for when the modal is shown
    payFeesModal._element.addEventListener('show.bs.modal', function () {
        updateAmounts(); // Update amounts on modal open
    });

    // Event listener for course selection change
    courseSelect.addEventListener('change', updateAmounts);

    // Recalculate amounts when paid amount changes
    paidAmountInput.addEventListener('input', updateAmounts);

    // Initial call to set the total amount and remaining amount
    updateAmounts();
});

function searchInvoices() {
    var input, filter, table, rows, cells, i, j, match;
    input = document.getElementById("searchBar");
    filter = input.value.toLowerCase();
    table = document.getElementById("payTable");
    rows = table.getElementsByTagName("tr");

    for (i = 1; i < rows.length; i++) { // Start from 1 to skip the header row
        cells = rows[i].getElementsByTagName("td");
        match = false;

        for (j = 0; j < cells.length; j++) {
            if (cells[j]) {
                if (cells[j].innerHTML.toLowerCase().indexOf(filter) > -1) {
                    match = true;
                }
            }
        }

        rows[i].style.display = match ? "" : "none";
    }
}

function togglePaymentFields() {
        const paymentMethod = document.getElementById('payment_method').value;
       
        if (paymentMethod === 'mpesa') {
            // Redirect to M-Pesa integration page
            window.location.href = 'daraja.php'; // Replace with your actual URL
        }  else if (paymentMethod === 'credit_card') {
        // Redirect to Credit Card payment page
        window.location.href = 'creditpayment.php'; // Replace with your actual Credit Card URL
        } else if( paymentMethod === 'cash'){
            window.location.href = 'cash.php'; 

        }
    }
        document.addEventListener('DOMContentLoaded', function () {
    var emailButtons = document.querySelectorAll('[data-bs-target="#emailReceiptModal"]');
    emailButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var paymentId = button.getAttribute('data-id');
            document.getElementById('emailReceiptPaymentId').value = paymentId;
        });
    });
});
      document.addEventListener('DOMContentLoaded', function() {
    var viewReceiptButtons = document.querySelectorAll('[data-bs-target="#viewReceiptModal"]');

    viewReceiptButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var paymentId = this.getAttribute('data-id');
            fetchReceiptDetails(paymentId);
        });
    });

    function fetchReceiptDetails(paymentId) {
        // Construct the URL for fetching the PDF
        var pdfUrl = 'get_receipt.php?id=' + paymentId;
        
        // Open the PDF in a new tab
        window.open(pdfUrl, '_blank');
    }
});
                  document.addEventListener('DOMContentLoaded', function() {
    const totalAmountField = document.getElementById('totalAmount');
    const paidAmountField = document.getElementById('paidAmount');
    const statusField = document.getElementById('status');
    
    function updateStatus() {
        const totalAmount = parseFloat(totalAmountField.value) || 0;
        const paidAmount = parseFloat(paidAmountField.value) || 0;

        if (paidAmount >= totalAmount) {
            statusField.value = 'Paid';
        } else if (paidAmount > 0) {
            statusField.value = 'Pending';
        } else {
            statusField.value = 'Unpaid';
        }
    }

    totalAmountField.addEventListener('input', updateStatus);
    paidAmountField.addEventListener('input', updateStatus);
});

        let sideBarOpen = false;
        let menuIcon = document.querySelector('.sidebar');

        function openSideBar() {
            if (!sideBarOpen) {
                menuIcon.classList.add('sidebar-responsive');
                sideBarOpen = true;
            }
        }

        function closeSideBar() {
            if (sideBarOpen) {
                menuIcon.classList.remove('sidebar-responsive');
                sideBarOpen = false;
            }
        }

        function confirmLogout(event) {
            event.preventDefault();
            if (confirm("Are you sure you want to log out?")) {
                window.location.href = event.target.href;
            }
        }
    </script>
</body>
</html>