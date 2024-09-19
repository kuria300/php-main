<?php
include("DB_connect.php");
session_start();

include('res/functions.php');
 
$message = $error = "";

if (isset($_SESSION["id"]) && isset($_SESSION["role"])) {
   
    $userRole = $_SESSION["role"];
    $userId = $_SESSION["id"];
    $adminType = $_SESSION["admin_type"] ?? '';
    $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
    $text_size = isset($_COOKIE['text_size']) ? $_COOKIE['text_size'] : 'medium';
    // Map roles to display names
    $roleNames = [
        "1" => "Admin",
        "2" => "Student",
        "3" => "Parent"
    ];
    $displayRole = $roleNames[$userRole] ?? "Parent";
    
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $courseId = intval($_POST['course_id']);
    
    // Check if the student is already enrolled in the selected course
    $checkQuery = "SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?";
    $stmt = $connect->prepare($checkQuery);
    $stmt->bind_param('ii', $userId, $courseId);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;

    // Check if the student is already enrolled in any course directly in the students table
    $checkStudentQuery = "SELECT student_course FROM students WHERE student_id = ?";
    $stmtStudent = $connect->prepare($checkStudentQuery);
    $stmtStudent->bind_param('i', $userId);
    $stmtStudent->execute();
    $studentResult = $stmtStudent->get_result();
    $student = $studentResult->fetch_assoc();

    if ($student) {
        // Decode the current list of course names
        $currentCourses = json_decode($student['student_course'], true);

        // If decoding fails or student_course was not a valid JSON array, handle single course scenario
        if (!is_array($currentCourses)) {
            // If student_course is a string and not a JSON array
            if (!empty($student['student_course'])) {
                $currentCourses = [$student['student_course']];
            } else {
                $currentCourses = [];
            }
        }
        // Check if the student is already enrolled in 3 courses
        if (count($currentCourses) >= 2) {
            $error = "You cannot enroll in more than 2 courses.";
        } else {
            if (!$exists) {
                // Add course to the enrollments table
                $insertQuery = "INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)";
                $stmt = $connect->prepare($insertQuery);
                $stmt->bind_param('ii', $userId, $courseId);
                if ($stmt->execute()) {
                    // Fetch the new course name
                    $courseNameQuery = "SELECT course_name FROM courses WHERE course_id = ?";
                    $stmt = $connect->prepare($courseNameQuery);
                    $stmt->bind_param('i', $courseId);
                    $stmt->execute();
                    $courseResult = $stmt->get_result();
                    $courseRow = $courseResult->fetch_assoc();
                    $courseName = $courseRow['course_name'];
                    
                    // Add the new course name if not already present
                    if (!in_array($courseName, $currentCourses)) {
                        $currentCourses[] = $courseName;

                        // Update the student_course field with the updated list
                        $updatedCourses = json_encode($currentCourses);
                        $updateQuery = "UPDATE students SET student_course = ? WHERE student_id = ?";
                        $stmt = $connect->prepare($updateQuery);
                        $stmt->bind_param('si', $updatedCourses, $userId);
                        if ($stmt->execute()) {
                            $message = "Successfully enrolled in the course.";
                        } else {
                            $error = "Failed to update student courses.";
                        }
                    } else {
                        $error = "Already enrolled in this course.";
                    }
                } else {
                    $error = "Failed to enroll in the course.";
                }
            } else {
                $error = "Already enrolled in this course.";
            }
        }
    } else {
        $error = "Student not found.";
    }
}

// Fetch courses enrolled by the student with course names
$query = "
    SELECT c.course_id, c.course_name, c.course_number, c.course_fee
    FROM courses c
    INNER JOIN enrollments e ON c.course_id = e.course_id
    WHERE e.student_id = ?
";
$stmt = $connect->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$courseId = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
$studentId = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;

