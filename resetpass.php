<?php
session_start();
include('DB_connect.php');

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $errors = array();
    // Validate token and get user details
    $query = "SELECT email, user_type FROM password_resets WHERE token = ? AND expiry > NOW()";
    $stmt = $connect->prepare($query);
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->store_result();
    
    
        // Form to reset password
        if (isset($_POST['update_password'])) {
            $new_password = trim($_POST['password']);
            $confirm_password = trim($_POST['confirm_password']);
            
            if (empty($new_password) || $new_password !== $confirm_password) {
                $errors[] = "Passwords do not match or are empty";
            } elseif (strlen($new_password) < 6) {
                $errors[] = "Password must be at least 6 characters long";
            } else {
                // Fetch email and user type
                $stmt = $connect->prepare("SELECT email, user_type FROM password_resets WHERE token = ?");
                $stmt->bind_param('s', $token);
                $stmt->execute();
                $stmt->bind_result($email, $user_type);
                $stmt->fetch();
                $stmt->close();
        
                // Hash the new password
                //$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
                // Update password based on user type
                if ($user_type === 'Student') {
                    $query = "UPDATE students SET student_password = ? WHERE student_email = ?";
                } elseif ($user_type === 'Parent') {
                    $query = "UPDATE parents SET parent_password = ? WHERE parent_email = ?";
                } else {
                    $query = "UPDATE admin_users SET admin_password = ? WHERE admin_email = ?";
                }
        
                $stmt = $connect->prepare($query);
                if (!$stmt) {
                    echo "Prepare failed: (" . $connect->errno . ") " . $connect->error;
                }
                $stmt->bind_param('ss', $new_password, $email);
                if (!$stmt->execute()) {
                    echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
                }
        
                // Delete the reset token
                $query = "DELETE FROM password_resets WHERE token = ?";
                $stmt = $connect->prepare($query);
                if (!$stmt) {
                    echo "Prepare failed: (" . $connect->errno . ") " . $connect->error;
                }
                $stmt->bind_param('s', $token);
                if (!$stmt->execute()) {
                    echo "Execute failed: (" . $stmt->errno . ") " . $stmt->error;
                }
        
                $message = "Password updated successfully";
            }
        }
    }
   
    $stmt->close();
    $connect->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="icon" href="logo2.png">
    <link rel="stylesheet" href="css/cont1.css">
    <!-- Bootstrap and Font Awesome links -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body>
<div class="imageBox">
        <img src="login-logo.png" alt="Fee management">
 </div>
    <section class="contact-us">
        <div class="form-container">
        <?php
    if (!empty($errors)) {
        echo '<div class="error-messages">';
        foreach ($errors as $error) {
            echo "<p>$error</p>";
        }
        echo '</div>';
    }

    if (!empty($message)) {
        echo "<p class='success-message'>$message</p>";
    }
    ?>

    <form action="resetpass.php?token=<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="form-contact">
           
                <h3 class="mb-2 mt-2">Reset Password</h3>
                <div class="mb-4 mt-4">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" id="password" required>
                </div>
                <div class="mb-4 mt-4">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" id="confirm_password" required>
                </div>
                <div class="mb-4">
                    <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </div>
    </section>
</body>
</html>