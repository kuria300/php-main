<?php   
session_start();
include('DB_connect.php');

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$client_id = $_ENV['CLIENT_ID'];
$secret_id= $_ENV['SECRET_ID'];

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


$id = $_SESSION['id']; 


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pay'])) {

   // Assuming this is used somewhere else for filtering
    
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;

    $payment_method = 'Credit card'; 
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $paid_amount = filter_input(INPUT_POST, 'paid_amount', FILTER_VALIDATE_FLOAT);
    $total_amount = filter_input(INPUT_POST, 'total_amount', FILTER_VALIDATE_FLOAT);
    $remaining_amount = isset($_POST['remaining_amount']) ? floatval($_POST['remaining_amount']) : 0;
   
    $status = ''; // Default to 'pending'
    $payment_date = date('Y-m-d H:i:s');

    $due_date = date('Y-m-d', strtotime($payment_date .' -10 days'));
   
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

        

$student_query = "SELECT parent_id, course_id FROM students WHERE student_id = ?";
$stmt = $connect->prepare($student_query);
$stmt->bind_param('i',  $id);
$stmt->execute();
$stmt->bind_result($parents_id, $courses_id);
$stmt->fetch();
$stmt->close();

$fetch_parent_query = "SELECT parent_name FROM parents WHERE parent_id = ?";
$parent_stmt = $connect->prepare($fetch_parent_query);
$parent_stmt->bind_param('i', $parents_id);
$parent_stmt->execute();
$parent_row = $parent_stmt->get_result()->fetch_assoc();
$parent_name = $parent_row ? $parent_row['parent_name'] : '';

