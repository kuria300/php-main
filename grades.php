<?php
session_start();
 include('DB_connect.php');


 if (!isset($_SESSION["role"])) {
    header("Location: Admin.php");
    exit;
}

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
    $displayRole = $roleNames[$userRole] ?? "Parent";

}
if (isset($_POST['add_grade'])) {
    // Retrieve form data
    $student_id = $_POST['student_id'];
    $grade = $_POST['grade'];
    $academic_year_id = $_POST['academic_year_id'];

    // Validate inputs
    if (!is_numeric($student_id) || !is_numeric($grade) || $grade < 0 || $grade > 100 || !is_numeric($academic_year_id)) {
        die("Invalid input data.");
    }

    // Prepare SQL statement to insert data
    $stmt = $connect->prepare("INSERT INTO grades (student_id, grade, academic_year_id) VALUES (?, ?, ?)");
    $stmt->bind_param("idi", $student_id, $grade, $academic_year_id);

    // Execute the statement
    if ($stmt->execute()) {
        header('Location: grades.php?msg=add');
        exit();
    } else {
        echo "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
    }

    // Close the statement
    $stmt->close();
}
if (isset($_POST['update_grade'])) {
    // Get form data
    $grade_id = $_POST['grade_id']; // Assuming you are using this to identify which grade to update
    $grade = $_POST['grade'];
    $academic_year_id = $_POST['academic_year_id']; // Add academic_year_id

    // Validate inputs
    if (!is_numeric($grade_id) || !is_numeric($grade) || $grade < 0 || $grade > 100 || !is_numeric($academic_year_id)) {
        die("Invalid input data.");
    }

    // Prepare and execute the statement to update the grade
    $stmt = $connect->prepare("UPDATE grades SET grade = ?, academic_year_id = ? WHERE grade_id = ?");
    $stmt->bind_param("dii", $grade, $academic_year_id, $grade_id);

    if ($stmt->execute()) {
        header('Location: grades.php?msg=edit');
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    // Close the statement
    $stmt->close();
}
// Handle delete action
if (isset($_POST['delete_grade'])) {
    // Retrieve the grade ID to be deleted
    $grade_id = $_POST['grade_id'];

    // Validate input
    if (!is_numeric($grade_id)) {
        die("Invalid grade ID.");
    }

    // Prepare SQL statement to delete the grade
    $stmt = $connect->prepare("DELETE FROM grades WHERE grade_id = ?");
    $stmt->bind_param("i", $grade_id);

    // Execute the statement
    if ($stmt->execute()) {
        header('Location: grades.php?msg=delete');
        exit();
    } else {
        echo "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
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
    <title>Grades</title>
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
                <h1 class="mt-2 head-update">Grades Management</h1>
                <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="grades.php">Grades Management</a></li>
                    <li class="breadcrumb-item active">Add Grade</li>
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
                                <span class="material-symbols-outlined text-bold">grade</span> Add New Grade
                            </div>
                            <div class="card-body">
                                <!-- Form to add new grade -->
                                <form method="POST" action="grades.php">
                                    <div class="form-group mb-3">
                                        <label for="student_id">Student ID:</label>
                                        <input type="number" id="student_id" name="student_id" class="form-control" required>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label for="grade">Grade (%):</label>
                                        <input type="number" id="grade" name="grade" class="form-control" min="0" max="100" step="0.01" required>
                                    </div>
                                    <button type="submit" name="add_grade" class="btn btn-primary">Add Grade</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
            } else if ($_GET['action'] == 'edit') {
                if (isset($_GET['id'])) {
                    // Fetch grade details
                    $grade_id = $_GET['id'];
                    $stmt = $connect->prepare("
                        SELECT g.grade_id, g.grade, s.student_name, s.student_number, g.academic_year_id, ay.academic_year
                        FROM grades g
                        JOIN students s ON g.student_id = s.student_id
                        JOIN academic_years ay ON g.academic_year_id = ay.academic_year_id
                        WHERE g.grade_id = ?");
                    $stmt->bind_param("i", $grade_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $grade = $result->fetch_assoc();
                    
                    // Fetch academic years for the dropdown
                    $academic_year_query = "SELECT academic_year_id, academic_year FROM academic_years";
                    $academic_year_result = $connect->query($academic_year_query);
            
                    ?>
                    <h1 class="mt-2 head-update">Edit Grade</h1>
                    <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                        <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="grades.php" style="color: #f8f9fa;">Grades Management</a></li>
                        <li class="breadcrumb-item active">Edit Grade</li>
                    </ol>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <span class="material-symbols-outlined">edit</span> Grade Edit Form
                                </div>
                                <div class="card-body">
                                    <form action="grades.php" method="post">
                                    <div class="form-group mb-3">
                                <label for="grade_id">Grade ID:</label>
                                <input type="number" id="grade_id" name="grade_id" class="form-control" value="<?php echo htmlspecialchars($grade['grade_id']); ?>" required readonly>
                            </div>
                                        <div class="form-group mb-3">
                                            <label for="grade">Grade (%):</label>
                                            <input type="number" id="grade" name="grade" class="form-control" value="<?php echo htmlspecialchars($grade['grade']); ?>" min="0" max="100" step="0.01" required>
                                        </div>
                                        <div class="form-group mb-3">
                            <label for="academic_year_id">Academic Year:</label>
                            <select id="academic_year_id" name="academic_year_id" class="form-control" required>
                                <option value="" disabled>Select Academic Year</option>
                                <?php
                                while ($academic_year = $academic_year_result->fetch_assoc()) {
                                    $selected = ($academic_year['academic_year_id'] == $grade['academic_year_id']) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($academic_year['academic_year_id']) . '" ' . $selected . '>' . htmlspecialchars($academic_year['academic_year']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                                        <button type="submit" name="update_grade" class="btn btn-primary">Update Grade</button>
                                    </form>
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
            } else if ($_GET['action'] == 'delete') {
                if (isset($_GET['id'])) {
                    $grade_id = $_GET['id'];
                    $stmt = $connect->prepare("
                        SELECT g.grade_id, g.grade, s.student_name, ay.academic_year
                        FROM grades g
                        JOIN students s ON g.student_id = s.student_id
                        JOIN academic_years ay ON g.academic_year_id = ay.academic_year_id
                        WHERE g.grade_id = ?");
                    $stmt->bind_param("i", $grade_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $grade = $result->fetch_assoc();
                    ?>
                    <h1 class="mt-2 head-update">Delete Grade</h1>
                    <ol class="breadcrumb mb-4 small">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="grades.php">Grades Management</a></li>
                        <li class="breadcrumb-item active">Delete Grade</li>
                    </ol>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <span class="material-symbols-outlined">delete</span> Confirm Delete
                                </div>
                                <div class="card-body">
                                    <p>Are you sure you want to delete the following grade record?</p>
                                    <ul>
                                        <li><strong>Grade ID:</strong> <?php echo htmlspecialchars($grade['grade_id']); ?></li>
                                        <li><strong>Student Name:</strong> <?php echo htmlspecialchars($grade['student_name']); ?></li>
                                        <li><strong>Grade (%):</strong> <?php echo htmlspecialchars($grade['grade']); ?></li>
                                        <li><strong>Academic Year:</strong> <?php echo htmlspecialchars($grade['academic_year']); ?></li>
                                    </ul>
                                    <form action="grades.php?action=delete" method="post">
                                        <input type="hidden" name="grade_id" value="<?php echo htmlspecialchars($grade['grade_id']); ?>">
                                        <button type="submit" name="delete_grade" class="btn btn-danger">Confirm Delete</button>
                                        <a href="grades.php" class="btn btn-secondary">Cancel</a>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            }
        } else {
            ?>
            <h1 class="mt-2 head-update">Grades Management</h1>
            <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
    <li class="breadcrumb-item" style="color: #f8f9fa;"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
    <li class="breadcrumb-item active">Grades Management</li>
</ol>
            <?php
            if (isset($_GET['msg'])) {
                if ($_GET['msg'] == 'add') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>Grade added successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'edit') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>Grade updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if($_GET['msg'] == 'delete') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>Grade deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
            }
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <span class="material-symbols-outlined">grade</span> All Grades
                        </div>
                        <div class="col-md-6 d-flex justify-content-end align-items-center">
                            <!-- Search Bar -->
                            <div class="mb-0 me-3">
                                <input type="text" id="searchBar" class="form-control" placeholder="Search Grades..." onkeyup="searchGrades()">
                            </div>
                            <!-- Button to trigger modal -->
                            <?php if ($displayRole === 'Admin'): ?>
                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                                Add Grade
                            </button>
                            <?php endif; ?>
                        </div>
                        <!-- Modal for adding a grade -->
                        <div class="modal fade" id="addGradeModal" tabindex="-1" aria-labelledby="addGradeModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="addGradeModalLabel">Add Grade</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <!-- Form to add new grade -->
                                        <form method="POST" action="grades.php">
                                        <div class="form-group mb-3">
        <label for="student_id">Student:</label>
        <select id="student_id" name="student_id" class="form-control" required>
            <option value="" disabled selected>Select a student</option>
            <?php
            // Fetch students from the database
            $student_query = "SELECT student_id, student_name, student_number FROM students";
            $student_result = $connect->query($student_query);

            // Check if the query returned any results
            if ($student_result->num_rows > 0) {
                // Iterate through the results and create an option for each student
                while ($student = $student_result->fetch_assoc()) {
                    echo '<option value="' . htmlspecialchars($student['student_id']) . '">' . htmlspecialchars($student['student_name']) . ' (' . htmlspecialchars($student['student_number']) . ')</option>';
                }
            } else {
                echo '<option value="" disabled>No students found</option>';
            }
            ?>
        </select>
    </div>
                                            <div class="form-group mb-3">
                                                <label for="grade">Grade (%):</label>
                                                <input type="number" id="grade" name="grade" class="form-control" min="0" max="100" step="0.01" required>
                                            </div>
                                            <div class="form-group mb-3">
        <label for="academic_year_id">Academic Year:</label>
        <select id="academic_year_id" name="academic_year_id" class="form-control" required>
            <option value="" disabled selected>Select Academic Year</option>
            <?php
            $academic_year_query = "SELECT academic_year_id, academic_year FROM academic_years";
            $academic_year_result = $connect->query($academic_year_query);
  
            while ($academic_year = $academic_year_result->fetch_assoc()) {
                echo '<option value="' . htmlspecialchars($academic_year['academic_year_id']) . '">' . htmlspecialchars($academic_year['academic_year']) . '</option>';
            }
            ?>
        </select>
    </div>
                                            <input type="hidden" name="grade_id" value="<?php echo htmlspecialchars($grade['grade_id']); ?>">
                                            <button type="submit" name="add_grade" class="btn btn-primary mt-3">Add Grade</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <div class="card-body">
                        <div class="grades-section mt-1 mb-2">
                            <h3>Grades</h3>
                            <div class="table-responsive">
                            <table class="table table-striped" id="gradesTable">
    <thead>
        <tr>
            <th>Student Name</th>
            <th>Grade (%)</th>
            <th>Academic Year</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php
        include('DB_connect.php');
        $results_per_page = 10;

    // Find the total number of pages
    $total_query = "SELECT COUNT(*) AS total FROM grades";
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
    if ($displayRole === 'Admin') {
        // Admins can view all grades
        $query = "SELECT g.grade_id, s.student_name, s.student_number, g.grade, ay.academic_year
                  FROM grades g
                  JOIN students s ON g.student_id = s.student_id
                  JOIN academic_years ay ON g.academic_year_id = ay.academic_year_id
                  LIMIT ? OFFSET ?";
    } elseif ($displayRole === 'Parent') {
        // Parents can view grades of their own children
        $query = "SELECT g.grade_id, s.student_name, s.student_number, g.grade, ay.academic_year
                  FROM grades g
                  JOIN students s ON g.student_id = s.student_id
                  JOIN academic_years ay ON g.academic_year_id = ay.academic_year_id
                  WHERE s.parent_id = ?
                  LIMIT ? OFFSET ?";
    } else {
        // Students can only view their own grades
        $query = "SELECT g.grade_id, s.student_name, s.student_number, g.grade, ay.academic_year
                  FROM grades g
                  JOIN students s ON g.student_id = s.student_id
                  JOIN academic_years ay ON g.academic_year_id = ay.academic_year_id
                  WHERE g.student_id = ?
                  LIMIT ? OFFSET ?";
    }
    
    $stmt = $connect->prepare($query);
    
    if ($displayRole === 'Admin') {
        $stmt->bind_param("ii", $results_per_page, $offset);

    } elseif($displayRole === 'Parent'){

        $stmt->bind_param("iii", $userId, $results_per_page, $offset);
    }
    else {
        $stmt->bind_param("iii", $userId, $results_per_page, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $grade_id = urlencode($row['grade_id']);
            $delete_modal_id = "deleteGradeModal{$row['grade_id']}";
            $delete_modal_label = "deleteGradeModalLabel{$row['grade_id']}";
            $view_modal_id = "viewGradeModal{$row['grade_id']}";
            $view_modal_label = "viewGradeModalLabel{$row['grade_id']}";

            echo "<tr>
               
                 <td>" . htmlspecialchars($row['student_name']) . ' (' . htmlspecialchars($row['student_number']) . ')' . "</td>
                <td>{$row['grade']}</td>
                <td>{$row['academic_year']}</td>
                <td>
                <button type='button' class='btn btn-info btn-sm' data-bs-toggle='modal' data-bs-target='#{$view_modal_id}'>
                    View
                </button>";
            // Conditionally render the Edit and Delete buttons for Admins
            if ($displayRole === 'Admin') {
                echo "    <a href='grades.php?action=edit&id={$grade_id}' class='btn btn-warning btn-sm'>Edit</a>
                        <button type='button' class='btn btn-danger btn-sm' data-bs-toggle='modal' data-bs-target='#{$delete_modal_id}'>
                            <i class='bi bi-trash'></i> Delete
                        </button>

                        <!-- Modal for deleting a grade -->
                        <div class='modal fade' id='{$delete_modal_id}' tabindex='-1' aria-labelledby='{$delete_modal_label}' aria-hidden='true'>
                            <div class='modal-dialog'>
                                <div class='modal-content'>
                                    <div class='modal-header'>
                                        <h5 class='modal-title' id='{$delete_modal_label}'>Confirm Delete</h5>
                                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                    </div>
                                    <div class='modal-body'>
                                        Are you sure you want to delete this grade record?
                                    </div>
                                    <div class='modal-footer'>
                                        <form action='grades.php?action=delete' method='post'>
                                            <input type='hidden' name='grade_id' value='{$row['grade_id']}'>
                                            <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                            <button type='submit' name='delete_grade' class='btn btn-danger'>Delete</button>
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
                            <h5 class='modal-title' id='{$view_modal_label}'>View Grade Details</h5>
                            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                        </div>
                        <div class='modal-body'>
                            <p><strong>Grade ID:</strong> {$row['grade_id']}</p>
                            <p><strong>Student Name:</strong> {$row['student_name']}</p>
                            <p><strong>Grade (%):</strong> {$row['grade']}</p>
                            <p><strong>Grade (%):</strong> {$row['academic_year']}</p>
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
<nav aria-label="Page navigation">
    <ul class="pagination">
        <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="grades.php?page=<?php echo $page - 1; ?>" aria-label="Previous">
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
                <a class="page-link" href="grades.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="grades.php?page=<?php echo $page + 1; ?>" aria-label="Next">
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
    </div>
</main>
        <!--main-->
    </div>

    <script>
        function searchGrades() {
    var input, filter, table, rows, cells, i, j, match;
    input = document.getElementById("searchBar");
    filter = input.value.toLowerCase();
    table = document.getElementById("gradesTable");
    rows = table.getElementsByTagName("tr");

    for (i = 1; i < rows.length; i++) { // Start from 1 to skip the header row
        cells = rows[i].getElementsByTagName("td");
        match = false;

        for (j = 0; j < cells.length; j++) {
            if (cells[j]) {
                if (cells[j].innerHTML.toLowerCase().indexOf(filter) > -1) {
                    match = true;
                }
            }
        }

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