<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin')) {
    header("location: login.php");
    exit();
}

$message = '';
$message_type = '';

// Fetch all departments to populate the dropdown
$departments = [];
$sql_departments = "SELECT id, department_name FROM departments ORDER BY department_name ASC";
$result_departments = $conn->query($sql_departments);
if ($result_departments) {
    while ($row = $result_departments->fetch_assoc()) {
        $departments[] = $row;
    }
} else {
    error_log("Failed to fetch departments for deletion: " . $conn->error);
    $message = "Error fetching departments.";
    $message_type = "error";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);

    if (empty($department_id)) {
        $message = "Please select a department to delete.";
        $message_type = "error";
    } else {
        // IMPORTANT: Check for associated users or files before deleting
        // Check users table
        $sql_check_users = "SELECT id FROM users WHERE department_id = ?"; // Assuming users table has department_id
        if ($stmt_check_users = $conn->prepare($sql_check_users)) {
            $stmt_check_users->bind_param("i", $department_id);
            $stmt_check_users->execute();
            $stmt_check_users->store_result();
            if ($stmt_check_users->num_rows > 0) {
                $message = "Cannot delete department. There are users associated with this department. Please reassign them first.";
                $message_type = "error";
            }
            $stmt_check_users->close();
        } else {
            $message = "Database error checking associated users: " . $conn->error;
            $message_type = "error";
        }

        // Check files table
        if (empty($message)) { // Only proceed if no user conflicts
            $sql_check_files = "SELECT id FROM files WHERE department = (SELECT department_name FROM departments WHERE id = ?)"; // Assuming files table stores department_name directly
            // ALTERNATIVELY, if files table stores department_id:
            // $sql_check_files = "SELECT id FROM files WHERE department_id = ?";
            if ($stmt_check_files = $conn->prepare($sql_check_files)) {
                $stmt_check_files->bind_param("i", $department_id);
                $stmt_check_files->execute();
                $stmt_check_files->store_result();
                if ($stmt_check_files->num_rows > 0) {
                    $message = "Cannot delete department. There are files associated with this department. Please move/delete them first.";
                    $message_type = "error";
                }
                $stmt_check_files->close();
            } else {
                $message = "Database error checking associated files: " . $conn->error;
                $message_type = "error";
            }
        }


        if (empty($message)) { // If no associations found, proceed with deletion
            $sql_get_dept_name = "SELECT department_name FROM departments WHERE id = ?";
            $stmt_get_name = $conn->prepare($sql_get_dept_name);
            $stmt_get_name->bind_param("i", $department_id);
            $stmt_get_name->execute();
            $stmt_get_name->bind_result($dept_name_to_delete);
            $stmt_get_name->fetch();
            $stmt_get_name->close();


            $sql_delete = "DELETE FROM departments WHERE id = ?";
            if ($stmt_delete = $conn->prepare($sql_delete)) {
                $stmt_delete->bind_param("i", $department_id);
                if ($stmt_delete->execute()) {
                    $message = "Department '<b>" . htmlspecialchars($dept_name_to_delete) . "</b>' deleted successfully!";
                    $message_type = "success";
                    // Redirect back to admin_dashboard with a success message
                    $_SESSION['message'] = $message;
                    $_SESSION['message_type'] = $message_type;
                    header("location: admin_dashboard.php");
                    exit();
                } else {
                    $message = "Error deleting department: " . $stmt_delete->error;
                    $message_type = "error";
                }
                $stmt_delete->close();
            } else {
                $message = "Database error preparing deletion statement: " . $conn->error;
                $message_type = "error";
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Department - CHSI Storage System</title>
    <link rel="icon" type="image/x-icon" href="logo.jpg">
    <style>
        body {
            font-family: 'Inter', Arial, sans-serif;
            background-color: #f0fdf4;
            background-image: url('background.jpg');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 1.5rem;
            box-sizing: border-box;
        }

        .container {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-radius: 1.5rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.2);
            padding: 3rem;
            width: 100%;
            max-width: 500px;
            text-align: center;
            animation: fadeInScale 0.8s ease-out forwards;
            opacity: 0;
            transform: scale(0.95);
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .logo-container {
            margin-bottom: 2rem;
        }
        .logo-container img {
            max-width: 150px;
            height: auto;
            filter: drop-shadow(0 0 4px rgba(0,0,0,0.08));
        }

        h2 {
            font-size: 2rem;
            color: #333;
            font-weight: 700;
            margin-bottom: 1.5rem;
            letter-spacing: -0.01em;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 0.6rem;
            font-weight: 600;
            color: #555;
            font-size: 0.95rem;
        }

        select {
            width: 100%;
            padding: 1.1rem;
            border: 1px solid #c8d0da;
            border-radius: 0.6rem;
            font-size: 1rem;
            color: #333;
            box-sizing: border-box;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
            appearance: none; /* Remove default arrow on some browsers */
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666666%22%20d%3D%22M287%2C197.3L159.2%2C69.5c-3.6-3.6-8.6-5.4-13.6-5.4s-10%2C1.8-13.6%2C5.4L5.4%2C197.3c-7.2%2C7.2-7.2%2C18.8%2C0%2C26.1c7.2%2C7.2%2C18.8%2C7.2%2C26.1%2C0l113.8-113.8L260.9%2C223.4c7.2%2C7.2%2C18.8%2C7.2%2C26.1%2C0C294.2%2C216.1%2C294.2%2C204.5%2C287%2C197.3z%22%2F%3E%3C%2Fsvg%3E'); /* Custom arrow */
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 0.8em;
            padding-right: 2.5rem; /* Space for the arrow */
        }

        select:focus {
            border-color: #e74c3c; /* Red border on focus for delete */
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.2);
            outline: none;
            background-color: #ffffff;
        }

        .primary-btn {
            background-image: linear-gradient(to right, #e74c3c 0%, #c0392b 100%); /* Red gradient for delete */
            color: white;
            padding: 1rem 1.8rem;
            border: none;
            border-radius: 0.6rem;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(231, 76, 60, 0.4);
            transition: all 0.3s ease;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            text-decoration: none;
            display: inline-block;
            width: auto;
            margin-top: 1rem;
        }
        .primary-btn:hover {
            background-image: linear-gradient(to right, #c0392b 0%, #e74c3c 100%);
            box-shadow: 0 6px 15px rgba(231, 76, 60, 0.6);
            transform: translateY(-4px);
        }
        .primary-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(231, 76, 60, 0.3);
        }

        .back-link {
            display: block;
            margin-top: 2rem;
            color: #2E8B57;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
            font-size: 0.95rem;
        }
        .back-link:hover {
            color: #4CAF50;
            text-decoration: underline;
        }

        /* Message Box Styling */
        .message-box {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.95rem;
            line-height: 1.4;
            text-align: center;
        }

        .message-box.success {
            background-color: #e6ffed;
            color: #1a6d32;
            border: 1px solid #b3ffda;
        }

        .message-box.error {
            background-color: #ffe0e6;
            color: #cc0033;
            border: 1px solid #ffb3c1;
        }

        @media (max-width: 600px) {
            .container {
                padding: 2rem;
                border-radius: 1rem;
                margin: 1rem;
            }
            h2 {
                font-size: 1.8rem;
                margin-bottom: 1rem;
            }
            select {
                padding: 0.9rem;
                font-size: 0.95rem;
            }
            .primary-btn {
                padding: 0.8rem 1.2rem;
                font-size: 1rem;
            }
            .back-link {
                margin-top: 1.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <img src="logoo.png" alt="CHSI Logo">
        </div>
        <h2>Delete Department</h2>

        <?php if (!empty($message)): ?>
            <div class="message-box <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Are you absolutely sure you want to delete the selected department? This action cannot be undone and may affect associated data.');">
            <div class="form-group">
                <label for="department_id">Select Department to Delete:</label>
                <select id="department_id" name="department_id" required>
                    <option value="">-- Select a Department --</option>
                    <?php if (!empty($departments)): ?>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= htmlspecialchars($department['id']) ?>"><?= htmlspecialchars($department['department_name']) ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>No departments available</option>
                    <?php endif; ?>
                </select>
            </div>
            <button type="submit" class="primary-btn">Delete Department</button>
        </form>
        <a href="admin_dashboard.php" class="back-link">← Back to Admin Dashboard</a>
    </div>
</body>
</html>