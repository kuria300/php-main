<?php   
session_start();
include('DB_connect.php');

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Access sensitive information from environment variables
$consumer_key = $_ENV['MPESA_CONSUMER_KEY'];
$consumer_secret = $_ENV['MPESA_CONSUMER_SECRET'];
$lipa_na_mpesa_online_shortcode_key = $_ENV['LIPA_NA_MPESA_ONLINE_SHORTCODE_KEY'];
$shortcode = $_ENV['SHORTCODE'];
$callback_url = $_ENV['CALLBACK_URL'];


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
$student_id = $_SESSION['id'];
function getAccessToken() {
    global $consumer_key, $consumer_secret; // Use global to access the variables
  
    $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $headers = [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/json; charset=utf8'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERPWD, $consumer_key . ':' . $consumer_secret);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response_data = json_decode($response, true);
     
    return isset($response_data['access_token']) ? $response_data['access_token'] : '';
    curl_close($ch);
}


function callMpesaApi($mpesa_number, $paid_amount) {
    global $shortcode, $lipa_na_mpesa_online_shortcode_key, $callback_url;
    $url = "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";
    $access_token = getAccessToken();

  
    $password = base64_encode($shortcode . $lipa_na_mpesa_online_shortcode_key . date('YmdHis'));
    

    $data = [
        "BusinessShortCode" => $shortcode,
        "Password" => $password,
        "Timestamp" => date('YmdHis'),
        "TransactionType" => "CustomerPayBillOnline",
        "Amount" => $paid_amount, // Change this to the desired amount
        "PartyA"=> $mpesa_number,
        "PartyB"=>  $shortcode,
        "PhoneNumber"=> $mpesa_number,
        "CallBackURL" => $callback_url,
        "AccountReference" => "AutoReceipt",
        "TransactionDesc" => "Payment for Fees"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    echo "HTTP Code: $http_code\n";
    echo "Response: $response\n";

    // Decode the response
    $response_data = json_decode($response, true);
    
    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON decode error: " . json_last_error_msg() . "\n";
        return false;
    }

    // Check the response code
    if (isset($response_data['ResponseCode']) && $response_data['ResponseCode'] == '0') {
        if (isset($response_data['CheckoutRequestID'])) {
            $_SESSION['latest_checkout_id'] = $response_data['CheckoutRequestID'];
        }
        return true; // Payment was successful
    } else {
        echo "Error: " . ($response_data['errorMessage'] ?? 'Unknown error') . "\n";
        return false; // Payment failed
    }
    
    curl_close($ch);
}
function queryAPI() {
    global $shortcode, $lipa_na_mpesa_online_shortcode_key; // Declare globals
    if (!isset($_SESSION['latest_checkout_id'])) {
        return "No recent CheckoutRequestID found.";
    }

    $ch = curl_init('https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query');
    $access_token = getAccessToken();
    
    $timestamp = date('YmdHis');
    $password = base64_encode($shortcode . $lipa_na_mpesa_online_shortcode_key . $timestamp);
    
    $CheckoutRequestID = $_SESSION['latest_checkout_id'];

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);

    $data = json_encode([
        "BusinessShortCode" => $shortcode,
        "Password" => $password,
        "Timestamp" => $timestamp,
        "CheckoutRequestID" => $CheckoutRequestID
    ]);
    
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return ["error" => "cURL Error: $error_msg"]; // Return error as array
    }

    $data_to = json_decode($response, true); // Decode as associative array
    curl_close($ch);
    
    return $data_to; // Return the full response
}

function processTransaction() {
    $maxAttempts = 5; // Maximum attempts to check the transaction status
    $attempt = 0;

    while ($attempt < $maxAttempts) {
        $response = queryAPI(); // Query the transaction status

        if (isset($response['error'])) {
            echo $response['error']; // Output any cURL errors
            return;
        }

        if (isset($response['errorCode']) && $response['errorCode'] === '500.001.1001') {
            echo "Transaction is still being processed: " . $response['errorMessage'] . "<br>";
            sleep(10); // Wait for 10 seconds before retrying
            $attempt++;
            continue; // Continue polling for transaction status
        }

        // Handle completion of transaction
        return handleTransactionResult($response); // Process and return the result message
    }

    return "Transaction is still being processed after multiple attempts. Please check your M-Pesa app or contact support.";
}

function handleTransactionResult($data_to) {
    $message = "Unknown Result Code";

    if (isset($data_to['ResultCode'])) {
        switch ($data_to['ResultCode']) {
            case 0:
                $message = 'Transaction is Successful';
                break;
            case 1:
                $message = 'Balance is Insufficient to Complete Transaction';
                break;
            case 1032:
                $message = 'Transaction has been Cancelled by User';
                break;
            case 1037:
                $message = 'Timeout in Completing Transaction';
                break;
            default:
                $message = 'Unhandled Result Code: ' . $data_to['ResultCode'];
                break;
        }
    }

    return $message; // Return the message instead of echoing
}



