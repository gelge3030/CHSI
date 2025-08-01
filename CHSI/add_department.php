<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['upload_message'] = "Unauthorized access. Admins only.";
    $_SESSION['upload_type'] = "error";
    header('Location: login.php'); // Redirect to login or dashboard
    exit();
}

$department_name_err = "";
$success_message = "";
$error_message = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate department name
    if (empty(trim($_POST["department_name"]))) {
        $department_name_err = "Please enter a department name.";
    } else {
        $department_name = trim($_POST["department_name"]);

        // Check if department already exists
        $sql = "SELECT id FROM departments WHERE department_name = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_department_name);
            $param_department_name = $department_name;
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $department_name_err = "This department already exists.";
                }
            } else {
                error_log("Department check failed: " . $stmt->error);
                $error_message = "An unexpected error occurred during department check.";
            }
            $stmt->close();
        } else {
            error_log("Prepare statement failed (department check): " . $conn->error);
            $error_message = "Database error. Please try again later.";
        }
    }

    // If no errors, insert into database
    if (empty($department_name_err) && empty($error_message)) {
        $sql = "INSERT INTO departments (department_name) VALUES (?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_department_name);
            $param_department_name = $department_name;

            if ($stmt->execute()) {
                $success_message = "Department '" . htmlspecialchars($department_name) . "' added successfully!";
                $_SESSION['upload_message'] = $success_message;
                $_SESSION['upload_type'] = "success";
                header("location: admin_dashboard"); // Redirect back to admin dashboard
                exit();
            } else {
                error_log("Insert department failed: " . $stmt->error);
                $error_message = "Error adding department. Please try again.";
            }
            $stmt->close();
        } else {
            error_log("Prepare statement failed (department insert): " . $conn->error);
            $error_message = "Database error. Please try again later.";
        }
    } else {
        $error_message = $department_name_err; // Set overall error message if there's a specific validation error
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Department - CHSI Storage System</title>
    <link rel="icon" type="image/x-icon" href="logo.jpg">
    <style>
        body {
            font-family: 'Inter', Arial, sans-serif;
            background-color: #f0fdf4; /* Light green background */
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
            overflow: auto;
        }

        .add-department-container {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-radius: 1rem;
            box-shadow: 0 0.4rem 1.2rem rgba(0, 0, 0, 0.15);
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
            box-sizing: border-box;
            text-align: center;
            animation: fadeInScale 0.8s ease-out forwards;
            opacity: 0;
            transform: scale(0.98);
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .logo-header {
            margin-bottom: 2rem;
        }

        .logo-header img {
            max-width: 180px;
            height: auto;
            margin-bottom: 0.8rem;
            filter: drop-shadow(0 0 4px rgba(0,0,0,0.08));
        }

        h2 {
            font-size: 2.2rem;
            color: #333;
            margin-top: 0;
            margin-bottom: 2rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .form-group {
            margin-bottom: 1.2rem;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 600;
            font-size: 0.95rem;
        }

        input[type="text"] {
            width: 100%;
            padding: 0.9rem 1.1rem;
            border: 1px solid #c8d0da;
            border-radius: 0.5rem;
            font-size: 1rem;
            box-sizing: border-box;
            background-color: #fcfcfc;
            color: #333;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
        }

        input[type="text"]:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
            outline: none;
            background-color: #ffffff;
        }

        .help-block {
            font-size: 0.85rem;
            color: #e74c3c;
            margin-top: 0.4rem;
            display: block;
            text-align: left;
        }

        .primary-btn {
            background-image: linear-gradient(to right, #4CAF50 0%, #2E8B57 100%);
            color: white;
            padding: 0.9rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            box-shadow: 0 3px 8px rgba(76, 175, 80, 0.3);
            transition: all 0.25s ease;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-top: 1.5rem;
            text-decoration: none; /* For link-styled buttons */
            display: inline-block; /* For link-styled buttons */
        }

        .primary-btn:hover {
            background-image: linear-gradient(to right, #2E8B57 0%, #4CAF50 100%);
            box-shadow: 0 5px 12px rgba(76, 175, 80, 0.5);
            transform: translateY(-3px);
        }

        .primary-btn:active {
            transform: translateY(0);
            box-shadow: 0 1px 4px rgba(76, 175, 80, 0.2);
        }
        
        .back-link {
            display: block;
            margin-top: 1.8rem;
            font-size: 0.95rem;
            color: #555;
            text-decoration: none;
        }

        .back-link a {
            color: #4CAF50;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .back-link a:hover {
            color: #2E8B57;
            text-decoration: underline;
        }

        .message {
            padding: 0.75rem;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            text-align: center;
            font-size: 0.95rem;
            line-height: 1.3;
            font-weight: 500;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .error {
            background-color: #ffe0e6;
            color: #cc0033;
            border: 1px solid #ffb3c1;
        }
        .success {
            background-color: #e6ffed;
            color: #1a6d32;
            border: 1px solid #b3ffda;
        }

        /* Responsive adjustments */
        @media (max-width: 500px) {
            .add-department-container {
                padding: 1.8rem;
                margin: 1rem;
            }
            h2 {
                font-size: 1.8rem;
                margin-bottom: 1.5rem;
            }
            .primary-btn {
                padding: 0.8rem 1.2rem;
                font-size: 1rem;
            }
            input[type="text"] {
                padding: 0.8rem 1rem;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <div class="add-department-container">
        <div class="logo-header">
            <img src="logoo.png" alt="CHSI Company Logo">
        </div>
        <h2>Add New Department</h2>

        <?php 
        // Display messages
        if (!empty($success_message)) {
            echo "<div class='message success'>" . htmlspecialchars($success_message) . "</div>";
        } elseif (!empty($error_message)) {
            echo "<div class='message error'>" . htmlspecialchars($error_message) . "</div>";
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="department_name">Department Name</label>
                <input type="text" name="department_name" id="department_name" placeholder="e.g., Human Resources" class="form-control <?php echo (!empty($department_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars(isset($_POST['department_name']) ? trim($_POST['department_name']) : ''); ?>" required>
                <span class="help-block"><?php echo $department_name_err; ?></span>
            </div>
            <div class="form-group">
                <button type="submit" class="primary-btn">Add Department</button>
            </div>
            <p class="back-link"><a href="admin_dashboard.php">Back to Dashboard</a></p>
        </form>
    </div>
</body>
</html>