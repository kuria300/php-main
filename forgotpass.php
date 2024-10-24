<?php 
include('DB_connect.php');

require('C:/xampp/htdocs/sms/PHPMailer-master/src/PHPMailer.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/Exception.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/SMTP.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_POST['reset_password'])) {
    $email = trim($_POST['email']);
    $user_type = trim($_POST['user_type']);
    $errors = array();

    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address";
    }

    if (empty($errors)) {
        // Check if email exists for the selected user type
        if ($user_type === 'Student') {
            $query = "SELECT student_id FROM students WHERE student_email = ?";
        } elseif ($user_type === 'Parent') {
            $query = "SELECT parent_id FROM parents WHERE parent_email = ?";
        } else {
            $query = "SELECT admin_id FROM admin_users WHERE admin_email = ?";
        }

        $stmt = $connect->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $errors[] = "Email address not found for the selected user type";
        } else {
            // Generate a unique reset token
            $token = bin2hex(random_bytes(32)); 
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour 

            // Store the token in the database
            $query = "INSERT INTO password_resets (email, token, expiry, user_type) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), expiry = VALUES(expiry)";
            $stmt = $connect->prepare($query);
            $stmt->bind_param('ssss', $email, $token, $expiry, $user_type);
            $stmt->execute();

            // Send reset link to user
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'eugenekuria66@gmail.com';
                $mail->Password   = 'iqxl rubd okpk csun';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('eugenekuria66@gmail.com', 'Eugene Kuria');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body    = "To reset your password, please click the following link: <a href='http://localhost/sms/resetpass.php?token=$token'>Reset Password</a>";

                $mail->send();
                $message = "A password reset link has been sent to your email.";
            } catch (Exception $e) {
                if (strpos($mail->ErrorInfo, 'address couldn\'t be found') === false) {
                    // Log or handle only if it's not a specific type of error
                    error_log("Mail Error: {$mail->ErrorInfo}");
                }
            }
        }

        $stmt->close();
    }
}
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
$connect->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="icon" href="logo2.png">
    <link rel="stylesheet" href="css/cont1.css">
     <!--Booststrap links-->
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
      <!--Booststrap links-->
      <!--font awesome-->
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
     <!--font awesome-->
</head>
<body>
     <!--Navbar-->
     <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold heading" href="#"><span class="material"> <bold class="change-color"><?php echo $systemName; ?></bold></span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
                <div class="navbar-nav">
                    <a class="nav-link" href="start.php">Home</a>
                    <a class="nav-link" href="about.php">About Us</a>
                    <a class="nav-link" href="contact.php">Contact Us</a>
                </div>
            </div>
            <span class="navbar-text me-4"><a class="nav-link" href="Admin.php">Login</a></span>
        </div>
    </nav>
      <!--Navbar-->
      <!--form control contact us-->
      <div class="imageBox">
        <img src="login-logo.png" alt="Fee management">
     </div>

     <section class="contact-us">
     <div class="form-container">
     <form action="forgotpass.php" method="post" class="form-contact">
                <h3 class="mb-2 mt-2">Forgot Password</h3>
                <div class="mb-4 mt-4">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" name="email" class="form-control" id="email" placeholder="name@example.com" required>
                </div>
                <div class="mb-4">
                    <label for="user_type" class="form-label">User Type</label>
                    <select name="user_type" class="form-control" id="user_type" required>
                        <option value="Student">Student</option>
                        <option value="Parent">Parent</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div class="mb-4">
                    <button type="submit" name="reset_password" class="btn btn-primary">Send Reset Link</button>
                </div>
            </form>
      </div>
      </section>
    
<!--form control contact us-->

<footer class="main-footer px-3">
                <div class="pull-right hidden-xs">
                <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>
                </div>
            </footer>
</body>
</html>