// Ensure both IDs are valid
if ($courseId > 0 && $studentId > 0) {
    // Prepare the query to delete the student from the course
    $query = "
        DELETE FROM enrollments
        WHERE course_id = ? AND student_id = ?
    ";

    $stmt = $connect->prepare($query);
    $stmt->bind_param("ii", $courseId, $studentId);

    if ($stmt->execute()) {
        header('Location: addcourse.php?msg=delete'); // Redirect to avoid form resubmission
        exit();
    } else {
        echo 'Error: ' . $stmt->error;
    }

    $stmt->close();
}
// Fetch all courses for dropdown
$queryAllCourses = "SELECT course_id, course_name FROM courses";
$resultAllCourses = $connect->query($queryAllCourses);

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
    <title>Add Course</title>
    <link rel="icon" href="logo2.png">
    <link id="theme-style" rel="stylesheet" href="css/<?= htmlspecialchars($theme); ?>.css">
<link id="text-size-style" rel="stylesheet" href="css/<?= htmlspecialchars($text_size); ?>.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!--Boostraplinks-->
    <!--font awesome cdn-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!--font awesome cdn-->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <!--custom css-->
    <link rel="stylesheet" href="css/Profile.css">
    
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
                placeholder="search"
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
            <h1 class="mt-2 head-update">Enrolled Courses</h1>
        <ol class="breadcrumb mb-4 small">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Enrolled Courses</li>
        </ol>
        
        <?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
        
        <!-- Enrollment Form -->
        <?php if ($displayRole === 'Student'): ?>
        <div class="mb-4">
            <h5>Enroll in a Course</h5>
            <form action="addcourse.php" method="post">
                <div class="mb-3">
                    <label for="courseSelect" class="form-label">Select Course</label>
                    <select class="form-select" id="courseSelect" name="course_id" required>
                        <option value="">Select a course</option>
                        <?php
                        if ($resultAllCourses->num_rows > 0) {
                            while ($row = $resultAllCourses->fetch_assoc()) {
                                $course_id = htmlspecialchars($row['course_id']);
                                $course_name = htmlspecialchars($row['course_name']);
                                echo '<option value="' . $course_id . '">' . $course_name . '</option>';
                            }
                        } else {
                            echo '<option value="" disabled>No courses available</option>';
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Enroll</button>
            </form>
        </div>
       
        <!-- Enrolled Courses Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <span class="material-symbols-outlined">manage_accounts</span> Enrolled Courses List
                    </div>
                    <div class="card-body">
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
                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#viewCourseModal' . $course_id . '">
                                            <i class="bi bi-eye"></i> View
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
                                       
                                    </div>
                                </td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';
                        } else {
                            echo '<p>No courses enrolled.</p>';
                        }
                        ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($displayRole === 'Admin'): ?>
                    <?php
            if (isset($_GET['msg'])) {
                if ($_GET['msg'] == 'delete') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>Student Unenrolled successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
            }
            ?>
