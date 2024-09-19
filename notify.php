<?php
session_start();
include('DB_connect.php');

include('res/functions.php');
 
if (isset($_SESSION["id"]) && isset($_SESSION["role"])) {
   
    $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
    $text_size = isset($_COOKIE['text_size']) ? $_COOKIE['text_size'] : 'medium';
    $userRole = $_SESSION["role"];
    $userId = $_SESSION['id'];
    $adminType = $_SESSION["admin_type"] ?? '';

    $roleNames = [
        "1" => "Admin",
        "2" => "Student",
        "default" => "Parent"
    ];
    $displayRole = $roleNames[$userRole] ?? $roleNames["default"];
}

require('C:/xampp/htdocs/sms/PHPMailer-master/src/PHPMailer.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/Exception.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/SMTP.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


// Get reminders for display
$query = "SELECT id, payment_id, reminder_type, sent_at FROM reminders";
$result = $connect->query($query);

if ($result) {
    $reminders = [];
    while ($row = $result->fetch_assoc()) {
        $reminders[] = $row;
    }
} else {
    $reminders = [];
}

// Get overdue payments
$currentDate = date('Y-m-d');
$query = "SELECT payment_id, student_id, total_amount,paid_amount, due_date FROM deposit WHERE due_date < ? AND status = 'pending'";
$stmt = $connect->prepare($query);
$stmt->bind_param('s', $currentDate);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Error executing query: " . $connect->error);
}

$overduePayments = [];
while ($row = $result->fetch_assoc()) {
    $overduePayments[] = $row;
}

// Debugging output


function getOverduePayments($connect) {
    $currentDate = date('Y-m-d');
    $query = "SELECT payment_id, student_id, total_amount ,paid_amount, due_date FROM deposit WHERE due_date < ? AND status = 'pending'";
    $stmt = $connect->prepare($query);
    $stmt->bind_param('s', $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $overduePayments = [];
    while ($row = $result->fetch_assoc()) {
        $overduePayments[] = $row;
    }
    return $overduePayments;
    // Debugging output
    echo '<pre>';
    print_r($overduePayments);
    echo '</pre>';

    
}
function getAdminDetails($connect, $userId) {
    $query = "SELECT admin_email FROM admin_users WHERE admin_id = ?";
    $stmt = $connect->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc() ?? null;
}
function getStudents($connect) {
    $query = "SELECT student_id, student_email, student_contact_number1 FROM students";
    $result = $connect->query($query);

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    return $students;
}

function sendReminder($connect, $payment, $student, $smtpDetails) {
    $email = $student['student_email'];
    $amount = $payment['total_amount'];
    $paid_amount = $payment['paid_amount'];
    $dueDate = $payment['due_date'];
    $userId = $_SESSION['id']; 
    $userRole = $_SESSION['role'];
    $message = "Dear " . ($student['student_id'] ? 'Student' : 'Parent') . 
    "You have a payment of Ksh" . number_format($amount, 2) . " with an amount already paid of Ksh" . number_format($paid_amount, 2) . "due on $dueDate." . 
    "Please make sure to complete the payment before the due date to avoid any penalties. Thank you.";

  
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'eugenekuria66@gmail.com';
        $mail->Password   = 'iqxl rubd okpk csun'; // Update with actual password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('eugenekuria66@gmail.com', 'moses'); // Update with actual name
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Payment Reminder';
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);

        $mail->send();

        // Insert reminder into the database
        $stmt = $connect->prepare("INSERT INTO reminders (payment_id, reminder_type, sent_at) VALUES (?, 'email', NOW())");
        $stmt->bind_param('i', $payment['payment_id']);
        $stmt->execute();
        
        // Insert reminder into the notifications database
        $stmt = $connect->prepare("INSERT INTO messages (user_id, user_role, message) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $student['student_id'], $userRole, $message);
        $stmt->execute();
        
        return true; // Indicate success
    } catch (Exception $e) {
        return false; // Indicate failure
    }
}