if ($displayRole === 'Student' || $displayRole === 'Admin') {
    $parentIdToBind = $parents_id; // Use the parent's ID fetched from the students table
} else {
    $parentIdToBind = $id; // Assuming $id is the student_id in this context
}
if ($displayRole === 'Parent' || $displayRole === 'Admin') {
    // Fetch student_id for the current parent
    $student_query = "SELECT student_id FROM students WHERE parent_id = ?";
    $stmt = $connect->prepare($student_query);
    $stmt->bind_param('i', $id); // Assuming $id is the parent_id
    $stmt->execute();
    $stmt->bind_result($studentIdToBind);
    $stmt->fetch();
    $stmt->close();

} else {
    // If role is not Student or Admin, set parentIdToBind to the current user id
    $studentIdToBind = $id; // Assuming $id is the student_id in this context
}

 // Check if previous payment exists
 $previous_payment_query = "SELECT paid_amount, remaining_amount, status FROM deposit WHERE student_id = ? AND course_id = ? ORDER BY payment_date DESC LIMIT 1";
 $stmt = $connect->prepare($previous_payment_query);
 $stmt->bind_param('ii', $student_id, $course_id);
 $stmt->execute();
 $stmt->bind_result($previous_paid, $previous_remaining, $previous_status);
 $stmt->fetch();
 $stmt->close();

 if ($previous_paid) {
     // Update the paid amount and remaining balance based on previous payment
     $new_paid_amount = $previous_paid + $paid_amount;
     $new_remaining_amount = $total_amount - $new_paid_amount;

     // Update the payment status based on new amounts
     if ($new_paid_amount >= $total_amount) {
         $new_status = 'Paid';
     } elseif ($new_paid_amount > 0) {
         $new_status = 'Pending';
     } else {
         $new_status = 'Unpaid';
     }

     // Update the deposit table with the new values
     $update_query = "UPDATE deposit SET paid_amount = ?, remaining_amount = ?, status = ?, parent_name = ?, payment_date = NOW() WHERE student_id = ? AND course_id = ?";
     $stmt = $connect->prepare($update_query);
     $stmt->bind_param('ddssii', $new_paid_amount, $new_remaining_amount, $new_status, $parent_name, $student_id, $course_id);

     if ($stmt->execute()) {
         // Redirect with success message if update is successful
         header('Location: deposit.php?msg=success');
         exit();
     } else {
         echo "Error: " . $stmt->error;
     }
     $stmt->close();
 }else{
$payment_number = 'Credit Card Payment'; 
        // Insert payment details into the database
    $query = "  INSERT INTO deposit (student_id, due_date, total_amount, paid_amount, payment_number, payment_method, status, payment_date, parent_id, parent_name, remaining_amount, course_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";

            if ($stmt = $connect->prepare($query)) {
                $stmt->bind_param("isssssssssi", $studentIdToBind, $due_date, $total_amount, $paid_amount, $payment_number, $payment_method, $status, $parentIdToBind, $parent_name, $remaining_amount, $course_id);

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
<div class="modal" tabindex="-1" role="dialog" aria-labelledby="mpesaModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="mpesaModalLabel">Credit Card Payment</h3>
                <button type="button" class="close" aria-label="Close" onclick="window.location.reload();">
                    
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" action="creditpayment.php" id="paymentForm">
               
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
                        <label for="courseSelect" class="form-label">Select Course:</label>
                        <select class="form-select" id="courseSelect" name="course_id" onchange="updateTotalAmount()">
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course['course_id']); ?>" data-fee="<?php echo htmlspecialchars($course['course_fee']); ?>">
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
        <label for="courseSelect" class="form-label">Select Course:</label>
        <select class="form-select" id="courseSelect" name="course_id">
            <option value="">Select a student first</option>
        </select>
    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="totalAmount" class="form-label">Payable amount:</label>
                        <input type="number" class="form-control" id="totalAmount" name="total_amount" min="50" value="<?php echo htmlspecialchars($courses[0]['course_fee'] ?? ''); ?>" required readonly>
                    </div>
                    <div class="mb-3">
                    <label for="paidAmount" class="form-label">Paid Amount</label>
                    <input type="number" class="form-control" id="paidAmount" name="paid_amount" min="0" required oninput="updateRemainingAmount()">
                </div>
                <div class="mb-3">
                    <label for="remainingAmount" class="form-label">Remaining Amount</label>
                    <input type="number" class="form-control" id="remainingAmount" name="remaining_amount" readonly>
                </div>
                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>">
                <input type="hidden" name="parent_id" value="<?php echo htmlspecialchars($parent_id); ?>">
                <div id="paypal-button-container" name="update_pay"></div>
                <a href="deposit.php" class="btn btn-secondary" data-bs-dismiss="modal">Back</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://www.paypal.com/sdk/js?client-id=<?php echo htmlspecialchars($client_id); ?>&currency=USD">/*This ID is required to authenticate your PayPal account.*/</script>
</head>
    
    <script>
    paypal.Buttons({
       
        createOrder(data, actions) {
            const paidAmount = document.getElementById('paidAmount').value; // Get the total amount from the input field

            if (isNaN(paidAmount) || paidAmount <= 0) {
        alert('Please enter a valid amount.');
        return actions.reject(); // Reject the order creation
    }

            return actions.order.create({
                purchase_units: [{
                    amount: {
                        value: paidAmount // Pass the correct amount here
                    }
                }]
            });
        },

        onApprove(data, actions) {
    return actions.order.capture().then(function(orderData) {
        if (orderData && orderData.purchase_units && orderData.purchase_units[0].payments && orderData.purchase_units[0].payments.captures) {
        const transaction = orderData.purchase_units[0].payments.captures[0]; // Corrected access to 'captures'
        
        
        console.log(transaction);
    } else {
        console.error('Transaction data not found.');
    }

        
        // Manually submit the form
        document.getElementById('paymentForm').submit();
        });
 },

        onError(err) {
    console.error(err);
    alert('An error occurred during the transaction: ' + err.message);
},
    }).render('#paypal-button-container');
</script>
<script>
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

// Event listener for course selection
document.getElementById('courseSelect').addEventListener('change', function() {
    var selectedOption = this.options[this.selectedIndex];
    var fee = selectedOption.getAttribute('data-fee');

    // Update the total amount
    var totalAmountInput = document.getElementById('totalAmount');
    totalAmountInput.value = fee ? fee : '';

    // Update the remaining amount
    updateRemainingAmount();
});
    function updateTotalAmount() {
        const courseSelect = document.getElementById('courseSelect');
        const totalAmountInput = document.getElementById('totalAmount');

        // Get the selected option's data-fee attribute
        const selectedOption = courseSelect.options[courseSelect.selectedIndex];
        const courseFee = selectedOption.getAttribute('data-fee');

        // Update the total amount input
        totalAmountInput.value = courseFee;
    }

    // Initialize the total amount on page load if a course is already selected
    window.onload = function() {
        updateTotalAmount();
    };

    function updateRemainingAmount() {
        const totalAmount = parseFloat(document.getElementById('totalAmount').value) || 0;
        const paidAmount = parseFloat(document.getElementById('paidAmount').value) || 0;
        const remainingAmount = totalAmount - paidAmount;

        document.getElementById('remainingAmount').value = remainingAmount < 0 ? 0 : remainingAmount; // Ensure remaining amount is not negative
    }

    // Initialize the remaining amount on page load
    window.onload = function() {
        updateRemainingAmount();
    };
</script>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>