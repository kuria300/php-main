<?php
session_start();
include('DB_connect.php');

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Access sensitive information from environment variables
$smtp=$_ENV['SMTP'];
$mails=$_ENV['MAIL'];
$pass=$_ENV['PASS'];
$pass2=$_ENV['PASS2'];
$port=$_ENV['PORT'];
 
if(isset($_SESSION["id"]) && isset($_SESSION["role"])){
   
    $userRole = $_SESSION["role"];
    $adminType = $_SESSION["admin_type"] ?? '';
    $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
    $text_size = isset($_COOKIE['text_size']) ? $_COOKIE['text_size'] : 'medium';
    
    $roleNames = [
        "1" => "Admin",
        "2" => "Student",
        "default" => "Parent"
    ];
    // Determine role name based on the session
    $displayRole = $roleNames[$userRole] ?? $roleNames["default"];
  }
 
  function Generate_student_number($number) {
    $number += 1;
    return sprintf('S%06d', $number);
}

$message = $error = '';

require('C:/xampp/htdocs/sms/PHPMailer-master/src/PHPMailer.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/Exception.php');
require('C:/xampp/htdocs/sms/PHPMailer-master/src/SMTP.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_POST['add_student'])) {
    $formdata = array();
    $error = array();

    // Get the maximum student ID in table or the most recent one
    $query = 'SELECT MAX(student_id) AS ID FROM students';
    $result = $connect->query($query);
    $row = $result->fetch_assoc();
    $max_student_id = $row['ID'] ? $row['ID'] : 0;
    // generate a new student ID based on the one before
    $formdata['student_number'] = Generate_student_number($max_student_id);

    // Validate form inputs
    $required_fields = [
        'student_name' => 'Student Name',
        'student_email' => 'Email',
        'student_parent_name' => 'Parent',
        'student_date_of_birth' => 'Date of Birth',
        'student_address' => 'Address',
        'student_date_of_admission' => 'Date of Admission',
        'student_contact_number1' => 'Contact Number',
        'course' => 'course',
        'status' => 'status'
    ];

    foreach ($required_fields as $field => $label) {
        if (empty($_POST[$field])) {
            $error[] = "$label Required";
        } else {
            $formdata[$field] = trim($_POST[$field]);
        }
    }

    $generated_password = bin2hex(random_bytes(8));

    if (!filter_var($_POST["student_email"], FILTER_VALIDATE_EMAIL)) {
        $error[] = "Invalid Email Address";
    } else {
        $formdata["student_email"] = trim($_POST["student_email"]);
    }

    // Optional field
    $formdata['student_contact_number2'] = trim($_POST['student_contact_number2']);

    // Handle image upload
    if (!empty($_FILES['student_image']['name'])) {
        $image_name = $_FILES['student_image']['name'];
        $temporary_name = $_FILES['student_image']['tmp_name'];
        $image_size = $_FILES['student_image']['size'];
        $image_extension = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));

        $valid_extensions = ['jpeg', 'png', 'jpg'];
        if (in_array($image_extension, $valid_extensions) && $image_size <= 2000000) {
            $new_image_name = time() . '-' . rand() . '.' . $image_extension;
            if (move_uploaded_file($temporary_name, "upload/" . $new_image_name)) {
                $formdata['student_image'] = $new_image_name;
            } else {
                $error[] = "Failed to upload image";
            }
        } else {
            $error[] = $image_size > 2000000 ? "Image Size Exceeds 2MB" : "Invalid Image File";
        }
    }

    // Validate course selection
    $course_id = intval($_POST['course']);
    $course_query = 'SELECT course_name FROM courses WHERE course_id = ?';
    $course_stmt = $connect->prepare($course_query);
    $course_stmt->bind_param('i', $course_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();

    if ($course_result->num_rows > 0) {
        $course_row = $course_result->fetch_assoc();
        $formdata['course_name'] = $course_row['course_name'];
        $formdata['course_id'] = $course_id;
    } else {
        $errors[] = "Invalid Course Selected";
    }
    
    // Validate status
    $valid_statuses = ['active', 'graduated', 'withdrawn'];
    if (!in_array($_POST['status'], $valid_statuses)) {
        $error[] = "Invalid Status";
    } else {
        $formdata['status'] = trim($_POST['status']);
    }
    $parent_id = intval($_POST['student_parent_name']);
        $parent_query = 'SELECT parent_name FROM parents WHERE parent_id = ?';
        $parent_stmt = $connect->prepare($parent_query);
        $parent_stmt->bind_param('i', $parent_id);
        $parent_stmt->execute();
        $parent_result = $parent_stmt->get_result();
        $parent_row = $parent_result->fetch_assoc();
        $formdata['parent_name'] = $parent_row['parent_name'];


$stmt = $connect->prepare('
    INSERT INTO students (
        student_number, 
        student_email, 
        student_name, 
        student_date_of_birth, 
        student_address, 
        student_date_of_admission, 
        student_contact_number1, 
        student_contact_number2, 
        student_image, 
        student_added_on, 
        course_id, 
        student_course, 
        status,
        parent_id,
        student_parent_name,
        student_password
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)
');

if (!$stmt) {
    die('Prepare failed: ' . htmlspecialchars($connect->error));
}

// Bind the parameters
$stmt->bind_param(
    'sssssssssississ', 
    $formdata['student_number'],
    $formdata['student_email'],
    $formdata['student_name'],
    $formdata['student_date_of_birth'],
    $formdata['student_address'],
    $formdata['student_date_of_admission'],
    $formdata['student_contact_number1'],
    $formdata['student_contact_number2'],
    $formdata['student_image'],
    $formdata['course_id'], 
    $formdata['course_name'],
    $formdata['status'],
    $parent_id, 
    $formdata['parent_name'],
    $generated_password 
);

if ($stmt->execute()) {
    // Get the newly inserted student's ID
    $student_id = $connect->insert_id;

    // Setup and send email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP'];  // Use 'smtp.gmail.com'
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL'];  // Use your Gmail address
        $mail->Password   = $_ENV['PASS']; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['PORT'];  // Use port 587
    


        // Recipients
        $mail->setFrom('eugenekuria66@gmail.com', 'Eugene Kuria');
        $mail->addAddress($formdata['student_email'], $formdata['student_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Account Details';
        $mail->Body    = "Dear {$formdata['student_name']},<br><br>Your account has been created.<br>Your password is: $generated_password  <br><br>Thank you.";

        $mail->send();

        // Insert into enrollments table
        $enroll_stmt = $connect->prepare('INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)');
        $enroll_stmt->bind_param('ii', $student_id, $formdata['course_id']);
        $enroll_stmt->execute();
        $enroll_stmt->close();

        // Redirect after successful operations
        header('Location: Student.php?msg=add');
        exit();
    } catch (Exception $e) {
        $errors[] = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
       
    }
} else {
    $errors[] = "Failed to insert student record.";
}

$stmt->close();

}
// Handle student update form submission
$form_submitted = isset($_POST['edit_student']);
if ($form_submitted) {
    $formdata = array();
    $errors = array();

    // Validate form inputs
    $required_fields = [
        'student_name' => 'Student Name',
        'student_email' => 'Email',
        'student_parent_name' => 'Parent',
        'student_date_of_birth' => 'Date of Birth',
        'student_address' => 'Address',
        'student_date_of_admission' => 'Date of Admission',
        'student_contact_number1' => 'Contact Number',
        'course' => 'course',
        'status' => 'status'
       
    ];

    foreach ($required_fields as $field => $label) {
        if (empty($_POST[$field])) {
            $errors[] = "$label Required";
        } else {
            $formdata[$field] = trim($_POST[$field]);
        }
    }

    if (!filter_var($_POST["student_email"], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid Email Address";
    } else {
        $formdata["student_email"] = trim($_POST["student_email"]);
    }

    // Optional field
    $formdata['student_contact_number2'] = trim($_POST['student_contact_number2']);
    
    // Handle image upload
    $formdata['student_image'] = $_POST['hidden_student_image']; //used if no new image is uploaded

    // Check if an image file has been uploaded
    if (!empty($_FILES['student_image']['name'])) {
        $image_name = $_FILES['student_image']['name'];// Original name of the uploaded image
        $image_type = $_FILES['student_image']['type']; // MIME type of the uploaded image (e.g., image/jpeg)
        $temporary_name = $_FILES['student_image']['tmp_name']; // Temporary location of the uploaded file
        $image_size = $_FILES['student_image']['size'];  // Size of the uploaded image in bytes
        $image_extension = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));  // Extract the image file extension and convert it to lowercase

        $valid_extensions = ['jpeg', 'png', 'jpg'];
        // Check if the image extension is valid and the image size is within the allowed limit (<= 2MB)
        if (in_array($image_extension, $valid_extensions) && $image_size <= 2000000) {
              // Generate a new unique name for the uploaded image based on current timestamp and a random number
            $new_image_name = time() . '-' . rand() . '.' . $image_extension;
            if (move_uploaded_file($temporary_name, "upload/" . $new_image_name)) {
                $formdata['student_image'] = $new_image_name;
            } else {
                $errors[] = "Failed to upload image";
            }
        } else {
            $errors[] = $image_size > 2000000 ? "Image Size Exceeds 2MB" : "Invalid Image File";
        }
    }
     // Fetch course details
     $course_id = intval($_POST['course']);
     $course_query = 'SELECT course_name FROM courses WHERE course_id = ?';
     $course_stmt = $connect->prepare($course_query);
     $course_stmt->bind_param('i', $course_id);
     $course_stmt->execute();
     $course_result = $course_stmt->get_result();
 
     if ($course_result->num_rows > 0) {
         $course_row = $course_result->fetch_assoc();
         $formdata['course_name'] = $course_row['course_name'];
         $formdata['course_id'] = $course_id;
     } else {
         $errors[] = "Invalid Course Selected";
     }

     $valid_statuses = ['active', 'graduated', 'withdrawn'];
     if (!in_array($_POST['status'], $valid_statuses)) {
         $errors[] = "Invalid Status";
     } else {
         $formdata['status'] = trim($_POST['status']);
     }

     if (isset($_POST['student_parent_name']) && !empty($_POST['student_parent_name'])) {
        $parent_id = intval($_POST['student_parent_name']); 
    
        // Prepare and execute the query to fetch the parent name
        $parent_query = 'SELECT parent_name FROM parents WHERE parent_id = ?';
        if ($parent_stmt = $connect->prepare($parent_query)) {
            $parent_stmt->bind_param('i', $parent_id);
            $parent_stmt->execute();
            $parent_result = $parent_stmt->get_result();
    
            // Fetch the parent name if the query returns a result
            if ($parent_result->num_rows > 0) {
                $parent_row = $parent_result->fetch_assoc();
                $parent_name = $parent_row['parent_name'];
            } else {
                $parent_name = 'Unknown'; 
            }
            $parent_stmt->close();
        }
    if (empty($errors)) {
        // Check if email already exists
        $query = 'SELECT COUNT(*) FROM students WHERE student_email = ? AND student_id != ?';
        
        // Prepare the statement
        $stmt = $connect->prepare($query);
        $stmt->bind_param('si', $formdata["student_email"], $_POST['student_id']);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $errors[] = "Email Address Already Exists.";
        } else {
            // Update student record
    $query = 'UPDATE students 
    SET student_name = ?, 
        student_email = ?, 
        student_parent_name = ?, 
        student_date_of_birth = ?, 
        student_address = ?, 
        student_date_of_admission = ?, 
        student_contact_number1 = ?, 
        student_contact_number2 = ?, 
        student_image = ?, 
        status = ?, 
        course_id = ?, 
        student_course = ?, 
        student_updated_on = NOW(), 
        parent_id = ? 
    WHERE student_id = ?';

$stmt = $connect->prepare($query);
$stmt->bind_param(
     'ssssssssssisii', 
    $formdata['student_name'],
    $formdata['student_email'],
    $parent_name, 
    $formdata['student_date_of_birth'],
    $formdata['student_address'],
    $formdata['student_date_of_admission'],
    $formdata['student_contact_number1'],
    $formdata['student_contact_number2'],
    $formdata['student_image'],
    $formdata['status'],
    $formdata['course_id'],
    $formdata['course_name'],
    $parent_id, 
    $_POST['student_id'] 
);
if ($stmt->execute()) {

    // Check if the course has changed
    $old_course_query = 'SELECT course_id FROM students WHERE student_id = ?';
    $old_course_stmt = $connect->prepare($old_course_query);
    $old_course_stmt->bind_param('i', $_POST['student_id']);
    $old_course_stmt->execute();
    $old_course_result = $old_course_stmt->get_result();
    $old_course_row = $old_course_result->fetch_assoc();
    $old_course_id = $old_course_row['course_id'];
    
    $old_course_stmt->close();

    if ($formdata['course_id'] != $old_course_id) {
        // Remove the old course enrollment
        $delete_enroll_stmt = $connect->prepare('DELETE FROM enrollments WHERE student_id = ? AND course_id = ?');
        $delete_enroll_stmt->bind_param('ii', $_POST['student_id'], $old_course_id);
        $delete_enroll_stmt->execute();
        $delete_enroll_stmt->close();

        // Add the new course enrollment
        $insert_enroll_stmt = $connect->prepare('INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)');
        $insert_enroll_stmt->bind_param('ii', $_POST['student_id'], $formdata['course_id']);
        if (!$insert_enroll_stmt->execute()) {
            $errors[] = "Failed to update course enrollment.";
        }
        $insert_enroll_stmt->close();
    }

    header('Location: Student.php?msg=edit');
    exit();
} else {
    $errors[] = "Failed to update student";
}
            $stmt->close();
        }
    }
}
}
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_POST['delete_student'])) {
    // Sanitize and validate the student ID from POST data
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;

    // Check if the student ID is valid
    if ($student_id > 0) {
        // First, delete any related records in the enrollments table
        $deleteEnrollmentsQuery = "DELETE FROM enrollments WHERE student_id = ?";
        if ($enrollStmt = $connect->prepare($deleteEnrollmentsQuery)) {
            $enrollStmt->bind_param('i', $student_id);
            $enrollStmt->execute();
            $enrollStmt->close();
        }
        // Next, delete any related records in the grades table
        $deleteGradesQuery = "DELETE FROM grades WHERE student_id = ?";
         if ($gradesStmt = $connect->prepare($deleteGradesQuery)) {
            $gradesStmt->bind_param('i', $student_id);
            $gradesStmt->execute();
            $gradesStmt->close();
        }
        // Prepare SQL DELETE statement for students
        $deleteStudentQuery = "DELETE FROM students WHERE student_id = ?";
        if ($stmt = $connect->prepare($deleteStudentQuery)) {
            // Bind the student ID parameter
            $stmt->bind_param('i', $student_id);

            // Execute the statement
            if ($stmt->execute()) {
               
                header('Location: Student.php?msg=delete');
                exit();
            } else {
               
                echo "Error: Could not execute the delete query.";
            }

            // Close the statement
            $stmt->close();
        } else {
            echo "Error preparing delete statement.";
        }
    } else {
        echo "Invalid student ID.";
    }
}

