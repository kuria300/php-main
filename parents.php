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
$formdata = array();
$errors = array();

require('C:/xampp/htdocs/sms/PHPMailer-master/src/PHPMailer.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/Exception.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/SMTP.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_POST['add_parent'])) {
   

    // Validate form inputs
    $required_fields = [
        'parent_name' => 'Parent Name',
        'parent_email' => 'Email',
        'parent_password' => 'Password',
        'parent_date_of_birth' => 'Date of Birth',
        'parent_address' => 'Address',
        'parent_sex' => 'Gender',
    ];

    foreach ($required_fields as $field => $label) {
        if (empty($_POST[$field])) {
            $errors[] = "$label Required";
        } else {
            $formdata[$field] = trim($_POST[$field]);
        }
    }

    if (!filter_var($_POST["parent_email"], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid Email Address";
    } else {
        $formdata["parent_email"] = trim($_POST["parent_email"]);
    }

    if (!empty($errors)) {
        // Convert errors array to a query string format
        $errorMessages = urlencode(implode('; ', $errors)); // Use semicolon to separate messages
    
        header('Location: parents.php?msg=error&errors=' . $errorMessages); // Redirect with query parameters
        exit();
    }



    // Handle image upload
    if (!empty($_FILES['parent_image']['name'])) {
        $image_name = $_FILES['parent_image']['name'];
        $temporary_name = $_FILES['parent_image']['tmp_name'];
        $image_size = $_FILES['parent_image']['size'];
        $image_extension = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

        $valid_extensions = ['jpeg', 'png', 'jpg'];
        if (in_array($image_extension, $valid_extensions) && $image_size <= 2000000) {
            $new_image_name = time() . '-' . rand() . '.' . $image_extension;
            if (move_uploaded_file($temporary_name, "upload/" . $new_image_name)) {
                $formdata['parent_image'] = $new_image_name;
            } else {
                $errors[] = "Failed to upload image";
            }
        } else {
            $errors[] = $image_size > 2000000 ? "Image Size Exceeds 2MB" : "Invalid Image File";
        }
    }

    
    // Validate status
    $valid_sex = ['male', 'female'];
    if (!in_array($_POST['parent_sex'], $valid_sex)) {
        $errors[] = "Invalid Gender";
    } else {
        $formdata['parent_sex'] = trim($_POST['parent_sex']);
    }

    if (empty($errors)) {
        // Prepare SQL query to insert a new parent
$stmt = $connect->prepare('
INSERT INTO parents (
    parent_name, 
    parent_email, 
    parent_address, 
    parent_password,
    parent_date_of_birth, 
    parent_image, 
    parent_sex,
    parent_added_on
) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
');

// Bind parameters with the correct types
$stmt->bind_param(
'sssssss', // Types: s for string
$formdata['parent_name'],
$formdata['parent_email'],
$formdata['parent_address'],
$formdata['parent_password'],
$formdata['parent_date_of_birth'],
$formdata['parent_image'],
$formdata['parent_sex']
);
    
        if ($stmt->execute()) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';  // Set the SMTP server to send through
                $mail->SMTPAuth   = true;
                $mail->Username   = 'eugenekuria66@gmail.com'; // SMTP username
                $mail->Password   = 'iqxl rubd okpk csun'; // SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Recipients
                $mail->setFrom('eugenekuria66@gmail.com', 'Eugene Kuria');
                $mail->addAddress($formdata['parent_email'], $formdata['parent_name']);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Your Account Details';
                $mail->Body    = "Dear {$formdata['parent_name']},<br><br>Your account has been created.<br>Your password is: {$formdata['parent_password']}<br><br>Thank you.";

                $mail->send();
            } catch (Exception $e) {
                if (strpos($mail->ErrorInfo, 'address couldn\'t be found') === false) {
                    // Log or handle only if it's not a specific type of error
                    error_log("Mail Error: {$mail->ErrorInfo}");
                }
            }
        
            header('Location: parents.php?msg=add');
            exit();
        } else {
            $errors[] = "Failed to insert record";
        }

        $stmt->close();
    }
}

