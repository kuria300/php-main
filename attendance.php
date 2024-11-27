<?php
session_start();
 include('DB_connect.php');
 
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

// Handle form submission

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_attendance'])) {
        $student_id = intval($_POST['student_name']);
        $course_id = intval($_POST['course_name']);
        $present_days = intval($_POST['present_days']);

        $semester_start = new DateTime();
        // Set semester end date to 6 weeks from the semester start
        $semester_end = clone $semester_start;
        $semester_end->modify('+6 weeks');

        // Total number of days in the range
        $total_days = $semester_start->diff($semester_end)->days;
        // Calculate present days from attendance table
       
        // Calculate attendance percentage
        $attendance_percentage = ($total_days > 0) ? ($present_days / $total_days) * 100 : 0;

        
        // Insert the attendance record with percentage
        $insert_query = "
            INSERT INTO attendance (student_id, course_id, present_days, attendance_percentage,semester_start, semester_end)
            VALUES (?, ?, ?, ?, ?, ?)
        ";

        if ($stmt = $connect->prepare($insert_query)) {
            $stmt->bind_param('iiidss', $student_id, $course_id, $present_days, $attendance_percentage, $semester_start->format('Y-m-d'), $semester_end->format('Y-m-d'));
            if ($stmt->execute()) {
                header('Location: attendance.php?msg=add');
                exit();
            } else {
                echo "Error executing query: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Error preparing statement: " . $connect->error;
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_attendance'])) {
        $attendance_id = intval($_POST['attendance_id']);
        $present_days = intval($_POST['present_days']);

        // Fetch existing semester start and end dates from the database
        $select_query = "SELECT semester_start, semester_end FROM attendance WHERE attendance_id = ?";
        
        if ($stmt = $connect->prepare($select_query)) {
            $stmt->bind_param('i', $attendance_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $semester_start = new DateTime($row['semester_start']);
                    $semester_end = new DateTime($row['semester_end']);
                } else {
                    echo "Attendance record not found.";
                    exit();
                }
            } else {
                echo "Error executing query: " . $stmt->error;
                exit();
            }
            $stmt->close();
        } else {
            echo "Error preparing statement: " . $connect->error;
            exit();
        }

        // Calculate total days in the semester
        $total_days = $semester_start->diff($semester_end)->days;

        // Calculate new attendance percentage
        $attendance_percentage = ($total_days > 0) ? ($present_days / $total_days) * 100 : 0;

       
        // Update the record
        $update_query = "
            UPDATE attendance
            SET present_days = ?, attendance_percentage = ?
            WHERE attendance_id = ?
        ";

        if ($stmt = $connect->prepare($update_query)) {
            $stmt->bind_param('idi', $present_days, $attendance_percentage, $attendance_id);
            if ($stmt->execute()) {
                header('Location: attendance.php?msg=edit');
                exit();
            } else {
                echo "Error executing query: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Error preparing statement: " . $connect->error;
        }
    }
}
$attendance_id = '';
$present_days = '';
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && !empty($_GET['id'])) {
    $attendance_id = intval($_GET['id']);
    // Prepare and execute the query to get the present_days
    $query = "SELECT present_days FROM attendance WHERE attendance_id = ?";
    if ($stmt = $connect->prepare($query)) {
        $stmt->bind_param('i', $attendance_id);
        $stmt->execute();
        $stmt->bind_result($present_days);
        $stmt->fetch();
        $stmt->close();
    } else {
        echo "Error preparing query: " . $connect->error;
    }
} else {
    // Handle the case where attendance_id is not set
    
    $present_days = ''; // Default value or handle as needed
}
// Prepare students array for JavaScript
$studentsResult = $connect->query("SELECT student_id, student_name FROM students");

$students = [];
while ($row = $studentsResult->fetch_assoc()) {
    $students[] = $row;
}

