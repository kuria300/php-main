<?php
session_start();
 include('DB_connect.php');

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
// Handle form submission for adding fees
if (isset($_POST['add_fees'])) {
    // Initialize an array to hold error messages
    $error = array();

    // Retrieve form data and clean it
    $student_id = '';
    $issue_date = '';
    $total_amount = '';
    $paid_amount = '';
    $status = '';
    $due_date ='';
    
    // Retrieve and clean form data
    if (isset($_POST['student_id'])) {
        $student_id = trim($_POST['student_id']);
    }
    
    if (isset($_POST['issue_date'])) {
        $issue_date = trim($_POST['issue_date']);
    }
    if (isset($_POST['due_date'])) {
        $due_date = trim($_POST['due_date']);
    }
    
    if (isset($_POST['total_amount'])) {
        $total_amount = trim($_POST['total_amount']);
    }
    
    if (isset($_POST['paid_amount'])) {
        $paid_amount = trim($_POST['paid_amount']);
    }
    
   
    // Validate form data
    if (empty($issue_date)) {
        $error[] = 'Issue date is required.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $issue_date);
        if (!$date || $date->format('Y-m-d') !== $issue_date) {
            $error[] = 'Invalid issue date format. Please use YYYY-MM-DD.';
        }
    }
    if (empty($due_date)) {
        $error[] = 'Due date is required.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $due_date);
        if (!$date || $date->format('Y-m-d') !== $due_date) {
            $error[] = 'Invalid due date format. Please use YYYY-MM-DD.';
        }
    }
    if (empty($total_amount) || !is_numeric($total_amount) || $total_amount <= 0) {
        $error[] = 'Total amount must be a number greater than 0.';
    }
    
    if (empty($paid_amount) || !is_numeric($paid_amount) || $paid_amount < 0) {
        $error[] = 'Paid amount must be a number and cannot be negative.';
    }
    
    if ($paid_amount >= $total_amount) {
        $status = 'Paid';
    } elseif ($paid_amount > 0) {
        $status = 'Pending';
    } else {
        $status = 'Unpaid';
    }
    // If there are no validation errors, proceed with database insertion
    if (empty($error)) {
        if ($connect instanceof mysqli) {
            $stmt = $connect->prepare("
                INSERT INTO invoices (student_id, issue_date, due_date, total_amount, paid_amount, status) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            if ($stmt) {
                // Bind parameters and execute statement
                $stmt->bind_param('issdds', $student_id, $issue_date, $due_date, $total_amount, $paid_amount, $status);

                if ($stmt->execute()) {
                    // Redirect to payment.php with a success message
                    header('Location: payment.php?msg=add');
                    exit();
                } else {
                    $error[] = 'Failed to add the invoice. Please try again.';
                }
                $stmt->close();
            } else {
                $error[] = 'Database error. Please try again later.';
            }
        } else {
            $error[] = 'Database connection error.';
        }
        $connect->close();
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_POST['delete_payment'])) {
    // Retrieve and validate the payment ID from POST data
    $payment_id = isset($_POST['payment_id']) ? $_POST['payment_id'] : '';

    // Validate the payment ID
    if (!is_numeric($payment_id) || $payment_id <= 0) {
        echo "<div class='alert alert-danger'>Invalid payment ID.</div>";
        exit();
    }

    // Prepare SQL statement to delete the payment record
    $stmt = $connect->prepare("DELETE FROM deposit WHERE payment_id = ?");
    $stmt->bind_param("i", $payment_id);

    // Execute the statement
    if ($stmt->execute()) {
        // Redirect or provide success feedback
        header('Location: payment.php?msg=delete');
        exit();
    } else {
        // Handle SQL execution error
        echo "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
    }

    // Close the statement
    $stmt->close();
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
    <title>Payment History</title>
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
        <main class="main-container">
    <div class="container-fluid mt-2 px-4">
    <?php 
        if (isset($_GET['action'])) {
            if ($_GET['action'] == 'add') {
                ?>
                <h1 class="mt-2 head-update">Payments and Invoices</h1>
                <ol class="breadcrumb mb-4 small">
                    <li class="breadcrumb-item"><a href="dashboard.php">Student Dashboard</a></li>
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
                    <ol class="breadcrumb mb-4 small">
                        <li class="breadcrumb-item"><a href="dashboard.php">Student Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="payment.php">Payment and Invoices</a></li>
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
                            Copyright © 2024-2025 <a href="#">AutoReceipt system</a>. All rights reserved  
                        </div>
                    </footer>
                    <?php
                }
            }
        } else {
            ?>
            <h1 class="mt-2 head-update">Payment Management</h1>
            <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
                <li class="breadcrumb-item active">Payment and Invoices</li>
            </ol>
            <?php
            if (isset($_GET['msg'])) {
                if ($_GET['msg'] == 'add') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> New Fees Successfully Added
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'delete') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Payment Successfully Deleted
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
            }
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <span class="material-symbols-outlined">manage_accounts</span> Invoices
                        </div>
                        <div class="col-md-6 d-flex justify-content-end align-items-center">
                            <!-- Search Bar -->
                            <div class="mb-0 me-3">
                                <input type="text" id="searchBar" class="form-control" placeholder="Search History..." onkeyup="searchInvoices()">
                            </div>
                            <!-- Button to trigger modal -->
                           
                        </div>

                        <!-- Modal -->
                        <div class="modal fade" id="addFeesModal" tabindex="-1" aria-labelledby="addFeesModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="addFeesModalLabel">Add Fees</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <!-- Form to add fees -->
                                        <form action="payment.php" method="post">
                                        <div class="mb-3">
                                                    <label for="studentSelect" class="form-label">Select Student</label>
                                                    <select id="studentSelect" class="form-select" name="student_id" required>
                                                        <?php
                                                        
                                                         include('DB_connect.php');
 
                                                         // Check if session and database connection are valid
                                                         if (isset($_SESSION['id']) && $connect instanceof mysqli) {
                                                             $loggedInStudentId = $_SESSION['id']; // Get the logged-in student's ID
                                                         
                                                             // Prepare the SQL query to fetch the logged-in student's name
                                                             $query = "
                                                                 SELECT students.student_id, students.student_name 
                                                                 FROM students
                                                                 WHERE students.student_id = ?
                                                                 ORDER BY students.student_name
                                                             ";
                                                             $studentStmt = $connect->prepare($query);
                                                             if ($studentStmt) {
                                                                 $studentStmt->bind_param('i', $loggedInStudentId); // Bind the logged-in student's ID
                                                                 $studentStmt->execute();
                                                                 $studentResult = $studentStmt->get_result();
                                                                 if ($studentResult->num_rows > 0) {
                                                                     $studentRow = $studentResult->fetch_assoc();
                                                                     // Output the single student's name as the only option
                                                                     echo '<option value="' . htmlspecialchars($studentRow['student_id']) . '" selected>' 
                                                                     . htmlspecialchars($studentRow['student_name']) . '</option>';
                                                                 } else {
                                                                     // If no student is found, display a placeholder option
                                                                     echo '<option value="">No student found</option>';
                                                                 }
                                                                 $studentStmt->close();
                                                             } else {
                                                                 // Display an error if the statement could not be prepared
                                                                 echo '<option value="">Error fetching student</option>';
                                                             }
                                                         } else {
                                                             // Display an error if the database connection is not valid or session is not set
                                                             echo '<option value="">Database connection error or session not valid</option>';
                                                         }
                                                         $connect->close();
                                                         ?>
                                                    </select>
                                                </div>
                                            <div class="mb-3">
                                                <label for="issueDate" class="form-label">Issue Date</label>
                                                <input type="date" class="form-control" id="issueDate" name="issue_date" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="dueDate" class="form-label">Due Date</label>
                                                <input type="text" class="form-control" id="dueDate" name="due_date" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label for="totalAmount" class="form-label">Total Amount</label>
                                                <input type="number" class="form-control" id="totalAmount" name="total_amount"  min="50" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="paidAmount" class="form-label">Paid Amount</label>
                                                <input type="number" class="form-control" id="paidAmount" name="paid_amount"  min="50" required >
                                            </div>
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
                                                    <select id="status" name="status" disabled>
                                                        <option value="Unpaid">Unpaid</option>
                                                        <option value="Pending">Pending</option>
                                                        <option value="Paid">Paid</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                            <button type="submit" class="btn btn-primary" name="add_fees">Add Fees</button>
                                           
                                        </form>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
    <div class="card-body">
        <!-- Invoices Section -->
        <div class="invoices-section mt-1 mb-2">
            <h3>My Payment History</h3>
            <?php
            // Re-open connection
            include('DB_connect.php');

            // Check if student_id is set in the session
            if (isset($_SESSION['id'])) {
                $student_id = $_SESSION['id'];

                if ($connect instanceof mysqli) {
                    // Define records per page
                    $records_per_page = 10;

                    // Get current page number from query string, default to 1 if not set
                    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $current_page = max($current_page, 1); // Ensure it's at least 1

                    // Calculate the offset for the SQL query
                    $offset = ($current_page - 1) * $records_per_page;

                    // Base query
                    $query = "
                        SELECT 
                            deposit.payment_id, 
                            students.student_name, 
                            students.student_number, 
                            deposit.payment_method, 
                            deposit.total_amount, 
                            deposit.paid_amount, 
                            deposit.status, 
                            deposit.remaining_amount,
                            deposit.payment_number, 
                            deposit.payment_date, 
                            deposit.due_date
                        FROM 
                            deposit
                        JOIN 
                            students ON deposit.student_id = students.student_id
                    ";

                    // Add filter based on user role
                    if ($displayRole === 'Student') {
                        $query .= " WHERE deposit.student_id = ?";
                    } elseif ($displayRole === 'Parent') {
                        $query .= " WHERE students.parent_id = ?";
                    } elseif ($displayRole === 'Admin') {
                        // No additional WHERE clause needed
                    } else {
                        echo 'No Payment Found.';
                        exit;
                    }

                    // Add LIMIT and OFFSET for pagination
                    $query .= " LIMIT ? OFFSET ?";

                    $stmt = $connect->prepare($query);

                    if ($stmt) {
                        // Bind the parameter based on role
                        if ($displayRole === 'Student' || $displayRole === 'Parent') {
                            $stmt->bind_param('iii', $student_id, $records_per_page, $offset);
                        } else {
                            $stmt->bind_param('ii', $records_per_page, $offset);
                        }

                        $stmt->execute();
                        $result = $stmt->get_result();

                        // Count total records
                        $total_query = "
                            SELECT COUNT(*) as total FROM deposit
                            JOIN students ON deposit.student_id = students.student_id
                        ";

                        if ($displayRole === 'Student') {
                            $total_query .= " WHERE deposit.student_id = ?";
                        } elseif ($displayRole === 'Parent') {
                            $total_query .= " WHERE students.parent_id = ?";
                        }

                        $total_stmt = $connect->prepare($total_query);
                        if ($total_stmt) {
                            if ($displayRole === 'Student' || $displayRole === 'Parent') {
                                $total_stmt->bind_param('i', $student_id);
                            }
                            $total_stmt->execute();
                            $total_result = $total_stmt->get_result();
                            $total_row = $total_result->fetch_assoc();
                            $total_records = $total_row['total'];
                            $total_pages = ceil($total_records / $records_per_page);
                        }
                        $total_stmt->close();

                        if ($result->num_rows > 0) {
                            echo '<table class="table table-bordered" id="invoiceTable">';
                            echo '<thead><tr><th>Student Name</th><th>Student Number</th><th>Payment Method</th><th>Total Amount</th><th>Paid Amount</th><th>Remaining Amount</th><th>Status</th><th>Payment Number</th><th>Payment Date</th><th>Due Date</th><th>Action</th></tr></thead><tbody>';
                            while ($row = $result->fetch_assoc()) {
                                //$remainingAmount = $row['total_amount'] - $row['paid_amount'];
                                $payment_id = htmlspecialchars($row['payment_id']);
                                $view_modal_id = "viewPaymentModal{$payment_id}";
                                $view_modal_label = "viewPaymentModalLabel{$payment_id}";
                                $delete_modal_id = "deletePaymentModal{$payment_id}";
                                $delete_modal_label = "deletePaymentModalLabel{$payment_id}";

                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($row['student_name']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['student_number']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['payment_method']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['total_amount']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['paid_amount']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['remaining_amount']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['status']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['payment_number']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['payment_date']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['due_date']) . '</td>';
                                echo '<td>
                                    <div class="btn-group" role="group" aria-label="Actions">
                                        <button type="button" class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#' . $view_modal_id . '">
                                            <i class="bi bi-eye"></i>View
                                        </button>';

                                if ($displayRole === 'Admin') {
                                    echo '<button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#' . $delete_modal_id . '">
                                        <i class="bi bi-trash"></i>Delete
                                    </button>';
                                }

                                echo '<!-- View Payment Modal -->
                                    <div class="modal fade" id="' . $view_modal_id . '" tabindex="-1" aria-labelledby="' . $view_modal_label . '" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="' . $view_modal_label . '">Payment Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Payment ID:</strong> ' . htmlspecialchars($row['payment_id']) . '</p>
                                                    <p><strong>Student Name:</strong> ' . htmlspecialchars($row['student_name']) . '</p>
                                                    <p><strong>Student Number:</strong> ' . htmlspecialchars($row['student_number']) . '</p>
                                                    <p><strong>Payment Method:</strong> ' . htmlspecialchars($row['payment_method']) . '</p>
                                                    <p><strong>Total Amount:</strong> ' . htmlspecialchars($row['total_amount']) . '</p>
                                                    <p><strong>Paid Amount:</strong> ' . htmlspecialchars($row['paid_amount']) . '</p>
                                                    <p><strong>Remaining Amount:</strong> ' . htmlspecialchars($row['remaining_amount']) . '</p>
                                                    <p><strong>Status:</strong> ' . htmlspecialchars($row['status']) . '</p>
                                                    <p><strong>Payment Number:</strong> ' . htmlspecialchars($row['payment_number']) . '</p>
                                                    <p><strong>Payment Date:</strong> ' . htmlspecialchars($row['payment_date']) . '</p>
                                                    <p><strong>Due Date:</strong> ' . htmlspecialchars($row['due_date']) . '</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>';

                                if ($displayRole === 'Admin') {
                                    echo '<!-- Delete Payment Modal -->
                                    <div class="modal fade" id="' . $delete_modal_id . '" tabindex="-1" aria-labelledby="' . $delete_modal_label . '" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="' . $delete_modal_label . '">Confirm Delete</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Are you sure you want to delete this payment record?
                                                </div>
                                                <div class="modal-footer">
                                                    <form action="payment.php?action=delete" method="post">
                                                        <input type="hidden" name="payment_id" value="' . $payment_id . '">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="delete_payment" class="btn btn-danger">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>';
                                }

                                echo '</div>
                                    </td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';

                            // Pagination controls
                            echo '<nav aria-label="Page navigation example">';
                            echo '<ul class="pagination justify-content-start">';
                            
                            // Previous button
                            echo '<li class="page-item ' . ($current_page <= 1 ? 'disabled' : '') . '">';
                            echo '<a class="page-link" href="?page=' . ($current_page - 1) . '" aria-label="Previous">';
                            echo 'Previous';
                            echo '</a>';
                            echo '</li>';
                            
                            // Page numbers
                            for ($i = 1; $i <= $total_pages; $i++) {
                                echo '<li class="page-item ' . ($current_page == $i ? 'active' : '') . '">';
                                echo '<a class="page-link" href="?page=' . $i . '">' . $i . '</a>';
                                echo '</li>';
                            }
                            
                            // Next button
                            echo '<li class="page-item ' . ($current_page >= $total_pages ? 'disabled' : '') . '">';
                            echo '<a class="page-link" href="?page=' . ($current_page + 1) . '" aria-label="Next">';
                            echo 'Next';
                            echo '</a>';
                            echo '</li>';
                            
                            echo '</ul>';
                            echo '</nav>';
                        } else {
                            echo '<p>No payment records found.</p>';
                        }
                        $stmt->close();
                    } else {
                        echo '<p>Failed to prepare SQL statement for payments.</p>';
                    }

                    $connect->close();
                } else {
                    echo '<p>Database connection is not valid.</p>';
                }
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
            <?php
        }
        ?>
            </main>
        <!-- main-->
            <script>
                
                document.addEventListener('DOMContentLoaded', function() {
    const issueDateField = document.getElementById('issueDate');
    const dueDateField = document.getElementById('dueDate');
    
    function updateDueDate() {
        const issueDate = new Date(issueDateField.value);
        if (!isNaN(issueDate.getTime())) {
            const dueDate = new Date(issueDate);
            dueDate.setDate(dueDate.getDate() + 42); // Add 6 weeks (42 days)
            const formattedDueDate = dueDate.toISOString().split('T')[0]; // Format as YYYY-MM-DD
            dueDateField.value = formattedDueDate;
        } else {
            dueDateField.value = '';
        }
    }
    
    issueDateField.addEventListener('change', updateDueDate);
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
document.addEventListener('DOMContentLoaded', function() {
    var viewReceiptButtons = document.querySelectorAll('[data-bs-target="#viewReceiptModal"]');

    viewReceiptButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var invoiceId = this.getAttribute('data-id');
            fetchReceiptDetails(invoiceId);
        });
    });

    function fetchReceiptDetails(invoiceId) {
        // Construct the URL for fetching the PDF
        var pdfUrl = 'get_receipt.php?id=' + invoiceId;
        
        // Open the PDF in a new tab
        window.open(pdfUrl, '_blank');
    }
});
function searchInvoices() {
    var input, filter, table, rows, cells, i, j, match;
    input = document.getElementById("searchBar");
    filter = input.value.toLowerCase();
    table = document.getElementById("invoiceTable");
    rows = table.getElementsByTagName("tr");

    for (i = 1; i < rows.length; i++) { // Start from 1 to skip the header row
        cells = rows[i].getElementsByTagName("td");
        match = false;

        for (j = 0; j < cells.length; j++) {
            if (cells[j]) {
                // Check if the cell contains the filter text
                if (cells[j].innerText.toLowerCase().includes(filter)) {
                    match = true;
                    break; // Stop searching once a match is found
                }
            }
        }

        // Display the row if a match is found, otherwise hide it
        rows[i].style.display = match ? "" : "none";
    }
}
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