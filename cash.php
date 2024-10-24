<?php   
session_start();
include('DB_connect.php');

if (isset($_SESSION["id"]) && isset($_SESSION["role"])) {
    // Store user role for easier access

    $userId = $_SESSION["id"];
    $userRole = $_SESSION["role"];
    $adminType = $_SESSION["admin_type"] ?? '';
    $theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
    $text_size = isset($_COOKIE['text_size']) ? $_COOKIE['text_size'] : 'medium';
    // Map roles to display names
    $roleNames = [
        "1" => "Admin",
        "2" => "Student",
        "3" => "Parent"
    ];
    // Determine role name based on the session
    $displayRole = $roleNames[$userRole] ?? "Parent";
}
$due_date = '';

// Calculate the due date only when the form is being loaded
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    $payment_date = date('Y-m-d H:i:s');
    // Calculate the due date as 10 days after the payment date
    $due_date = date('Y-m-d', strtotime($payment_date .' -9 days'));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_payment'])) {
    include('DB_connect.php'); // Ensure this file initializes $connect

    $id = $_SESSION['id']; // Assuming this is used somewhere else for filtering
    
    $student_id = intval($_POST['student_name']);
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    $paid_amount = isset($_POST['paid_amount']) ? floatval($_POST['paid_amount']) : 0;
    $remaining_amount = isset($_POST['remaining_amount']) ? floatval($_POST['remaining_amount']) : 0;
    $status = ''; // Default to 'pending'

    $parent_id= isset($_POST['parent_id']);
    $payment_date = date('Y-m-d H:i:s');

    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : '';
   
    if ($paid_amount >= $total_amount) {
        $status = 'Paid';
    } elseif ($paid_amount > 0) {
        $status = 'Pending';
    } else {
        $status = 'Unpaid';
    }
    // Basic validation
    $errorMessages = '';
    $errors = [];
    if (empty($payment_method)) {
        $errors[] = 'Payment method is required.';
    }

    if ($total_amount <= 0) {
        $errors[] = 'payable amount must be greater than zero.';
    }

    if ($paid_amount < 0) {
        $errors[] = 'Paid amount cannot be negative.';
    }

    if ($paid_amount > $total_amount) {
        $errors[] = 'Paid amount cannot be greater than the total amount.';
    }

    // Or however you get the parent_id from the form
$fetch_parent_query = "SELECT p.parent_name
FROM students s
JOIN parents p ON s.parent_id = p.parent_id
WHERE s.student_id = ?;";
$parent_stmt = $connect->prepare($fetch_parent_query);
$parent_stmt->bind_param('i', $id);
$parent_stmt->execute();
$parent_result = $parent_stmt->get_result();

if ($parent_row = $parent_result->fetch_assoc()) {
    $parent_name = $parent_row['parent_name'];
} else {
    $parent_name = ''; // Handle as needed
}
$parent_stmt->close();
    

    if (!empty($errors)) {
        // Convert errors array to a query string format
        $errorMessages = urlencode(implode('; ', $errors)); // Use semicolon to separate messages
    
        header('Location: deposit.php?msg=error&errors=' . $errorMessages); // Redirect with query parameters
        exit();
    }else {
       
        $message = 'Payment processed successfully.';
    }


$student_query = "SELECT parent_id FROM students WHERE student_id = ?";
$stmt = $connect->prepare($student_query);
$stmt->bind_param('i',  $id);
$stmt->execute();
$stmt->bind_result($parents_id);
$stmt->fetch();
$stmt->close();

$fetch_parent_query = "SELECT parent_name FROM parents WHERE parent_id = ?";
$parent_stmt = $connect->prepare($fetch_parent_query);
$parent_stmt->bind_param('i', $id);
$parent_stmt->execute();
$parent_row = $parent_stmt->get_result()->fetch_assoc();
$parent_name = $parent_row ? $parent_row['parent_name'] : '';

if ($displayRole === 'Student' || $displayRole === 'Admin') {
    $parentIdToBind = $parents_id; // Use the parent's ID fetched from the students table
} else {
    $parentIdToBind = $id; // Assuming $id is the student_id in this context
}



    // Insert payment details into the database
    $query = " INSERT INTO deposit (student_id, payment_number, due_date, total_amount, paid_amount, payment_method, status, payment_date, parent_id, parent_name, remaining_amount)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";

            if ($stmt = $connect->prepare($query)) {
                $stmt->bind_param("isssssssss", $student_id, $payment_number, $due_date, $total_amount, $paid_amount, $payment_method, $status, $parentIdToBind, $parent_name, $remaining_amount);

                if ($stmt->execute()) {
                    // Redirect to the same page with a success message
                    header('Location: deposit.php?msg=success');
                    exit();
    } else {
        // Print error if execution fails
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    } else {
        echo '<p>Failed to prepare the SQL statement.</p>';
    }

}
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'add') {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> Fees Successfully paid
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    if ($_GET['msg'] == 'success') {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> Payment processed successfully
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
    }
    if ($_GET['msg'] === 'error' && isset($_GET['errors'])) {
        // Decode and display error messages
        $errors = urldecode($_GET['errors']);
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        echo '<i class="bi bi-exclamation-circle"></i> ' . htmlspecialchars($errors);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    } 
}

