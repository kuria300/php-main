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
    $displayRole = $roleNames[$userRole];

    
}$parentId = $_SESSION["id"]; 

// Fetch messages for students related to the logged-in parent
$stmt = $connect->prepare("
   SELECT m.id, m.message, m.created_at, s.student_name
FROM messages m
JOIN students s ON m.user_id = s.student_id
WHERE s.parent_id = ?
");
$stmt->bind_param('i', $parentId);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_notice'])) {
    $message_id = intval($_POST['notice_id']);
    $message_content = $_POST['notice_content'];

    // Prepare and execute the update statement
    $stmt = $connect->prepare("UPDATE messages SET message = ? WHERE id = ?");
    $stmt->bind_param("si", $message_content, $message_id);
    $stmt->execute();

    // Redirect back to the message management page
    header("Location: noticeboard.php?msg=edit");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $message_id = intval($_POST['message_id']);

    if ($connect instanceof mysqli) {
        // Prepare the delete statement
        $stmt = $connect->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->bind_param("i", $message_id);
        
        if ($stmt->execute()) {
            // Success message (optional)
            echo "<script>alert('Message deleted successfully.');</script>";
        } else {
            // Error message
            echo "<script>alert('Error deleting message.');</script>";
        }

        $stmt->close(); // Close statement after use

        // Redirect back to the message management page or reload
        header("Location: noticeboard.php?msg=delete");
        exit();
    } else {
        echo "Database connection error.";
        exit();
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
    <title>NoticeBoard</title>
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
             <div class="container-fluid">
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
                    include('DB_connect.php');
                    $message_id = intval($_GET['id']);
    
                    // Prepare statement to fetch the existing message
                    $stmt = $connect->prepare("SELECT message FROM messages WHERE id = ?");
                    $stmt->bind_param("i", $message_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $notices = $result->fetch_assoc();
                    ?>
                    <h1 class="mt-2 head-update">Noticeboard Management</h1>
                    <ol class="breadcrumb mb-4 small" style="background-color:#9b9999; color: white; padding: 10px; border-radius: 5px;">
                        <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="noticeboard.php" style="color: #f8f9fa;">Noticeboard Management</a></li>
                        <li class="breadcrumb-item active">Edit Notice</li>
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
                                    <span class="material-symbols-outlined text-bold">edit_note</span> Edit Notice
                                </div>
                                <div class="card-body">
                                    <!-- Form to edit notice -->
                                    <form method="POST" action="noticeboard.php">
                                        <div class="form-group mb-3">
                                            <label for="notice_id">Notice ID:</label>
                                            <input type="number" id="notice_id" name="notice_id" class="form-control" value="<?php echo htmlspecialchars($message_id); ?>" required readonly>
                                        </div>
                                        <div class="form-group mb-3">
                                            <label for="notice_content">Notice Content:</label>
                                            <textarea id="notice_content" name="notice_content" class="form-control" rows="5" required><?php echo htmlspecialchars($notices['message']);?></textarea>
                                        </div>
                                        <button type="submit" name="edit_notice" class="btn btn-primary">Update Notice</button>
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
            }else {
            ?>
             <h1 class="mt-2 head-update">Message Management</h1>
                <ol class="breadcrumb mb-4 small" style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                    <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
                    <li class="breadcrumb-item active">Message Management</li>
                </ol>
                <?php
            if (isset($_GET['msg'])) {
                if ($_GET['msg'] == 'edit') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>Message updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                if($_GET['msg'] == 'delete') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>Message deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
            }
            ?>
        <div class="card mb-4">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-6">
                        <span class="material-symbols-outlined">message</span> Messages
                    </div>
                    <div class="col-md-6 d-flex justify-content-end align-items-center">
                    <!-- Search Bar -->
                    <div class="mb-0 me-3">
                        <input type="text" id="searchBar" class="form-control" placeholder="Search messages..." onkeyup="searchNotice()">
                    </div>
                </div>
            </div>
    <div class="card-body">
    <div class="table-responsive">
        <h3>Messages</h3>
        <?php
        // Include database connection
        include('DB_connect.php');

        // Number of items per page
        $items_per_page = 10;
        $current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $offset = ($current_page - 1) * $items_per_page;

        // Assume session contains the user role and ID 
        $user_id = $_SESSION['id'] ?? ''; // Get logged-in user ID

        if ($connect instanceof mysqli) {
            // Prepare the total records query based on the role
            if ($displayRole === 'Admin') {
                // Admin can see all messages
                $stmt_total = $connect->prepare("SELECT COUNT(*) AS total_records FROM messages");
            } elseif ($displayRole === 'Parent') {
                // Parent sees messages of their children
                $stmt_total = $connect->prepare("
                    SELECT COUNT(*) AS total_records 
                    FROM messages m 
                    JOIN students s ON m.user_id = s.student_id 
                    WHERE s.parent_id = ?
                ");
                $stmt_total->bind_param("i", $user_id);
            } else {
                // Student sees their own messages
                $stmt_total = $connect->prepare("
                    SELECT COUNT(*) AS total_records 
                    FROM messages 
                    WHERE user_id = ?
                ");
                $stmt_total->bind_param("i", $user_id);
            }
            $stmt_total->execute();
            $result_total = $stmt_total->get_result();
            $total_records = $result_total->fetch_assoc()['total_records'];
            $total_pages = ceil($total_records / $items_per_page);

            // Prepare SQL query to fetch paginated message data based on the role
            if ($displayRole === 'Admin') {
                $stmt_messages = $connect->prepare("
                    SELECT m.id, m.message, m.created_at, s.student_name 
                    FROM messages m 
                    JOIN students s ON m.user_id = s.student_id 
                    LIMIT ? OFFSET ?
                ");
                $stmt_messages->bind_param("ii", $items_per_page, $offset);
            } elseif ($displayRole === 'Parent') {
                $stmt_messages = $connect->prepare("
                    SELECT m.id, m.message, m.created_at, s.student_name 
                    FROM messages m 
                    JOIN students s ON m.user_id = s.student_id 
                    WHERE s.parent_id = ? 
                    LIMIT ? OFFSET ?
                ");
                $stmt_messages->bind_param("iii", $user_id, $items_per_page, $offset);
            } else {
                $stmt_messages = $connect->prepare("
                    SELECT m.id, m.message, m.created_at, s.student_name 
                    FROM messages m 
                    JOIN students s ON m.user_id = s.student_id 
                    WHERE m.user_id = ? 
                    LIMIT ? OFFSET ?
                ");
                $stmt_messages->bind_param("iii", $user_id, $items_per_page, $offset);
            }
            
            // Bind parameters for pagination
            if ($displayRole === 'Parent') {
                $stmt_messages->bind_param("iii", $user_id, $items_per_page, $offset);
            } elseif ($displayRole === 'Admin') {
                $stmt_messages->bind_param("ii", $items_per_page, $offset);
            } else {
                $stmt_messages->bind_param("iii", $user_id, $items_per_page, $offset);
            }
            
            $stmt_messages->execute();
            $result = $stmt_messages->get_result();

            if ($result->num_rows > 0): ?>
                <table class="table table-bordered" id="messageTable">
                    <thead>
                        <tr>
                           
                            <th>Student Name</th>
                            <th>Message</th>
                            <th>Date Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                            $message_id = htmlspecialchars($row['id']);
                            $student_name = htmlspecialchars($row['student_name']);
                            $message_content = htmlspecialchars($row['message']);
                            $message_date = htmlspecialchars($row['created_at']);
                            ?>
                            <tr>
                                
                                <td><?php echo $student_name; ?></td>
                                <td><?php echo htmlspecialchars(substr($message_content, 0, 50)) . '...'; ?></td>
                                <td><?php echo $message_date; ?></td>
                                <td>
                                    <div class="btn-group" role="group" aria-label="Actions">
                                        <!-- View Button -->
                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#viewMessageModal<?php echo $message_id; ?>">
                                            <i class="bi bi-eye"></i> View
                                        </button>

                                        <!-- Edit Button (Visible to Admin only) -->
                                        <?php if ($displayRole === 'Admin'): ?>
                                            <a href="noticeboard.php?action=edit&id=<?php echo $message_id; ?>" class="btn btn-info btn-sm ms-2 me-2">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        <?php endif; ?>

                                        <!-- Delete Button (Visible to Admin only) -->
                                        <?php if ($displayRole === 'Admin'): ?>
                                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteMessageModal<?php echo $message_id; ?>">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        <?php endif; ?>

                                        <!-- View Message Modal -->
                                        <div class="modal fade" id="viewMessageModal<?php echo $message_id; ?>" tabindex="-1" aria-labelledby="viewMessageModalLabel<?php echo $message_id; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="viewMessageModalLabel<?php echo $message_id; ?>">Message Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p><strong>ID:</strong> <?php echo $message_id; ?></p>
                                                        <p><strong>Student Name:</strong> <?php echo $student_name; ?></p>
                                                        <p><strong>Message:</strong> <?php echo $message_content; ?></p>
                                                        <p><strong>Date Created:</strong> <?php echo $message_date; ?></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Delete Message Modal (Visible to Admin only) -->
                                        <?php if ($displayRole === 'Admin'): ?>
                                            <div class="modal fade" id="deleteMessageModal<?php echo $message_id; ?>" tabindex="-1" aria-labelledby="deleteMessageModalLabel<?php echo $message_id; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteMessageModalLabel<?php echo $message_id; ?>">Confirm Delete</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Are you sure you want to delete this message?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <form action="noticeboard.php?action=delete" method="post">
                                                                <input type="hidden" name="message_id" value="<?php echo $message_id; ?>">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="delete_message" class="btn btn-danger">Delete</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No messages available.</p>
            <?php endif; ?>

            <!-- Pagination Controls -->
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                            <span aria-hidden="true">Previous</span>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" aria-label="Next">
                            <span aria-hidden="true">Next</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <?php
            // Close statements and connection
            $stmt_total->close();
            $stmt_messages->close();
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
                    </div>
                </footer>
            </div>
            <?php
        }
        ?>
        </main>
        <!--main-->
    </div>

    <script>
        function searchNotice() {
    var input, filter, table, rows, cells, i, j, match;
    input = document.getElementById("searchBar");
    filter = input.value.toLowerCase();
    table = document.getElementById("messageTable");
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