<?php
session_start();
include('DB_connect.php');

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Access sensitive information from environment variables
$smtp=$_ENV['SMTP'];
$mails=$_ENV['MAIL'];
$pass=$_ENV['PASS'];
$pass2=$_ENV['PASS2'];
$port=$_ENV['PORT'];
 
if(isset($_SESSION["id"]) && isset($_SESSION["role"])){
    // Store user role for easier access
    
    $userRole = $_SESSION["role"];
    $adminType = $_SESSION["admin_type"] ?? '';
    $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
 $text_size = isset($_COOKIE['text_size']) ? $_COOKIE['text_size'] : 'medium';
    // Map roles to display names
    $roleNames = [
        "1" => "Admin",
        "2" => "Student",
        "default" => "Parent"
    ];
    // Determine role name based on the session
    $displayRole = $roleNames[$userRole] ?? $roleNames["default"];
}
$message=$error="";

require('C:/xampp/htdocs/sms/PHPMailer-master/src/PHPMailer.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/Exception.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/SMTP.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_POST["add_user"])) {
  $formdata = array();
  $errors = array();
  // Validate username
  if (empty($_POST["admin_name"])) {
      $errors[] = "Username is Required";
  } else {
      $formdata["admin_name"] = trim($_POST["admin_name"]);
  }
  if (empty($_POST["admin_password"])) {
    $errors[] = "Password is Required";
} else {
    $formdata["admin_password"] = trim($_POST["admin_password"]);
    
    // Additional validation (e.g., minimum length)
    if (strlen( $formdata["admin_password"]) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    } 
}

  // Validate email
  if (empty($_POST['admin_email'])) {
      $errors[] = "Email is Required";
  } else {
      if (!filter_var($_POST["admin_email"], FILTER_VALIDATE_EMAIL)) {
          $errors[] = "Invalid Email Address";
      } else {
          $formdata["admin_email"] = trim($_POST["admin_email"]);
      }
    }
    if (empty($errors)) {
        // Check if email already exists
        $query = 'SELECT COUNT(*) FROM admin_users WHERE admin_email = ?';
        $stmt = $connect->prepare($query);
        $stmt->bind_param('s', $formdata["admin_email"]);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $errors[] = "Email Address Already Exists.";
        } else {
            // Prepare data for insertion
            $data = array(
                'admin_name' => $formdata['admin_name'],
                'admin_email' => $formdata['admin_email'],
                'admin_password' => $formdata['admin_password'],
                'admin_type' => 'user',
                'admin_status' => 'Enable'
            );

            // Insert new user into the database
            $query = '
            INSERT INTO admin_users
            (admin_name, admin_email, admin_password, admin_type, admin_status, admin_added_on)
            VALUES (?, ?, ?, ?, ?, NOW())
            ';
            $stmt = $connect->prepare($query);
            $stmt->bind_param('sssss', $data['admin_name'], $data['admin_email'], $data['admin_password'], $data['admin_type'], $data['admin_status']);
            if ($stmt->execute()) {
                $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = $_ENV['SMTP'];  // Use 'smtp.gmail.com'
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['MAIL'];  // Use your Gmail address
                $mail->Password   = $_ENV['PASS']; // Use your app password (if 2FA is enabled)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $_ENV['PORT'];  // Use port 587
            

                // Recipients
                $mail->setFrom('eugenekuria66@gmail.com', 'Eugene Kuria');
                $mail->addAddress($data['admin_email'], $data['admin_name']);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Your Account Details';
                $mail->Body    = "Dear Admin,<br><br>Your account has been created.<br>Your password is: {$data['admin_password']}<br><br>Thank you.";

                $mail->send();
            } catch (Exception $e) {
                if (strpos($mail->ErrorInfo, 'address couldn\'t be found') === false) {
                    // Log or handle only if it's not a specific type of error
                    error_log("Mail Error: {$mail->ErrorInfo}");
                }
            }
                header('Location: studententry.php?message=add');
                exit();
            } else {
                $errors[] = "Failed to add user.";
            }
            $stmt->close();
        }
    }
}
$form_submitted = isset($_POST['edit_user']);
if($form_submitted){
$formdata = array();
  $errors = array();
  // Validate username
  if (empty($_POST["admin_name"])) {
      $errors[] = "Username is Required";
  } else {
      $formdata["admin_name"] = trim($_POST["admin_name"]);
  }
  if (empty($_POST["admin_password"])) {
    $errors[] = "Password is Required";
} else {
    $formdata["admin_password"] = trim($_POST["admin_password"]);
    
    // Additional validation (e.g., minimum length)
    if (strlen( $formdata["admin_password"]) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    } 
}

  // Validate email
  if (empty($_POST['admin_email'])) {
      $errors[] = "Email is Required";
  } else {
      if (!filter_var($_POST["admin_email"], FILTER_VALIDATE_EMAIL)) {
          $errors[] = "Invalid Email Address";
      } else {
          $formdata["admin_email"] = trim($_POST["admin_email"]);
      }
    }
}
if (empty($errors)) {
    // Check if email already exists
    $query = 'SELECT COUNT(*), admin_password FROM admin_users WHERE admin_email = ? AND admin_id != ?';
    $stmt = $connect->prepare($query);
    $stmt->bind_param('si', $formdata["admin_email"], $_POST['admin_id']);
    $stmt->execute();
    $stmt->bind_result($count, $original_password);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        $errors[] = "Email Address Already Exists.";
    } else {

            
        $query = 'UPDATE admin_users SET admin_name = ?, admin_email = ?, admin_password = ? WHERE admin_id = ?';
        $stmt = $connect->prepare($query);
        $stmt->bind_param('sssi', $formdata['admin_name'], $formdata['admin_email'], $formdata['admin_password'], $_POST['admin_id']);


        $form_password = trim($formdata['admin_password']);
        $original_password = trim($original_password); 

        $is_password_changed = ($form_password !== $original_password);
        if ($stmt->execute()) {
            // Send email only if password has changed
            if ($is_password_changed) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = $_ENV['SMTP'];  // Use 'smtp.gmail.com'
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $_ENV['MAIL'];  // Use your Gmail address
                    $mail->Password   = $_ENV['PASS']; // Use your app password (if 2FA is enabled)
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = $_ENV['PORT'];  // Use port 587
                

                    // Recipients
                    $mail->setFrom('eugenekuria66@gmail.com', 'Eugene Kuria');
                    $mail->addAddress($formdata['admin_email'], $formdata['admin_name']);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Your Account Details';
                    $mail->Body    = "Dear Admin,<br><br>Your account has been updated.<br>Your new password is: {$formdata['admin_password']}<br><br>Thank you.";

                    $mail->send();
                } catch (Exception $e) {
                    if (strpos($mail->ErrorInfo, 'address couldn\'t be found') === false) {
                        // Log or handle only if it's not a specific type of error
                        error_log("Mail Error: {$mail->ErrorInfo}");
                    }
                }
                header('Location: studententry.php?message=edit');
                exit();
            } 
        $stmt->close();
    }
  }
}
if (isset($_GET['action'], $_GET['id'], $_GET['status']) && $_GET['action'] == 'delete') {
    $admin_id = $_GET['id'];
    $status = $_GET['status'];

    // Check if the status is valid
    if ($status !== 'Enable' && $status !== 'Disable') {
        // Handle invalid status
        exit('Invalid status');
    }

    // Prepare the SQL query
    $query = 'UPDATE admin_users 
              SET admin_status = ?
              WHERE admin_id = ?';
    $stmt = $connect->prepare($query);
    $stmt->bind_param('si', $status, $admin_id);

    if ($stmt->execute()) {
        // Redirect to the same page to reflect changes
        header('Location: studententry.php?message=' . urlencode($status));
        exit();
    } else {
        // Handle SQL execution failure
        echo "Failed to update status.";
    }
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
    $stmt->bind_param("i", $userId); 
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc(); // Fetch associative array
    } else {
        $admin = null; // Handle user not found case
    }
    $stmt->close();
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

 ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="icon" href="logo2.png">
    <link id="theme-style" rel="stylesheet" href="css/<?= htmlspecialchars($theme); ?>.css">
    <link id="text-size-style" rel="stylesheet" href="css/<?= htmlspecialchars($text_size); ?>.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables.net-bs5/2.1.3/dataTables.bootstrap5.css" integrity="sha512-d0jyKpM/KPRn5Ys8GmjfSZSN6BWmCwmPiGZJjiRAycvLY5pBoYeewUi2+u6zMyW0D/XwQIBHGk2coVM+SWgllw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js" integrity="sha512-BkpSL20WETFylMrcirBahHfSnY++H2O1W+UnEEO4yNIl+jI2+zowyoGJpbtk6bx97fBXf++WJHSSK2MV4ghPcg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables.net-bs5/2.1.3/dataTables.bootstrap5.min.js" integrity="sha512-Cwi0jz7fz7mrX990DlJ1+rmiH/D9/rjfOoEex8C9qrPRDDqwMPdWV7pJFKzhM10gAAPlufZcWhfMuPN699Ej0w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
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
            <form class="d-flex ms-auto ">
              <div class="input-group my-lg-0">
                <input 
                type="text"
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
              <h1 class="mt-2 head-update">User Management</h1>
              <?php
                // Check if the 'action' parameter is set
                if (isset($_GET['action'])) {
                    if ($_GET['action'] == 'add') {
                        ?>
                        <ol class="breadcrumb mb-4 small"  style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                            <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
                            <li class="breadcrumb-item active"><a href="studententry.php" style="color: #f8f9fa;">User Management</a></li>
                            <li class="breadcrumb-item active">Add User</li>
                        </ol>
                        <?php if (!empty($errors)): ?>
                                            <div class="alert alert-danger">
                                                <ul>
                                                    <?php foreach ($errors as $error): ?>
                                                        <li><?= htmlspecialchars($error) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        
                                        <span class="material-symbols-outlined text-bold">manage_accounts</span> Add New User
                                    </div>
                                    <div class="card-body">
                                        <form method="post" class="side-form">
                                            <div class="mb-3">
                                                <label>UserName<span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="admin_name" placeholder="UserName"  />
                                            </div>
                                            <div class="mb-3">
                                                <label>Email Address<span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" name="admin_email" placeholder="Email Address"  />
                                            </div>
                                            <div class="mb-3">
                                                <label>Password<span class="text-danger">*</span></label>
                                                <input type="password" class="form-control" name="admin_password" placeholder="Password"  />
                                            </div>
                                            <input type="submit" name="add_user" class="btn btn-success" value="Add">
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    } elseif (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] == 'edit') {
                        $admin_id = intval($_GET['id']); // Ensure admin_id is an integer

                        // Prepare and execute the query
                        $stmt = $connect->prepare("SELECT * FROM admin_users WHERE admin_id = ?");
                        $stmt->bind_param('i', $admin_id);
                        $stmt->execute();
                        
                        // Get the result
                        $result = $stmt->get_result();

                        // Fetch the user data
                        if ($user_row = $result->fetch_assoc()) {
                            ?>
                            <ol class="breadcrumb mb-4 small"  style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                                <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
                                <li class="breadcrumb-item active"><a href="studententry.php" style="color: #f8f9fa;">User Management</a></li>
                                <li class="breadcrumb-item active">Edit User</li>
                            </ol>
                            <?php if (isset($errors) && !empty($errors)): ?>
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                <?php foreach ($errors as $error): ?>
                                                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?><br>
                                                <?php endforeach; ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>;
                                        <?php endif; ?>

                                        <?php if ($form_submitted && isset($message) && !empty($message) && empty($errors)): ?>
                                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                             <?php  echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>;
                                        <?php endif; ?>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                       
                                            <span class="material-symbols-outlined text-bold">manage_accounts</span> Edit User
                                        </div>
                                        <div class="card-body">
                                            <form method="post" class="side-form">
                                                <div class="mb-3">
                                                    <label>UserName<span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" name="admin_name" placeholder="UserName" value="<?php echo htmlspecialchars($user_row['admin_name']); ?>"  />
                                                </div>
                                                <div class="mb-3">
                                                    <label>Email Address<span class="text-danger">*</span></label>
                                                    <input type="email" class="form-control" name="admin_email" placeholder="Email Address" value="<?php echo htmlspecialchars($user_row['admin_email']); ?>"  />
                                                </div>
                                                <div class="mb-3">
                                                    <label>Password<span class="text-danger">*</span></label>
                                                    <input type="password" class="form-control" name="admin_password" placeholder="Password" value="<?php echo htmlspecialchars($user_row['admin_password']); ?>" />
                                                </div>
                                                <input type="hidden" name="admin_id" value="<?php echo htmlspecialchars($user_row['admin_id']); ?>">
                                                <input type="submit" name="edit_user" class="btn btn-success" value="Edit">
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        } else {
                            echo "<p>User not found.</p>";
                        }

                        // Close the statement
                        $stmt->close();
                    }
                }
                ?>
               
        </div>
            
              <footer class="main-footer px-3">
                <div class="pull-right hidden-xs">
                  
                <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>
              </footer>
        </main>
         <!--main-->
    <!--custom tag-->
    <script>
