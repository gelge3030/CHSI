<?php
session_start();
require_once 'config.php';

$username = $email = $department_id = $role_id = "";
$password = $confirm_password = "";
$username_err = $email_err = $password_err = $confirm_password_err = $department_err = "";
$success_message = $error_message = "";

// Fetch departments for the dropdown
$departments = [];
$sql_departments = "SELECT id, department_name FROM departments ORDER BY department_name ASC";
$result_departments = $conn->query($sql_departments);
if ($result_departments && $result_departments->num_rows > 0) {
    while ($row = $result_departments->fetch_assoc()) {
        $departments[] = [
            'id' => $row['id'],
            'name' => $row['department_name']
        ];
    }
} else {
    error_log("No departments found in 'departments' table or query failed.");
    $error_message = "No departments available. Please contact administrator.";
}

// Get the default role ID - using the first available role
$default_role_id = null;
$sql_role = "SELECT id FROM roles ORDER BY id ASC LIMIT 1";
$result_role = $conn->query($sql_role);
if ($result_role && $result_role->num_rows > 0) {
    $row_role = $result_role->fetch_assoc();
    $default_role_id = $row_role['id'];
} else {
    error_log("No roles found in 'roles' table.");
    $error_message = "System configuration error. Please contact administrator.";
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Debug: Check what POST data we're receiving
    error_log("POST data received: " . print_r($_POST, true));

    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', trim($_POST["username"]))) {
        $username_err = "Username can only contain letters, numbers, and underscores.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE username = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = trim($_POST["username"]);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                error_log("Username check execution failed: " . $stmt->error);
                $error_message = "An unexpected error occurred. Please try again.";
            }
            $stmt->close();
        } else {
            error_log("Username check prepare failed: " . $conn->error);
            $error_message = "Database error. Please try again later.";
        }
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $email_err = "This email is already registered.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                error_log("Email check execution failed: " . $stmt->error);
                $error_message = "An unexpected error occurred. Please try again.";
            }
            $stmt->close();
        } else {
            error_log("Email check prepare failed: " . $conn->error);
            $error_message = "Database error. Please try again later.";
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    // Validate department
    if (!isset($_POST["department_id"]) || empty(trim($_POST["department_id"]))) {
        $department_err = "Please select a department.";
    } else {
        $selected_dept_id = (int)trim($_POST["department_id"]);
        
        // Validate if selected department ID exists in our fetched departments
        $valid_dept = false;
        foreach ($departments as $dept) {
            if ($dept['id'] == $selected_dept_id) {
                $valid_dept = true;
                $department_id = $selected_dept_id;
                break;
            }
        }
        
        if (!$valid_dept) {
            $department_err = "Invalid department selected.";
        }
    }

    // Check input errors before inserting in database
    if (empty($username_err) && empty($email_err) && empty($password_err) && 
        empty($confirm_password_err) && empty($department_err) && empty($error_message) && 
        !is_null($default_role_id)) {

        // Prepare an insert statement with role_id instead of role
        $sql = "INSERT INTO users (username, email, password, department_id, role_id) VALUES (?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssii", $param_username, $param_email, $param_password, $param_department_id, $param_role_id);

            // Set parameters
            $param_username = $username;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            $param_department_id = $department_id;
            $param_role_id = $default_role_id;

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                $success_message = "Account created successfully! You can now log in.";
                // Clear fields after successful registration
                $username = $email = $department_id = "";
            } else {
                error_log("Insert execution failed: " . $stmt->error);
                $error_message = "Registration failed. Please try again later.";
            }
            $stmt->close();
        } else {
            error_log("Insert prepare failed: " . $conn->error);
            $error_message = "Database error. Please try again later.";
        }
    } else {
        if (empty($error_message)) {
            $error_message = "Please correct the errors below.";
        }
    }

    // Set success/error messages in session
    if (!empty($success_message)) {
        $_SESSION['register_message'] = $success_message;
        $_SESSION['register_type'] = 'success';
        header("location: login.php");
        exit();
    } elseif (!empty($error_message)) {
        $_SESSION['register_message'] = $error_message;
        $_SESSION['register_type'] = 'error';
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - CHSI Storage System</title>
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
            align-items: center;
            margin: 0;
            padding: 1.5rem;
            box-sizing: border-box;
            overflow: auto;
        }

        .register-container {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-radius: 1rem;
            box-shadow: 0 0.4rem 1.2rem rgba(0, 0, 0, 0.15);
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
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

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 0.9rem 1.1rem;
            border: 1px solid #c8d0da;
            border-radius: 0.5rem;
            font-size: 1rem;
            box-sizing: border-box;
            background-color: #fcfcfc;
            color: #333;
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
            font-family: 'Inter', Arial, sans-serif;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
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

        .btn-register {
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
        }

        .btn-register:hover {
            background-image: linear-gradient(to right, #2E8B57 0%, #4CAF50 100%);
            box-shadow: 0 5px 12px rgba(76, 175, 80, 0.5);
            transform: translateY(-3px);
        }

        .btn-register:active {
            transform: translateY(0);
            box-shadow: 0 1px 4px rgba(76, 175, 80, 0.2);
        }

        .login-link {
            display: block;
            margin-top: 1.8rem;
            font-size: 0.95rem;
            color: #555;
            text-decoration: none;
        }

        .login-link a {
            color: #4CAF50;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .login-link a:hover {
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
            .register-container {
                padding: 1.8rem;
                margin: 1rem;
            }
            h2 {
                font-size: 1.8rem;
                margin-bottom: 1.5rem;
            }
            .btn-register {
                padding: 0.8rem 1.2rem;
                font-size: 1rem;
            }
            input[type="text"],
            input[type="email"],
            input[type="password"],
            select {
                padding: 0.8rem 1rem;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo-header">
            <img src="logoo.png" alt="CHSI Company Logo">
        </div>
        <h2>Create Account</h2>

        <?php 
        // Display messages from session if set
        if (isset($_SESSION['register_message'])) {
            echo "<div class='message " . htmlspecialchars($_SESSION['register_type']) . "'>" . htmlspecialchars($_SESSION['register_message']) . "</div>";
            unset($_SESSION['register_message']);
            unset($_SESSION['register_type']);
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" placeholder="Enter your username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>" required>
                <span class="help-block"><?php echo $username_err; ?></span>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" placeholder="Enter your email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" required>
                <span class="help-block"><?php echo $email_err; ?></span>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="Enter your password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required>
                <span class="help-block"><?php echo $password_err; ?></span>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm your password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" required>
                <span class="help-block"><?php echo $confirm_password_err; ?></span>
            </div>
            
            <div class="form-group">
                <label for="department_id">Department</label>
                <select name="department_id" id="department_id" class="form-control <?php echo (!empty($department_err)) ? 'is-invalid' : ''; ?>" required>
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo (int)$dept['id']; ?>" <?php echo (isset($department_id) && $department_id == $dept['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="help-block"><?php echo $department_err; ?></span>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn-register">REGISTER</button>
            </div>
            <p class="login-link">Already have an account? <a href="login.php">Login here</a></p>
        </form>
    </div>
</body>
</html>