/*if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mpesa_number = $_POST['mpesa_number'];
    $total_amount = $_POST['total_amount'];
    $paid_amount = $_POST['paid_amount'];
  

    // Call the M-Pesa API
    if ($mpesa_number && $paid_amount) {
        // Call the M-Pesa API
        $result = callMpesaApi($mpesa_number, $paid_amount);
        if ($result) {
            echo "Payment initiated successfully.";
            // Optionally query the payment status
            $check = queryAPI();
            echo $check; // Output the result of the query
        } else {
            echo "Payment initiation failed.";
        }
    } else {
        echo "Missing required fields.";
    }
}*/

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_pay'])) {
    include('DB_connect.php'); // Ensure this file initializes $connect

    $id = $_SESSION['id']; // Assuming this is used for filtering
    
    // Gather and sanitize inputs
    $student_id = isset($_POST['student_name']) ? intval($_POST['student_name']) : 0;
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    $mpesa_number = filter_input(INPUT_POST, 'mpesa_number', FILTER_SANITIZE_STRING);
    $paid_amount = filter_input(INPUT_POST, 'paid_amount', FILTER_VALIDATE_FLOAT);
    $total_amount = filter_input(INPUT_POST, 'total_amount', FILTER_VALIDATE_FLOAT);
    $remaining_amount = isset($_POST['remaining_amount']) ? floatval($_POST['remaining_amount']) : 0;
    $payment_date = date('Y-m-d H:i:s');
    $due_date = date('Y-m-d', strtotime($payment_date .' -10 days'));

    // Determine payment status
    $status = ($paid_amount >= $total_amount) ? 'Paid' : (($paid_amount > 0) ? 'Pending' : 'Unpaid');

    // Basic validation
    $errors = [];
    if (empty($mpesa_number)) {
        $errors[] = 'M-pesa number is required.';
    }
    if ($paid_amount < 0) {
        $errors[] = 'Paid amount cannot be negative.';
    }
    if ($paid_amount > $total_amount) {
        $errors[] = 'Paid amount cannot be greater than the total amount.';
    }

    if (!empty($errors)) {
        $errorMessages = urlencode(implode('; ', $errors));
        header('Location: deposit.php?msg=error&errors=' . $errorMessages);
        exit();
    }

    // Fetch parent ID and name
    $student_query = "SELECT parent_id FROM students WHERE student_id = ?";
    $stmt = $connect->prepare($student_query);
    $stmt->bind_param('i',  $id);
    $stmt->execute();
    $stmt->bind_result($parents_id);
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

    // Call the M-Pesa API
    $result = callMpesaApi($mpesa_number, $paid_amount);
    if (!$result) {
        header('Location: deposit.php?msg=error&errors=Payment initiation failed.');
        exit();
    }

    // Process the transaction
    $transactionMessage = processTransaction(); // Implement this function to return the transaction status
    if ($transactionMessage !== 'Transaction is Successful') {
        header('Location: deposit.php?msg=error&errors=' . urlencode($transactionMessage));
        exit();
    }

    // Insert payment details into the database
    $query = "INSERT INTO deposit (student_id, payment_number, due_date, total_amount, paid_amount, payment_method, status, payment_date, parent_id, parent_name, remaining_amount)
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";
    
    if ($stmt = $connect->prepare($query)) {
        $stmt->bind_param("isssssssss", $student_id, $mpesa_number, $due_date, $total_amount, $paid_amount, $payment_method, $status, $parentIdToBind, $parent_name, $remaining_amount);
        
        if ($stmt->execute()) {
            header('Location: deposit.php?msg=success');
            exit();
        } else {
            echo "Error: " . $stmt->error; // Debugging
        }
        $stmt->close();
    } else {
        echo "Failed to prepare SQL statement.";
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
    <title>M-Pesa Payment</title>
    <link rel="icon" href="logo2.png">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .modal {
            display: block; /* Make modal always visible */
            background: rgba(0, 0, 0, 0.5); /* Semi-transparent background */
            height: 100%; /* Full height to cover the screen */
            overflow: auto; /* Allow scrolling if needed */
        }
        .modal-dialog {
            position: fixed;
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
                <h3 class="modal-title" id="mpesaModalLabel">M-Pesa Payment</h3>
                <button type="button" class="close" aria-label="Close" onclick="window.location.reload();">
                    
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" action="daraja.php">

              
                    <div class="mb-3">
                        <label for="mpesaNumber" class="form-label">M-Pesa Number</label>
                        <input type="text" id="mpesaNumber" class="form-control" name="mpesa_number" required>
                    </div>
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
                <label for="payment_method">Payment Method:</label>
                                          
                                                <select id="payment_method" name="payment_method" >
                                                <option value="cash" class="text-muted">Choose Payment</option>
                                                    <option value="mpesa">M-Pesa</option>
                                                </select><br><br>
                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>">
                <input type="hidden" name="parent_id" value="<?php echo htmlspecialchars($parent_id); ?>">
                    <button type="submit" class="btn btn-success mb-2" name="submit_pay">Pay via M-Pesa</button>
                    <a href="deposit.php" class="btn btn-secondary" data-bs-dismiss="modal">Back</a>
                </form>
            </div>
        </div>
    </div>
</div>
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

// Function to update remaining amount based on total amount and paid amount
function updateRemainingAmount() {
    var totalAmount = parseFloat(document.getElementById('totalAmount').value) || 0;
    var paidAmount = parseFloat(document.getElementById('paidAmount').value) || 0;
    var remainingAmount = totalAmount - paidAmount;

    document.getElementById('remainingAmount').value = remainingAmount >= 0 ? remainingAmount : 0;
}

// Event listener for paid amount input
document.getElementById('paidAmount').addEventListener('input', function() {
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