// Fetch courses from the database based on enrollments
// This query assumes you have an enrollments table that links students and courses
$coursesResult = $connect->query("
    SELECT e.student_id, c.course_id, c.course_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
");

// Prepare courses array for JavaScript
$courses = [];
while ($row = $coursesResult->fetch_assoc()) {
    $courses[] = $row;
}

// Convert to JSON for use in JavaScript
$studentsJson = json_encode($students);
$coursesJson = json_encode($courses);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_POST['delete_attendance'])) {
    // Retrieve and validate the attendance ID from POST data
    $attendance_id = isset($_POST['attendance_id']) ? intval($_POST['attendance_id']) : '';

    // Validate the attendance ID
    if (empty($attendance_id) || !is_numeric($attendance_id) || $attendance_id <= 0) {
        echo "<div class='alert alert-danger'>Invalid attendance ID.</div>";
        exit();
    }

    // Prepare SQL statement to delete the attendance record
    $stmt = $connect->prepare("DELETE FROM attendance WHERE attendance_id = ?");
    if ($stmt === false) {
        echo "<div class='alert alert-danger'>Error preparing the statement: " . htmlspecialchars($connect->error) . "</div>";
        exit();
    }

    $stmt->bind_param("i", $attendance_id);

    // Execute the statement
    if ($stmt->execute()) {
        // Redirect or provide success feedback
        header('Location: attendance.php?msg=delete_success');
        exit();
    } else {
        // Handle SQL execution error
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($stmt->error) . "</div>";
    }

    // Close the statement
    $stmt->close();
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
    <title>Attendance</title>
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
                    <h1 class="mt-2 head-update">Attendance Management</h1>
    <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
        <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="attendance.php" style="color: #f8f9fa;">All Attendance</a></li>
        <li class="breadcrumb-item active">Edit Attendance</li>
    </ol>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header">
                    <span class="material-symbols-outlined">manage_accounts</span> Attendance Edit Form
                </div>
                <div class="card-body">
                    <form action="attendance.php" method="post">
                        <div class="form-group mb-3">
                            <label for="attendance_id">Attendance ID:</label>
                            <input type="number" id="attendance_id" name="attendance_id" class="form-control" value="<?php echo $attendance_id; ?>" required readonly>
                        </div>
                        <div class="form-group mb-3">
                            <label for="present_days">Present Days:</label>
                            <input type="number" id="present_days" name="present_days" class="form-control" value="<?php echo $present_days; ?>" required>
                        </div>
                        <button type="submit" name="update_attendance" class="btn btn-primary">Update Attendance</button>
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
            <h1 class="mt-2 head-update">Attendance Management</h1>
            <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
                <li class="breadcrumb-item active">All Attendance</li>
            </ol>
            <?php
            if (isset($_GET['msg'])) {
                if ($_GET['msg'] == 'add') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Attendance records have been successfully added for the semester.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'delete_success') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Attendance records deleted successfully
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'edit') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> Attendance records updated successfully
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
            }
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <span class="material-symbols-outlined">manage_accounts</span> All Attendance
                        </div>
                        <div class="col-md-6 d-flex justify-content-end align-items-center">
                            <!-- Search Bar -->
                            <div class="mb-0 me-3">
                                <input type="text" id="searchBar" class="form-control" placeholder="Search Attendance..." onkeyup="searchAttend()">
                            </div>
                            <!-- Button to trigger modal -->
                            <?php if ($displayRole === 'Admin'): ?>
                              <!-- Button to trigger modal -->
                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addFeesModal">
                                Add Attendance
                                </button>
                             <?php endif; ?>
                        </div>
                        <!-- Modal -->
                        <div class="modal fade" id="addFeesModal" tabindex="-1" aria-labelledby="addFeesModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="addFeesModalLabel">Add Attendance</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <!-- Form to add fees -->
                                        <form method="POST" action="attendance.php">
                                        <div class="form-group mb-3">
                                              <label for="student_name">Student:</label>
                                                   <select id="student_name" name="student_name" class="form-control" required>
                                                    <option value="">Select a student</option>
                                                      <?php
                                                      // Output student options
                                                       foreach ($students as $student) {
                                                       echo "<option value=\"" . $student['student_id'] . "\">" . htmlspecialchars($student['student_name']) . "</option>";
                                                        }
                                                         ?>
                                                        </select>
                                                       </div>
                                                       <div class="form-group mb-3">
                                                           <label for="course_name">Course:</label>
                                                             <select id="course_name" name="course_name" class="form-control" required>
                                                        <option value="">Select a student first</option>
                                                     </select>
                                                  </div>
                                                  <div class="form-group mb-3">
                                                  <label for="present_days">Present Days:</label>
                                                  <input type="number" id="present_days" name="present_days" min="0" required><br>
                                                   </div>
                                                 <button type="submit" name="add_attendance" class="btn btn-primary mt-3">Add Attendance</button>
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
                            <h3>Attendance</h3>
                            <div class="table-responsive">
    <table class="table table-striped" id="invoicesTable">
        <thead>
            <tr>
               
                <th>Student Name</th>
                <th>Course Name</th>
                <th>Semester Start</th>
                <th>Semester End</th>
                <th>Present Days</th>
                <th>Percentage</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            include('DB_connect.php');

            $results_per_page = 10;

            // Determine the total number of pages
            $total_query = "SELECT COUNT(*) AS total FROM attendance";
            $total_result = $connect->query($total_query);
            $total_row = $total_result->fetch_assoc();
            $total_results = $total_row['total'];
            $total_pages = ceil($total_results / $results_per_page);
            
            // Get the current page number from the URL, default to 1 if not set
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            
            // Ensure page number is valid
            if ($page < 1) $page = 1;
            if ($page > $total_pages) $page = $total_pages;
            
            // Calculate the offset
            $offset = ($page - 1) * $results_per_page;
            
            // Adjust the query based on user role
            if ($displayRole === 'Admin') {
                // Admins can view all attendance records
                $query = "SELECT a.attendance_id, s.student_name, c.course_name, a.semester_start, a.semester_end, a.present_days, a.attendance_percentage
                FROM attendance a
                JOIN courses c ON a.course_id = c.course_id
                JOIN students s ON a.student_id = s.student_id
                LIMIT ? OFFSET ?";
                $stmt = $connect->prepare($query);
                $stmt->bind_param("ii", $results_per_page, $offset);
            } elseif ($displayRole === 'Parent') {
                // Parents can view attendance records of their own children
                $query = "SELECT a.attendance_id, s.student_name, c.course_name,a.semester_start, a.semester_end, present_days, attendance_percentage
                          FROM attendance a
                          JOIN students s ON a.student_id = s.student_id
                          JOIN courses c ON a.course_id = c.course_id
                          WHERE s.parent_id = ?
                          LIMIT ? OFFSET ?";
                $stmt = $connect->prepare($query);
                $stmt->bind_param("iii", $userId, $results_per_page, $offset);
            } else {
                // Students can only view their own attendance records
                $query = "SELECT a.attendance_id, s.student_name, c.course_name, a.semester_start, a.semester_end, a.present_days, a.attendance_percentage
              FROM attendance a
              JOIN students s ON a.student_id = s.student_id
              JOIN courses c ON a.course_id = c.course_id
              WHERE s.student_id = ?
              LIMIT ? OFFSET ?";
                $stmt = $connect->prepare($query);
                $stmt->bind_param("iii", $userId, $results_per_page, $offset);
            }
            
            // Execute the statement
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $attendance_id = urlencode($row['attendance_id']);
                $delete_modal_id = "deleteAttendanceModal{$row['attendance_id']}";
                $delete_modal_label = "deleteAttendanceModalLabel{$row['attendance_id']}";
                $view_modal_id = "viewAttendanceModal{$row['attendance_id']}";
                $view_modal_label = "viewAttendanceModalLabel{$row['attendance_id']}";

                echo "<tr>
                   
                    <td>{$row['student_name']}</td>
                    <td>{$row['course_name']}</td>
                    <td>{$row['semester_start']}</td>
                    <td>{$row['semester_end']}</td>
                    <td>{$row['present_days']}</td>
                    <td>{$row['attendance_percentage']}</td>
                    <td>
                    <button type='button' class='btn btn-info btn-sm' data-bs-toggle='modal' data-bs-target='#{$view_modal_id}'>
                    View
                </button>";
                // Conditionally render the Edit and Delete buttons for Admins
                if ($displayRole === 'Admin') {
                    echo "    <a href='attendance.php?action=edit&id={$attendance_id}' class='btn btn-warning btn-sm'>Edit</a>
                            <button type='button' class='btn btn-danger btn-sm' data-bs-toggle='modal' data-bs-target='#{$delete_modal_id}'>
                                <i class='bi bi-trash'></i> Delete
                            </button>

                            <!-- Modal -->
                            <div class='modal fade' id='{$delete_modal_id}' tabindex='-1' aria-labelledby='{$delete_modal_label}' aria-hidden='true'>
                    <div class='modal-dialog'>
                        <div class='modal-content'>
                            <div class='modal-header'>
                                <h5 class='modal-title' id='{$delete_modal_label}'>Confirm Delete</h5>
                                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                            </div>
                            <div class='modal-body'>
                                Are you sure you want to delete this attendance record?
                            </div>
                            <div class='modal-footer'>
                                <form action='attendance.php?action=delete' method='post'>
                                    <input type='hidden' name='attendance_id' value='{$attendance_id}'>
                                    <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                    <button type='submit' class='btn btn-danger' name='delete_attendance'>Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>";
                }

                echo "  </td>
                </tr>";
                echo "<div class='modal fade' id='{$view_modal_id}' tabindex='-1' aria-labelledby='{$view_modal_label}' aria-hidden='true'>
                <div class='modal-dialog'>
                    <div class='modal-content'>
                        <div class='modal-header'>
                            <h5 class='modal-title' id='{$view_modal_label}'>View Attendance Details</h5>
                            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                        </div>
                        <div class='modal-body'>
                            <p><strong>Attendance ID:</strong> {$row['attendance_id']}</p>
                            <p><strong>Student ID:</strong> {$row['student_name']}</p>
                            <p><strong>Course Name:</strong> {$row['course_name']}</p>
                            <p><strong>Semester Start:</strong> {$row['semester_start']}</p>
                            <p><strong>Semester End:</strong> {$row['semester_end']}</p>
                            <p><strong>Present Days:</strong> {$row['present_days']}</p>
                            <p><strong>Percentage:</strong> {$row['attendance_percentage']}</p>
                        </div>
                        <div class='modal-footer'>
                            <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Close</button>
                        </div>
                    </div>
                </div>
            </div>";
            }
            ?>
        </tbody>
    </table>
