<?php
include('DB_connect.php');

if (isset($_GET['parent_id']) && !empty($_GET['parent_id'])) {
    $parent_id = intval($_GET['parent_id']);

    // Prepare the query to fetch students for the selected parent
    $students_query = "SELECT student_id, student_name FROM students WHERE parent_id = ? ORDER BY student_name";
    $students_stmt = $connect->prepare($students_query);
    $students_stmt->bind_param('i', $parent_id);
    $students_stmt->execute();
    $students_result = $students_stmt->get_result();

    // Output the students as options
    echo '<option value="">Select a student here</option>';
    
    if ($students_result->num_rows > 0) {
        while ($studentRow = $students_result->fetch_assoc()) {

            echo '<option value="' . htmlspecialchars($studentRow['student_id']) . '">' . htmlspecialchars($studentRow['student_name']) . '</option>';
        }
    } else {
        echo '<option value="">No students found</option>';
    }

    $students_stmt->close();
} else {
    echo '<option value="">Invalid parent ID</option>';
}

$connect->close();
?>