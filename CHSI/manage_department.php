<?php
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("location: login.php");
    exit;
}

require_once "config.php";

$message = '';
$message_type = '';

// Handle department deletion
if (isset($_POST['delete_department'])) {
    $department_id = trim($_POST['department_id']);
    
    if (!empty($department_id)) {
        // First check if any users are assigned to this department
        $sql_check_users = "SELECT COUNT(*) as user_count FROM users WHERE department_id = ?";
        if ($stmt_check_users = $conn->prepare($sql_check_users)) {
            $stmt_check_users->bind_param("i", $department_id);
            $stmt_check_users->execute();
            $result = $stmt_check_users->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['user_count'] > 0) {
                $_SESSION['message'] = "Cannot delete department. There are " . $row['user_count'] . " user(s) still assigned to this department.";
                $_SESSION['message_type'] = "error";
            } else {
                // Check if department exists
                $sql_check = "SELECT id FROM departments WHERE id = ?";
                if ($stmt_check = $conn->prepare($sql_check)) {
                    $stmt_check->bind_param("i", $department_id);
                    $stmt_check->execute();
                    $stmt_check->store_result();
                    
                    if ($stmt_check->num_rows == 1) {
                        $sql_delete = "DELETE FROM departments WHERE id = ?";
                        if ($stmt_delete = $conn->prepare($sql_delete)) {
                            $stmt_delete->bind_param("i", $department_id);
                            if ($stmt_delete->execute()) {
                                $_SESSION['message'] = "Department deleted successfully.";
                                $_SESSION['message_type'] = "success";
                            } else {
                                $_SESSION['message'] = "Error deleting department: " . $stmt_delete->error;
                                $_SESSION['message_type'] = "error";
                            }
                            $stmt_delete->close();
                        }
                    } else {
                        $_SESSION['message'] = "Department not found.";
                        $_SESSION['message_type'] = "error";
                    }
                    $stmt_check->close();
                }
            }
            $stmt_check_users->close();
        }
    } else {
        $_SESSION['message'] = "Please select a department to delete.";
        $_SESSION['message_type'] = "error";
    }
    
    header("location: manage_department.php");
    exit();
}

// Handle adding new department
if (isset($_POST['add_department'])) {
    $new_department_name = trim($_POST['new_department_name']);

    if (!empty($new_department_name)) {
        $sql_check = "SELECT id FROM departments WHERE department_name = ?";
        if ($stmt_check = $conn->prepare($sql_check)) {
            $stmt_check->bind_param("s", $new_department_name);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $_SESSION['message'] = "Department '" . htmlspecialchars($new_department_name) . "' already exists.";
                $_SESSION['message_type'] = "error";
            } else {
                $sql_insert = "INSERT INTO departments (department_name) VALUES (?)";
                if ($stmt_insert = $conn->prepare($sql_insert)) {
                    $stmt_insert->bind_param("s", $new_department_name);
                    if ($stmt_insert->execute()) {
                        $_SESSION['message'] = "Department '" . htmlspecialchars($new_department_name) . "' added successfully.";
                        $_SESSION['message_type'] = "success";
                    } else {
                        $_SESSION['message'] = "Error adding department: " . $stmt_insert->error;
                        $_SESSION['message_type'] = "error";
                    }
                    $stmt_insert->close();
                } else {
                    $_SESSION['message'] = "Error preparing statement: " . $conn->error;
                    $_SESSION['message_type'] = "error";
                }
            }
            $stmt_check->close();
        } else {
            $_SESSION['message'] = "Error preparing statement: " . $conn->error;
            $_SESSION['message_type'] = "error";
        }
    } else {
        $_SESSION['message'] = "Department name cannot be empty.";
        $_SESSION['message_type'] = "error";
    }
    header("location: manage_department.php");
    exit();
}

// Fetch departments for the dropdown and display
$departments = [];
$sql_departments = "SELECT id, department_name FROM departments ORDER BY department_name";
if ($stmt_depts = $conn->prepare($sql_departments)) {
    $stmt_depts->execute();
    $result_depts = $stmt_depts->get_result();
    while ($row = $result_depts->fetch_assoc()) {
        $departments[] = $row;
    }
    $stmt_depts->close();
} else {
    $message = "Error loading departments: " . $conn->error;
    $message_type = "error";
}

$conn->close();