$query = "";
$imageField = "";
$id=  $_SESSION['id'];

if ($userRole === "1") { 
    $query = "SELECT * FROM admin_users WHERE admin_id = ?";
    $imageField = 'admin_image';
} elseif ($userRole === "2") { 
    $query = "SELECT * FROM students WHERE student_id = ?";
    $imageField = 'student_image';
} else { 
    $query = "SELECT * FROM parents WHERE parent_id = ?";
    $imageField = 'parent_image';
}

if ($stmt = $connect->prepare($query)) {
    $stmt->bind_param("i", $id); 
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc(); // Fetch associative array
    } else {
        $admin = null; 
    }
    $stmt->close();
}
// Fetch user preferences
  
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
        $systemName = 'AutoReceipt';  
        
        // Optionally log or display a message
        error_log("No settings found in the database.");
    }
} else {
    // Handle query failure
    $systemName = 'AutoReceipt'; 
   
    // Optionally log or display a message
    error_log("Query failed: " . $connect->error);
}
 ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students</title>
    <link rel="icon" href="logo2.png">
    <link id="theme-style" rel="stylesheet" href="css/<?= htmlspecialchars($theme); ?>.css">
    <link id="text-size-style" rel="stylesheet" href="css/<?= htmlspecialchars($text_size); ?>.css">
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
     <main class="main-container">
         <div class="container-fluid mt-2 px-4">
         <?php 
                  if(isset($_GET['action'])){
                      if($_GET['action']== 'add'){
                        ?>
                        <h1 class="mt-2 head-update">Student Management</h1>
                         <ol class="breadcrumb mb-4 small"  style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                            <li class="breadcrumb-item"><a href="dashboard.php"  style="color: #f8f9fa;">Dashboard</a></li>
                            <li class="breadcrumb-item active"><a href="Student.php"  style="color: #f8f9fa;">Student Management</a></li>
                            <li class="breadcrumb-item active">Add Student</li>
                        </ol>
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
                        <div class="row">
                            <div class="col-md-12">
                             
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <span class="material-symbols-outlined text-bold">manage_accounts</span> Add New Student
                                    </div>
                                    <div class="card-body">
                                      <form method="post" enctype="multipart/form-data">
                                         <div class="mb-3">
                                           <label>Student Name<span class="text-danger">*</span></label>
                                           <input type="text" class="form-control" name="student_name" placeholder="Student Name" />
                                         </div>
                                         <div class="mb-3">
                                           <label>Student Email<span class="text-danger">*</span></label>
                                           <input type="email" class="form-control" name="student_email" placeholder="Student Email" />
                                         </div>
                                         <div class="mb-3">
                                                <label for="parent" class="form-label">Parent<span class="text-danger">*</span></label>
                                                <select class="form-select" id="student_parent_name" name="student_parent_name">
                                                    <option value="">Select Parent</option>
                                                    <?php
                                                    // Fetch parents from the database
                                                    include('DB_connect.php');
                                                    $query = "SELECT parent_id, parent_name FROM parents";
                                                    $result = $connect->query($query);
                                                    if ($result->num_rows > 0) {
                                                        while ($row = $result->fetch_assoc()) {
                                                            echo '<option value="' . htmlspecialchars($row['parent_id']) . '">' . htmlspecialchars($row['parent_name']) . '</option>';
                                                        }
                                                    } else {
                                                        echo '<option value="">No parents available</option>';
                                                    }
                                                    $connect->close();
                                                    ?>
                                                </select>
                                            </div>
                                         <div class="mb-3">
                                              <label>Student Date of Birth<span class="text-danger">*</span></label>
                                              <input type="date" class="form-control date-picker" name="student_date_of_birth" />
                                          </div>
                                          <div class="mb-4">
                                              <label>Address<span class="text-danger">*</span></label>
                                              <textarea name="student_address" class="form-control col-md-6"></textarea>
                                          </div>
                                          <div class="mb-3">
                                              <label>Date of Admission<span class="text-danger">*</span></label>
                                              <input type="date" class="form-control date-picker" name="student_date_of_admission" id="dateof" />
                                          </div>
                                         <div class="mb-3">
                                           <label>Contact Number 1<span class="text-danger">*</span></label>
                                           <input type="text" class="form-control" name="student_contact_number1" placeholder="Contact Number" />
                                         </div>
                                         <div class="mb-3">
                                           <label>Contact Number 2<span class="text-muted">(Optional)</span></label>
                                           <input type="text" class="form-control" name="student_contact_number2" placeholder="Contact Number" />
                                         </div>
                                         <div class="mb-3">
                                            <label>Status</label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="active">Active</option>
                                                <option value="graduated">Graduated</option>
                                                <option value="withdrawn">Withdrawn</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="course" class="form-label">Course<span class="text-danger">*</span></label>
                                            <select class="form-select" id="course" name="course" required>
                                                <?php
                                                // Fetch courses from the database
                                                include('DB_connect.php');
                                                $query = "SELECT course_id, course_name FROM courses";
                                                $result = $connect->query($query);
                                                if ($result->num_rows > 0) {
                                                    while ($row = $result->fetch_assoc()) {
                                                        echo '<option value="' . htmlspecialchars($row['course_id']) . '">' . htmlspecialchars($row['course_name']) . '</option>';
                                                    }
                                                } else {
                                                    echo '<option value="">No courses available</option>';
                                                }
                                                $connect->close();
                                                ?>
                                            </select>
                                        </div>
                                         <div class="mb-3">
                                           <label>Image</label><br/>
                                           <input type="file" class="form-control" name="student_image" />
                                           <span class="text-muted">Only .jpg & .png file allowed</span>
                                         </div>
                                         <div class="mt-4 mb-0">
                                           <input type="submit" name="add_student" value="Add" class="btn btn-success"/>
                                         </div>
                                      </form> 
                                    </div>
                                </div>
                            </div>
                        </div>

                        <footer class="main-footer px-3">
                <div class="pull-right hidden-xs"> 
                <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>  
              </footer>
                       <?php
                        
                      }else if($_GET['action'] == 'edit'){
                          if(isset($_GET['id'])){
                            $student_id = intval($_GET['id']); 

                            // Prepare and execute the query
                            $stmt = $connect->prepare("SELECT * FROM students WHERE student_id = ?");
                            $stmt->bind_param('i', $student_id);
                            $stmt->execute();
                            
                            // Get the result
                            $result = $stmt->get_result();

                              if($user_row = $result->fetch_assoc()){
                                ?>
                                 <h1 class="mt-2 head-update">Student Management</h1>
                                    <ol class="breadcrumb mb-4 small"   style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                                        <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
                                        <li class="breadcrumb-item"><a href="Student.php" style="color: #f8f9fa;">Student Management</a></li>
                                        <li class="breadcrumb-item active">Edit Student</a></li>
                                      </ol>
                                      <?php 
                                      
                                      ?>
                                       <?php if (isset($errors) && !empty($errors)): ?>
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                <?php foreach ($errors as $error): ?>
                                                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?><br>
                                                <?php endforeach; ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($form_submitted && isset($message) && !empty($message) && empty($errors)): ?>
                                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                             <?php  echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                        <?php endif; ?>
                              <div class="row">
                                  <div class="col-md-12">
                                        <div class="card mb-4">
                                          <div class="card-header">
                                
                                              <span class="material-symbols-outlined">manage_accounts</span>Student Edit Form
                                            </div>
                                           
                                      <div class="card-body">
                                      <form method="post" enctype="multipart/form-data">
                                      <div class="mb-3">
                                           <label>Student Name<span class="text-danger">*</span></label>
                                           <input type="text" class="form-control" name="student_name" placeholder="Student Name" value="<?php echo htmlspecialchars($user_row['student_name']); ?>" />
                                         </div>
                                         <div class="mb-3">
                                           <label>Student Email<span class="text-danger">*</span></label>
                                           <input type="email" class="form-control" name="student_email" placeholder="Student Email" value="<?php echo htmlspecialchars($user_row['student_email']); ?>" />
                                         </div>
                                         <div class="mb-3">
                                                <label for="parent" class="form-label">Parent<span class="text-danger">*</span></label>
                                                <select class="form-select" id="student_parent_name" name="student_parent_name">
                                                    <option value="">Select Parent</option>
                                                    <?php
                                                    // Fetch parents from the database
                                                    include('DB_connect.php');
                                                    $query = "SELECT parent_id, parent_name FROM parents";
                                                    $result = $connect->query($query);
                                                    if ($result->num_rows > 0) {
                                                        while ($row = $result->fetch_assoc()) {
                                                            $selected = (isset($formdata['parent_id']) && $formdata['parent_id'] == $row['parent_id']) ? 'selected' : '';
                                                            echo '<option value="' . htmlspecialchars($row['parent_id']) . '" ' . $selected . '>' . htmlspecialchars($row['parent_name']) . '</option>';
                                                        }
                                                    } else {
                                                        echo '<option value="">No parents available</option>';
                                                    }
                                                    $connect->close();
                                                    ?>
                                                </select>
                                            </div>
                                         <div class="mb-3">
                                              <label>Student Date of Birth<span class="text-danger">*</span></label>
                                              <input type="date" class="form-control date-picker" name="student_date_of_birth" value="<?php echo htmlspecialchars($user_row['student_date_of_birth']); ?>" />
                                          </div>
                                          <div class="mb-4">
                                              <label>Address<span class="text-danger">*</span></label>
                                              <textarea name="student_address" class="form-control col-md-6"><?php echo $user_row['student_address'];?></textarea>
                                          </div>
                                          <div class="mb-3">
                                              <label>Date of Admission<span class="text-danger">*</span></label>
                                              <input type="date" class="form-control date-picker" name="student_date_of_admission" value="<?php echo htmlspecialchars($user_row['student_date_of_admission']); ?>"/>
                                          </div>
                                         <div class="mb-3">
                                           <label>Contact Number 1<span class="text-danger">*</span></label>
                                           <input type="text" class="form-control" name="student_contact_number1" placeholder="Contact Number" value="<?php echo htmlspecialchars($user_row['student_contact_number1']); ?>"/>
                                         </div>
                                         <div class="mb-3">
                                           <label>Contact Number 2<span class="text-muted">(Optional)</span></label>
                                           <input type="text" class="form-control" name="student_contact_number2" placeholder="Contact Number" value="<?php echo htmlspecialchars($user_row['student_contact_number2']); ?>"/>
                                         </div>
                                         <div class="mb-3">
                                            <label>Course</label>
                                            <select class="form-select" id="course" name="course" required>
                                                <?php
                                                include('DB_connect.php');
                                                
                                                $query = 'SELECT course_id, course_name FROM courses';
                                                $result = $connect->query($query);

                                                // Prepare selected course
                                                $selected_course = !empty($user_row['course_id']) ? $user_row['course_id'] : '';

                                                while ($row = $result->fetch_assoc()) {
                                                    $selected = $row['course_id'] == $selected_course ? 'selected' : '';
                                                    echo '<option value="' . htmlspecialchars($row['course_id']) . '" ' . $selected . '>' . htmlspecialchars($row['course_name']) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                         <div class="mb-3">
                                            <label>Status</label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="active" <?php echo $user_row['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="graduated" <?php echo $user_row['status'] == 'graduated' ? 'selected' : ''; ?>>Graduated</option>
                                                <option value="withdrawn" <?php echo $user_row['status'] == 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                                            </select>
                                        </div>
                                         <div class="mb-3">
                                           <label>Image</label><br/>
                                           <input type="file" class="form-control" name="student_image"/><br/>
                                           <span class="text-muted">Only .jpg & .png file allowed</span> <br/>
                                           <?php 
                                            if($user_row['student_image'] != ''){
                                              echo '<img src="upload/'.$user_row['student_image'].'" class="img-thumbnail" width=100 />';
                                              echo '<input type="hidden" name="hidden_student_image" value="'.$user_row['student_image'].'"/>';
                                            }
                                           ?>
                                         </div>
                                         <div class="mt-4 mb-0">
                                          <input type="hidden" name="student_id" value="<?php echo $user_row['student_id'] ?>" />
                                           <input type="submit" name="edit_student" value="Edit" class="btn btn-success"/>
                                         </div>
                                      </form>
                                      
                                      </div>
                                      
                                    </div>
                                  </div>
                              </div>
                              <footer class="main-footer px-3">
                                          <div class="pull-right hidden-xs"> 
                                          <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>  
                                        </footer>
                                <?php
                              }
                          }
                      }
                  }else{
                    ?>
              <h1 class="mt-2 head-update">Student Management</h1>
               <ol class="breadcrumb mb-4 small"  style="background-color:#9b9999 ; color: white; padding: 10px; border-radius: 5px;">
                  <li class="breadcrumb-item"><a href="dashboard.php" style="color: #f8f9fa;">Dashboard</a></li>
                  <li class="breadcrumb-item active">Student Management</a></li>
                </ol>
                <?php
                if (isset($_GET['msg'])) {
                    if ($_GET['msg'] == 'add') {
                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i> Successfully Added student and Sent Email
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
                    }
                    if ($_GET['msg'] == 'edit') {
                      echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                          <i class="bi bi-check-circle"></i> Successfully updated student
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
                  }
                  if ($_GET['msg'] == 'delete') {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i>Student deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';
                }
                }
                ?>
                <div class="card mb-4 ">
                  <div class="card-header">
                    <div class="row">
                      <div class="col-md-6">
                         <span class="material-symbols-outlined">manage_accounts</span>All Students
                      </div>
                   <div class="col-md-6 d-flex justify-content-end add-button ">
                   <a href="Student.php?action=add" class="btn btn-success btn-sm">Add New Student</a>
                  </div>
            </div>
        </div>
        
        <div class="card-body">
        <div class="table-responsive">
             <table id="student_data" class="table table-bordered table-striped">
                  <thead>
                     <tr>
                        
                        <th>Image</th>
                        <th>Admission Number</th>
                        <th>Student Name</th>
                        <th>Student Email</th>
                        <th>Student Address</th>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Action</th>
                     </tr>
                  </thead>
             </table>
             </div>
            </div>
            </div>
        </div>
        <footer class="main-footer px-3">
                <div class="pull-right hidden-xs"> 
                <p>&copy; <?php echo date('Y'); ?> <a href="dashboard.php" class="text-white"><?php echo $systemName; ?></a>. All rights reserved.</p>
              </footer>
        </main>
         <!--main-->
    <!--custom tag-->
    <script>
        // Sidebar Toggle Functions
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
        //DataTable is initialized and ready to use once the page content is fully loaded.
        $(document).ready(function() {
    $('#student_data').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        order: [],
        scrollX: false,  // Ensure horizontal scrolling is disabled
        ajax: {
            //The URL to which the AJAX request is sent. In this case, data will be fetched from here
            url: "action.php",
            type: "POST",
            data: function(d) {
                //request is for fetching student data.
                d.action = 'fetch_student';
            }
        }
    });
});
    </script>
    <?php
      }
      ?>
         
</body>
</html>

<?php 
?>