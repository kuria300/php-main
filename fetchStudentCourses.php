<?php
include('DB_connect.php');

if (isset($_GET['student_id']) && $connect instanceof mysqli) {
    $student_id = $_GET['student_id'];

    $query = "
        SELECT courses.course_id AS id, courses.course_name AS name, courses.course_fee AS fee
        FROM courses
        JOIN enrollments ON courses.course_id = enrollments.course_id
        WHERE enrollments.student_id = ?
    ";

    $stmt = $connect->prepare($query);
    if ($stmt) {
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $courses = [];
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row; // Collect course data
        }

        // Return the course data as JSON
        echo json_encode($courses);
    } else {
        echo json_encode(['error' => 'Error preparing statement']);
    }
} else {
    echo json_encode(['error' => 'Invalid parameters']);
}
?>