if (isset($_POST['send_reminders'])) {
    $smtpDetails = getAdminDetails($connect, $userId);
    if (!$smtpDetails) {
        die("SMTP details not found.");
    }

    $students = getStudents($connect);
    $overduePayment = getOverduePayments($connect);

    print_r($overduePayment);
print_r($students);

    $remindersSent = 0;
    $notificationMessages = [];

    if (is_array($overduePayments) && is_array($students)) {
foreach ($overduePayments as $payment) {
    foreach ($students as $student) {
        if ($student['student_id'] == $payment['student_id']) {
            $message = sendReminder($connect, $payment, $student, $smtpDetails);
            if ($message) {
                $notificationMessages[] = [
                    'user_id' => $student['student_id'],
                    'user_role' => $_SESSION['role'],
                    'message' => $message
                ];
                $remindersSent++;
            }
        }
    }
}
    }
   

     // Display number of reminders sent
     echo "<p>$remindersSent reminders sent.</p>";

     // Refresh the reminders list
     $query = "SELECT id, payment_id, reminder_type, sent_at FROM reminders";
     $result = $connect->query($query);
 
     if ($result) {
         $reminders = [];
         while ($row = $result->fetch_assoc()) {
             $reminders[] = $row;
             header('Location: notify.php?msg=edit');
             exit();
         }
     } else {
         $reminders = [];
     }
}
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_POST['delete_reminder'])) {
    // Retrieve and validate the reminder ID from POST data
    $reminder_id = isset($_POST['reminder_id']) ? $_POST['reminder_id'] : '';

    // Validate the reminder ID
    if (!is_numeric($reminder_id) || $reminder_id <= 0) {
        echo "<div class='alert alert-danger'>Invalid reminder ID.</div>";
        exit();
    }

    // Prepare SQL statement to delete the reminder record
    $stmt = $connect->prepare("DELETE FROM reminders WHERE id = ?");
    $stmt->bind_param("i", $reminder_id);

    // Execute the statement
    if ($stmt->execute()) {
        // Redirect or provide success feedback
        header('Location: notify.php?msg=delete');
        exit();
    } else {
        // Handle SQL execution error
        echo "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
    }

    // Close the statement
    $stmt->close();
}
if (isset($_GET['action']) && $_GET['action'] === 'success' && isset($_POST['delete_payment'])) {
    // Retrieve and validate the payment ID from POST data
    $payment_id = isset($_POST['payment_id']) ? $_POST['payment_id'] : '';

    // Validate the payment ID
    if (!is_numeric($payment_id) || $payment_id <= 0) {
        echo "<div class='alert alert-danger'>Invalid payment ID.</div>";
        exit();
    }

    // Prepare SQL statement to delete the payment record
    $stmt = $connect->prepare("DELETE FROM deposit WHERE payment_id = ?");
    if ($stmt === false) {
        echo "<div class='alert alert-danger'>Error preparing the SQL statement: " . $connect->error . "</div>";
        exit();
    }

    $stmt->bind_param("i", $payment_id);

    // Execute the statement
    if ($stmt->execute()) {
        // Redirect or provide success feedback
        header('Location: notify.php?msg=success');
        exit();
    } else {
        // Handle SQL execution error
        echo "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
    }

    // Close the statement
    $stmt->close();
}

