<?php
include('DB_connect.php');

if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = intval($_GET['student_id']);

    // Prepare the query to fetch courses for the selected student
    $query = "SELECT c.course_id, c.course_name, c.course_fee 
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = ?";
    $stmt = $connect->prepare($query);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Output the courses as options
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<option value="' . htmlspecialchars($row['course_id']) . '" data-fee="' . htmlspecialchars($row['course_fee']) . '">';
            echo htmlspecialchars($row['course_name']) . ' - Ksh.' . htmlspecialchars($row['course_fee']);
            echo '</option>';
        }
    } else {
        echo '<option value="">No courses found</option>';
    }

    $stmt->close();
} else {
    echo '<option value="">Invalid student ID</option>';
}

$connect->close();
?>