// Display messages from session if any
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - CHSI Storage System</title>
    <link rel="icon" type="image/x-icon" href="logo.jpg">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        
        :root {
            --primary-color: #4CAF50;
            --primary-dark: #2E8B57;
            --secondary-color: #f0fdf4;
            --danger-color: #e74c3c;
            --danger-dark: #c0392b;
            --text-dark: #333;
            --text-medium: #666;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --bg-container: rgba(255, 255, 255, 0.9);
            --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.2);
            --shadow-lg: 0 6px 15px rgba(0, 0, 0, 0.25);
            --border-radius: 1rem;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background-color: var(--secondary-color);
            background-image: url('background.jpg');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            overflow: auto;
        }
        
        .dashboard-container {
            background-color: var(--bg-container);
            backdrop-filter: blur(8px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            padding: 3rem;
            width: 100%;
            max-width: 900px;
            animation: fadeInScale 0.8s ease-out forwards;
            opacity: 0;
            transform: scale(0.95);
            margin: auto;
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

        /* Header Section - Improved alignment */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .main-heading-content {
            display: flex;
            align-items: center;
            flex-grow: 1;
        }

        .main-heading-content img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-right: 1rem;
            filter: drop-shadow(0 0 4px rgba(0,0,0,0.08));
        }

        .title-section {
            flex: 1;
        }

        h2.page-title {
            font-size: 2.25rem;
            color: var(--text-dark);
            font-weight: 700;
            letter-spacing: -0.01em;
            margin: 0 0 0.5rem 0;
            line-height: 1.2;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            color: var(--text-medium);
            margin: 0;
            line-height: 1.4;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .action-buttons a, .action-buttons button, .btn {
            padding: 0.9rem 1.5rem;
            border: none;
            border-radius: 0.6rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.02em;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: white;
            color: var(--primary-dark);
            border: 2px solid var(--primary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .btn-secondary:hover {
            background-color: var(--secondary-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, var(--danger-dark) 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, var(--danger-dark) 0%, var(--danger-color) 100%);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        /* Content Sections - Better spacing */
        .content-wrapper {
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }

        .section {
            background-color: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
        }

        h3.section-title {
            font-size: 1.5rem;
            color: var(--text-dark);
            font-weight: 700;
            margin: 0 0 1.5rem 0;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-color);
        }

        /* Form styling - Better alignment */
        .form-container {
            display: flex;
            gap: 1rem;
            align-items: stretch;
            flex-wrap: wrap;
        }

        .form-container input[type="text"], 
        .form-container select {
            flex: 1;
            min-width: 250px;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 0.6rem;
            font-size: 1rem;
            font-family: 'Inter', Arial, sans-serif;
            background-color: white;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-container input[type="text"]:focus, 
        .form-container select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .form-container .btn {
            flex-shrink: 0;
            align-self: stretch;
            min-width: 160px;
        }
        
        /* Message box styling */
        .message-box {
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0.75rem;
            font-weight: 500;
            font-size: 1rem;
            line-height: 1.5;
            text-align: center;
            border: 2px solid;
        }
        .message-box.success {
            background-color: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
        }
        .message-box.error {
            background-color: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }
        .message-box.info {
            background-color: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        /* Current departments section */
        .current-departments-section {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 1rem;
            padding: 2rem;
            border: 1px solid var(--border-color);
        }

        .current-departments-section h3 {
            font-size: 1.5rem;
            color: var(--text-dark);
            font-weight: 700;
            margin: 0 0 1.5rem 0;
            text-align: center;
        }

        .dept-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .dept-item {
            background: linear-gradient(135deg, white 0%, #f8fafc 100%);
            padding: 1.25rem;
            border-radius: 0.75rem;
            border: 2px solid var(--border-color);
            font-size: 1rem;
            color: var(--text-dark);
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .dept-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }

        .no-departments {
            text-align: center;
            color: var(--text-medium);
            font-style: italic;
            padding: 2rem;
            background-color: white;
            border-radius: 0.75rem;
            border: 1px dashed var(--border-color);
        }

        /* Mobile responsiveness - Improved */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
                align-items: flex-start;
            }
            
            .dashboard-container {
                padding: 1.5rem;
                margin-top: 1rem;
            }
            
            .header-section {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 1.5rem;
                margin-bottom: 2rem;
            }
            
            .main-heading-content {
                flex-direction: column;
                align-items: center;
            }
            
            .main-heading-content img {
                margin-right: 0;
                margin-bottom: 1rem;
            }
            
            .title-section {
                text-align: center;
            }
            
            h2.page-title {
                font-size: 1.75rem;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: center;
            }
            
            .content-wrapper {
                gap: 1.5rem;
            }
            
            .section {
                padding: 1.5rem;
            }
            
            .form-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .form-container input[type="text"], 
            .form-container select,
            .form-container .btn {
                min-width: unset;
                width: 100%;
            }
            
            .dept-list {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .current-departments-section {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            h2.page-title {
                font-size: 1.5rem;
            }
            
            .page-subtitle {
                font-size: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .section {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header-section">
            <div class="main-heading-content">
                <img src="logoo.png" alt="CHSI Logo">
                <div class="title-section">
                    <h2 class="page-title">Manage Departments</h2>
                    <p class="page-subtitle">Add and delete departments for the system.</p>
                </div>
            </div>
            <div class="action-buttons">
                <a href="admin_dashboard.php" class="btn btn-secondary">Back to Admin Dashboard</a>
                <a href="logout.php" class="btn btn-primary">Logout</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message-box <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="content-wrapper">
            <div class="section">
                <h3 class="section-title">Add New Department</h3>
                <form action="manage_department.php" method="post" class="form-container">
                    <input type="text" name="new_department_name" placeholder="Enter new department name" required maxlength="100">
                    <button type="submit" name="add_department" class="btn btn-primary">Add Department</button>
                </form>
            </div>

            <div class="section">
                <h3 class="section-title">Delete a Department</h3>
                <?php if (!empty($departments)): ?>
                    <form action="manage_department.php" method="post" class="form-container">
                        <select name="department_id" required>
                            <option value="">Select Department to Delete</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo (int)$dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="delete_department" class="btn btn-danger" onclick="return confirm('WARNING: Are you sure you want to delete this department? This action cannot be undone.');">Delete Department</button>
                    </form>
                <?php else: ?>
                    <p class="no-departments">No departments available to delete.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($departments)): ?>
            <div class="current-departments-section">
                <h3>Current Departments</h3>
                <div class="dept-list">
                    <?php foreach ($departments as $dept): ?>
                        <div class="dept-item">
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>