<?php
session_start();
include('DB_connect.php');

/*function is_login() {
    return isset($_SESSION["admin_id"]) && isset($_SESSION["role"]);
  }
  
  // Function to check if the user is a master user
  function is_master_user() {
    return isset($_SESSION["admin_type"]) && $_SESSION["admin_type"] === "master";
  }
  
  if (!is_login()) {
    header("Location: Admin.php");
    exit();
  }*/
  
  
if(isset($_SESSION["id"]) && isset($_SESSION["role"])){
        // Store user role for easier access
        $userRole = $_SESSION["role"];
        // Map roles to display names
        $roleNames = [
            "1" => "Admin",
            "2" => "Student",
            "default" => "Parent"
        ];
        // Determine role name based on the session
        $displayRole = $roleNames[$userRole] ?? $roleNames["default"];
    }
 ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoReceipt-Dashboard</title>
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
                <a class=" nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="bi bi-person-fill"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item text-muted" href="settings.php">Settings</a></li>
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
                  <span class="material"> <bold class="change-color">A</bold>utoReceipt </span>
                </div>
                <span class="material-symbols-outlined" onclick="closeSideBar()"> close</span>
            </div>
             <ul class="sidebar-list">
                 <li class="sidebar-list-item">
                    <a href="dashboard.php" class="nav-link px-3 active">
                        <span class="material-symbols-outlined">dashboard</span> Dashboard
                 </li>
                
                 <li class="sidebar-list-item">
                    
                    <a class="nav-link px-3 mt-3 sidebar-link active" 
                    data-bs-toggle="collapse" 
                    href="#collapseExample" 
                    role="button"
                    aria-expanded="false" 
                    aria-controls="collapseExample">
                        <span class="material-symbols-outlined">dashboard</span> Fee Manager
                        <span class="right-icon ms-2"><i class="bi bi-chevron-down"></i></span>
                    </a>
                </li>
                <div class="collapse" id="collapseExample">
                    <div>
                        <ul class="navbar-nav ps-3">
                            
                                <li class="sidebar-list-item">
                                <a href="Student.php" class="nav-link px-3">
                                    <span><i class="bi bi-person-fill-add"></i></span>
                                    <span>Student Entry</span>
                                </a>
                            </li>
                            <li class="sidebar-list-item">
                                <a href="#" class="nav-link px-3">
                                    <span><i class="bi bi-cash"></i></span>
                                    <span>Deposit Fees</span>
                                </a>
                            </li>
                            <li class="sidebar-list-item">
                                <a href="#" class="nav-link px-3">
                                    <span><i class="bi bi-printer"></i></span>
                                    <span>Receipt</span>
                                </a>
                            </li>
                            <li class="sidebar-list-item">
                                <a href="#" class="nav-link px-3">
                                    <span><i class="bi bi-wallet"></i></span>
                                    <span>Manage Fees</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <li class="sidebar-list-item">
                    <a class="nav-link px-3 mt-3 sidebar-link active" 
                    data-bs-toggle="collapse" 
                    href="#collapseReports" 
                    role="button"
                    aria-expanded="false" 
                    aria-controls="collapseReports">
                        <span class="material-symbols-outlined">dashboard</span> Reports
                        <span class="right-icon ms-2"><i class="bi bi-chevron-down"></i></span>
                    </a>
                </li>
                <div class="collapse" id="collapseReports">
                    <div>
                        <ul class="navbar-nav ps-3">
                            <li class="sidebar-list-item">
                                <a href="#" class="nav-link px-3">
                                    <span><i class="bi bi-person-fill-add"></i></span>
                                    <span>Records</span>
                                </a>
                            </li>
                            <li class="sidebar-list-item">
                                <a href="#" class="nav-link px-3">
                                    <span><i class="bi bi-receipt"></i></span>
                                    <span>Courses</span>
                                </a>
                            </li>
                            <li class="sidebar-list-item">
                                <a href="studententry.php" class="nav-link px-3">
                                    <span><i class="bi bi-bell-fill"></i></span>
                                    <span>Manage Users</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
             
                 <li class="sidebar-list-item">
                    <a href="profile.php" class="nav-link px-3 mt-3 active">
                        <span class="material-symbols-outlined">dashboard</span> Update Profile
                       </a>
                 </li>
                 <li class="sidebar-list-item">
                    <a href="updatepass.php" class="nav-link px-3 mt-3 active">
                        <span class="material-symbols-outlined">dashboard</span>Update Password
                       </a>
                 </li>
                 <li class="sidebar-list-item">
                    <a href="logout.php" class="nav-link px-3 mt-3 active" onclick="confirmLogout(event)">
                        <span class="material-symbols-outlined">dashboard</span> Log Out
                       </a>
                 </li>
              </ul>
              <div class="sb-sidenav-footer ">
                        <div class="small">Logged in as:<span class="px-1"><?php echo htmlspecialchars($displayRole); ?></span></div>
               </div>
        </aside>
        
        <!--sidetag end-->
        <!--main-->
        <main class="main-container">
            <div class="container-fluid mt-2 px-4">
                <?php 
                if(isset($_GET['action'])){
                    if($_GET['action'] == 'add'){
                        ?>
                        <h1 class="mt-2 head-update">Fees Management</h1>
                        <ol class="breadcrumb mb-4 small">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active"><a href="fees.php">Account Information</a></li>
                            <li class="breadcrumb-item active">Fees Information</li>
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
                                        <span class="material-symbols-outlined text-bold">manage_accounts</span> Fee Receipts
                                    </div>
                                   
                                    <div class="card-body">
                                        <table id="receipt_data" class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                <th>Receipt ID</th>
                                                <th>Account ID</th>
                                                <th>Transaction ID</th>
                                                <th>Transaction Date</th>
                                                <th>Amount</th>
                                                <th>Payment Method</th>
                                                <th>Receipt Details</th>
                                                <th>Action</th>
                                                </tr>
                                            </thead>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    } else if($_GET['action'] == 'edit'){
                        if(isset($_GET['id'])){
                            $student_id = intval($_GET['id']); // Ensure student_id is an integer
                            
                            // Prepare and execute the query
                            $stmt = $connect->prepare("SELECT * FROM students WHERE student_id = ?");
                            $stmt->bind_param('i', $student_id);
                            $stmt->execute();
                            
                            // Get the result
                            $result = $stmt->get_result();
                            
                            if($user_row = $result->fetch_assoc()){
                                ?>
                                 <h1 class="mt-2 head-update">Student Management</h1>
                                <ol class="breadcrumb mb-4 small">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="Student.php">Student Management</a></li>
                                    <li class="breadcrumb-item active">Edit Student</li>
                                </ol>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <span class="material-symbols-outlined">manage_accounts</span> Student Edit Form
                                            </div>
                                            <div class="card-body">
                                                <!-- Edit student form goes here -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                    }
                } else {
                    ?>
                    <h1 class="mt-2 head-update">Fees Management</h1>
                    <ol class="breadcrumb mb-4 small">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Fees Management</li>
                    </ol>
                    <?php
                    if (isset($_GET['msg'])) {
                        if ($_GET['msg'] == 'add') {
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle"></i> Successfully added fees
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                        }
                        if ($_GET['msg'] == 'edit') {
                            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle"></i> Successfully updated fees
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
                        }
                    }
                    ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-md-6">
                                    <span class="material-symbols-outlined">manage_accounts</span> Fees Management
                                </div>
                                <div class="col-md-6 d-flex justify-content-end add-button">
                                    <a href="fees.php?action=add" class="btn btn-success btn-sm">Add</a>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <div class="card-body">
                                <table id="fees_data" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Receipt ID</th>
                                             <th>Receipt Number</th>
                                            <th>Amount</th>
                                            <th>Payment Date</th>
                                            <th>Status</th>
                                            <th>Ed. Level</th>
                                            <th>Year</th>
                                            <th>Academic year</th>
                                            <th>View Full Details</a></th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php
                }
                ?>
                <footer class="main-footer px-3">
                    <div class="pull-right hidden-xs"> 
                        Copyright © 2024-2025 <a href="#">AutoReceipt system</a>. All rights reserved  
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
function toggleRotation(event) {
    event.preventDefault(); // Prevent default behavior of the link

    const chevronIcon = document.getElementById('chevronIcon');

    // Toggle the 'rotate' class on the icon
    chevronIcon.classList.toggle('rotate');
}
$(document).ready(function() {
    $('#fees_data').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        order: [],
        ajax: {
            url: "action.php",
            type: "POST",
            data: function(d) {
                d.action = 'fetch_fees';
            }
        }
    });

    $('#receipt_data').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        order: [],
        ajax: {
            url: "action.php",
            type: "POST",
            data: function(d) {
                d.action = 'fetch_receipt';
            }
        }
    });
});
    </script>
</body>
</html>

