<?php
include("DB_connect.php");
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["name"], $_POST["pass"], $_POST["role"])) {
        $name = filter_var($_POST["name"], FILTER_SANITIZE_EMAIL);
        $pass = $_POST["pass"];
        $role = $_POST["role"];

        // Input validation
        if (empty($name) || empty($pass)) {
            $err = "Email and password are required";
            header("Location: ./Admin.php?error=" . urlencode($err));
            exit;
        }
        if (!filter_var($name, FILTER_VALIDATE_EMAIL)) {
            $err = "Invalid email address";
            header("Location: ./Admin.php?error=" . urlencode($err));
            exit;
        }
        if (empty($role)) {
            $err = "Role is required";
            header("Location: ./Admin.php?error=" . urlencode($err));
            exit;
        }

        // Determine the table and columns based on the role
        switch ($role) {
            case "1":
                $sql = "SELECT * FROM admin_users WHERE admin_email = ?";
                $emailColumn = 'admin_email';
                $passwordColumn = 'admin_password';
                $idColumn = 'admin_id';
                $typeColumn = 'admin_type';
                break;
            case "2":
                $sql = "SELECT * FROM students WHERE student_email = ?";
                $emailColumn = 'student_email';
                $passwordColumn = 'student_password';
                $idColumn = 'student_id';
                $typeColumn = null;
                break;
            case "3":
                $sql = "SELECT * FROM parents WHERE parent_email = ?";
                $emailColumn = 'parent_email';
                $passwordColumn = 'parent_password';
                $idColumn = 'parent_id';
                $typeColumn = null;
                break;
            default:
                $err = "Invalid role";
                header("Location: ./Admin.php?error=" . urlencode($err));
                exit;
        }

        // Prepare SQL statement
        if ($stmt = $connect->prepare($sql)) {
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $result = $stmt->get_result();

            // Check if exactly one row is returned
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                $userEmail = $user[$emailColumn];
                $userPassword = $user[$passwordColumn];
                $userId = $user[$idColumn];
               // if ($name === $userEmail && password_verify($pass, $userPassword)) {
                if ($name === $userEmail && $pass === $userPassword) {
                    $_SESSION['id'] = $userId;
                    $_SESSION['role'] = $role;
                    if ($typeColumn !== null) {
                        $_SESSION['admin_type'] = $user[$typeColumn];
                    }

                    header("Location: dashboard.php");
                    exit;
                } else {
                    $err = "Incorrect email, password or role";
                    header("Location: ./Admin.php?error=" . urlencode($err));
                    exit;
                }
            } else {
                $err = "Incorrect email, password or role";
                header("Location: ./Admin.php?error=" . urlencode($err));
                exit;
            }

            $stmt->close();
        } else {
            $err = "An error occurred";
            header("Location: ./Admin.php?error=" . urlencode($err));
            exit;
        }
    } else {
        header("Location: ./Admin.php");
        exit;
    }

    $connect->close();
}
?>