</div>
<nav aria-label="Page navigation">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="attendance.php?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">Previous</span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">Previous</span>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="attendance.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="attendance.php?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">Next</span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">Next</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
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
         // Store the students and courses data from PHP in JavaScript variables
var students = <?php echo $studentsJson; ?>;
var courses = <?php echo $coursesJson; ?>;

// Function to update the courses dropdown based on the selected student
function updateCourses() {
    var studentId = document.getElementById('student_name').value;
    var courseDropdown = document.getElementById('course_name');

    // Clear existing options
    courseDropdown.innerHTML = '<option value="">Select a course</option>';

    if (studentId) {
        // Filter courses for the selected student
        var filteredCourses = courses.filter(function(course) {
            return course.student_id == studentId;
        });

        // Populate course dropdown
        filteredCourses.forEach(function(course) {
            var option = document.createElement('option');
            option.value = course.course_id;
            option.text = course.course_name;
            courseDropdown.add(option);
        });
    }
}

// Attach event listener to student dropdown to update courses on change
document.getElementById('student_name').addEventListener('change', updateCourses);
    

    // Attach event listener to student dropdown
    function searchAttend() {
        var input, filter, table, rows, cells, i, j, txtValue, found;
        input = document.getElementById('searchBar');
        filter = input.value.toLowerCase();
        table = document.getElementById('invoicesTable');
        rows = table.getElementsByTagName('tr');

        for (i = 1; i < rows.length; i++) { // Start from 1 to skip the header row
            cells = rows[i].getElementsByTagName('td');
            found = false;
            for (j = 0; j < cells.length; j++) {
                cell = cells[j];
                txtValue = cell.textContent || cell.innerText;
                if (txtValue.toLowerCase().indexOf(filter) > -1) {
                    found = true;
                    break; // Stop searching this row
                }
            }
            rows[i].style.display = found ? "" : "none";
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