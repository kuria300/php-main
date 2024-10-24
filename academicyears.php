<?php
session_start();
 include('DB_connect.php');

 include('res/functions.php');
 
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

if (isset($_POST['add_academic_year'])) {
    // Retrieve and sanitize form inputs
    $academic_year = trim($_POST['academic_year']);
    $description = trim($_POST['description']);

    // Validation
    $errors = [];
    if (empty($academic_year)) {
        $errors[] = 'Academic Year is required.';
    }
    if (empty($description)) {
        $errors[] = 'Description is required.';
    }

    if (empty($errors)) {
        // Check if the academic year already exists
        $stmt = $connect->prepare("SELECT academic_year FROM academic_years WHERE academic_year = ?");
        $stmt->bind_param("s", $academic_year);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = 'Academic Year already exists.';
        } else {
            // Insert new academic year
            $stmt = $connect->prepare("INSERT INTO academic_years (academic_year, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $academic_year, $description);

            if ($stmt->execute()) {
                header("Location: academicyears.php?msg=add");
                exit(); // Ensure no further code is executed
            } else {
                $errors[] = 'Failed to add academic year.';
            }
            $stmt->close();
        }
    }
}

if (isset($_POST['update_academic_year'])) {
    // Retrieve and sanitize form inputs
    $academic_year_id = isset($_POST['academic_id']) ? trim($_POST['academic_id']) : ''; // Updated field name
    $academic_year = isset($_POST['academic_year']) ? trim($_POST['academic_year']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // Check if there are no validation errors
    if (!empty($academic_year) && !empty($description)) {
        // Prepare and execute the update statement
        $stmt = $connect->prepare("UPDATE academic_years SET academic_year = ?, description = ? WHERE academic_year_id = ?");
        
        if ($stmt === false) {
            die('Prepare failed: ' . htmlspecialchars($connect->error));
        }

        $stmt->bind_param("ssi", $academic_year, $description, $academic_year_id);

        if ($stmt->execute()) {
            // Redirect to the same page with a success message
            header("Location: academicyears.php?msg=edit");
            exit(); // Ensure no further code is executed
        } else {
            die('Execute failed: ' . htmlspecialchars($stmt->error));
        }

        $stmt->close();
    } 
}


if (isset($_POST['delete_academic'])) {
    // Retrieve the grade ID to be deleted
    $grade_id = $_POST['year_id'];

    // Validate input
    if (!is_numeric($grade_id)) {
        die("Invalid grade ID.");
    }

    // Prepare SQL statement to delete the grade
    $stmt = $connect->prepare("DELETE FROM academic_years WHERE academic_year_id = ?");
    $stmt->bind_param("i", $grade_id);

    // Execute the statement
    if ($stmt->execute()) {
        header('Location: academicyears.php?msg=delete');
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
    <title>Academic Years</title>
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
                    <li class="breadcrumb-item active"><a href="grades.php">Grades Management</a></li>
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
                        // Fetch academic year details
                        $year_id = $_GET['id'];
                        $stmt = $connect->prepare("
                            SELECT academic_year_id, academic_year, description
                            FROM academic_years
                            WHERE academic_year_id = ?");
                        $stmt->bind_param("i", $year_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $academic_year = $result->fetch_assoc();
                
                        ?>
                        <h1 class="mt-2 head-update">Edit Academic Year</h1>
                        <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                            <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="academicyears.php" style="color: #f8f9fa;">Academic Years Management</a></li>
                            <li class="breadcrumb-item active">Edit Academic Year</li>
                        </ol>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <span class="material-symbols-outlined">edit</span> Academic Year Edit Form
                                    </div>
                                    <div class="card-body">
                                    <form method="post" action="academicyears.php"> <!-- Ensure the action points to the correct PHP file -->
    <div class="form-group mb-3">
        <label for="academic_id">Academic Year ID:</label>
        <input type="number" id="academic_id" name="academic_id" class="form-control" value="<?php echo htmlspecialchars($academic_year['academic_year_id']); ?>" required readonly>
    </div>
    <div class="form-group mb-3">
        <label for="academic_year">Academic Year:</label>
        <input type="text" id="academic_year" name="academic_year" class="form-control" value="<?php echo htmlspecialchars($academic_year['academic_year']); ?>" required>
    </div>
    <div class="form-group mb-3">
        <label for="description">Description:</label>
        <textarea id="description" name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($academic_year['description']); ?></textarea>
    </div>
    <button type="submit" name="update_academic_year" class="btn btn-primary">Update Academic Year</button>
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
            } 
        }else{
            ?>
            <h1 class="mt-2 head-update">Academic year Management</h1>
            <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
                <li class="breadcrumb-item active">Academic Year Management</li>
            </ol>
            <?php
            if (isset($_GET['msg'])) {
                if ($_GET['msg'] == 'add') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>Academic year added successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'edit') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>Academic year updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if ($_GET['msg'] == 'delete') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>Academic year deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
            }
            ?>
           <div class="card mb-4">
    <div class="card-header">
        <div class="row">
            <div class="col-md-6">
                <span class="material-symbols-outlined">calendar_today</span> All Academic Years
            </div>
            <div class="col-md-6 d-flex justify-content-end align-items-center">
                <!-- Search Bar -->
                <div class="mb-0 me-3">
                    <input type="text" id="searchBar" class="form-control" placeholder="Search Academic Years..." onkeyup="searchAcademicYears()">
                </div>
                <!-- Button to trigger modal -->
                <?php if ($displayRole === 'Admin'): ?>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addAcademicYearModal">
                    Add Academic Year
                </button>
                <?php endif; ?>
            </div>
            <!-- Modal for adding an academic year -->
            <div class="modal fade" id="addAcademicYearModal" tabindex="-1" aria-labelledby="addAcademicYearModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addAcademicYearModalLabel">Add Academic Year</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Form to add new academic year -->
                            <form method="POST" action="academicyears.php">
                                <div class="form-group mb-3">
                                    <label for="academic_year">Academic Year:</label>
                                    <input type="text" id="academic_year" name="academic_year" class="form-control" required>
                                </div>
                                <div class="form-group mb-3">
                                    <label for="description">Description:</label>
                                    <textarea id="description" name="description" class="form-control" rows="3" required></textarea>
                                </div>
                                <button type="submit" name="add_academic_year" class="btn btn-primary mt-3">Add Academic Year</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
// Number of records per page
// Number of records per page
$records_per_page = 5;

// Calculate the total number of pages
$total_query = "SELECT COUNT(*) as total FROM academic_years";
$total_result = $connect->query($total_query);

if ($total_result) {
    $total_row = $total_result->fetch_assoc();
    $total_records = $total_row['total'];
    $total_pages = ceil($total_records / $records_per_page);
} else {
    // Handle query error
    die("Error fetching total records: " . $connect->error);
}

// Get the current page number from query string, default to 1
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;

// Ensure the page number is within range
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages) $current_page = $total_pages;

// Calculate the offset for the SQL query
$offset = ($current_page - 1) * $records_per_page;

// Fetch data with pagination
$query = "SELECT academic_year_id, academic_year, description FROM academic_years LIMIT ?, ?";
$stmt = $connect->prepare($query);
if ($stmt) {
    $stmt->bind_param('ii', $offset, $records_per_page);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Handle prepare statement error
    die("Error preparing query: " . $connect->error);
}
?>


                <div class="table-responsive">
                    <div class="card-body">
                        <div class="grades-section mt-1 mb-2">
                            <h3>Academic Year</h3>
                            <div class="table-responsive">
                            <table class="table table-striped" id="academicTable">
    <thead>
    <tr>
        
        <th>Academic Year</th>
        <th>Description</th>
        <th>Actions</th>
    </tr>
<tbody>
    <?php
    // Fetch data from the database

    while ($row = $result->fetch_assoc()) {
        $year_id = urlencode($row['academic_year_id']);
        $delete_modal_id = "deleteYearModal{$row['academic_year_id']}";
        $delete_modal_label = "deleteYearModalLabel{$row['academic_year_id']}";
        $view_modal_id = "viewYearModal{$row['academic_year_id']}";
        $view_modal_label = "viewYearModalLabel{$row['academic_year_id']}";

        echo "<tr>
            
            <td>" . htmlspecialchars($row['academic_year']) . "</td>
            <td>" . htmlspecialchars($row['description']) . "</td>
            <td>
                <button type='button' class='btn btn-info btn-sm' data-bs-toggle='modal' data-bs-target='#{$view_modal_id}'>
                    View
                </button>";
        // Conditionally render the Edit and Delete buttons for Admins
        if ($displayRole === 'Admin') {
            echo "   <a href='academicyears.php?action=edit&id={$year_id}' class='btn btn-warning btn-sm'>Edit</a>
                    <button type='button' class='btn btn-danger btn-sm' data-bs-toggle='modal' data-bs-target='#{$delete_modal_id}'>
                        <i class='bi bi-trash'></i>
                    </button>

                    <!-- Modal for deleting an academic year -->
                    <div class='modal fade' id='{$delete_modal_id}' tabindex='-1' aria-labelledby='{$delete_modal_label}' aria-hidden='true'>
                        <div class='modal-dialog'>
                            <div class='modal-content'>
                                <div class='modal-header'>
                                    <h5 class='modal-title' id='{$delete_modal_label}'>Confirm Delete</h5>
                                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                </div>
                                <div class='modal-body'>
                                    Are you sure you want to delete this academic year record?
                                </div>
                                <div class='modal-footer'>
                                    <form action='academicyears.php?action=delete' method='post'>
                                        <input type='hidden' name='year_id' value='{$row['academic_year_id']}'>
                                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                        <button type='submit' name='delete_academic' class='btn btn-danger'>Delete</button>
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
                        <h5 class='modal-title' id='{$view_modal_label}'>View Academic Year Details</h5>
                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                    </div>
                    <div class='modal-body'>
                        <p><strong>Year ID:</strong> {$row['academic_year_id']}</p>
                        <p><strong>Academic Year:</strong> {$row['academic_year']}</p>
                        <p><strong>Description:</strong> {$row['description']}</p>
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
                            <!-- Pagination Controls -->
                            <nav aria-label="Page navigation">
    <ul class="pagination">
        <!-- Previous Page Link -->
        <?php if ($current_page > 1): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $current_page - 1; ?>">Previous</a></li>
        <?php else: ?>
            <li class="page-item disabled"><span class="page-link">Previous</span></li>
        <?php endif; ?>

        <!-- Page Number Links -->
        <?php for ($page = 1; $page <= $total_pages; $page++): ?>
            <?php if ($page == $current_page): ?>
                <li class="page-item active"><span class="page-link"><?php echo $page; ?></span></li>
            <?php else: ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page; ?>"><?php echo $page; ?></a></li>
            <?php endif; ?>
        <?php endfor; ?>

        <!-- Next Page Link -->
        <?php if ($current_page < $total_pages): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $current_page + 1; ?>">Next</a></li>
        <?php else: ?>
            <li class="page-item disabled"><span class="page-link">Next</span></li>
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
    </div>
</main>
        <!--main-->
    </div>

    <script>
        function searchAcademicYears() {
    var input, filter, table, rows, cells, i, j, match;
    input = document.getElementById("searchBar");
    filter = input.value.toLowerCase();
    table = document.getElementById("academicTable");
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