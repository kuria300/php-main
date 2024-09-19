<?php
include('DB_connect.php');

if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = intval($_GET['student_id']);

    $query = "SELECT student_number FROM students WHERE student_id = ?";
    $stmt = $connect->prepare($query);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo '<option value="' . htmlspecialchars($row['student_number']) . '">' . htmlspecialchars($row['student_number']) . '</option>';
    } else {
        echo '<option value="">No admission number found</option>';
    }

    $stmt->close();
} else {
    echo '<option value="">Invalid student ID</option>';
}

$connect->close();
?>