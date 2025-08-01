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

// Initialize variables for user data
$user_id = $username = ""; // Removed $role
$department_id = null; // Initialize department_id as null
$username_err = $department_err = ""; // Removed $role_err

// Check if an ID is provided in the URL
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    $user_id = trim($_GET["id"]);

    // Prepare a select statement to fetch user details (excluding role)
    $sql = "SELECT id, username, department_id FROM users WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $param_id);
        $param_id = $user_id;

        if ($stmt->execute()) {
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                // Fetch result row
                $row = $result->fetch_assoc();

                // Retrieve individual field value
                $username = $row["username"];
                $department_id = $row["department_id"]; // Fetch department_id
            } else {
                // URL doesn't contain valid id. Redirect to error page or manage_users.
                header("location: manage_users.php");
                exit();
            }
        } else {
            echo "Oops! Something went wrong. Please try again later.";
        }
        $stmt->close();
    }
} else if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process form submission when data is sent via POST
    $user_id = $_POST["id"];

    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        $username = trim($_POST["username"]);
    }

    // Validate department (can be empty/null, so no error if not selected)
    // Ensure department_id is null if the value is empty string, otherwise cast to int
    $department_id = !empty(trim($_POST["department_id"])) ? (int)trim($_POST["department_id"]) : null;

    // Check input errors before updating in database
    if (empty($username_err)) { // Removed $role_err from check
        // Prepare an update statement (removed role from update)
        $sql = "UPDATE users SET username = ?, department_id = ? WHERE id = ?";

        if ($stmt = $conn->prepare($sql)) {
            // Bind parameters (removed role parameter)
            $stmt->bind_param("sii", $param_username, $param_department_id, $param_id);

            // Set parameters
            $param_username = $username;
            $param_department_id = $department_id;
            $param_id = $user_id;

            if ($stmt->execute()) {
                $_SESSION['message'] = "User updated successfully.";
                $_SESSION['message_type'] = "success";
                header("location: manage_users.php");
                exit();
            } else {
                $_SESSION['message'] = "Error updating user: " . $stmt->error;
                $_SESSION['message_type'] = "error";
            }
            $stmt->close();
        }
    }
} else {
    // If no ID is provided and not a POST request, redirect
    header("location: manage_users.php");
    exit();
}

// Fetch all departments for the dropdown
$departments = [];
$sql_departments = "SELECT id, department_name FROM departments ORDER BY department_name ASC";
if ($stmt_departments = $conn->prepare($sql_departments)) {
    $stmt_departments->execute();
    $result_departments = $stmt_departments->get_result();
    while ($row = $result_departments->fetch_assoc()) {
        $departments[] = $row;
    }
    $stmt_departments->close();
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
    <title>Edit User - CHSI Storage System</title>
    <link rel="icon" type="image/x-icon" href="logo.jpg">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        
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
            align-items: flex-start;
            margin: 0;
            padding: 1.5rem;
            box-sizing: border-box;
            overflow: auto;
        }
        .container {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-radius: 1.5rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.2);
            padding: 3rem;
            width: 100%;
            max-width: 600px;
            text-align: center;
            animation: fadeInScale 0.8s ease-out forwards;
            opacity: 0;
            transform: scale(0.95);
            position: relative;
            margin-top: 2rem;
            margin-bottom: 2rem;
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

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .main-heading-content {
            display: flex;
            align-items: center;
            flex-grow: 1;
            text-align: left;
        }

        .main-heading-content img {
            max-width: 80px;
            height: auto;
            margin-right: 15px;
            filter: drop-shadow(0 0 4px rgba(0,0,0,0.08));
        }

        h2.main-heading {
            font-size: 2.25rem;
            color: #333;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin: 0;
            text-align: left;
            flex-grow: 1;
        }

        .primary-btn, .secondary-btn {
            padding: 0.9rem 1.5rem;
            border: none;
            border-radius: 0.6rem;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            margin-left: 0.5rem;
        }
        .primary-btn {
            background-image: linear-gradient(to right, #4CAF50 0%, #2E8B57 100%);
            color: white;
            box-shadow: 0 4px 10px rgba(76, 175, 80, 0.4);
        }
        .primary-btn:hover {
            background-image: linear-gradient(to right, #2E8B57 0%, #4CAF50 100%);
            box-shadow: 0 6px 15px rgba(76, 175, 80, 0.6);
            transform: translateY(-4px);
        }
        .primary-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(76, 175, 80, 0.3);
        }
        
        .secondary-btn {
            background-color: #f0fdf4;
            color: #2E8B57;
            border: 1px solid #4CAF50;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .secondary-btn:hover {
            background-color: #e6ffed;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .secondary-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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

        input[type="text"],
        select {
            width: 100%;
            padding: 1.1rem;
            border: 1px solid #c8d0da;
            border-radius: 0.6rem;
            font-size: 1rem;
            color: #333;
            box-sizing: border-box;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
        }

        input[type="text"]:focus,
        select:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
            outline: none;
        }

        .error-message {
            color: #e74c3c;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            text-align: left;
            font-weight: 500;
        }

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
        .message-box.info {
            background-color: #e6f7ff;
            color: #0a436a;
            border: 1px solid #c7e5ff;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .container {
                padding: 2rem;
                border-radius: 1rem;
                margin: 1.5rem;
            }
            .header-section {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 1.5rem;
            }
            .main-heading-content {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 1rem;
            }
            h2.main-heading {
                font-size: 1.8rem;
                text-align: center;
            }
            .primary-btn, .secondary-btn {
                width: 100%;
                margin-top: 1rem;
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <div class="main-heading-content">
                <img src="logoo.png" alt="CHSI Logo">
                <h2 class="main-heading">Edit User</h2>
            </div>
            <div>
                <a href="manage_users.php" class="secondary-btn">Back to Manage Users</a>
                <a href="logout.php" class="primary-btn">Logout</a>
            </div>
        </div>

        <p style="text-align: left; margin-top: -1rem; margin-bottom: 2rem; color: #666;">Modify user account details.</p>

        <?php if (!empty($message)): ?>
            <div class="message-box <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="id" value="<?= htmlspecialchars($user_id); ?>">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" value="<?= htmlspecialchars($username); ?>" required>
                <span class="error-message"><?= $username_err; ?></span>
            </div>

            <!-- Removed Role Field -->
            
            <div class="form-group">
                <label for="department_id">Department</label>
                <select name="department_id" id="department_id">
                    <option value="">Select Department (Optional)</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id']; ?>" <?= ($department_id == $dept['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($dept['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="error-message"><?= $department_err; ?></span>
            </div>

            <div class="form-group">
                <button type="submit" class="primary-btn">Update User</button>
            </div>
        </form>
    </div>
</body>
</html>