$query = 'SELECT c.course_id, c.course_name, c.course_fee 
FROM enrollments e
JOIN courses c ON e.course_id = c.course_id
WHERE e.student_id = ?';
$stmt = $connect->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

// Store courses and fees in an array
$courses = [];
while ($row = $result->fetch_assoc()) {
$courses[] = $row;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Card Payment</title>
    <link rel="icon" href="logo2.png">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            overflow: auto; /* Allow scrolling on the body */
        }
        .modal {
            display: block; /* Make modal always visible */
            background: rgba(0, 0, 0, 0.5); /* Semi-transparent background */
            height: 100%; /* Full height to cover the screen */
            overflow: auto; /* Allow scrolling if needed */
        }
        .modal-dialog {
            position:absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%); /* Center the modal */
            max-width: 500px; /* Optional: Set a max width */
            width: 100%; /* Optional: Full width on smaller screens */
        }
        .modal-content {
            border-radius: 10px;
        }
        .form-label {
            font-weight: bold;
        }
        .btn {
            width: 100%;
        }
    </style>
</head>
<body>

<!-- M-Pesa Payment Modal -->
<div class="modal" tabindex="-1" id="payFeesModal" role="dialog" aria-labelledby="payFeesModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"id="payFeesModalLabel">Cash Payment</h3>
                <button type="button" class="close" aria-label="Close" onclick="window.location.reload();">
                    
                </button>
            </div>
            <div class="modal-body">
                <form action="deposit.php" method="post">
                <?php include('DB_connect.php');

if ($displayRole === 'Admin') {
    // Prepare query to select all parent IDs
    $student_query = "SELECT parent_id FROM parents";
    $stmt = $connect->prepare($student_query);
    // Execute the query
    $stmt->execute();
    // Bind result
    $stmt->bind_result($parent_id);
 
    // Close the statement
    $stmt->close();
   
}
?>
                <?php if ($displayRole === 'Admin'): ?>
                    <div class="mb-3">
                        <label for="parentSelect" class="form-label">Select Parent</label>
                        <select id="parentSelect" class="form-select" name="parent_id" required>
                        <?php
        include('DB_connect.php');

        // Prepare the query to fetch all parents
        $parent_query = "SELECT parent_id, parent_name FROM parents ORDER BY parent_name";
        $parent_stmt = $connect->prepare($parent_query);

        // Check if the statement was prepared correctly
        if ($parent_stmt) {
            $parent_stmt->execute();
            $parent_result = $parent_stmt->get_result();

            // Check if there are any parents
            if ($parent_result->num_rows > 0) {
                // Iterate over each parent
                while ($parentRow = $parent_result->fetch_assoc()) {
                    // Display each parent as an option
                    echo '<option value="' . htmlspecialchars($parentRow['parent_id']) . '">' . htmlspecialchars($parentRow['parent_name']) . '</option>';
                }
            } else {
                echo '<option value="">No parents found</option>';
            }

            $parent_stmt->close();
        } else {
            echo '<option value="">Error preparing statement.</option>';
        }

        $connect->close();
        ?>
                        </select>
                    </div>     
                                            <div class="mb-3">
    <label for="studentSelect" class="form-label">Select Student</label>
    <select id="studentSelect" class="form-select" name="student_name" required>
        <option value="">Select a parent first</option>
    </select>
</div>
<div class="mb-3">
    <label for="studentNumber" class="form-label">Admission Number</label>
    <select id="studentNumber" class="form-select" name="student_number" required>
        <option value="">Select a student</option>
    </select>
</div>
<div class="mb-3">
    <label for="courseSelect" class="form-label">Select Courses:</label>
    <select id="courseSelect" class="form-select" name="course_id">
        <!-- Options will be populated dynamically -->
    </select>
</div>

<div class="mb-3">
    <label for="totalAmount" class="form-label">Total Payable Amount:</label>
    <input type="number" class="form-control" id="totalAmount" name="total_amount" min="50" required readonly>
