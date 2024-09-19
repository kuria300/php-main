<?php
include('DB_connect.php');

if (isset($_GET['student_id'])) {
    $student_id = intval($_GET['student_id']);
    
    // Prepare SQL query
    $stmt = $connect->prepare("
        SELECT c.course_id, c.course_name 
        FROM enrollment e
        JOIN courses c ON e.course_id = c.course_id
        WHERE e.student_id = ?
    ");
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $courses = [];

    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }

    $stmt->close();
    $connect->close();
}
?>