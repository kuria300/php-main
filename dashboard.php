<?php
session_start();
 include('DB_connect.php');
 

 if (!isset($_SESSION["role"])) {
    header("Location: Admin.php");
    exit;
}

$recentGrades = 0;

if (isset($_SESSION["id"]) && isset($_SESSION["role"])) {
    
    $userId = $_SESSION["id"];
    $userRole = $_SESSION["role"];
    $adminType = $_SESSION["admin_type"] ?? '';
    $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
    $text_size = isset($_COOKIE['text_size']) ? $_COOKIE['text_size'] : 'medium';
    $roleNames = [
        "1" => "Admin",
        "2" => "Student",
        "3" => "Parent"
    ];
    $displayRole = $roleNames[$userRole] ?? 'Parent';

    if ($displayRole === 'Student' || $displayRole === 'Parent') {
        $table = ($displayRole === 'Student') ? 'students' : 'parents';
        $idColumn = ($displayRole === 'Student') ? 'student_id' : 'parent_id';

        $stmt = $connect->prepare("SELECT * FROM $table WHERE $idColumn = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        $stmt = $connect->prepare("SELECT id, message FROM messages WHERE user_id = ? AND user_role = ? AND status = 'active' AND is_read = 0");
        $stmt->bind_param('is', $userId, $displayRole);
        $stmt->execute();
        $result = $stmt->get_result();

        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $notifications = array_map("unserialize", array_unique(array_map("serialize", $notifications)));
        $stmt->close();
    } 
    
}
if (isset($_SESSION['id']) && $displayRole === 'Parent') {
    $parent_id = $_SESSION['id'];

    // Establish database connection
    if ($connect instanceof mysqli) {
        // Query to get notifications for students related to this parent
        $query = "
            SELECT m.id, m.message 
            FROM messages m
            JOIN students s ON m.user_id = s.student_id
            WHERE s.parent_id = ? AND m.status = 'active' AND m.is_read = 0
        ";

        $stmt = $connect->prepare($query);

        if ($stmt) {
            $stmt->bind_param('i', $parent_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Fetch all notifications
                $notifications = $result->fetch_all(MYSQLI_ASSOC);
            } else {
                $notifications = []; // No notifications found
            }

            $stmt->close();
        } 
    }
}


if ($connect instanceof mysqli) {
    $student_id = $_SESSION['id']; // Assuming student ID is stored in session

    $query = "SELECT AVG(grade) AS average_grade FROM grades WHERE student_id = ?";
    $stmt = $connect->prepare($query);
    if ($stmt) {
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $recentGrades = round($row['average_grade'], 2);
        }
        $stmt->close();
    }

    $query = "SELECT COUNT(*) AS course_count FROM enrollments WHERE student_id = ?";
    $stmt = $connect->prepare($query);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    // Retrieve the course count
    $course_count = $data['course_count'] ?? 0;

    // Fetch total attendance and present attendance
    $query = "
    SELECT course_id
    FROM enrollments
    WHERE student_id = ?
    ";
    
    $stmt = $connect->prepare($query);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $course_ids = [];
    while ($row = $result->fetch_assoc()) {
        $course_ids[] = $row['course_id'];
    }
    
    $attendance_percentages = [];

    foreach ($course_ids as $course_id) {
        $query = "
         SELECT a.course_id, c.course_name, a.attendance_percentage
    FROM attendance a
    JOIN courses c ON a.course_id = c.course_id
    WHERE a.student_id = ? AND a.course_id = ?
        ";
    
        $stmt = $connect->prepare($query);
        $stmt->bind_param('ii', $student_id,  $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
    
        if ($data) {

           
            $attendance_percentages[$data['course_name']] = $data['attendance_percentage'];
        } else {
            // Handle the case where there is no data for the course
            $attendance_percentages[$course_id] = 'No data available';
        }

    }
$stmt->close();
}

$query = "SELECT COUNT(*) AS total_notices FROM messages WHERE user_id = ?";
$stmt = $connect->prepare($query);
if ($stmt) {
    // Bind the user ID parameter
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $noticesCount = $row['total_notices'];
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['notification_id'])) {
    $notificationId = intval($_POST['notification_id']);
    
    $stmt = $connect->prepare("UPDATE messages SET is_read = 1, status = 'dismissed' WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $notificationId, $userId);
    $stmt->execute();
    $stmt->close();
}




if ($connect instanceof mysqli) {
    $parentId = $_SESSION['id']; // Get parent ID from session

    // Prepare and execute SQL query to fetch students associated with the logged-in parent
    $stmt = $connect->prepare("
        SELECT student_id, student_name, student_email, student_number, student_course, student_contact_number1
        FROM students
        WHERE parent_id = ?
    ");
    $stmt->bind_param("i", $parentId); // Bind parent ID parameter
    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row; // Store each student's data in the array
    }
    $stmt->close(); // Always close the statement

    foreach ($students as &$student) {
        // Initialize values
       
        // Recent Grades
        $stmt = $connect->prepare("SELECT AVG(grade) AS average_grade FROM grades WHERE student_id = ?");
        $stmt->bind_param('i', $student['student_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $student['recentGrades'] = round($row['average_grade'], 2);
        }
        $stmt->close();

        // Course Count
        $query = "SELECT COUNT(*) AS course_count FROM students WHERE student_id = ?";
        $stmt = $connect->prepare($query);
        $stmt->bind_param('i', $student['student_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $student['course_count'] = $row['course_count'];
        } else {
            $student['course_count'] = 0; // No courses found
        }
        $stmt->close();

        $query = "SELECT COUNT(*) AS total_notices FROM messages WHERE user_id = ?";
        $stmt = $connect->prepare($query);
       if ($stmt) {
    // Bind the user ID parameter
       $stmt->bind_param('i', $student['student_id']);
       $stmt->execute();
       $result = $stmt->get_result();
      if ($row = $result->fetch_assoc()) {
        $student['total_notices'] = $row['total_notices'];
       }else {
        $student['total_notices'] = 0; // No courses found
       }
         $stmt->close();
        // Attendance Percentage
        $query = "SELECT course_id FROM students WHERE student_id = ?";
        $stmt = $connect->prepare($query);
        $stmt->bind_param('i', $student['student_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        $course_id = $data['course_id'] ?? null;
        $stmt->close();

        $query = "SELECT attendance_percentage FROM attendance WHERE student_id = ? AND course_id = ?";
        $stmt = $connect->prepare($query);
        $stmt->bind_param('ii', $student['student_id'], $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        $student['attendance_percentage'] = $data['attendance_percentage'] ?? 0;
        $stmt->close();
    }
   
}
}

$stmt_students = $connect->prepare("SELECT COUNT(*) AS total_students FROM students");
$stmt_students->execute();
$result_students = $stmt_students->get_result();
$total_students = $result_students->fetch_assoc()['total_students'];

// Get total number of users
$stmt_users = $connect->prepare("SELECT COUNT(*) AS total_users FROM admin_users");
$stmt_users->execute();
$result_users = $stmt_users->get_result();
$total_users = $result_users->fetch_assoc()['total_users'];

// Get total number of parents
$stmt_parents = $connect->prepare("SELECT COUNT(*) AS total_parents FROM parents");
$stmt_parents->execute();
$result_parents = $stmt_parents->get_result();
$total_parents = $result_parents->fetch_assoc()['total_parents'];
// Get total deposited fees
$stmt_deposited_fees_paid = $connect->prepare("SELECT SUM(paid_amount) AS total_deposited_fees FROM deposit WHERE status = 'paid'");
$stmt_deposited_fees_paid->execute();
$result_deposited_fees_paid = $stmt_deposited_fees_paid->get_result();
$total_deposited_fees_paid = $result_deposited_fees_paid->fetch_assoc()['total_deposited_fees'];

$stmt_total_fees = $connect->prepare("SELECT SUM(total_amount) AS total_fees FROM deposit"); 
$stmt_total_fees->execute();
$result_total_fees = $stmt_total_fees->get_result();
$total_fees = $result_total_fees->fetch_assoc()['total_fees'];

$stmt_deposited_fees_pending = $connect->prepare("SELECT SUM(paid_amount) AS total_deposited_fees_pending FROM deposit WHERE status = 'pending'");
$stmt_deposited_fees_pending->execute();
$result_deposited_fees_pending = $stmt_deposited_fees_pending->get_result();
$total_deposited_fees_pending = $result_deposited_fees_pending->fetch_assoc()['total_deposited_fees_pending']; // Use null coalescing operator to default to 0 if no result

// Calculate the difference between total fees and deposited fees (this is pending fees)
$pending_fees = $total_fees - $total_deposited_fees_pending;
// Output the results (make sure to handle cases where these might be null)
$total_fees = $total_fees ?: 0;
$total_deposited_fees_paid = $total_deposited_fees_paid ?: 0;
$total_deposited_fees_pending = $total_deposited_fees_pending ?: 0;
$pending_fees = $pending_fees ?: 0;

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

// Close the connection
$connect->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DashBoard</title>
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
            <form class="d-flex ms-auto" method="GET" action="search_result.php">
                <div class="input-group my-lg-0">
                    <input 
                        type="text"
                        name="query"
                        class="form-control"
                        placeholder="Search for..."
                        aria-label="search"
                        aria-describedby="button-addon2"
                        required
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
        <?php if ($displayRole === 'Parent'): ?>
            <main class="main-container">
    <div class="container-fluid">
        <div class="main-title">
            <h2>Parent Dashboard</h2>
        </div>
        <?php if (!empty($notifications)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php foreach ($notifications as &$notification): ?>
                    <div class="notification-item">
                        <?php echo htmlspecialchars($notification['message']); ?>
                        <form method="post" action="" class="d-inline">
                            <input type="hidden" name="notification_id" value="<?php echo htmlspecialchars($notification['id']); ?>">
                            <button type="submit" class="btn btn-link btn-sm">Close</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php else: ?>
            <p>No notifications at this time.</p>
        <?php endif; ?>
        <!-- Add other dashboard content here -->
    </div>
    
        <div class="main-cards">
            <?php foreach ($students as &$student): ?>
        <div class="cards">
            <div class="card-inner">
                <h3><?php echo htmlspecialchars( $student['student_name']); ?> - Recent Grades</h3>
                <span class="material-symbols-outlined">grade</span>
            </div>
            <h1><?php echo htmlspecialchars($student['recentGrades'] ?? '0'); ?>%</h1>
        </div>
        <div class="cards">
            <div class="card-inner">
                <h3><?php echo htmlspecialchars($student['student_name']); ?> - Notices</h3>
                <span class="material-symbols-outlined">event</span>
            </div>
            <h1><?php echo htmlspecialchars( $student['total_notices'] ?? '0'); ?></h1>
        </div>
        <div class="cards">
            <div class="card-inner">
                <h3><?php echo htmlspecialchars($student['student_name']); ?> - Enrolled Courses</h3>
                <span class="material-symbols-outlined">assignment</span>
            </div>
            <h1><?php echo htmlspecialchars($student['course_count'] ?? '0'); ?></h1>
        </div>
        <div class="cards">
            <div class="card-inner">
                <h3><?php echo htmlspecialchars($student['student_name']); ?> - Attendance</h3>
                <span class="material-symbols-outlined">check_circle</span>
            </div>
            <h1><?php echo htmlspecialchars($student['attendance_percentage'] ?? '0'); ?>%</h1>
        </div>
        <?php endforeach; ?>
        </div>
         <!-- Student Information Section -->
         
        <div class="text-1">
            <p>Welcome back, <?php echo htmlspecialchars( $user['parent_name']); ?>!</p>
        </div>
        <div class="student-details mt-4">
        <h3>Your Children</h3>
      

<div class="student-details mt-4">
    <ul class="list-group">
        <?php if (!empty($students)): // Check if $students array has data ?>
            <?php foreach ($students as &$student): // Loop through each student ?>
                <li class="list-group-item">
                    <strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_number']); ?><br>
                    <strong>Student Name:</strong> <?php echo htmlspecialchars($student['student_name']); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($student['student_email']); ?><br>
                    <strong>Course:</strong> <?php echo htmlspecialchars($student['student_course']); ?><br>
                    <strong>Contact Number:</strong> <?php echo htmlspecialchars($student['student_contact_number1']); ?>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li class="list-group-item">No student information available.</li>
        <?php endif; ?>
    </ul>
        </div>
            <?php endif; ?>
        <?php if ($displayRole === 'Student'): ?>
<main class="main-container">
    <div class="container-fluid">
        <div class="main-title">
            <h2>Student Dashboard</h2>
        </div>
        <?php if (!empty($notifications)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php foreach ($notifications as &$notification): ?>
                    <div class="notification-item">
                        <?php echo htmlspecialchars($notification['message']); ?>
                        <form method="post" action="" class="d-inline">
                            <input type="hidden" name="notification_id" value="<?php echo htmlspecialchars($notification['id']); ?>">
                            <button type="submit" class="btn btn-link btn-sm">Close</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php else: ?>
            <p>No notifications at this time.</p>
        <?php endif; ?>
        <!-- Add other dashboard content here -->
    </div>
        <div class="main-cards">
            <div class="cards">
                <div class="card-inner">
                    <h3>Recent Grades</h3>
                    <span class="material-symbols-outlined">grade</span>
                </div>
                <h1><?php echo htmlspecialchars($recentGrades); ?>%</h1>
            </div>
            <div class="cards">
                <div class="card-inner">
                    <h3>Notices</h3>
                    <span class="material-symbols-outlined">event</span>
                </div>
                <h1><?php echo htmlspecialchars($noticesCount); ?></h1>
            </div>
            <div class="cards">
               <div class="card-inner">
                   <h3>Enrolled Courses</h3>
                   <span class="material-symbols-outlined">assignment</span>
              </div>
             <h1><?php echo htmlspecialchars($course_count); ?></h1>
           </div>
            <div class="cards">
            <div class="card-inner">
            <h3>Attendance</h3>
    <span class="material-symbols-outlined">check_circle</span>
</div>
<?php if (!empty($attendance_percentages)): ?>
    <?php foreach ($attendance_percentages as $course_name => $attendance_percentage): ?>
        <?php if ($attendance_percentage === 'No data available'): ?> <!-- Display message if no data is available -->
            <div class="attendance-info">
                <h1>No data available</h1>
            </div>
        <?php elseif ($attendance_percentage > 0): ?> <!-- Only display if attendance percentage is greater than 0 -->
            <div class="attendance-info">
                <h1><?php echo htmlspecialchars($course_name); ?> - <?php echo htmlspecialchars($attendance_percentage) . '%'; ?></h1>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php else: ?>
    <h1>No attendance data available.</h1>
<?php endif; ?>
            </div>
        </div>
        
        <div class="text-1">
            <p>Welcome back, <?php echo htmlspecialchars( $user['student_name']); ?>!</p>
        </div>
        <div class="student-details mt-4">
            <h3>Profile Information</h3>
            <ul class="list-group">
            <li class="list-group-item"><strong>Student ID:</strong> <?php echo htmlspecialchars( $user['student_number']); ?></li>
                <li class="list-group-item"><strong>Email:</strong> <?php echo htmlspecialchars( $user['student_email']); ?></li>
                <li class="list-group-item"><strong>Course:</strong> <?php echo htmlspecialchars( $user['student_course']); ?></li>
                <li class="list-group-item"><strong>contact Number:</strong> <?php echo htmlspecialchars( $user['student_contact_number1']); ?></li>
            </ul>

            </ul>
        </div>
        <?php endif; ?>
        <?php if ($displayRole === 'Admin'): ?>
        <main class="main-container">
            <div class="container-fluid">
                <div class="main-title">
                    <h2> Admin Dashboard</h2>
                </div>
              
                <div class="main-cards">
                    <div class="cards">
                        <div class="card-inner">
                            <h3>Students</h3>
                            <span class="material-symbols-outlined">school</span>
                        </div>
                        <h1><?php echo htmlspecialchars($total_students); ?></h1>
                    </div>
                    <div class="cards">
                        <div class="card-inner">
                            <h3>Users</h3>
                            <span class="material-symbols-outlined">groups</span>
                        </div>
                        <h1><?php echo htmlspecialchars($total_users); ?></h1>
                    </div>
                    <div class="cards">
                        <div class="card-inner">
                            <h3>Parents</h3>
                            <span class="material-symbols-outlined">grid_view</span>
                        </div>
                        <h1><?php echo htmlspecialchars($total_parents); ?></h1>
                    </div>
                    <div class="cards">
                        <div class="card-inner">
                            <h3>Deposited Fees</h3>
                            <span class="material-symbols-outlined">
                            <span class="material-symbols-outlined">attach_money</span>
                            </span>
                        </div>
                        <h1> 
                    <div>
                        <strong>Paid:</strong> <small>Ksh</small> <?php echo number_format($total_deposited_fees_paid, 2); ?><br>
                        <strong>Pending:</strong> <small>Ksh</small> <?php echo number_format($pending_fees, 2); ?>
                    </div></h1>
                    </div>
                </div>
                <div class="text-1">
                    <p>Welcome Back Admin!</p>
                </div>
                <?php endif; ?>
                <footer class="main-footer px-3">
                    <div class="pull-right hidden-xs">
                    <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>
                    </div>
                </footer>
            </div>
        </main>
       
        <!--main-->
    </div>

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