$items_per_page = 10; // Number of items per page
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get total records for pagination
$stmt_total = $connect->prepare("
    SELECT COUNT(*) AS total_records
    FROM reminders
");
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_records = $result_total->fetch_assoc()['total_records'];
$total_pages = ceil($total_records / $items_per_page);

// Fetch paginated reminders
$stmt_reminders = $connect->prepare("
    SELECT id, payment_id, reminder_type, sent_at
    FROM reminders
    LIMIT ? OFFSET ?
");
$stmt_reminders->bind_param("ii", $items_per_page, $offset);
$stmt_reminders->execute();
$result_reminders = $stmt_reminders->get_result();

$reminders = [];
while ($row = $result_reminders->fetch_assoc()) {
    $reminders[] = $row;
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

// Close connection
$connect->close();

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="icon" href="logo2.png">
    <link id="theme-style" rel="stylesheet" href="css/<?= htmlspecialchars($theme); ?>.css">
    <link id="text-size-style" rel="stylesheet" href="css/<?= htmlspecialchars($text_size); ?>.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/dataTables.bootstrap.min.css" integrity="sha512-BMbq2It2D3J17/C7aRklzOODG1IQ3+MHw3ifzBHMBwGO/0yUqYmsStgBjI0z5EYlaDEFnvYV7gNYdD3vFLRKsA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables.net-bs5/2.1.3/dataTables.bootstrap5.css" integrity="sha512-d0jyKpM/KPRn5Ys8GmjfSZSN6BWmCwmPiGZJjiRAycvLY5pBoYeewUi2+u6zMyW0D/XwQIBHGk2coVM+SWgllw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.18/css/bootstrap-select.min.css" integrity="sha512-ARJR74swou2y0Q2V9k0GbzQ/5vJ2RBSoCWokg4zkfM29Fb3vZEQyv0iWBMW/yvKgyHSR/7D64pFMmU8nYmbRkg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.css" integrity="sha512-gp+RQIipEa1X7Sq1vYXnuOW96C4704yI1n0YB9T/KqdvqaEgL6nAuTSrKufUX3VBONq/TPuKiXGLVgBKicZ0KA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.0/css/responsive.dataTables.min.css">
    
    <script src="https://cdn.datatables.net/responsive/2.4.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.js" integrity="sha512-+k1pnlgt4F1H8L7t3z95o3/KO+o78INEcXTbnoJQ/F2VqDVhWoaiVml/OEHv9HsVgxUaVW+IbiZPUJQfF/YxZw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js" integrity="sha512-BkpSL20WETFylMrcirBahHfSnY++H2O1W+UnEEO4yNIl+jI2+zowyoGJpbtk6bx97fBXf++WJHSSK2MV4ghPcg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables.net-bs5/2.1.3/dataTables.bootstrap5.min.js" integrity="sha512-Cwi0jz7fz7mrX990DlJ1+rmiH/D9/rjfOoEex8C9qrPRDDqwMPdWV7pJFKzhM10gAAPlufZcWhfMuPN699Ej0w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.min.js" integrity="sha512-mh+AjlD3nxImTUGisMpHXW03gE6F4WdQyvuFRkjecwuWLwD2yCijw4tKA3NsEFpA1C3neiKhGXPSIGSfCYPMlQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/moment@2.30.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.18/js/bootstrap-select.min.js" integrity="sha512-yDlE7vpGDP7o2eftkCiPZ+yuUyEcaBwoJoIhdXv71KZWugFqEphIS3PU60lEkFaz8RxaVsMpSvQxMBaKVwA5xg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!--Boostraplinks-->
    <!--font awesome cdn-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!--font awesome cdn-->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <!--custom css-->
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
            <form class="d-flex ms-auto" method="GET" action="search_result.php">
                <div class="input-group my-lg-0">
                    <input 
                        type="text"
                        name="query"
                        class="form-control"
                        placeholder="Search for..."
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
                            <i class="bi bi-person-fill"></i>
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
        <main class="main-container">
         <div class="container-fluid mt-2 px-4">
         <?php 
                  if(isset($_GET['action'])){
                      if($_GET['action']== 'add'){
                        ?>
                        <h1 class="mt-2 head-update">Add Courses</h1>
                         <ol class="breadcrumb mb-4 small">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active"><a href="course.php">Course Management</a></li>
                            <li class="breadcrumb-item active">Add New Course</li>
                        </ol>
                        <div class="row">
                            <div class="col-md-12">
                              <?php
                              if (!empty($error)) {
                                $errorMessages = '<ul class="list-unstyled">';
                                foreach ($error as $err) {
                                    $errorMessages .= '<li>' . htmlspecialchars($err) . '</li>';
                                }
                                $errorMessages .= '</ul>';
                                
                                echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">'
                                   . $errorMessages .
                                   '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                                   . '</div>';
                            }
                              ?>
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <span class="material-symbols-outlined text-bold">manage_accounts</span> Add New Course
                                    </div>
                                    <div class="card-body">
                                      <form method="post" enctype="multipart/form-data">
                                      <div class="form-group mb-3">
                                            <label for="course_name">Course Name</label>
                                            <input type="text" class="form-control" id="course_name" name="course_name">
                                        </div>
                                        <div class="form-group mb-3">
                                            <label for="course_number">Course Number</label>
                                            <input type="text" class="form-control" id="course_number" name="course_number">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Add Course</button>
                                      </form> 
                                    </div>
                                </div>
                            </div>
                        </div>

                        <footer class="main-footer px-3">
                          <div class="pull-right hidden-xs"> 
                           Copyright © 2024-2025 <a href="#">AutoReceipt system</a>. All rights reserved  
                       </footer>
                       <?php
                      

                      }else if(isset($_GET['action']) && $_GET['action'] == 'edit'){
                        if (isset($_GET['id'])) {
                          include('DB_connect.php');
                            $reminder_id = intval($_GET['id']); 
                
                            // Prepare and execute the query
                            $stmt = $connect->prepare("SELECT * FROM reminders WHERE id = ?");
                            $stmt->bind_param('i',  $reminder_id); 
                             $stmt->execute();
                              
                            // Get the result
                            $result = $stmt->get_result();
                
                            if ($reminder_row = $result->fetch_assoc()) {
                                ?>
                                <h1 class="mt-2 head-update">Edit Reminder</h1>
                                <ol class="breadcrumb mb-4 small">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="notify.php">Reminder</a></li>
                                    <li class="breadcrumb-item active">Edit Reminder</li>
                                </ol>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <span class="material-symbols-outlined">manage_accounts</span> Reminders Edit Form
                                            </div>
                                            <div class="card-body">
                                                <form method="post" enctype="multipart/form-data">
                                                    <div class="form-group mb-3">
                                                        <label for="reminder_type">Reminder Type</label>
                                                        <input type="text" class="form-control" id="reminder_type" name="reminder_type" value="<?php echo htmlspecialchars($reminder_row['reminder_type']); ?>" required>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label for="sent_at">Sent At</label>
                                                        <input type="datetime-local" class="form-control" id="sent_at" name="sent_at" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($reminder_row['sent_at']))); ?>" required>
                                                    </div>
                                                    <input type="hidden" name="reminder_id" value="<?php echo htmlspecialchars($reminder_row['id']); ?>">
                                                    <button type="submit" class="btn btn-primary" name="edit_reminder">Save Changes</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                              <footer class="main-footer px-3">
                                          <div class="pull-right hidden-xs"> 
                                          Copyright © 2024-2025 <a href="#">AutoReceipt system</a>. All rights reserved  
                                        </footer>
                                <?php
                              }
                          }
                      }
                  }else{
                    ?>
                        <h2 class="mt-2 head-update">Reminders</h2>
                        <ol class="breadcrumb mb-4 small">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Notifications</li>
                        </ol>

                        <?php
                        if (isset($_GET['msg'])) {
                            if ($_GET['msg'] == 'add') {
                                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle"></i> New Reminder Successfully Added
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
                            }
                            if ($_GET['msg'] == 'edit') {
                                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="bi bi-check-circle"></i>  Reminders Successfully Sent
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                          </div>';
                            }
                            if ($_GET['msg'] == 'delete') {
                                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="bi bi-check-circle"></i>  Reminders Successfully deleted
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                      </div>';
                            }
                            if ($_GET['msg'] == 'success') {
                                echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="bi bi-check-circle"></i>  Overdue Payment Successfully deleted
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                      </div>';
                            }
                        }
                        ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <div class="row">
                                    <div class="col-md-6">
                                        <span class="material-symbols-outlined">manage_accounts</span> Reminders
                                    </div>
                                    <div class="col-md-6 d-flex justify-content-end">
                                    <form action="notify.php" method="post">
                                        <input type="submit" name="send_reminders" value="Send Reminders" class="btn btn-primary">
                                    </form>
                                    
                                    </div>
                                </div>
                            </div>

                            <div class="card-body">
                                 <!-- Pagination Controls -->
                                <nav aria-label="Page navigation">
                                    <ul class="pagination">
                                        <?php if ($current_page > 1): ?>
                                            <li class="page-item"><a class="page-link" href="?page=<?php echo $current_page - 1; ?>">Previous</a></li>
                                        <?php else: ?>
                                            <li class="page-item disabled"><span class="page-link">Previous</span></li>
                                        <?php endif; ?>

                                        <?php for ($page = 1; $page <= $total_pages; $page++): ?>
                                            <?php if ($page == $current_page): ?>
                                                <li class="page-item active"><span class="page-link"><?php echo $page; ?></span></li>
                                            <?php else: ?>
                                                <li class="page-item"><a class="page-link" href="?page=<?php echo $page; ?>"><?php echo $page; ?></a></li>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <?php if ($current_page < $total_pages): ?>
                                            <li class="page-item"><a class="page-link" href="?page=<?php echo $current_page + 1; ?>">Next</a></li>
                                        <?php else: ?>
                                            <li class="page-item disabled"><span class="page-link">Next</span></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="reminderTable">
                                        <thead>
                                        <tr>
                                            <th>Reminder ID</th>
                                            <th>Payment ID</th>
                                            <th>Reminder Type</th>
                                            <th>Sent At</th>
                                            <th>Action</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        foreach ($reminders as $reminder) {
                                            $reminder_id = htmlspecialchars($reminder['id']);
                                            $payment_id = htmlspecialchars($reminder['payment_id']);
                                            $reminder_type = htmlspecialchars($reminder['reminder_type']);
                                            $sent_at = htmlspecialchars($reminder['sent_at']);

                                            echo '<tr>';
                                            echo '<td>' . $reminder_id . '</td>';
                                            echo '<td>' . $payment_id . '</td>';
                                            echo '<td>' . $reminder_type . '</td>';
                                            echo '<td>' . $sent_at . '</td>';
                                            echo '<td>
                                                <div class="btn-group" role="group" aria-label="Actions">
                                                    <!-- View Button -->
                                                    <button type="button" class="btn btn-success btn-sm me-3" data-bs-toggle="modal" data-bs-target="#viewReminderModal' . $reminder_id . '">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>

                                                    <!-- Edit Button -->
                                                    
                                                    <!-- Delete Button -->
                                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteReminderModal' . $reminder_id . '">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>

                                                    <!-- View Reminder Modal -->
                                                    <div class="modal fade" id="viewReminderModal' . $reminder_id . '" tabindex="-1" aria-labelledby="viewReminderModalLabel' . $reminder_id . '" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="viewReminderModalLabel' . $reminder_id . '">Reminder Details</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p><strong>Reminder ID:</strong> ' . $reminder_id . '</p>
                                                                    <p><strong>Payment ID:</strong> ' . $payment_id . '</p>
                                                                    <p><strong>Reminder Type:</strong> ' . $reminder_type . '</p>
                                                                    <p><strong>Sent At:</strong> ' . $sent_at . '</p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Delete Reminder Modal -->
                                                    <div class="modal fade" id="deleteReminderModal' . $reminder_id . '" tabindex="-1" aria-labelledby="deleteReminderModalLabel' . $reminder_id . '" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteReminderModalLabel' . $reminder_id . '">Confirm Delete</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Are you sure you want to delete this reminder?
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <form action="notify.php?action=delete" method="post">
                                                                        <input type="hidden" name="reminder_id" value="' . $reminder_id . '">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" name="delete_reminder"class="btn btn-danger">Delete</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>';
                                            echo '</tr>';
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Overdue Payments Section -->
                      <div class="col-md-6">
                        <div class="card mt-5">
                            <div class="card-body">
                                <h3>Overdue Payments</h3>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="paymentsTable">
                                        <thead>
                                        <tr>
                                            <th>Payment ID</th>
                                            <th>Student ID</th>
                                            <th>Total Amount</th>
                                            <th>Due Date</th>
                                            <th>Action</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        foreach ($overduePayments as $payment) {
                                            $payment_id = htmlspecialchars($payment['payment_id']);
                                            $student_id = htmlspecialchars($payment['student_id']);
                                            $total_amount = htmlspecialchars($payment['total_amount']);
                                            $due_date = htmlspecialchars($payment['due_date']);

                                            echo '<tr>';
                                            echo '<td>' . $payment_id . '</td>';
                                            echo '<td>' . $student_id . '</td>';
                                            echo '<td>' . $total_amount . '</td>';
                                            echo '<td>' . $due_date . '</td>';
                                            echo '<td>
                                                <div class="btn-group" role="group" aria-label="Actions">
                                                  
                                                    <!-- Delete Button -->
                                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deletePaymentModal' . $payment_id . '">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>

                                                    <!-- Delete Payment Modal -->
                                                    <div class="modal fade" id="deletePaymentModal' . $payment_id . '" tabindex="-1" aria-labelledby="deletePaymentModalLabel' . $payment_id . '" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deletePaymentModalLabel' . $payment_id . '">Confirm Delete</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    Are you sure you want to delete this payment?
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <form action="notify.php?action=success" method="post">
                                                                        <input type="hidden" name="payment_id" value="' . $payment_id . '">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" name="delete_payment" class="btn btn-danger">Delete</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>';
                                            echo '</tr>';
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                  </div>
                  <footer class="main-footer px-3">
                          <div class="pull-right hidden-xs"> 
                          Copyright © 2024-2025 <a href="#">AutoReceipt system</a>. All rights reserved  
                        </footer>
                  </main>
                  <?php 
                            }
          ?>
    
         <!--main-->
    <!--custom tag-->
    <script>
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