</div>
                                                <div class="mb-3">
                                                    <label for="paidAmount" class="form-label">Paid Amount:</label>
                                                    <input type="number" class="form-control" id="paidAmount" name="paid_amount" min="0" required>
                                                </div>
                                                <div class="mb-3">
        <label for="remainingAmount" class="form-label">Remaining Amount</label>
        <input type="number" class="form-control" id="remainingAmount" name="remaining_amount" readonly>
    </div>
                                            <div class="mb-3">
                                                <label for="dueDate" class="form-label">Due Date</label>
                                                <input type="text" class="form-control" id="dueDate" name="due_date" value="<?php echo htmlspecialchars($due_date); ?>" readonly>
                                            </div>
                                            <label for="payment_method">Payment Method:</label>
                                                <select id="payment_method" name="payment_method" required>
                                                <option value="cash" class="text-muted">Choose Payment</option>
                                                    <option value="cash">Cash</option>
                                                    <option value="mpesa">M-Pesa</option>
                                                    <option value="credit_card">Credit Card</option>
                                                </select><br><br>

                                                <div class="mb-3">
                                                    <label for="status" class="form-label">Status</label>
                                                    <div class="custom-select-wrapper">
                                                        <div class="custom-select">
                                                            <div class="selected">Select Status</div>
                                                            <div class="dropdown-menu">
                                                                <div class="dropdown-item btn-danger" data-value="Unpaid">Unpaid</div>
                                                                <div class="dropdown-item btn-warning" data-value="Pending">Pending</div>
                                                                <div class="dropdown-item btn-success" data-value="Paid">Paid</div>
                                                            </div>
                                                            <select id="status" name="status">
                                                                <option value="Unpaid">Unpaid</option>
                                                                <option value="Pending">Pending</option>
                                                                <option value="Paid">Paid</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <button type="submit" class="btn btn-success" name="submit_payment">Submit Payment</button>
                                                </div>
                                            </form>
                    <?php endif; ?>
                <?php
                    include('DB_connect.php');
                       
                    $student_query = "SELECT parent_id FROM students WHERE student_id = ?";
                    $stmt = $connect->prepare($student_query);
                    $stmt->bind_param('i',  $userId);
                    $stmt->execute();
                    $stmt->bind_result($parent_id);
                    $stmt->fetch();
                    $stmt->close();       
                ?>
                <?php if ($displayRole === 'Student'): ?>
                <div class="mb-3">
                                                    <label for="studentSelect" class="form-label">Select Student</label>
                                                    <select id="studentSelect" class="form-select" name="student_name" required>
                                                    <?php
                                                     include('DB_connect.php');
                                                                                                            
                                                        // Check if session and database connection are valid
                                                        if (isset($_SESSION['id']) && $connect instanceof mysqli) {
                                                            $loggedInUser = $_SESSION['id']; // Get the logged-in user's ID

                                                            // Determine the role and prepare the query accordingly
                                                            if ($displayRole === 'Parent') {
                                                                $query = "
                                                                    SELECT students.student_id, students.student_name 
                                                                    FROM students
                                                                    WHERE students.parent_id = ?
                                                                    ORDER BY students.student_name
                                                                ";
                                                            } elseif ($displayRole === 'Student') {
                                                                // Fetching a specific student
                                                                $query = "
                                                                    SELECT students.student_id, students.student_name 
                                                                    FROM students
                                                                    WHERE students.student_id = ?
                                                                    ORDER BY students.student_name
                                                                ";
                                                            } else {
                                                                echo '<option value="">Invalid role</option>';
                                                                exit;
                                                            }

                                                            // Prepare and execute the query
                                                            $studentStmt = $connect->prepare($query);
                                                            if ($studentStmt) {
                                                                // Bind parameters based on the role
                                                                $studentStmt->bind_param('i', $loggedInUser); // Bind the logged-in user's ID
                                                                $studentStmt->execute();
                                                                $studentResult = $studentStmt->get_result();

                                                                if ($studentResult->num_rows > 0) {
                                                                    while ($studentRow = $studentResult->fetch_assoc()) {
                                                                        // Output each student as an option in the dropdown
                                                                        echo '<option value="' . htmlspecialchars($studentRow['student_id']) . '">' 
                                                                        . htmlspecialchars($studentRow['student_name']) . '</option>';
                                                                    }
                                                                } else {
                                                                    // If no students are found, display a placeholder option
                                                                    echo '<option value="">No students found</option>';
                                                                }
                                                                $studentStmt->close();
                                                            } else {
                                                                // Display an error if the statement could not be prepared
                                                                echo '<option value="">Error preparing the query</option>';
                                                            }
                                                        } else {
                                                            // Display an error if the database connection is not valid or session is not set
                                                            echo '<option value="">Database connection error or session not valid</option>';
                                                        }

                                                        // Close the database connection

                                                        ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="studentSelect" class="form-label">Admission Number</label>
                                                    <select id="studentSelect" class="form-select" name="student_number" required>
                                                    <?php
                                                    include('DB_connect.php');

                                                    // Check if session and database connection are valid
                                                    if (isset($_SESSION['id']) && $connect instanceof mysqli) {
                                                        $loggedInUserId = $_SESSION['id']; // Get the logged-in user's ID

                                                        // Determine the role and prepare the query accordingly
                                                        if ($displayRole === 'Parent') {
                                                            // Parent: Fetch all students related to this parent
                                                            $query = "
                                                                SELECT students.student_id, students.student_number 
                                                                FROM students
                                                                WHERE students.parent_id = ?
                                                                ORDER BY students.student_number
                                                            ";
                                                        } elseif ($displayRole === 'Student') {
                                                            // Student: Fetch the logged-in student's details
                                                            $query = "
                                                                SELECT students.student_id, students.student_number 
                                                                FROM students
                                                                WHERE students.student_id = ?
                                                                ORDER BY students.student_number
                                                            ";
                                                        } else {
                                                            echo '<option value="">Invalid role</option>';
                                                            exit;
                                                        }

                                                        // Prepare and execute the query
                                                        $studentStmt = $connect->prepare($query);
                                                        if ($studentStmt) {
                                                            // Bind parameters based on the role
                                                            $studentStmt->bind_param('i', $loggedInUserId); // Bind the logged-in user's ID
                                                            $studentStmt->execute();
                                                            $studentResult = $studentStmt->get_result();

                                                            if ($studentResult->num_rows > 0) {
                                                                while ($studentRow = $studentResult->fetch_assoc()) {
                                                                    // Output each student as an option in the dropdown
                                                                    echo '<option value="' . htmlspecialchars($studentRow['student_id']) . '"';
                                                                    if ($displayRole === 'Student' && $studentRow['student_id'] == $loggedInUserId) {
                                                                        echo ' selected'; // Mark the current student as selected
                                                                    }
                                                                    echo '>' . htmlspecialchars($studentRow['student_number']) . '</option>';
                                                                }
                                                            } else {
                                                                // If no students are found, display a placeholder option
                                                                echo '<option value="">No students found</option>';
                                                            }

                                                            $studentStmt->close();
                                                        } else {
                                                            // Display an error if the statement could not be prepared
                                                            echo '<option value="">Error preparing the query</option>';
                                                        }
                                                    } else {
                                                        // Display an error if the database connection is not valid or session is not set
                                                        echo '<option value="">Database connection error or session not valid</option>';
                                                    }

                                                    // Close the database connection
                                                    $connect->close();
                                                    ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                                    <label for="parentSelect" class="form-label">Select Parent</label>
                                                                    <select id="parentSelect" class="form-select" name="parent_id" required>
                                                                    <?php
                                                                include('DB_connect.php');

                                                                // Ensure $parent_id is correctly set and valid
                                                                $parent_id = intval($parent_id); // Cast to integer if $parent_id should be an integer
                                                                
                                                                // Debugging: Print the parent_id to verify it's being set correctly
                                                                    echo 'Parent ID: ' . $parent_id;
                                                                
                                                                // Prepare the query to fetch parent details
                                                                $parent_query = "SELECT parent_id, parent_name FROM parents WHERE parent_id = ? ORDER BY parent_name";
                                                                $stmt = $connect->prepare($parent_query);
                                                                
                                                                // Check if the statement was prepared correctly
                                                                if ($stmt) {
                                                                    $stmt->bind_param('i', $parent_id);
                                                                    $stmt->execute();
                                                                    $parent_result = $stmt->get_result();
                                                                
                                                                    if ($parent_result->num_rows > 0) {
                                                                        while ($parentRow = $parent_result->fetch_assoc()) {
                                                                            echo '<option value="' . htmlspecialchars($parentRow['parent_id']) . '"';
                                                                            if ($parentRow['parent_id'] == $parent_id) {
                                                                                echo ' selected'; // Mark the current parent as selected
                                                                            }
                                                                            echo '>' . htmlspecialchars($parentRow['parent_name']) . '</option>';
                                                                        }
                                                                    } else {
                                                                        echo '<option value="">No parents found</option>';
                                                                    }
                                                                
                                                                    $stmt->close();
                                                                }   
                                                            ?>
                                                                    </select>
                                                                </div>     
                                                                <div class="mb-3">
                                                <label for="courseSelect" class="form-label">Select Course:</label>
                                                <select class="form-select" id="courseSelect" name="course_id">
                                                    <?php foreach ($courses as $course): ?>
                                                        <option value="<?php echo htmlspecialchars($course['course_id']); ?>" data-fee="<?php echo htmlspecialchars($course['course_fee']); ?>">
                                                            <?php echo htmlspecialchars($course['course_name']); ?> - Ksh.<?php echo htmlspecialchars($course['course_fee']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label for="totalAmount" class="form-label">Payable amount:</label>
                                                <input type="number" class="form-control" id="totalAmount" name="total_amount" min="50" value="<?php echo htmlspecialchars($courses[0]['course_fee'] ?? ''); ?>" required readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label for="paidAmount" class="form-label">Paid Amount</label>
                                                <input type="number" class="form-control" id="paidAmount" name="paid_amount"  min="50" required >
                                            </div>
                                            <div class="mb-3">
                                                <label for="remainingAmount" class="form-label">Remaining Amount</label>
                                                <input type="number" class="form-control" id="remainingAmount" name="remaining_amount" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label for="dueDate" class="form-label">Due Date</label>
                                                <input type="text" class="form-control" id="dueDate" name="due_date" value="<?php echo htmlspecialchars($due_date); ?>" readonly>
                                            </div>
                                            <label for="payment_method">Payment Method:</label>
                                          
                                                <select id="payment_method" name="payment_method" >
                                                <option value="cash" class="text-muted">Choose Payment</option>
                                                    <option value="cash">Cash</option>
                                                    <option value="mpesa">M-Pesa</option>
                                                    <option value="credit_card">Credit Card</option>
                                                </select><br><br>


                                            <div class="mb-3">
                                            <label for="status" class="form-label">Status</label>             
                                            <select id="status" name="status" disabled>
                                                <option value="Unpaid">Unpaid</option>
                                                <option value="Pending">Pending</option>
                                                <option value="Paid">Paid</option>
                                            </select>
                                           
                                            </div>
                                            <div class="modal-footer mt-2">
                                            <button type="submit" class="btn btn-success me-2" name="submit_payment">Submit Payment</button>
                                                    <a href="deposit.php" class="btn btn-secondary" data-bs-dismiss="modal">Back</a>
                                            </div>
                                        <?php endif; ?>     
                                        <?php if ($displayRole === 'Parent'): ?>  
                                            <div class="mb-3">
                                                        <label for="studentSelect" class="form-label">Select Student</label>
                                                        <select id="studentSelect" class="form-select" name="student_name" required>
                                                        <?php
                                                        include('DB_connect.php');
                                                        
                                                                // Check if session and database connection are valid
                                                                if (isset($_SESSION['id']) && $connect instanceof mysqli) {
                                                                    $loggedInUser = $_SESSION['id']; // Get the logged-in user's ID

                                                                    // Determine the role and prepare the query accordingly
                                                                
                                                                        $query = "
                                                                            SELECT students.student_id, students.student_name 
                                                                            FROM students
                                                                            WHERE students.parent_id = ?
                                                                            ORDER BY students.student_name
                                                                        ";
                                                                    // Prepare and execute the query
                                                                    $studentStmt = $connect->prepare($query);
                                                                    if ($studentStmt) {
                                                                        // Bind parameters based on the role
                                                                        $studentStmt->bind_param('i', $loggedInUser); // Bind the logged-in user's ID
                                                                        $studentStmt->execute();
                                                                        $studentResult = $studentStmt->get_result();

                                                                        if ($studentResult->num_rows > 0) {
                                                                            while ($studentRow = $studentResult->fetch_assoc()) {
                                                                                // Output each student as an option in the dropdown
                                                                                echo '<option value="' . htmlspecialchars($studentRow['student_id']) . '">' 
                                                                                . htmlspecialchars($studentRow['student_name']) . '</option>';
                                                                            }
                                                                        } else {
                                                                            // If no students are found, display a placeholder option
                                                                            echo '<option value="">No students found</option>';
                                                                        }
                                                                        $studentStmt->close();
                                                                    } else {
                                                                        // Display an error if the statement could not be prepared
                                                                        echo '<option value="">Error preparing the query</option>';
                                                                    }
                                                                } else {
                                                                    // Display an error if the database connection is not valid or session is not set
                                                                    echo '<option value="">Database connection error or session not valid</option>';
                                                                }

                                                                // Close the database connection

                                                                ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="studentSelect" class="form-label">Admission Number</label>
                                                        <select id="studentSelect" class="form-select" name="student_number" required>
                                                        <?php
                                                        include('DB_connect.php');

                                                        // Check if session and database connection are valid
                                                        if (isset($_SESSION['id']) && $connect instanceof mysqli) {
                                                            $loggedInUserId = $_SESSION['id']; // Get the logged-in user's ID
                                                        
                                                                $query = "
                                                                    SELECT students.student_id, students.student_number 
                                                                    FROM students
                                                                    WHERE students.parent_id = ?
                                                                    ORDER BY students.student_number
                                                                ";
                                                            // Prepare and execute the query
                                                            $studentStmt = $connect->prepare($query);
                                                            if ($studentStmt) {
                                                                // Bind parameters based on the role
                                                                $studentStmt->bind_param('i', $loggedInUserId); // Bind the logged-in user's ID
                                                                $studentStmt->execute();
                                                                $studentResult = $studentStmt->get_result();

                                                                if ($studentResult->num_rows > 0) {
                                                                    while ($studentRow = $studentResult->fetch_assoc()) {
                                                                        // Output each student as an option in the dropdown
                                                                        echo '<option value="' . htmlspecialchars($studentRow['student_id']) . '"';
                                                                        if ($displayRole === 'Parent' && $studentRow['student_id'] == $loggedInUserId) {
                                                                            echo ' selected'; // Mark the current student as selected
                                                                        }
                                                                        echo '>' . htmlspecialchars($studentRow['student_number']) . '</option>';
                                                                    }
                                                                } else {
                                                                    // If no students are found, display a placeholder option
                                                                    echo '<option value="">No students found</option>';
                                                                }

                                                                $studentStmt->close();
                                                            } else {
                                                                // Display an error if the statement could not be prepared
                                                                echo '<option value="">Error preparing the query</option>';
                                                            }
                                                        } else {
                                                            // Display an error if the database connection is not valid or session is not set
                                                            echo '<option value="">Database connection error or session not valid</option>';
                                                        }

                                                        // Close the database connection
                                                        $connect->close();
                                                        ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                            <label for="parentSelect" class="form-label">Select Parent</label>
                            <select id="parentSelect" class="form-select" name="parent_id" required>
                            <?php
            include('DB_connect.php');

            // Check if the session is valid
            if (isset($_SESSION['id']) && $connect instanceof mysqli) {
                $parent_id = $_SESSION['id']; // Get the logged-in parent's ID
                
                // Prepare the query to fetch the parent details
                $parent_query = "SELECT parent_id, parent_name FROM parents WHERE parent_id = ?";
                $stmt = $connect->prepare($parent_query);

                // Check if the statement was prepared correctly
                if ($stmt) {
                    $stmt->bind_param('i', $parent_id);
                    $stmt->execute();
                    $parent_result = $stmt->get_result();

                    if ($parent_result->num_rows > 0) {
                        while ($parentRow = $parent_result->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($parentRow['parent_id']) . '" selected>' 
                                . htmlspecialchars($parentRow['parent_name']) . '</option>';
                        }
                    } else {
                        echo '<option value="">No parents found</option>';
                    }

                    $stmt->close();
                }
            } else {
                echo '<option value="">No valid session found.</option>';
            }
            ?>
                            </select>
                        </div>     
                        <div class="mb-3">
        <label for="courseSelect" class="form-label">Select Course:</label>
        <select class="form-select" id="courseSelect" name="course_id">
        <?php
            include('DB_connect.php');

            // Check if session is valid and parent ID is set
            if (isset($_SESSION['id']) && $connect instanceof mysqli) {
                $parent_id = $_SESSION['id'];

                // Query to get students related to the parent
                $student_query = "
                    SELECT students.student_id, students.student_name, courses.course_id, courses.course_name, courses.course_fee 
                    FROM students 
                    JOIN enrollments ON students.student_id = enrollments.student_id 
                    JOIN courses ON enrollments.course_id = courses.course_id 
                    WHERE students.parent_id = ?
                    ORDER BY students.student_name, courses.course_name
                ";

                $stmt = $connect->prepare($student_query);
                if ($stmt) {
                    $stmt->bind_param('i', $parent_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        // To keep track of already displayed courses
                        $displayed_courses = [];

                        while ($row = $result->fetch_assoc()) {
                            $course_id = $row['course_id'];
                            if (!isset($displayed_courses[$course_id])) {
                                echo '<option value="' . htmlspecialchars($course_id) . '" data-fee="' . htmlspecialchars($row['course_fee']) . '">';
                                echo htmlspecialchars($row['student_name']) . ': ' . htmlspecialchars($row['course_name']) . ' - Ksh.' . htmlspecialchars($row['course_fee']);
                                echo '</option>';
                                
                                // Mark this course as displayed
                                $displayed_courses[$course_id] = true;
                            }
                        }
                    } else {
                        echo '<option value="">No courses found for related students.</option>';
                    }

                    $stmt->close();
                } else {
                    echo '<option value="">Error preparing the query.</option>';
                }
            } else {
                echo '<option value="">No valid session found.</option>';
            }
            ?>
        </select>
    </div>
    <div class="mb-3">
        <label for="totalAmount" class="form-label">Payable amount:</label>
        <input type="number" class="form-control" id="totalAmount" name="total_amount" min="50" value="<?php echo htmlspecialchars($courses[0]['course_fee'] ?? ''); ?>" required readonly>
    </div>
<div class="mb-3">
    <label for="paidAmount" class="form-label">Paid Amount</label>
    <input type="number" class="form-control" id="paidAmount" name="paid_amount"  min="50" required >
</div>
 <div class="mb-3">
            <label for="remainingAmount" class="form-label">Remaining Amount</label>
            <input type="number" class="form-control" id="remainingAmount" name="remaining_amount" readonly>
        </div>
<div class="mb-3">
     <label for="dueDate" class="form-label">Due Date</label>
    <input type="text" class="form-control" id="dueDate" name="due_date" value="<?php echo htmlspecialchars($due_date); ?>" readonly>
</div>
<label for="payment_method">Payment Method:</label>
                                          
                                                <select id="payment_method" name="payment_method" >
                                                <option value="cash" class="text-muted">Choose Payment</option>
                                                    <option value="cash">Cash</option>
                                                    <option value="mpesa">M-Pesa</option>
                                                    <option value="credit_card">Credit Card</option>
                                                </select><br><br>
<label for="status" class="form-label">Status</label>
    <div class="custom-select-wrapper">
        <div class="custom-select">
                 <div class="dropdown-menu">
                     <div class="dropdown-item btn-danger" data-value="Unpaid">Unpaid</div>
                        <div class="dropdown-item btn-warning" data-value="Pending">Pending</div>
                            <div class="dropdown-item btn-success" data-value="Paid">Paid</div>
 </div>
 <select id="status" name="status" disabled>
     <option value="Unpaid">Unpaid</option>
     <option value="Pending">Pending</option>
     <option value="Paid">Paid</option>
</select>
 </div>
 </div>
   <div class="modal-footer mt-2">
   <button type="submit" class="btn btn-success me-2" name="submit_payment">Submit Payment</button>
        <a href="deposit.php" class="btn btn-secondary" data-bs-dismiss="modal">Back</a>
  </div>
  <?php endif; ?>  
</form>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Get the current PHP file name
        const currentFile = window.location.pathname.split("/").pop();

        // Determine the payment method based on the current file
        let selectedPaymentMethod = '';

        switch (currentFile) {
            case 'cash.php':
                selectedPaymentMethod = 'cash';
                break;
            case 'daraja.php':
                selectedPaymentMethod = 'mpesa';
                break;
            case 'creditpayment.php':
                selectedPaymentMethod = 'credit_card';
                break;
            default:
                selectedPaymentMethod = ''; // Default case
                break;
        }

        // Set the selected payment method in the dropdown
        const paymentSelect = document.getElementById('payment_method');
        if (selectedPaymentMethod) {
            paymentSelect.value = selectedPaymentMethod;
        }
    });
         document.addEventListener('DOMContentLoaded', function() {
    // Event handler for when a parent is selected
    document.getElementById('parentSelect').addEventListener('change', function() {
        var parentId = this.value;
        var studentSelect = document.getElementById('studentSelect');

        if (parentId) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'validate2.php?parent_id=' + encodeURIComponent(parentId), true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    studentSelect.innerHTML = xhr.responseText;
                } else {
                    studentSelect.innerHTML = '<option value="">Error fetching students</option>';
                }
            };
            xhr.send();
        } else {
            studentSelect.innerHTML = '<option value="">Select a parent first</option>';
        }
    });

    // Event handler for when a student is selected
    document.getElementById('studentSelect').addEventListener('change', function() {
        var studentId = this.value;
        var studentNumberSelect = document.getElementById('studentNumber');
        var courseSelect = document.getElementById('courseSelect');
        var mpesaNumberInput = document.getElementById('mpesaNumber');
        var totalAmountInput = document.getElementById('totalAmount');

        if (studentId) {
            // Fetch student numbers
            var xhrStudentNumber = new XMLHttpRequest();
            xhrStudentNumber.open('GET', 'validate3.php?student_id=' + encodeURIComponent(studentId), true);
            xhrStudentNumber.onload = function() {
                if (xhrStudentNumber.status === 200) {
                    studentNumberSelect.innerHTML = xhrStudentNumber.responseText;
                } else {
                    studentNumberSelect.innerHTML = '<option value="">Error fetching admission number</option>';
                }
            };
            xhrStudentNumber.send();

            // Fetch courses and fees
            var xhrCourseDetails = new XMLHttpRequest();
            xhrCourseDetails.open('GET', 'fetchCourseDetails.php?student_id=' + encodeURIComponent(studentId), true);
            xhrCourseDetails.onload = function() {
                if (xhrCourseDetails.status === 200) {
                    courseSelect.innerHTML = xhrCourseDetails.responseText;
                    calculateTotalAmount();
                } else {
                    courseSelect.innerHTML = '<option value="">Error fetching courses</option>';
                }
            };
            xhrCourseDetails.send();

            // Fetch payment numbers
            var xhrPaymentNumber = new XMLHttpRequest();
            xhrPaymentNumber.open('GET', 'fetchPaymentNumber.php?student_id=' + encodeURIComponent(studentId), true);
            xhrPaymentNumber.onload = function() {
                if (xhrPaymentNumber.status === 200) {
                    mpesaNumberInput.value = xhrPaymentNumber.responseText.trim();
                } else {
                    mpesaNumberInput.value = '';
                }
            };
            xhrPaymentNumber.send();
        } else {
            studentNumberSelect.innerHTML = '<option value="">Select a student</option>';
            courseSelect.innerHTML = '';
            mpesaNumberInput.value = '';
            totalAmountInput.value = '';
        }
    });

    // Event handler for when courses are selected
    document.getElementById('courseSelect').addEventListener('change', function() {
        calculateTotalAmount();
    });

    // Function to calculate the total amount based on selected courses
    function calculateTotalAmount() {
        var courseSelect = document.getElementById('courseSelect');
        var totalAmountInput = document.getElementById('totalAmount');
        var totalAmount = 0;

        Array.from(courseSelect.selectedOptions).forEach(function(option) {
            var fee = parseFloat(option.dataset.fee) || 0;
            totalAmount += fee;
        });

        totalAmountInput.value = totalAmount.toFixed(2);
    }
});
 
      function updateRemaining() {
    const totalAmount = parseFloat(document.getElementById('totalAmount').value) || 0;
    const paidAmount = parseFloat(document.getElementById('paidAmount').value) || 0;
    const remainingAmount = Math.max(0, totalAmount - paidAmount);
    document.getElementById('remainingAmount').value = remainingAmount;
}

