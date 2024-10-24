<?php
include('DB_connect.php');

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
        $systemName = str_replace('A', '<span class="purple">A</span>', $systemName);
    } else {
        // Handle case when no settings are found
        $systemName = 'AutoReceipt';  // Fallback value
        error_log("No settings found in the database.");
    }
} else {
    // Handle query failure
    $systemName = 'AutoReceipt';  // Fallback value
    error_log("Query failed: " . $connect->error);
}
$connect->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us</title>
    <link rel="stylesheet" href="css/about.css">
    <link rel="icon" href="logo2.png">
    <!-- Bootstrap links -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
        <a class="navbar-brand fw-bold heading" href="#"><span class="material"><?php echo htmlspecialchars_decode($systemName); ?></span></a>
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
    <!-- Navbar -->
    <div class="imageBox">
        <img src="login-logo.png" alt="Fee management">
     </div>
    <!-- About Us Section -->
    <section class="about-us">
        <div class="container mt-5">
            <h1 class="text-center mb-4">About Auto Receipt</h1>
            <p>Welcome to Auto Receipt, a cutting-edge system designed to streamline and automate receipt management for educational institutions. Our platform offers a comprehensive suite of features to manage, generate, and track receipts with ease.</p>

            <h2 class="mt-4">Our Mission</h2>
            <p>Our mission is to simplify receipt management through innovation and technology. We aim to provide a user-friendly and efficient system that reduces manual effort and minimizes errors, helping organizations stay organized and compliant.</p>

            <h2 class="mt-4">Key Features</h2>
            <ul>
                <li><strong>Automated Receipt Generation:</strong> Quickly generate receipts with customizable templates.</li>
                <li><strong>User-Friendly Interface:</strong> Intuitive design that simplifies receipt creation and management.</li>
                <li><strong>Integration Options:</strong> Seamlessly integrate with existing systems for enhanced functionality.</li>
                <li><strong>Online Payment API:</strong> Facilitate secure online payments, allowing users to pay fees directly through the system.</li> 
                <li><strong>Transparency:</strong> Promote clarity and accountability among parents, students, and administrators by providing real-time access to receipt information and payment statuses.</li>
            </ul>

            <h2 class="mt-4">Our Team</h2>
            <p>Our team comprises dedicated professionals with a passion for technology and a commitment to delivering high-quality solutions. We bring together expertise in software development, user experience design, and customer support to ensure that AutoReceipt meets the highest standards of excellence.</p>

            <h2 class="mt-4">Contact Us</h2>
            <p>If you have any questions or need assistance, feel free to reach out to us through our <a href="contact.php" style="color: white;">Contact Us</a>. We are here to help and look forward to hearing from you!</p>
        </div>
    </section>
    <!-- About Us Section -->

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-2">
        <div class="container">
        <p>&copy; <?php echo date('Y'); ?> <?php echo $systemName; ?></a>. All rights reserved.</p>
        </div>
    </footer>
    <!-- Footer -->
</body>
</html>