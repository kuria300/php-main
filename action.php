<?php 
include('DB_connect.php');

if (isset($_POST['action'])) {
    if ($_POST['action'] == 'fetch_user') {
        $query = "SELECT * FROM admin_users WHERE admin_type = 'user' ";

        // Search filter
        if (isset($_POST['search']['value']) && !empty($_POST['search']['value'])) {
            $searchValue = $connect->real_escape_string($_POST['search']['value']);
            $query .= "AND (admin_name LIKE '%$searchValue%' 
                        OR admin_email LIKE '%$searchValue%' 
                        OR admin_status LIKE '%$searchValue%') ";
        }

        // Order
        if (isset($_POST['order'])) {
            $columnIndex = intval($_POST['order']['0']['column']);
            $direction = $connect->real_escape_string($_POST['order']['0']['dir']);
            $columns = ['admin_id', 'admin_name', 'admin_email', 'admin_status']; // Make sure this matches your DB columns
            $orderByColumn = $columns[$columnIndex] ?? 'admin_id';
            $query .= "ORDER BY $orderByColumn $direction ";
        } else {
            $query .= "ORDER BY admin_id DESC ";
        }

        // Limit
        if (isset($_POST['length']) && $_POST['length'] != -1) {
            $start = intval($_POST['start']);
            $length = intval($_POST['length']);
            $query .= " LIMIT $start, $length";
        }

        // Execute the query
        $result = $connect->query($query);

        // Get filtered row count
        $filtered_rows = $result->num_rows;

        // Get total row count
        $totalRows = get_total_users($connect);

        // Prepare data for output
        $data = array();
        while ($row = $result->fetch_assoc()) {
            $sub_array = array();
            
            if ($row['admin_status'] == 'Enable') {
                $status = '<div class="badge bg-success">Enable</div>';
                $delete_button = '<button type="button" class="btn btn-danger btn-sm" onclick="delete_data(\'' . $row["admin_id"] . '\', \'' . $row["admin_status"] . '\')">
                                  <i class="fa fa-toggle-off" aria-hidden="true"></i> Disable</button>';
            } else {
                $status = '<div class="badge bg-danger">Disable</div>';
                $delete_button = '<button type="button" class="btn btn-success btn-sm" onclick="delete_data(\'' . $row["admin_id"] . '\', \'' . $row["admin_status"] . '\')">
                                  <i class="fa fa-toggle-on" aria-hidden="true"></i> Enable</button>';
            }
            
            $sub_array[] = $row['admin_name'];
            $sub_array[] = $row['admin_email'];
            $sub_array[] = $row['admin_password'];
            $sub_array[] = $row['admin_type'];
            $sub_array[] = $status;
            
            $admin_id = urlencode($row['admin_id']);
            $editButton = '<a href="adduser.php?action=edit&id=' . htmlspecialchars($admin_id, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary btn-sm me-2">Edit</a>';
            
            $sub_array[] = $editButton . ' ' . $delete_button;
            
            $data[] = $sub_array;
        }

        // Output JSON
        $output = array(
            "draw" => intval($_POST['draw']),
            'recordsTotal' => $totalRows,
            'recordsFiltered' => $filtered_rows,
            'data' => $data
        );
        echo json_encode($output);
    }

    if ($_POST['action'] == 'fetch_student') {
        $query = "SELECT students.*, courses.course_name FROM students 
        LEFT JOIN courses ON students.course_id = courses.course_id ";

if (isset($_POST['search']['value']) && !empty($_POST['search']['value'])) {
  $searchValue = $connect->real_escape_string($_POST['search']['value']);
  
  // Check if there's already a WHERE clause in the query
  if (strpos($query, 'WHERE') !== false) {
      $query .= ' AND (student_number LIKE "%' . $searchValue . '%" 
                        OR student_name LIKE "%' . $searchValue . '%")';
  } else {
      $query .= 'WHERE (student_number LIKE "%' . $searchValue . '%" 
                        OR student_name LIKE "%' . $searchValue . '%")';
  }
}

    
        if (isset($_POST['order'])) {
            $columnIndex = intval($_POST['order'][0]['column']);
            $direction = $connect->real_escape_string($_POST['order'][0]['dir']);
            $columns = ['student_id', 'student_number', 'student_name', 'student_email', 'student_address', 'course_name', 'status']; // Updated columns
            $orderByColumn = $columns[$columnIndex] ?? 'student_id';
            $query .= " ORDER BY $orderByColumn $direction ";
        } else {
            $query .= " ORDER BY student_id DESC ";
        }
      
       // Limit
       if (isset($_POST['length']) && $_POST['length'] != -1) {
        $start = intval($_POST['start']);
        $length = intval($_POST['length']);
        $query .= " LIMIT $start, $length";
    }
    
        // Execute the query
        $result = $connect->query($query);
    
        // Get filtered row count
        $filtered_rows = $result->num_rows;
    
        // Get total row count
        $totalRows = get_total_students($connect);
    
        $data = array();
        while ($row = $result->fetch_assoc()) {
            $sub_array = array();
    
            $sub_array[] = '<img src="upload/' . $row["student_image"] . '" width="50"/>';
            $sub_array[] = $row['student_number'];
            $sub_array[] = $row['student_name'];
            $sub_array[] = $row['student_email'];
            $sub_array[] = $row['student_address'];
           
            $sub_array[] = $row['course_name'];
            $sub_array[] = $row['status'];
           
            $sub_array[] = '
            <div class="d-flex">
                <span class="btn btn-sm btn-primary me-2" onclick="window.location.href=\'student.php?action=edit&id=' . htmlspecialchars($row['student_id']) . '\'">
                    <i class="bi bi-pen"></i>
                </span>
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal' . htmlspecialchars($row['student_id']) . '">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <!-- Modal -->
            <div class="modal fade" id="deleteModal' .htmlspecialchars($row['student_id']) . '" tabindex="-1" aria-labelledby="deleteModalLabel' .htmlspecialchars($row['student_id']) . '" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteModalLabel' .htmlspecialchars($row['student_id']) . '">Confirm Delete</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Are you sure you want to delete student?
                        </div>
                        <div class="modal-footer">
                            <form action="Student.php?action=delete" method="post">
                                 <input type="hidden" name="student_id" value="' . htmlspecialchars($row['student_id']) . '">;
                                 <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                 <button type="submit" name="delete_student" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        ';
    
            $data[] = $sub_array;
        }
    
        // Output JSON
        $output = array(
            "draw" => intval($_POST['draw']),
            'recordsTotal' => $totalRows,
            'recordsFiltered' => $filtered_rows,
            'data' => $data
        );
        echo json_encode($output);
    }

    if ($_POST['action'] == 'fetch_fees') {
        $query = "SELECT * FROM addfees ";

        if (isset($_POST['search']['value']) && !empty($_POST['search']['value'])) {
            $searchValue = $connect->real_escape_string($_POST['search']['value']);
            $query .= 'WHERE receipt_number LIKE "%' . $searchValue . '%" 
                        OR receipt_id LIKE "%' . $searchValue . '%"
                         OR status LIKE "%'. $searchValue.'%"' ;
        }

        if (isset($_POST['order'])) {
            $columnIndex = intval($_POST['order'][0]['column']);
            $direction = $connect->real_escape_string($_POST['order'][0]['dir']);
            $columns = ['receipt_id', 'receipt_number', 'amount', 'payment_date', 'status', 'edlevel', 'syear', 'academic_year']; // Make sure this matches your DB columns
            $orderByColumn = $columns[$columnIndex] ?? 'receipt_id';
            $query .= " ORDER BY $orderByColumn $direction ";
        } else {
            $query .= " ORDER BY receipt_id DESC ";
        }

        if (isset($_POST['length']) && $_POST['length'] != -1) {
            $start = intval($_POST['start']);
            $length = intval($_POST['length']);
            $query .= " LIMIT $start, $length";
        }

        // Execute the query
        $result = $connect->query($query);

        if (!$result) {
            die("Query failed: " . $connect->error);
        }

        // Get filtered row count
        $filtered_rows = $result->num_rows;

        // Get total row count
        $totalRows = get_total_fees($connect);

        $data = array();
        while ($row = $result->fetch_assoc()) {
            $sub_array = array();
            $status = ($row['status'] === 'Paid') ? 
                '<div class="badge bg-success"><i class="bi bi-check-lg"></i> Paid</div>' : 
                '<div class="badge bg-primary"><i class="bi bi-check-lg"></i> Pending...</div>';

            $sub_array[] = $row['receipt_id'];
            $sub_array[] = $row['receipt_number'];
            $sub_array[] = $row['amount'];
            $sub_array[] = $row['payment_date'];
            $sub_array[] = $status;
            $sub_array[] = $row['edlevel'];
            $sub_array[] = $row['syear'];
            $sub_array[] = $row['academic_year'];
            $sub_array[] = '<a href="fees.php?action=edit&id=' . htmlspecialchars($row['receipt_id']) . '" class="btn btn-sm btn-primary">View</a>';
            $sub_array[] = '
                <div class="d-flex">
                    <span class="btn btn-sm btn-primary me-2" onclick="window.location.href=\'fees.php?action=edit&id=' . htmlspecialchars($row['receipt_id']) . '\'">
                        <i class="bi bi-pen"></i>
                    </span>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal' . htmlspecialchars($row['receipt_id']) . '">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <!-- Modal -->
                <div class="modal fade" id="deleteModal' . htmlspecialchars($row['receipt_id']) . '" tabindex="-1" aria-labelledby="deleteModalLabel' . htmlspecialchars($row['receipt_id']) . '" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteModalLabel' . htmlspecialchars($row['receipt_id']) . '">Confirm Delete</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to delete this invoice?
                            </div>
                            <div class="modal-footer">
                                <form action="payment.php?action=delete&id=' . htmlspecialchars($row['receipt_id']) . '" method="post">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            ';
            $data[] = $sub_array;
        }

        // Output JSON
        $output = array(
            "draw" => intval($_POST['draw']),
            'recordsTotal' => $totalRows,
            'recordsFiltered' => $filtered_rows,
            'data' => $data
        );
        echo json_encode($output);
    }

}

function get_total_users($connect) {
    $query = "SELECT COUNT(*) as total FROM admin_users WHERE admin_type = 'user'";
    $result = $connect->query($query);
    if (!$result) {
        die("Query failed: " . $connect->error);
    }
    $row = $result->fetch_assoc();
    return $row['total'];
}

function get_total_students($connect) {
    $query = "SELECT COUNT(*) as total FROM students";
    $result = $connect->query($query);
    if (!$result) {
        die("Query failed: " . $connect->error);
    }
    $row = $result->fetch_assoc();
    return $row['total'];
}

function get_total_fees($connect) {
    $query = "SELECT COUNT(*) as total FROM addfees";
    $result = $connect->query($query);
    if (!$result) {
        die("Query failed: " . $connect->error);
    }
    $row = $result->fetch_assoc();
    return $row['total'];
}

?>