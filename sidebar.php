<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoReceipt-Dashboard</title>
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
                <a href="studententry.php" class="nav-link px-3">
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
                    <span>Print Receipt</span>
                </a>
            </li>
            <li class="sidebar-list-item">
                <a href="#" class="nav-link px-3">
                    <span><i class="bi bi-receipt"></i></span>
                    <span>Edit Receipt</span>
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
                    <span>Search Records</span>
                </a>
            </li>
            <li class="sidebar-list-item">
                <a href="#" class="nav-link px-3">
                    <span><i class="bi bi-printer"></i></span>
                    <span>Edit Records</span>
                </a>
            </li>
            <li class="sidebar-list-item">
                <a href="#" class="nav-link px-3">
                    <span><i class="bi bi-receipt"></i></span>
                    <span>Edit Courses</span>
                </a>
            </li>
            <li class="sidebar-list-item">
                <a href="#" class="nav-link px-3">
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