// Handle student update form submission
$form_submitted = isset($_POST['edit_parent']);
if ($form_submitted) {
    

    // Validate form inputs
    $required_fields = [
        'parent_name' => 'Parent Name',
        'parent_email' => 'Email',
        'parent_password' => 'Password',
        'parent_date_of_birth' => 'Date of Birth',
        'parent_address' => 'Address',
        'parent_sex' => 'Gender',
    ];

    foreach ($required_fields as $field => $label) {
        if (empty($_POST[$field])) {
            $errors[] = "$label Required";
        } else {
            $formdata[$field] = trim($_POST[$field]);
        }
    }

    if (!filter_var($_POST["parent_email"], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid Email Address";
    } else {
        $formdata["parent_email"] = trim($_POST["parent_email"]);
    }


    // Handle image upload
    if (!empty($_FILES['parent_image']['name'])) {
        $image_name = $_FILES['parent_image']['name'];
        $temporary_name = $_FILES['parent_image']['tmp_name'];
        $image_size = $_FILES['parent_image']['size'];
        $image_extension = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

        $valid_extensions = ['jpeg', 'png', 'jpg'];
        if (in_array($image_extension, $valid_extensions) && $image_size <= 2000000) {
            $new_image_name = time() . '-' . rand() . '.' . $image_extension;
            if (move_uploaded_file($temporary_name, "upload/" . $new_image_name)) {
                $formdata['parent_image'] = $new_image_name;
            } else {
                $errors[] = "Failed to upload image";
            }
        } else {
            $errors[] = $image_size > 2000000 ? "Image Size Exceeds 2MB" : "Invalid Image File";
        }
    } else {
        // No new image uploaded
        if (isset($_POST['hidden_parent_image'])) {
            $formdata['parent_image'] = $_POST['hidden_parent_image']; // Retain existing image
        } else {
            $formdata['parent_image'] = ''; // Set as empty string if no image is uploaded and no existing image
        }
    }
    
    // Validate status
    $valid_sex = ['male', 'female'];
    if (!in_array($_POST['parent_sex'], $valid_sex)) {
        $errors[] = "Invalid Gender";
    } else {
        $formdata['parent_sex'] = trim($_POST['parent_sex']);
    }

    if (empty($errors)) {
        // Check if email already exists
        $query = 'SELECT COUNT(*) FROM parents WHERE parent_email = ? AND parent_id != ?';
        
        // Prepare the statement
        $stmt = $connect->prepare($query);
        $stmt->bind_param('si', $formdata["parent_email"], $_POST['parent_id']);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $errors[] = "Email Address Already Exists.";
        } else {
            $query = 'SELECT parent_password FROM parents WHERE parent_id = ?';
            $stmt = $connect->prepare($query);
            $stmt->bind_param('i', $_POST['parent_id']);
            $stmt->execute();
            $stmt->bind_result($current_password);
            $stmt->fetch();
            $stmt->close();

            // Update student record
            $query = 'UPDATE parents 
            SET parent_name = ?, 
                parent_email = ?, 
                parent_address = ?, 
                parent_password = ?, 
                parent_date_of_birth = ?, 
                parent_image = ?, 
                parent_sex = ?, 
                parent_updated_on = NOW() 
            WHERE parent_id = ?';
        
        $stmt = $connect->prepare($query);
        $stmt->bind_param(
            'sssssssi',
            $formdata['parent_name'],
            $formdata['parent_email'],
            $formdata['parent_address'],
            $formdata['parent_password'],
            $formdata['parent_date_of_birth'],
            $formdata['parent_image'],
            $formdata['parent_sex'],
            $_POST['parent_id']
        );
        
        if ($stmt->execute()) {
                // Send email only if password has changed
                if ($current_password !== $formdata['parent_password']) {
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';  // Set the SMTP server to send through
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'eugenekuria66@gmail.com'; // SMTP username
                        $mail->Password   = 'iqxl rubd okpk csun'; // SMTP password
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        // Recipients
                        $mail->setFrom('eugenekuria66@gmail.com', 'Eugene Kuria');
                        $mail->addAddress($formdata['parent_email'], $formdata['parent_name']);

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Your Account Details';
                        $mail->Body    = "Dear {$formdata['parent_name']},<br><br>Your account has been updated.<br>Your password is: {$formdata['parent_password']}<br><br>Thank you.";

                        $mail->send();
                    } catch (Exception $e) {
                        if (strpos($mail->ErrorInfo, 'address couldn\'t be found') === false) {
                            // Log or handle only if it's not a specific type of error
                            error_log("Mail Error: {$mail->ErrorInfo}");
                        }
                    }
                }

                header('Location: parents.php?msg=edit');
                exit();
            } else {
                $errors[] = "Failed to update parent: " . $stmt->error;
            }
        $stmt->close();
        }
    }
}
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_POST['delete_parent'])) {
    // Retrieve and validate the parent ID from POST data
    $parent_id = isset($_POST['parent_id']) ? $_POST['parent_id'] : '';

    // Validate the parent ID
    if (!is_numeric($parent_id) || $parent_id <= 0) {
        echo "<div class='alert alert-danger'>Invalid parent ID.</div>";
        exit();
    }

    // Prepare SQL statement to delete the parent record
    $stmt = $connect->prepare("DELETE FROM parents WHERE parent_id = ?");
    $stmt->bind_param("i", $parent_id);

    // Execute the statement
    if ($stmt->execute()) {
        // Redirect or provide success feedback
        header('Location: parents.php?msg=delete');
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Parents</title>
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
        if (isset($_GET['action'])) {
            if ($_GET['action'] == 'add') {
                ?>
                <h1 class="mt-2 head-update">Parent Management</h1>
                <ol class="breadcrumb mb-4 small">
                    <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                    <li class="breadcrumb-item active"><a href="parents.php">Parent Management</a></li>
                    <li class="breadcrumb-item active">Add Parent</li>
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
                    $parent_id = intval($_GET['id']); // Ensure student_id is an integer

                    // Prepare and execute the query
                    $stmt = $connect->prepare("SELECT * FROM parents WHERE parent_id = ?");
                    $stmt->bind_param('i', $parent_id);
                    $stmt->execute();
                    
                    // Get the result
                    $result = $stmt->get_result();

                      if($user_row = $result->fetch_assoc()){
                    ?>
                     <h1 class="mt-2 head-update">Parent Management</h1>
                <ol class="breadcrumb mb-4 small">
                    <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                    <li class="breadcrumb-item active"><a href="parents.php">Parent Management</a></li>
                    <li class="breadcrumb-item active">Edit Parent</li>
                </ol>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header">
                                <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php foreach ($errors as $error): ?>
            <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
                                    <span class="material-symbols-outlined">manage_accounts</span>Parent Edit Form
                                </div>
                                <div class="card-body">
                                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="parentName" class="form-label">Parent Name</label>
                        <input type="text" class="form-control" id="parentName" name="parent_name" value="<?php echo htmlspecialchars($user_row['parent_name']); ?>" >
                    </div>
                    <div class="mb-3">
                        <label for="parentEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="parentEmail" name="parent_email" value="<?php echo htmlspecialchars($user_row['parent_email']); ?>" >
                    </div>
                    <div class="mb-3">
                        <label for="parentAddress" class="form-label">Address</label>
                        <input type="text" class="form-control" id="parentAddress" name="parent_address" value="<?php echo htmlspecialchars($user_row['parent_address']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="parentPassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="parentPassword" name="parent_password" value="<?php echo htmlspecialchars($user_row['parent_password']); ?>" >
                    </div>
                    <div class="mb-3">
                        <label for="parentDateOfBirth" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="parentDateOfBirth" name="parent_date_of_birth"value="<?php echo htmlspecialchars($user_row['parent_date_of_birth']); ?>">
                    </div>
                    <div class="mb-3">
                        <label>Image</label><br/>
                        <input type="file" class="form-control" name="parent_image" />
                        <span class="text-muted">Only .jpg & .png file allowed</span>
                        <?php 
                                            if($user_row['parent_image'] != ''){
                                              echo '<img src="upload/'.$user_row['parent_image'].'" class="img-thumbnail" width=100 />';
                                              echo '<input type="hidden" name="hidden_parent_image" value="'.$user_row['parent_image'].'"/>';
                                            }
                                           ?>
                    </div>
                    <div class="mb-3">
                        <label for="parentSex" class="form-label">Sex</label>
                        <select class="form-select" id="parentSex" name="parent_sex" >
                            <option value="">Select Sex</option>
                            <option value="male" <?php echo $user_row['parent_sex'] == 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $user_row['parent_sex'] == 'female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    <input type="hidden" name="parent_id" value="<?php echo $user_row['parent_id'] ?>" />
                    <button type="submit" class="btn btn-primary" name="edit_parent" value="Edit">Update Parent</button>
                </form>
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
        }
        } else {
            ?>
                <h1 class="mt-2 head-update">Parent Management</h1>
                <ol class="breadcrumb mb-4 small">
                    <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                    <li class="breadcrumb-item active">Parent Management</li>
                </ol>
            <?php
            if (isset($_GET['msg'])) {
                if ($_GET['msg'] == 'add') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>Parent Added and Email sent Successfully. 
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'edit') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>Successfully Edited Parent.
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
                        <i class="bi bi-check-circle"></i>Successfully Deleted Parent.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
            }
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <span class="material-symbols-outlined">manage_accounts</span> All Parents
                        </div>
                        <div class="col-md-6 d-flex justify-content-end align-items-center">
                            <!-- Search Bar -->
                            <div class="mb-0 me-3">
                                <input type="text" id="searchBar" class="form-control" placeholder="Search Invoices..." onkeyup="searchInvoices()">
                            </div>
                            <!-- Button to trigger modal -->
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addParentModal">
                                Add Parent
                            </button>
                        </div>
                       <!-- Add Parent Modal -->
<div class="modal fade" id="addParentModal" tabindex="-1" aria-labelledby="addParentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addParentModalLabel">Add Parent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Form to add parent -->
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="parentName" class="form-label">Parent Name</label>
                        <input type="text" class="form-control" id="parentName" name="parent_name" placeholder="Enter FullName">
                    </div>
                    <div class="mb-3">
                        <label for="parentEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="parentEmail" name="parent_email" placeholder="Enter Email">
                    </div>
                    <div class="mb-3">
                        <label for="parentAddress" class="form-label">Address</label>
                        <input type="text" class="form-control" id="parentAddress" name="parent_address" placeholder="Enter Address" >
                    </div>
                    <div class="mb-3">
                        <label for="parentPassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="parentPassword" name="parent_password" placeholder="Enter Password" >
                    </div>
                    <div class="mb-3">
                        <label for="parentDateOfBirth" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="parentDateOfBirth" name="parent_date_of_birth" >
                    </div>
                    <div class="mb-3">
                                           <label>Image</label><br/>
                                           <input type="file" class="form-control" name="parent_image" />
                                           <span class="text-muted">Only .jpg & .png file allowed</span>
                                         </div>
                    <div class="mb-3">
                        <label for="parentSex" class="form-label">Sex</label>
                        <select class="form-select" id="parentSex" name="parent_sex" >
                            <option value="">Select Sex</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" name="add_parent" value="Add">Add Parent</button>
                </form>
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
                            <h3>Parents</h3>
                            <?php
// Include database connection
include('DB_connect.php');


// Number of items per page
$items_per_page = 10;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

if ($connect instanceof mysqli) {
    // Get total records for pagination
    $stmt_total = $connect->prepare("
        SELECT COUNT(*) AS total_records
        FROM parents
    ");
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    $total_records = $result_total->fetch_assoc()['total_records'];
    $total_pages = ceil($total_records / $items_per_page);

    // Prepare SQL query to fetch parent data with pagination
    $stmt_parents = $connect->prepare("
        SELECT parent_id, parent_image, parent_name, parent_email, parent_address, parent_date_of_birth, parent_sex, parent_added_on, parent_updated_on
        FROM parents
        LIMIT ? OFFSET ?
    ");
    $stmt_parents->bind_param("ii", $items_per_page, $offset);
    $stmt_parents->execute();
    $result = $stmt_parents->get_result();

    if ($result->num_rows > 0) {
        echo '<table class="table table-bordered" id="parentTable">';
        echo '<thead><tr><th>Image</th><th>Parent Name</th><th>Email</th><th>Address</th><th>Date of Birth</th><th>Sex</th><th>Added On</th><th>Updated On</th><th>Action</th></tr></thead><tbody>';
        
        while ($row = $result->fetch_assoc()) {
            $parent_id = htmlspecialchars($row['parent_id']);
            $parent_name = htmlspecialchars($row['parent_name']);
            $parent_email = htmlspecialchars($row['parent_email']);
            $parent_address = htmlspecialchars($row['parent_address']);
            $parent_date_of_birth = htmlspecialchars($row['parent_date_of_birth']);
          
            $parent_sex = htmlspecialchars($row['parent_sex']);
            $parent_added_on = htmlspecialchars($row['parent_added_on']);
            $parent_updated_on = htmlspecialchars($row['parent_updated_on']);

            $parent_image = !empty($row["parent_image"]) ? '<img src="upload/' . htmlspecialchars($row["parent_image"], ENT_QUOTES, 'UTF-8') . '" width="50"/>' : 'No Image';

            echo '<tr>';
            echo '<td>' . $parent_image . '</td>';
            echo '<td>' . $parent_name . '</td>';
            echo '<td>' . $parent_email . '</td>';
            echo '<td>' . $parent_address . '</td>';
            echo '<td>' . $parent_date_of_birth . '</td>';
            echo '<td>' . $parent_sex . '</td>';
            echo '<td>' . $parent_added_on . '</td>';
            echo '<td>' . $parent_updated_on . '</td>';
            echo '<td>
                <div class="btn-group" role="group" aria-label="Actions">
                    <!-- View Button -->
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#viewParentModal' . $parent_id . '">
                        <i class="bi bi-eye"></i> View
                    </button>
                    
                    <a href="parents.php?action=edit&id=' . $parent_id . '" class="btn btn-info btn-sm ms-2 me-2">
                        <i class="bi bi-pencil"></i> Edit
                    </a>

                    <!-- Delete Button -->
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteParentModal' . $parent_id . '">
                        <i class="bi bi-trash"></i> Delete
                    </button>

                    <!-- View Parent Modal -->
                    <div class="modal fade" id="viewParentModal' . $parent_id . '" tabindex="-1" aria-labelledby="viewParentModalLabel' . $parent_id . '" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="viewParentModalLabel' . $parent_id . '">Parent Details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p><strong>Parent ID:</strong> ' . $parent_id . '</p>
                                    <p><strong>Parent Name:</strong> ' . $parent_name . '</p>
                                    <p><strong>Email:</strong> ' . $parent_email . '</p>
                                    <p><strong>Address:</strong> ' . $parent_address . '</p>
                                    <p><strong>Date of Birth:</strong> ' . $parent_date_of_birth . '</p>
                                    <p><strong>Image:</strong> ' .$parent_image. '</p>
                                    <p><strong>Sex:</strong> ' . $parent_sex . '</p>
                                    <p><strong>Added On:</strong> ' . $parent_added_on . '</p>
                                    <p><strong>Updated On:</strong> ' . $parent_updated_on . '</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Parent Modal -->
                    <div class="modal fade" id="deleteParentModal' . $parent_id . '" tabindex="-1" aria-labelledby="deleteParentModalLabel' . $parent_id . '" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="deleteParentModalLabel' . $parent_id . '">Confirm Delete</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    Are you sure you want to delete this parent record?
                                </div>
                                <div class="modal-footer">
                                    <form action="parent.php?action=delete" method="post">
                                     <input type="hidden" name="parent_id" value="'.$parent_id.'">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="delete_parent" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<p>No parents found.</p>';
    }
} else {
    echo '<p>Database connection error.</p>';
}
?>
<nav aria-label="Page navigation">
    <ul class="pagination">
        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo ($current_page - 1); ?>" aria-label="Previous">
                <span aria-hidden="true">Previous</span>
            </a>
        </li>
        <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
            <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
        <?php } ?>
        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
            <a class="page-link" href="?page=<?php echo ($current_page + 1); ?>" aria-label="Next">
                <span aria-hidden="true">Next</span>
            </a>
        </li>
    </ul>
</nav>
                        </div>
                    </div>
                </div>
            </div>
            
            <footer class="main-footer px-3">
                <div class="pull-right hidden-xs">
                    Copyright © 2024-2025 <a href="#">AutoReceipt system</a>. All rights reserved
                </div>
            </footer>
            </div>
            <?php
        }
        ?>
            </main>
        <!-- main-->
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