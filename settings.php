<?php
session_start();
include("DB_connect.php");


include('res/functions.php');
 
// Initialize variables
$message = $error = '';
$school_name = $school_address = $school_contact_number = $school_email_address = $school_website = "";
$system_name = $theme = $text_size = $setting_id = "";


if (isset($_SESSION["id"]) && isset($_SESSION["role"])) {
    // Store user role for easier access
   
    $userId = $_SESSION['id'];
    $userRole = $_SESSION["role"];
    $adminType = $_SESSION["admin_type"] ?? '';
    // Map roles to display names
    $roleNames = [
        "1" => "Admin",
        "2" => "Student",
        "3" => "Parent"
    ];
}
    // Determine role name based on the session
    $displayRole = $roleNames[$userRole] ?? 'Parent';

    // Fetch settings
    $query = "SELECT * FROM settings LIMIT 1";
    $result = $connect->query($query);

    if ($result) {
        $row = $result->fetch_assoc();
        if ($row) {
            $school_name = htmlspecialchars($row['school_name']);
            $school_address = htmlspecialchars($row['school_address']);
            $school_contact_number = htmlspecialchars($row['school_contact_number']);
            $school_email_address = htmlspecialchars($row['school_email_address']);
            $school_website = htmlspecialchars($row['school_website']);
            $system_name = htmlspecialchars($row['system_name']);
            $theme = htmlspecialchars($row['theme']);
            $text_size = htmlspecialchars($row['text_size']);
            $setting_id = $row['setting_id'];
        }
    } else {
        $error = "No settings found.";
    }

    if (isset($_POST["save"])) {
        // Get POST data
        $school_name = $_POST["school_name"] ?? '';
        $school_address = $_POST["school_address"] ?? '';
        $school_contact_number = $_POST["school_contact_number"] ?? '';
        $school_email_address = $_POST["school_email_address"] ?? '';
        $school_website = $_POST["school_website"] ?? '';
        $system_name = $_POST['system_name'] ?? '';
        $theme = $_POST['theme'] ?? '';
        $text_size = $_POST['text_size'];
        $setting_id = $_POST["setting_id"] ?? '';

        if ($displayRole === 'Admin') {
            // Admin can update all settings
            $query = "UPDATE settings 
                SET school_name = ?, 
                    school_address = ?, 
                    school_contact_number = ?, 
                    school_email_address = ?, 
                    school_website = ?, 
                    system_name = ?, 
                    theme = ?, 
                    text_size = ? 
                WHERE setting_id = ?";
            $stmt = $connect->prepare($query);

            // Bind parameters
            $stmt->bind_param('ssssssssi', $school_name, $school_address, $school_contact_number, $school_email_address, $school_website, $system_name, $theme, $text_size, $setting_id);

        } elseif ($displayRole === 'Student'|| $displayRole === 'Parent') {
            // Student can only update theme and text size
            $query = "UPDATE settings 
                SET theme = ?, 
                    text_size = ? 
                WHERE setting_id = ?";
            $stmt = $connect->prepare($query);

            // Bind parameters
            $stmt->bind_param('ssii', $theme, $text_size, $setting_id);
        }

        // Execute the query
        if ($stmt->execute()) {
          $expire = time() + (10 * 365 * 24 * 60 * 60); // 10 years in seconds

            setcookie('theme', $theme, $expire, "/"); // Expiration in 10 years
            setcookie('text_size', $text_size, $expire, "/"); // Expiration in 10 years
            $message = "Settings updated successfully.";
        } else {
            $error = "Failed to update settings.";
        }

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
// Fetch user preferences
  

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
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
                <span class="material"> <bold class="change-color"><?php echo $system_name; ?></bold></span>
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
    
        <!-- main content start -->
        <main class="main-container">
    <div class="container-fluid mt-2 px-4">
        <h1 class="mt-2 head-update">Settings</h1>

        <ol class="breadcrumb mb-4 small"  style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
            <li class="breadcrumb-item"><a href="dashboard.php"  style="color: #f8f9fa;">Dashboard</a></li>
            <li class="breadcrumb-item active">Settings</li>
        </ol>
        <?php if ($error) { ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php } ?>

<?php if ($message) { ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php } ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header">
                        
                        <span class="material-symbols-outlined">settings</span> Settings
                    </div>
                    <div class="card-body">
                    <form method="post" class="side-form">
                            <?php if ($displayRole === 'Admin'): ?>
                                <!-- Fields visible only to Admin -->
                                <div class="mb-3">
                                    <label for="school_name" class="form-label">School Name</label>
                                    <input type="text" id="school_name" name="school_name" class="form-control" value="<?= htmlspecialchars($school_name); ?>" >
                                </div>
                                <div class="mb-3">
                                    <label for="school_address" class="form-label">School Address</label>
                                    <input type="text" id="school_address" name="school_address" class="form-control" value="<?= htmlspecialchars($school_address); ?>" >
                                </div>
                                <div class="mb-3">
                                    <label for="school_contact_number" class="form-label">Contact Number</label>
                                    <input type="text" id="school_contact_number" name="school_contact_number" class="form-control" value="<?= htmlspecialchars($school_contact_number); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="school_email_address" class="form-label">Email Address</label>
                                    <input type="email" id="school_email_address" name="school_email_address" class="form-control" value="<?= htmlspecialchars($school_email_address); ?>">
                                </div>
                                <div class="mb-3">
                                     <label for="school_website" class="form-label">Website</label>
                                    <input type="url" id="school_website" name="school_website" class="form-control" value="<?= htmlspecialchars($school_website); ?>">
                                 </div>
                                <div class="mb-3">
                                    <label for="system_name" class="form-label">System Name</label>
                                    <input type="text" id="system_name" name="system_name" class="form-control" value="<?= htmlspecialchars($system_name); ?>">
                                </div>
                                
                            <?php endif; ?>

                            <!-- Fields visible to both Admin and Student -->
                            <div class="mb-3">
                                <label for="theme" class="form-label">Theme</label>
                                <select id="theme" name="theme" class="form-select">
                                    <option value="light" <?= $theme == 'light' ? 'selected' : ''; ?>>Light</option>
                                    <option value="dark" <?= $theme == 'dark' ? 'selected' : ''; ?>>Dark</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="text_size" class="form-label">Text Size</label>
                                <select id="text_size" name="text_size" class="form-select">
                                    <option value="small" <?= $text_size == 'small' ? 'selected' : ''; ?>>Small</option>
                                    <option value="medium" <?= $text_size == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="large" <?= $text_size == 'large' ? 'selected' : ''; ?>>Large</option>
                                </select>
                            </div>

                            <!-- Hidden field and submit button -->
                            <input type="hidden" name="setting_id" value="<?= htmlspecialchars($setting_id); ?>">
                            <button type="submit" name="save" class="btn btn-primary">Save</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="main-footer px-3">
                <div class="pull-right hidden-xs">
                <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $system_name; ?></a>. All rights reserved.</p>
                </div>
            </footer>
</main>
        <!-- main content end -->
    </div>
    <script>
        // Dynamically apply theme and text size
        document.addEventListener('DOMContentLoaded', function () {
    var theme = '<?= htmlspecialchars($theme); ?>';
    var textSize = '<?= htmlspecialchars($text_size); ?>';

    // Ensure that the CSS files are in the correct directory and named correctly
    var themeLink = document.getElementById('theme-style');
    var textSizeLink = document.getElementById('text-size-style');

    if (themeLink && textSizeLink) {
        themeLink.setAttribute('href', 'css/' + theme + '.css');
        textSizeLink.setAttribute('href', 'css/' + textSize + '.css');
    }
});
   
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
