<?php
session_start();
include('DB_connect.php');

include('res/functions.php');
 

if (!isset($_SESSION["role"])) {
    header("Location: Admin.php");
    exit;
}


$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
$text_size = isset($_COOKIE['text_size']) ? $_COOKIE['text_size'] : 'medium';

$imageField = isset($_POST['image']) ? $_POST['image'] : '';

if (!empty($_FILES[$imageField]['name'])) {
    $image_name = $_FILES[$imageField]['name'];
    $image_type = $_FILES[$imageField]['type'];
    $temporary_name = $_FILES[$imageField]['tmp_name'];
    $image_size = $_FILES[$imageField]['size'];
    $image_extension = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

    $valid_extensions = ['jpeg', 'png', 'jpg'];
    if (in_array($image_extension, $valid_extensions) && $image_size <= 2000000) {
        $new_image_name = time() . '-' . rand() . '.' . $image_extension;
        if (move_uploaded_file($temporary_name, "upload/" . $new_image_name)) {
            $formdata['image'] = $new_image_name;
        } else {
            $errors[] = "Failed to upload image";
        }
    } else {
        $errors[] = $image_size > 2000000 ? "Image Size Exceeds 2MB" : "Invalid Image File";
    }
} else {
    $formdata['image'] = $_POST['hidden_image'] ?? ($admin[$imageField] ?? 'default.jpg'); // Use existing image if no new image is uploaded
}

$admin = null;

$userRole = $_SESSION["role"];
$admin_id = $_SESSION["id"];
$adminType = $_SESSION["admin_type"] ?? '';

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
    $stmt->bind_param("i", $admin_id); // "i" for integer type
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc(); // Fetch associative array
    } else {
        $admin = null; // Handle user not found case
    }
    $stmt->close();
}

$roleNames = [
    "1" => "Admin",
    "2" => "Student",
    "default" => "Parent"
];
$displayRole = $roleNames[$userRole] ?? $roleNames["default"];

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
    <title>User Information</title>
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
    <div class="container-fluid">
        <h1 class="mt-2 head-update">Profile</h1>

        <ol class="breadcrumb mb-4 small">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">View User Information</li>
        </ol>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <span class="material-symbols-outlined">manage_accounts</span>User Information
                    </div>
                    <div class="container mt-5">
                                <div class="card" style="width: 20rem;">
                                    <!-- Display the image from the database -->
                                    <img src="upload/<?php echo htmlspecialchars($admin[$imageField] ?? 'default.jpg'); ?>" class="img-fluid img-thumbnail rounded-circle" name="image" alt="Profile Image" style="width: 200px; height: 200px;">
                                    <div class="card-body">
                                          <h6>UserName: <?php 
                                             // Determine the appropriate field based on user role
                                              if ($userRole == '1') {
                                        echo htmlspecialchars($admin['admin_name'] ?? 'Name not available');
                                             } elseif ($userRole == '2') {
           
                                                 echo htmlspecialchars($admin['student_name'] ?? 'Name not available');
                                             } else {
        
                                               echo htmlspecialchars($admin['parent_name'] ?? 'Name not available');
                                                 }
                                                    ?></h6>
                                                </div>
                                    <ul class="list-group list-group-flush">
                                        <?php if ($userRole === "1"): ?>
                                            <!-- Admin specific info -->
                                            <li class="list-group-item">Admin ID: <?php echo htmlspecialchars($admin['admin_id']); ?></li>
                                            <li class="list-group-item">Admin Type: <?php echo htmlspecialchars($admin['admin_type']); ?></li>
                                            <li class="list-group-item">Name: <?php echo htmlspecialchars($admin['admin_name']); ?></li>
                                            <li class="list-group-item">Email: <?php echo htmlspecialchars($admin['admin_email']); ?></li>
                                            <li class="list-group-item">Sex: <?php echo htmlspecialchars($admin['admin_sex']); ?></li>
                                            <li class="list-group-item">Status: <?php echo htmlspecialchars($admin['admin_status']); ?></li>
                                            <li class="list-group-item">Date Added: <?php echo htmlspecialchars($admin['admin_added_on']); ?></li>
                                        <?php elseif ($userRole === "2"): ?>
                                            <!-- Student specific info -->
                                            <li class="list-group-item">Student Number: <?php echo htmlspecialchars($admin['student_number']); ?></li>
                                            <li class="list-group-item">Name: <?php echo htmlspecialchars($admin['student_name']); ?></li>
                                            <li class="list-group-item">Email: <?php echo htmlspecialchars($admin['student_email']); ?></li>
                                            <li class="list-group-item">Address: <?php echo htmlspecialchars($admin['student_address']); ?></li>
                                            <li class="list-group-item">Birth Date: <?php echo htmlspecialchars($admin['student_date_of_birth']); ?></li>
                                            <li class="list-group-item">Contact Number: <?php echo htmlspecialchars($admin['student_contact_number1']); ?></li>
                                        <?php else: ?>
                                            <!-- Parent specific info -->
                                            <li class="list-group-item">Parent ID: <?php echo htmlspecialchars($admin['parent_id']); ?></li>
                                            <li class="list-group-item">Name: <?php echo htmlspecialchars($admin['parent_name']); ?></li>
                                            <li class="list-group-item">Email: <?php echo htmlspecialchars($admin['parent_email']); ?></li>
                                            <li class="list-group-item">Address: <?php echo htmlspecialchars($admin['parent_address']); ?></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <hr>
                        <div class="form-group row">
                            
                            <div class="col-md-6 mt-4 mb-2">
                                <a href="dashboard.php" class="btn btn-primary">
                                    <i class="fa fa-reply"></i> Home
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
              <footer class="main-footer px-3">
                <div class="pull-right hidden-xs">
                  
                Copyright © 2024-2025 <a href="#">AutoReceipt system</a>. All rights reserved  
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
function toggleRotation(event) {
    event.preventDefault(); // Prevent default behavior of the link

    const chevronIcon = document.getElementById('chevronIcon');

    // Toggle the 'rotate' class on the icon
    chevronIcon.classList.toggle('rotate');
}
    </script>
</body>
</html>