function validateForm() {
    const totalAmount = parseFloat(document.getElementById('totalAmount').value) || 0;
    const paidAmount = parseFloat(document.getElementById('paidAmount').value) || 0;

    if (paidAmount > totalAmount) {
        alert('Paid amount cannot exceed the total amount.');
        return false; // Prevent form submission
    }

    return true; // Allow form submission
}


// Event handler for when courses are selected
document.getElementById('studentSelect').addEventListener('change', function() {
    var studentId = this.value;
    var courseSelect = document.getElementById('courseSelect');
    var totalAmountInput = document.getElementById('totalAmount');
    var remainingAmountInput = document.getElementById('remainingAmount');
    var paidAmountInput = document.getElementById('paidAmount');

    if (studentId) {
        // Clear the existing courses
        courseSelect.innerHTML = '<option value="">Loading courses...</option>';
        totalAmountInput.value = '';
        remainingAmountInput.value = '';
        paidAmountInput.value = '';

        // Fetch courses for the selected student
        var xhrCourseDetails = new XMLHttpRequest();
        xhrCourseDetails.open('GET', 'fetchStudentCourses.php?student_id=' + encodeURIComponent(studentId), true);
        xhrCourseDetails.onload = function() {
            if (xhrCourseDetails.status === 200) {
                var courses = JSON.parse(xhrCourseDetails.responseText);
                courseSelect.innerHTML = ''; // Clear existing options

                var placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = 'Choose Course';
        placeholderOption.disabled = true; // Disable this option
        placeholderOption.selected = true; // Make it selected by default
        courseSelect.appendChild(placeholderOption);

                if (courses.length > 0) {
                    courses.forEach(function(course) {
                        var option = document.createElement('option');
                        option.value = course.id;
                        option.textContent = course.name + ' - Ksh.' + course.fee;
                        option.setAttribute('data-fee', course.fee);
                        courseSelect.appendChild(option);
                    });
                } else {
                    courseSelect.innerHTML = '<option value="">No courses available for this student</option>';
                }
            } else {
                courseSelect.innerHTML = '<option value="">Error fetching courses</option>';
            }
        };
        xhrCourseDetails.send();
    } else {
        courseSelect.innerHTML = '<option value="">Select a student first</option>';
        totalAmountInput.value = '';
        remainingAmountInput.value = '';
        paidAmountInput.value = '';
    }
});



    document.addEventListener('DOMContentLoaded', function () {
var payFeesModal = new bootstrap.Modal(document.getElementById('payFeesModal'));
var courseSelect = document.getElementById('courseSelect');
var totalAmountInput = document.getElementById('totalAmount');
var paidAmountInput = document.getElementById('paidAmount');
var remainingAmountInput = document.getElementById('remainingAmount');

// Function to update the amounts
function updateAmounts() {
    var selectedOption = courseSelect.options[courseSelect.selectedIndex];
    var courseFee = parseFloat(selectedOption.getAttribute('data-fee')) || 0;
    var paidAmount = parseFloat(paidAmountInput.value) || 0;
    var remainingAmount = courseFee - paidAmount;

    // Set remaining amount and total amount
    remainingAmountInput.value = remainingAmount > 0 ? remainingAmount : 0;
    totalAmountInput.value = courseFee; // Always show the full course fee as total amount
}

// Event listener for when the modal is shown
payFeesModal._element.addEventListener('show.bs.modal', function () {
    updateAmounts(); // Update amounts on modal open
});

// Event listener for course selection change
courseSelect.addEventListener('change', updateAmounts);

// Recalculate amounts when paid amount changes
paidAmountInput.addEventListener('input', updateAmounts);

// Initial call to set the total amount and remaining amount
updateAmounts();
});


              document.addEventListener('DOMContentLoaded', function() {
const totalAmountField = document.getElementById('totalAmount');
const paidAmountField = document.getElementById('paidAmount');
const statusField = document.getElementById('status');

function updateStatus() {
    const totalAmount = parseFloat(totalAmountField.value) || 0;
    const paidAmount = parseFloat(paidAmountField.value) || 0;

    if (paidAmount >= totalAmount) {
        statusField.value = 'Paid';
    } else if (paidAmount > 0) {
        statusField.value = 'Pending';
    } else {
        statusField.value = 'Unpaid';
    }
}

totalAmountField.addEventListener('input', updateStatus);
paidAmountField.addEventListener('input', updateStatus);
});
    
</script>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>