<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header">
                <span class="material-symbols-outlined">manage_accounts</span> Enrolled Courses List
            </div>
            <div class="card-body">
                <?php
                include('DB_connect.php');

                // Pagination variables
                $itemsPerPage = 10; // Number of items per page
                $currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
                $currentPage = $currentPage > 0 ? $currentPage : 1;
                $offset = ($currentPage - 1) * $itemsPerPage;

                // Total items query
                $totalItemsQuery = "
                SELECT COUNT(DISTINCT c.course_id) AS total_items
                FROM courses c
                LEFT JOIN enrollments e ON c.course_id = e.course_id
                ";
                
                $totalItemsStmt = $connect->prepare($totalItemsQuery);
                $totalItemsStmt->execute();
                $totalItemsResult = $totalItemsStmt->get_result();
                $totalItemsRow = $totalItemsResult->fetch_assoc();
                $totalItems = $totalItemsRow['total_items'];
                $totalPages = ceil($totalItems / $itemsPerPage);

                // Main query with LIMIT and OFFSET
                $query = "
                SELECT
                    c.course_id,
                    c.course_name,
                    c.course_number,
                    c.course_fee,
                    s.student_id,
                    s.student_name
                FROM
                    courses c
                LEFT JOIN
                    enrollments e ON c.course_id = e.course_id
                LEFT JOIN
                    students s ON e.student_id = s.student_id
                GROUP BY
                    c.course_id, c.course_name, c.course_number, c.course_fee, s.student_id, s.student_name
                LIMIT ? OFFSET ?
                ";
                
                // Prepare and execute the query
                $stmt = $connect->prepare($query);
                $stmt->bind_param("ii", $itemsPerPage, $offset);
                $stmt->execute();
                $result = $stmt->get_result();

                // Create an array to group students by course
                $courses = [];
                while ($row = $result->fetch_assoc()) {
                    $course_id = htmlspecialchars($row['course_id']);
                    $course_name = htmlspecialchars($row['course_name']);
                    $course_number = htmlspecialchars($row['course_number']);
                    $course_fee = htmlspecialchars($row['course_fee']);
                    $student_id = htmlspecialchars($row['student_id']);
                    $student_name = htmlspecialchars($row['student_name']);
                    
                    // Initialize course array if not set
                    if (!isset($courses[$course_id])) {
                        $courses[$course_id] = [
                            'course_name' => $course_name,
                            'course_number' => $course_number,
                            'course_fee' => $course_fee,
                            'students' => []
                        ];
                    }
                    
                    // Add student to the course
                    if ($student_id) {
                        $courses[$course_id]['students'][] = [
                            'student_id' => $student_id,
                            'student_name' => $student_name
                        ];
                    }
                }

                // Render the table with courses and dropdowns
                if (count($courses) > 0) {
                    echo '<table class="table table-bordered" id="courseTable">';
                    echo '<thead><tr><th>Course ID</th><th>Course Name</th><th>Course Number</th><th>Course Fee</th><th>Enrolled Students</th><th>Action</th></tr></thead><tbody>';
                    
                    foreach ($courses as $course_id => $course_data) {
                        $course_name = $course_data['course_name'];
                        $course_number = $course_data['course_number'];
                        $course_fee = $course_data['course_fee'];
                        $students = $course_data['students'];
                        
                        echo '<tr>';
                        echo '<td>' . $course_id . '</td>';
                        echo '<td>' . $course_name . '</td>';
                        echo '<td>' . $course_number . '</td>';
                        echo '<td>' . $course_fee . '</td>';
                        echo '<td>';
                        echo '<form method="POST" action="addcourse.php">';
                        echo '<select class="form-select" name="student_id" aria-label="Select student">';
                        echo '<option value="">Select student</option>';
                        
                        foreach ($students as $student) {
                            $student_name = htmlspecialchars($student['student_name']);
                            $student_id = htmlspecialchars($student['student_id']);
                            echo '<option value="' . $student_id . '">' . $student_name . '</option>';
                        }
                        
                        echo '</select>';
                        echo '<input type="hidden" name="course_id" value="' . $course_id . '">';
                        echo '<button type="submit" class="btn btn-danger btn-sm mt-2">Unenroll</button>';
                        echo '</form>';
                        echo '</td>';
                        echo '<td>
                            <div class="btn-group" role="group" aria-label="Actions">
                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#viewCourseModal' . $course_id . '">
                                    <i class="bi bi-eye"></i> View
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
                                                <p><strong>Enrolled Students:</strong> ' . count($students) . '</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                    echo '<p>No courses available.</p>';
                }
                ?>

                <!-- Pagination Controls -->
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <!-- Previous Button -->
                        <?php if ($currentPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $currentPage - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">Previous</span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link" aria-label="Previous">Previous</span>
                            </li>
                        <?php endif; ?>

                        <!-- Page Number Buttons -->
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <!-- Next Button -->
                        <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $currentPage + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">Next</span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link" aria-label="Next">Next</span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
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
    </script>
</body>
</html>