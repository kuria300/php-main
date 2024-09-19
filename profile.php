<?php
include("DB_connect.php");
session_start();
 
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
    
   

    // Determine query and types based on role
    switch ($userRole) {
        case '1':
            $fetchQuery = "SELECT * FROM admin_users WHERE admin_id = ?";
            $updateQuery = "UPDATE admin_users SET admin_name = ?, admin_email = ?, admin_image = ?, admin_address = ?, admin_sex = ?, admin_birth_date = ? WHERE admin_id = ?";
            $types = "ssssssi";
            $imageField = 'admin_image';
            break;
        case '2':
            $fetchQuery = "SELECT * FROM students WHERE student_id = ?";
            $updateQuery = "UPDATE students SET student_name = ?, student_email = ?, student_image = ?, student_address = ?, student_sex = ?, student_date_of_birth = ? WHERE student_id = ?";
            $types = "ssssssi";
            $imageField = 'student_image';
            break;
        case '3':
            $fetchQuery = "SELECT * FROM parents WHERE parent_id = ?";
            $updateQuery = "UPDATE parents SET parent_name = ?, parent_email = ?, parent_image = ?, parent_address = ?, parent_sex = ?, parent_date_of_birth = ? WHERE parent_id = ?";
            $types = "ssssssi";
            $imageField = 'parent_image';
            break;
        default:
            $error = "Invalid role.";
            break;
    }

    // Fetch user data
    if (isset($fetchQuery) && $stmt = $connect->prepare($fetchQuery)) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc(); // Fetch associative array
        } else {
            $error = "No user found with the given ID.";
        }
    }
    
    // Check if student_image exists and is not empty
    if (isset($user['student_image']) && !empty($user['student_image'])) {
        $formdata['image'] = $_POST['hidden_admin_image'] ?? $user['student_image'];
    } else {
        $formdata['image'] = 'default.jpg'; // Default fallback if not set
    }

    // Handle form submission
    if (isset($_POST["edit"])) {
        $formdata = [];
        $errors = [];
       
        if (!empty($_FILES[$imageField]['name'])) {
            $image_name = $_FILES[$imageField]['name'];
            $temporary_name = $_FILES[$imageField]['tmp_name'];
            $image_size = $_FILES[$imageField]['size'];
            $image_extension = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
        
            $valid_extensions = ['jpeg', 'png', 'jpg'];
            // Validate the image
            if (in_array($image_extension, $valid_extensions) && $image_size <= 2000000) {
                do {
                    $new_image_name = time() . '-' . rand() . '.' . $image_extension;
                    $upload_path = "upload/" . $new_image_name;
                } while (file_exists($upload_path));
        
                // Attempt to move the uploaded file
                if (move_uploaded_file($temporary_name, $upload_path)) {
                    $formdata['image'] = $new_image_name; // Store the new image name
                    echo "Uploaded Image: " . htmlspecialchars($new_image_name);
                } else {
                    $errors[] = "Failed to upload image. Please try again.";
                    echo "Upload Error: " . htmlspecialchars(error_get_last()['message']);
                }
            } else {
                $errors[] = $image_size > 2000000 ? "Image Size Exceeds 2MB" : "Invalid Image File. Only JPEG, PNG, and JPG files are allowed.";
            }
        } else {
            // Use existing image if no new image is uploaded
            $formdata['image'] = $_POST['hidden_admin_image'] ?? (!empty($user[$imageField]) ? $user[$imageField] : 'default.jpg');
        }
        // Validate and sanitize form inputs
        $formdata["name"] = trim($_POST["admin_name"] ?? '');
        $formdata["address"] = trim($_POST["admin_address"] ?? '');
        $formdata["birthdate"] = trim($_POST["admin_date"] ?? '');
        $formdata["sex"] = trim($_POST["admin_sex"] ?? '');
        $formdata["email"] = trim($_POST["admin_email"] ?? '');
       


        // Validate inputs
        if (empty($formdata["name"])) $errors[] = "Name is Required";
        if (empty($formdata["address"])) $errors[] = "Address is Required";
        if (empty($formdata["birthdate"])) $errors[] = "Birth date is Required";
        if (empty($formdata["sex"])) $errors[] = "Gender is Required";
        if (empty($formdata["email"])) {
          $errors[] = "Email is Required";
      } else if (!filter_var($formdata["email"], FILTER_VALIDATE_EMAIL)) {
          $errors[] = "Invalid Email Address";
      }

        if (empty($errors)) {
              
            // Prepare and execute update query
            if ($stmt = $connect->prepare($updateQuery)) {
               
                $stmt->bind_param($types, $formdata['name'], $formdata['email'], $formdata['image'], $formdata['address'], $formdata['sex'], $formdata['birthdate'], $userId);
                if ($stmt->execute()) {
                    $message = "Profile updated successfully!";
                } else {
                    $error = "Error updating profile.";
                }
                $stmt->close();
            } else {
                $error = "Failed to prepare the update statement.";
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
} else {
    $error = "User is not logged in.";
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
    <title>Profile</title>
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
            <h1 class="mt-2 head-update">Profile</h1>

            <ol class="breadcrumb mb-4 small">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" style="color:white;">Profile</a></li>
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
                   
                    <span class="material-symbols-outlined">
                        manage_accounts
                        </span>Edit Personal Information
                    </div>
                    <div class="card-body">
                    <form method="post" action="profile.php" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="exampleInputEmail1" class="form-label">UserName:</label>
                                <input type="text" class="form-control" name="admin_name" value="<?php echo htmlspecialchars($user[$userRole == '1' ? 'admin_name' : ($userRole == '2' ? 'student_name' : 'parent_name')] ?? ''); ?>" placeholder="UserName" aria-describedby="emailHelp">
                            </div>
                            <div class="mb-3">
                                <label for="exampleInputAddress1" class="form-label">Address:</label>
                                <input type="text" class="form-control" name="admin_address" value="<?php echo htmlspecialchars($user[$userRole == '1' ? 'admin_address' : ($userRole == '2' ? 'student_address' : 'parent_address')] ?? ''); ?>" placeholder="Address" maxlength="255">
                            </div>
                            <div class="mb-3">
                                <label for="exampleInputPassword1" class="form-label">Birth date:</label>
                                <input type="date" class="form-control" name="admin_date" value="<?php echo htmlspecialchars($user[$userRole == '1' ? 'admin_birth_date' : ($userRole == '2' ? 'student_date_of_birth' : 'parent_date_of_birth')] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                      <label for="sex" class="form-label">Sex</label>
                                          <select class="form-select" id="sex" name="admin_sex">
                                              <option value="male" <?php echo isset($user['sex']) && $user['sex'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                              <option value="female" <?php echo isset($user['sex']) && $user['sex'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
    <label for="admin_image">Profile Image: <span class="text-muted">(Optional)</span></label>
    <div class="image-preview mb-2">
        <img src="upload/<?php echo htmlspecialchars($user[$imageField] ?? 'default.jpg'); ?>" class="img-fluid img-thumbnail rounded-circle" alt="Profile Image" style="width: 100px; height: 100px;">
    </div>
    <input type="file" name="<?php echo $imageField; ?>" id="admin_image" class="form-control">
    <input type="hidden" name="hidden_admin_image" value="<?php echo htmlspecialchars($user[$imageField]); ?>">
</div>
                            <hr>
                            <div class="mb-3 mt-5">
                                <label for="exampleInputEmail1" class="form-label">Email address:</label>
                                <input type="email" class="form-control" name="admin_email" value="<?php echo htmlspecialchars($user[$userRole == '1' ? 'admin_email' : ($userRole == '2' ? 'student_email' : 'parent_email')] ?? ''); ?>" placeholder="Email" aria-describedby="emailHelp">
                            </div>
                            <div class="mb-3 ms-2">
                                <a href="updatepass.php">Update password</a>
                            </div>
                            <div class="box-body">
                                <hr>
                                <div class="form-group row">
                                    
                                    <div class="col-md-6 mt-4">
                                        <button type="submit" class="btn btn-primary" name="edit"><i class="fa fa-save">&nbsp;&nbsp;</i>Update</button>
                                        <a href="dashboard.php" class="btn btn-warning"><i class="fa fa-reply">&nbsp;&nbsp;</i>Cancel</a>
                                    </div>
                                </div>
                            </div>
                        </form>
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
    </script>
</body>
</html>