let sideBarOpen= false;
let menuIcon= document.querySelector('.sidebar');

function openSideBar(){
    if(!sideBarOpen){
        menuIcon.classList.add('sidebar-responsive')
        sideBarOpen= true;
    }
}
function closeSideBar(){
    if(sideBarOpen){
        menuIcon.classList.remove('sidebar-responsive')
        sideBarOpen= false;
    }
}
function confirmLogout(event) {
    event.preventDefault(); 
    if (confirm("Are you sure you want to log out?")) {
        window.location.href = event.target.href; 
    }
}
$(document).ready(function() {
    $('#user_table').DataTable({
        "processing": true, // Show processing indicator
        "serverSide": true, // Enable server-side processing
        "ajax": {
            "url": "action.php", // URL to your server-side processing script
            "type": "POST", // HTTP method
            "data": function(d) {
                d.action = 'fetch_user'; // Add additional parameters if needed
            }
        },
        "pageLength": 10, // Number of entries per page
        "lengthMenu": [10, 15, 50, 100], // Options for entries per page
        "order": [], // Initial ordering of columns (empty array means no default ordering)
        "language": {
            "search": "Search:", // Custom text for search box
            "paginate": {
                "previous": "Previous", // Custom text for pagination buttons
                "next": "Next",
                "first": "First",
                "last": "Last"
            },
            "lengthMenu": "Show _MENU_ entries", // Custom text for length menu
            "info": "Showing _START_ to _END_ of _TOTAL_ entries" // Custom text for info display
        },
        "responsive": true // Ensure the table is responsive
    });
});
    </script>
</body>
</html>

<?php 

?>
