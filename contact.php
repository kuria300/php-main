<?php 
include('DB_connect.php');

$errors = [];
$message = '';

// Process form data when submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact'])) {
    // Retrieve and sanitize user input
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $name = htmlspecialchars(trim($_POST['name']));
    $comment = htmlspecialchars(trim($_POST['comment']));

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Validate name (ensure it's not empty and has valid characters)
    if (empty($name) || !preg_match("/^[a-zA-Z\s]+$/", $name)) {
        $errors[] = "Invalid name. Only letters and spaces are allowed.";
    }

    // Validate comment (ensure it's not empty)
    if (empty($comment)) {
        $errors[] = "Comment cannot be empty.";
    }

    // If there are errors, handle them (e.g., display them to the user)
    if (!empty($errors)) {
        // Display errors
        foreach ($errors as $error) {
            echo "<div class='alert alert-danger' role='alert'>$error</div>";
        }
    } else {
        // Prepare the SQL statement
        $query = "INSERT INTO contact_form (name, email, comment) VALUES (?, ?, ?)";
        $stmt = $connect->prepare($query);

        if ($stmt === false) {
            die('Prepare failed: ' . $connect->error);
        }

        // Bind parameters and execute the statement
        $stmt->bind_param('sss', $name, $email, $comment);

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success' role='alert'>Your message has been sent successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger' role='alert'>There was a problem saving your message. Please try again later.</div>";
        }

        // Close the statement
        $stmt->close();
    }

    // Close the database connection
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
    <title>Contact Us</title>
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
    <form action="contact.php" method="POST" class="form-contact">
        <h3 class="mb-2 mt-2">How Can we Help You?</h3>
      <div class="mb-4 mt-4">
       <label for="exampleFormControlInput1" class="form-label">Email address<span>*</span></label>
       <input type="email" class="form-control" name="email" id="exampleFormControlInput1" placeholder="name@example.com">
      </div>
      <div class="mb-4 mt-4">
      <label for="exampleFormControlInput1" class="form-label">Name<span>*</span></label>
      <input type="text" class="form-control" name="name" id="exampleFormControlInput1" placeholder="name">
      </div>
      <div class="mb-4">
         <label for="exampleFormControlTextarea1" class="form-label">Leave Comment<span>*</span></label>
         <textarea class="form-control" name="comment" id="exampleFormControlTextarea1" rows="4"></textarea>
      </div>
      <div class="mb-4">
      <button type="submit mb-4" name="contact" class="btn btn-primary">Send</button>
      </div>
      </form>
      </div>
      </section>
    
<!--form control contact us-->

 <section>
 <div class="credit"><p>&copy; 2023 <?php echo $systemName; ?>. All rights reserved.</p></div>
 </section>

 
</body>
</html>