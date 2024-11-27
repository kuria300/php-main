<?php
session_start();
include('DB_connect.php');

 
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
  if (isset($_POST['course_name']) && isset($_POST['course_number']) && isset($_POST['course_fee']) && isset($_GET['action']) && $_GET['action'] == 'add') {
    // Retrieve and sanitize form data
    $course_name = trim($_POST['course_name']);
    $course_number = trim($_POST['course_number']);
    $course_fee = trim($_POST['course_fee']);

    // Validate input
    $error = [];
    if (empty($course_name) || empty($course_number) || empty($course_fee)) {
        $error[] = 'All fields are required.';
    }

    // Check if course_fee is a valid number
    if (!is_numeric($course_fee) || $course_fee <= 0) {
        $error[] = 'Course fee must be a positive number.';
    }

    if (empty($error)) {
        // Prepare and execute query to check if course already exists
        $stmt = $connect->prepare("SELECT COUNT(*) FROM courses WHERE course_name = ? OR course_number = ?");
        $stmt->bind_param('ss', $course_name, $course_number);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error[] = 'A course with the same name or number already exists.';
        } else {
            // Insert new course into the database
            $stmt = $connect->prepare("INSERT INTO courses (course_name, course_number, course_fee) VALUES (?, ?, ?)");
            $stmt->bind_param('ssd', $course_name, $course_number, $course_fee);
            if ($stmt->execute()) {
                header('Location: course.php?msg=add');
                exit();
            } else {
                $error[] = 'Failed to add course. Error: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}
    if (isset($_POST['course_id']) && isset($_POST['course_name']) && isset($_POST['course_number']) && isset($_POST['course_fee']) && isset($_GET['action']) && $_GET['action'] == 'edit') {
        $course_id = intval($_POST['course_id']);
        $course_name = trim($_POST['course_name']);
        $course_number = trim($_POST['course_number']);
        $course_fee = trim($_POST['course_fee']);
    
        // Validate input
        $error = [];
        if (empty($course_name) || empty($course_number) || empty($course_fee)) {
            $error[] = 'Course name, course number, and course fee are required.';
        }
    
        // Check if course_fee is a valid number
        if (!is_numeric($course_fee) || $course_fee <= 0) {
            $error[] = 'Course fee must be a positive number.';
        }
    
        if (empty($error)) {
            // Check if a course with the same name or number already exists
            $stmt = $connect->prepare("SELECT COUNT(*) FROM courses WHERE (course_name = ? OR course_number = ?) AND course_id != ?");
            $stmt->bind_param('ssi', $course_name, $course_number, $course_id);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
    
            if ($count > 0) {
                $error[] = 'A course with the same name or number already exists.';
            } else {
                // Update the course
                $stmt = $connect->prepare("UPDATE courses SET course_name = ?, course_number = ?, course_fee = ? WHERE course_id = ?");
                $stmt->bind_param('ssdi', $course_name, $course_number, $course_fee, $course_id);
                if ($stmt->execute()) {
                    // Redirect after successful update
                    header('Location: course.php?msg=edit');
                    exit();
                } else {
                    $error[] = 'Failed to update course. Error: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    } 
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_POST['delete_course'])) {
        // Retrieve and validate the course ID from POST data
        $course_id = isset($_POST['course_id']) ? $_POST['course_id'] : '';
    
        // Validate the course ID
        if (!is_numeric($course_id) || $course_id <= 0) {
            echo "<div class='alert alert-danger'>Invalid course ID.</div>";
            exit();
        }
    
        // Begin a transaction
        $connect->begin_transaction();
    
        try {
            // First, delete any related records in the enrollments table
            $deleteEnrollmentsQuery = "DELETE FROM enrollments WHERE course_id = ?";
            $enrollStmt = $connect->prepare($deleteEnrollmentsQuery);
            $enrollStmt->bind_param('i', $course_id);
    
            if (!$enrollStmt->execute()) {
                throw new Exception("Error deleting related enrollments: " . $enrollStmt->error);
            }
    
            // Next, prepare SQL statement to delete the course record
            $stmt_course = $connect->prepare("DELETE FROM courses WHERE course_id = ?");
            $stmt_course->bind_param("i", $course_id);
    
            // Execute the statement for deleting the course
            if ($stmt_course->execute()) {
                // Commit the transaction if both delete operations are successful
                $connect->commit();
                // Redirect or provide success feedback
                header('Location: course.php?msg=delete');
                exit();
            } else {
                throw new Exception("Error deleting course: " . $stmt_course->error);
            }
    
            // Close the course statement
            $stmt_course->close();
            $enrollStmt->close();
        } catch (Exception $e) {
            // Rollback the transaction in case of error
            $connect->rollback();
            // Store error message in session to display on redirect
            $_SESSION['error_message'] = "You cannot delete this course because there are students enrolled in it. Please unenroll the students first.";
            header('Location: course.php?msg=error');
            exit();
        }
    }
$queryAllCourses = "SELECT course_id, course_name FROM courses";
$resultAllCourses = $connect->query($queryAllCourses);

$query = "";
$imageField = "";
$id=$_SESSION['id'];

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
    $stmt->bind_param("i", $id); 
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc(); // Fetch associative array
    } else {
        $admin = null; 
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
    <title>Courses</title>
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
                  if(isset($_GET['action'])){
                      if($_GET['action']== 'add'){
                        ?>
                        <h1 class="mt-2 head-update">Add Courses</h1>
                         <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                            <li class="breadcrumb-item"><a href="dashboard.php"  style="color: #f8f9fa;">Dashboard</a></li>
                            <li class="breadcrumb-item active"><a href="course.php"  style="color: #f8f9fa;">Course Management</a></li>
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
                                        <div class=" form-group mb-3">
                                              <label for="courseFee" >Course Fee</label>
                                              <input type="number" class="form-control" id="courseFee" name="course_fee" min="0" step="0.01">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Add Course</button>
                                      </form> 
                                    </div>
                                </div>
                            </div>
                        </div>

                        <footer class="main-footer px-3">
                          <div class="pull-right hidden-xs"> 
                          <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>
                       </footer>
                       <?php
                      }else if(isset($_GET['action']) && $_GET['action'] == 'edit'){
                        if (isset($_GET['id'])) {
                            $course_id = intval($_GET['id']); // Ensure course_id is an integer
                
                            // Prepare and execute the query
                            $stmt = $connect->prepare("SELECT * FROM courses WHERE course_id = ?");
                            $stmt->bind_param('i', $course_id); // Bind the course_id parameter
                             $stmt->execute();
                            // Get the result
                            $result = $stmt->get_result();
                
                            if ($course_row = $result->fetch_assoc()) {
                                ?>
                                <h1 class="mt-2 head-update">Edit Courses</h1>
                                <ol class="breadcrumb mb-4 small"  style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                                    <li class="breadcrumb-item"><a href="dashboard.php"  style="color: #f8f9fa;">Dashboard</a></li>
                                    <li class="breadcrumb-item active"><a href="course.php"  style="color: #f8f9fa;">Course Management</a></li>
                                    <li class="breadcrumb-item active">Edit Course</li>
                                </ol>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <span class="material-symbols-outlined">manage_accounts</span> Course Edit Form
                                            </div>
                                            <div class="card-body">
                                                <form method="post" enctype="multipart/form-data">
                                                    <div class="form-group mb-3">
                                                        <label for="course_name">Course Name</label>
                                                        <input type="text" class="form-control" id="course_name" name="course_name" value="<?php echo htmlspecialchars($course_row['course_name']); ?>" required>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label for="course_number">Course Number</label>
                                                        <input type="text" class="form-control" id="course_number" name="course_number" value="<?php echo htmlspecialchars($course_row['course_number']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                            <label for="courseFee" class="form-label">Course Fee</label>
                                                            <input type="number" class="form-control" id="courseFee" name="course_fee" min="0" step="0.01"value="<?php echo htmlspecialchars($course_row['course_fee']); ?>" required>
                                                        </div>
                                                    <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_row['course_id']); ?>">
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                              <footer class="main-footer px-3">
                                          <div class="pull-right hidden-xs"> 
                                          <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p> 
                                        </footer>
                                <?php
                              }
                          }
                      }
                  }else{
                    ?>
              <h1 class="mt-2 head-update">Course Management</h1>
               <ol class="breadcrumb mb-4 small"  style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                  <li class="breadcrumb-item"><a href="dashboard.php"  style="color: #f8f9fa;">Dashboard</a></li>
                  <li class="breadcrumb-item active">Course Management</a></li>
                </ol>
                <?php
                if (isset($_GET['msg'])) {
                    if ($_GET['msg'] == 'add') {
                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i>New Course Successfully Added
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
                    }
                    if ($_GET['msg'] == 'edit') {
                      echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                          <i class="bi bi-check-circle"></i> Successfully updated Course
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
                  }
                  if ($_GET['msg'] == 'delete') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Successfully deleted Course
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'erro2') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Unenroll students first.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'error') {
                    // Display the error message if it exists in the session
                    if (isset($_SESSION['error_message'])) {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-x-circle"></i> ' . htmlspecialchars($_SESSION['error_message']) . '
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
                        unset($_SESSION['error_message']); // Clear the message after displaying
                    }
                }
                }
                ?>
                <div class="card mb-4 ">
                  <div class="card-header">
                    <div class="row">
                      <div class="col-md-6">
                         <span class="material-symbols-outlined">manage_accounts</span>Courses
                      </div>
                   <div class="col-md-6 d-flex justify-content-end add-button ">
                   <a href="course.php?action=add" class="btn btn-success btn-sm">Add New Course</a>
                  </div>
            </div>
        </div>
        
        <div class="card-body">
        <div class="table-responsive">
        <h3>Courses</h3>
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
        FROM courses
    ");
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    $total_records = $result_total->fetch_assoc()['total_records'];
    $total_pages = ceil($total_records / $items_per_page);

    // Prepare SQL query to fetch paginated course data
    $stmt_courses = $connect->prepare("
        SELECT course_id, course_name, course_number, course_fee
        FROM courses
        LIMIT ? OFFSET ?
    ");
    $stmt_courses->bind_param("ii", $items_per_page, $offset);
    $stmt_courses->execute();
    $result = $stmt_courses->get_result();

    ?>

                    <?php
                    if ($result->num_rows > 0) {
                        echo '<table class="table table-bordered" id="courseTable">';
                        echo '<thead><tr><th>Course ID</th><th>Course Name</th><th>Course Number</th><th>Course Fee</th><th>Action</th></tr></thead><tbody>';
                        
                        while ($row = $result->fetch_assoc()) {
                            $course_id = htmlspecialchars($row['course_id']);
                            $course_name = htmlspecialchars($row['course_name']);
                            $course_number = htmlspecialchars($row['course_number']);
                            $course_fee = htmlspecialchars($row['course_fee']);
                            
                            echo '<tr>';
                            echo '<td>' . $course_id . '</td>';
                            echo '<td>' . $course_name . '</td>';
                            echo '<td>' . $course_number . '</td>';
                            echo '<td>' . $course_fee . '</td>';
                            echo '<td>
                                <div class="btn-group" role="group" aria-label="Actions">
                                    <!-- View Button -->
                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#viewCourseModal' . $course_id . '">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    
                                    <a href="course.php?action=edit&id=' . $course_id . '" class="btn btn-info btn-sm ms-2 me-2">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    
                                    <!-- Delete Button -->
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteCourseModal' . $course_id . '">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>

                                    <!-- View Course Modal -->
                                    <div class="modal fade" id="viewCourseModal' . $course_id . '" tabindex="-1" aria-labelledby="viewCourseModalLabel' . $course_id . '" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="viewCourseModalLabel' . $course_id . '">Course Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Course ID:</strong> ' . $course_id . '</p>
                                                    <p><strong>Course Name:</strong> ' . $course_name . '</p>
                                                    <p><strong>Course Number:</strong> ' . $course_number . '</p>
                                                    <p><strong>Course Fee:</strong> ' . $course_fee . '</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete Course Modal -->
                                    <div class="modal fade" id="deleteCourseModal' . $course_id . '" tabindex="-1" aria-labelledby="deleteCourseModalLabel' . $course_id . '" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteCourseModalLabel' . $course_id . '">Confirm Delete</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Are you sure you want to delete this course?
                                                </div>
                                                <div class="modal-footer">
                                                    <form action="course.php?action=delete" method="post">
                                                        <input type="hidden" name="course_id" value="'.$course_id. '">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="delete_course" class="btn btn-danger">Delete</button>
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
                        echo '<p>No courses found.</p>';
                    }
                    ?>

                    <!-- Pagination Controls -->
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
                 <?php
                    // Close statements
                    $stmt_total->close();
                    $stmt_courses->close();
                    $connect->close();
                } else {
                    echo '<p>Database connection error.</p>';
                }
                ?>
             </div>
            </div>
            </div>
        </div>
        <footer class="main-footer px-3">
                <div class="pull-right hidden-xs"> 
                <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>
              </footer>
        </main>
         <!--main-->
    <!--custom tag-->
    <script>
        // Sidebar Toggle Functions
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
    <?php
      }
      ?>
         
</body>
</html>

<?php 
?>