<?php
include('DB_connect.php'); // Ensure this file contains the database connection

// Check if the student ID is set and valid
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = intval($_GET['student_id']);

    // Prepare the query to fetch the payment number for the student
    $query = "SELECT student_contact_number1 FROM students WHERE student_id = ?"; // Adjust the query based on your table and requirements
    $stmt = $connect->prepare($query);

    if ($stmt) {
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Output the latest payment number
            echo htmlspecialchars($row['student_contact_number1']);
        } else {
            // No payment number found
            echo '';
        }

        $stmt->close();
    } else {
        // Error preparing statement
        echo '';
    }

    $connect->close();
} else {
    // Invalid student ID
    echo '';
}
?>