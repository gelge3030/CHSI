<?php
session_start();

// Check if this is an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    if($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'redirect' => $_SESSION["role"] == 'admin' ? 'admin_dashboard.php' : 'dashboard.php'
        ]);
        exit;
    } else {
        echo "DEBUG: User is already logged in. Role: " . $_SESSION["role"] . ". Redirecting...<br>";
        if($_SESSION["role"] == 'admin'){
            header("location: admin_dashboard.php");
        } else {
            header("location: dashboard.php");
        }
        exit;
    }
}

// Include config file
require_once "config.php"; // Adjust path as necessary

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Check if username is empty
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter username.";
    } else{
        $username = trim($_POST["username"]);
    }

    // Check if password is empty
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }

    // Validate credentials
    if(empty($username_err) && empty($password_err)){
        // Prepare a select statement
        $sql = "SELECT id, username, password, role, department FROM users WHERE username = ?";

        if($stmt = $conn->prepare($sql)){
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_username);

            // Set parameters
            $param_username = $username;

            // Attempt to execute the prepared statement
            if($stmt->execute()){
                // Store result
                $stmt->store_result();

                // Check if username exists, if yes then verify password
                if($stmt->num_rows == 1){
                    // Bind result variables
                    $stmt->bind_result($id, $db_username, $hashed_password, $role, $department);
                    if($stmt->fetch()){
                        if(password_verify($password, $hashed_password)){
                            // Password is correct, so start a new session (already started at top)
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $db_username; // Use database username
                            $_SESSION["role"] = $role;
                            $_SESSION["department"] = $department;

                            // Handle AJAX response
                            if($is_ajax) {
                                header('Content-Type: application/json');
                                echo json_encode([
                                    'success' => true,
                                    'message' => 'Login successful!',
                                    'redirect' => $_SESSION["role"] == 'admin' ? 'admin_dashboard.php' : 'dashboard.php'
                                ]);
                                exit;
                            } else {
                                // Redirect user to appropriate dashboard page
                                if($_SESSION["role"] == 'admin'){
                                    header("location: admin_dashboard.php");
                                    exit;
                                } else {
                                    header("location: dashboard.php");
                                    exit;
                                }
                            }
                        } else{
                            // Password is not valid, display a generic error message
                            $login_err = "Invalid username or password.";
                            
                        }
                    }
                } else{
                    // Username does not exist - use generic error message for security
                    $login_err = "Invalid username or password.";
                }
            } else{
                $login_err = "Oops! Something went wrong with the database query. Please try again later.";
            }

            // Close statement
            $stmt->close();
        } else {
            $login_err = "Oops! Something went wrong preparing the statement. Please try again later.";
        }
    }

    // Handle AJAX error response
    if($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'errors' => [
                'username' => $username_err,
                'password' => $password_err,
                'login' => $login_err
            ]
        ]);
        exit;
    }

    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - CHSI Storage System</title>
    <link rel="icon" type="image/x-icon" href="logo.jpg">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        
        :root {
            --primary-color: #4CAF50;
            --primary-dark: #2E8B57;
            --secondary-color: #f0fdf4;
            --danger-color: #e74c3c;
            --danger-dark: #c0392b;
            --success-color: #27ae60;
            --text-dark: #333;
            --text-medium: #666;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --bg-container: rgba(255, 255, 255, 0.9);
            --shadow-md: 0 4px 10px rgba(0, 0, 0, 0.2);
            --shadow-lg: 0 6px 15px rgba(0, 0, 0, 0.25);
            --border-radius: 1.5rem;
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
            margin: 0;
            overflow: auto;
        }

        .login-container {
            background-color: var(--bg-container);
            backdrop-filter: blur(8px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            padding: 2.5rem 3rem 3rem 3rem;
            width: 100%;
            max-width: 420px;
            text-align: center;
            opacity: 1;
            transform: translateY(0) scale(1);
            position: relative;
            transition: all 0.5s ease-out;
        }

        .login-container.animate-in {
            opacity: 0;
            transform: translateY(30px) scale(0.95);
            animation: fadeInScale 0.8s ease-out forwards;
        }

        .login-container.no-animation {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .login-container.slide-out {
            opacity: 0;
            transform: translateX(-100%) scale(0.95);
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

        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
            to {
                opacity: 0;
                transform: translateX(-100%) scale(0.95);
            }
        }

        .logo-container {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding: 0 1rem;
        }
        .logo-container img {
            max-width: 220px;
            height: auto;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.1));
        }

        h2 {
            font-size: 2rem;
            color: var(--text-dark);
            font-weight: 700;
            margin-bottom: 1.8rem;
            letter-spacing: -0.01em;
        }

        .form-group {
            margin-bottom: 1.25rem;
            text-align: left;
        }

        .form-group:last-of-type {
            margin-bottom: 0;
            margin-top: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-medium);
            font-size: 0.9rem;
            padding-left: 0.25rem;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 1.5px solid var(--border-color);
            border-radius: 0.75rem;
            font-size: 1rem;
            color: var(--text-dark);
            box-sizing: border-box;
            transition: all 0.25s ease;
            background-color: #ffffff;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.15);
            outline: none;
            transform: translateY(-1px);
        }

        .is-invalid {
            border-color: var(--danger-color) !important;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.15) !important;
        }

        .primary-btn {
            background-image: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
            transition: all 0.3s ease;
            letter-spacing: 0.025em;
            text-transform: uppercase;
            width: 100%;
            margin-top: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .primary-btn:hover:not(:disabled) {
            background-image: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
            transform: translateY(-2px);
        }

        .primary-btn:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }

        .primary-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .primary-btn .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }

        .primary-btn.loading .spinner {
            display: inline-block;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            color: var(--danger-color);
            font-size: 0.85rem;
            margin-top: 0.4rem;
            text-align: left;
            font-weight: 500;
            padding-left: 0.25rem;
            min-height: 1.2rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .error-message.show {
            opacity: 1;
        }

        .login-error {
            color: var(--danger-color);
            margin-bottom: 1.25rem;
            font-weight: 500;
            padding: 0.875rem 1rem;
            background-color: rgba(231, 76, 60, 0.08);
            border-radius: 0.75rem;
            border-left: 4px solid var(--danger-color);
            font-size: 0.9rem;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .login-error.show {
            opacity: 1;
            transform: translateY(0);
        }

        .success-message {
            color: var(--success-color);
            margin-bottom: 1.25rem;
            font-weight: 500;
            padding: 0.875rem 1rem;
            background-color: rgba(39, 174, 96, 0.08);
            border-radius: 0.75rem;
            border-left: 4px solid var(--success-color);
            font-size: 0.9rem;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .success-message.show {
            opacity: 1;
            transform: translateY(0);
        }

        .register-text {
            margin-top: 1.25rem;
            font-size: 0.9rem;
            color: var(--text-medium);
            font-weight: 500;
            text-align: center;
            line-height: 1.4;
        }

        .register-text .register-link {
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 700;
            transition: color 0.2s ease-in-out, text-decoration 0.2s ease-in-out;
            cursor: pointer;
        }

        .register-text .register-link:hover {
            text-decoration: underline;
            color: var(--primary-color);
        }

        @media (max-width: 600px) {
            body {
                padding: 1rem;
            }
            .login-container {
                padding: 2rem 1.5rem 2.5rem 1.5rem;
                border-radius: 1rem;
                max-width: 100%;
                margin: 0;
            }
            h2 {
                font-size: 1.75rem;
                margin-bottom: 1.5rem;
            }
            input[type="text"],
            input[type="password"] {
                padding: 0.875rem 1rem;
                font-size: 0.95rem;
            }
            .primary-btn {
                padding: 0.875rem 1.5rem;
                font-size: 0.95rem;
            }
            .logo-container img {
                max-width: 180px;
            }
            .form-group {
                margin-bottom: 1rem;
            }
            .form-group:last-of-type {
                margin-top: 1.25rem;
            }
        }

        @media (max-width: 400px) {
            .login-container {
                padding: 1.5rem 1rem 2rem 1rem;
            }
            .logo-container img {
                max-width: 160px;
            }
            h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="logoo.png" alt="CHSI Logo">
        </div>
        
        <!-- Messages will be dynamically inserted here -->
        <div id="message-container"></div>
        
        <?php
        if(!empty($login_err)){
            echo '<div class="login-error show">' . htmlspecialchars($login_err) . '</div>';
        }
        ?>

        <form id="loginForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" id="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($username); ?>">
                <div class="error-message" id="username-error"><?php echo htmlspecialchars($username_err); ?></div>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" id="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                <div class="error-message" id="password-error"><?php echo htmlspecialchars($password_err); ?></div>
            </div>
            <div class="form-group">
                <button type="submit" class="primary-btn" id="loginBtn">
                    <span class="spinner"></span>
                    <span class="btn-text">Login</span>
                </button>
            </div>
            <p class="register-text">Don't have an account? <span class="register-link" id="registerLink">Sign up now</span>.</p>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const messageContainer = document.getElementById('message-container');
            const loginContainer = document.querySelector('.login-container');
            const registerLink = document.getElementById('registerLink');
            
            // Check if this is a fresh visit or page refresh
            const hasAnimated = sessionStorage.getItem('loginAnimated');
            if (!hasAnimated) {
                // First visit, play animation and mark as animated
                loginContainer.classList.add('animate-in');
                sessionStorage.setItem('loginAnimated', 'true');
            }
            // If already animated, container shows with default visible state
            
            // Handle register link click with smooth transition
            registerLink.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Add slide-out animation
                loginContainer.classList.add('slide-out');
                
                // Navigate to register page after animation
                setTimeout(() => {
                    // Clear the animation flag so register page can animate in fresh
                    sessionStorage.removeItem('loginAnimated');
                    window.location.href = 'register.php';
                }, 500);
            });
            
            // Clear error messages when user starts typing
            const inputs = document.querySelectorAll('input[type="text"], input[type="password"]');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearFieldError(this);
                });
            });

            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                clearAllErrors();
                
                // Show loading state
                setLoadingState(true);
                
                // Get form data
                const formData = new FormData(loginForm);
                
                // Make AJAX request
                fetch(loginForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    setLoadingState(false);
                    
                    if (data.success) {
                        // Show success message
                        showMessage('success', data.message || 'Login successful! Redirecting...');
                        
                        // Add slide-out animation before redirect
                        loginContainer.classList.add('slide-out');
                        
                        // Redirect after animation
                        setTimeout(() => {
                            // Clear animation flag for dashboard
                            sessionStorage.removeItem('loginAnimated');
                            window.location.href = data.redirect;
                        }, 1000);
                    } else {
                        // Handle validation errors
                        if (data.errors) {
                            if (data.errors.username) {
                                showFieldError('username', data.errors.username);
                            }
                            if (data.errors.password) {
                                showFieldError('password', data.errors.password);
                            }
                            if (data.errors.login) {
                                showMessage('error', data.errors.login);
                            }
                        }
                    }
                })
                .catch(error => {
                    setLoadingState(false);
                    console.error('Error:', error);
                    showMessage('error', 'An unexpected error occurred. Please try again.');
                });
            });

            function setLoadingState(loading) {
                if (loading) {
                    loginBtn.disabled = true;
                    loginBtn.classList.add('loading');
                    loginBtn.querySelector('.btn-text').textContent = 'Logging in...';
                } else {
                    loginBtn.disabled = false;
                    loginBtn.classList.remove('loading');
                    loginBtn.querySelector('.btn-text').textContent = 'Login';
                }
            }

            function showMessage(type, message) {
                const messageDiv = document.createElement('div');
                messageDiv.className = type === 'success' ? 'success-message' : 'login-error';
                messageDiv.textContent = message;
                
                // Clear existing messages
                messageContainer.innerHTML = '';
                messageContainer.appendChild(messageDiv);
                
                // Trigger animation
                setTimeout(() => {
                    messageDiv.classList.add('show');
                }, 10);
            }

            function showFieldError(fieldName, message) {
                const field = document.getElementById(fieldName);
                const errorDiv = document.getElementById(fieldName + '-error');
                
                field.classList.add('is-invalid');
                errorDiv.textContent = message;
                errorDiv.classList.add('show');
            }

            function clearFieldError(field) {
                const fieldName = field.name;
                const errorDiv = document.getElementById(fieldName + '-error');
                
                field.classList.remove('is-invalid');
                if (errorDiv) {
                    errorDiv.classList.remove('show');
                    setTimeout(() => {
                        if (!errorDiv.classList.contains('show')) {
                            errorDiv.textContent = '';
                        }
                    }, 300);
                }
            }

            function clearAllErrors() {
                // Clear field errors
                inputs.forEach(input => {
                    input.classList.remove('is-invalid');
                });
                
                document.querySelectorAll('.error-message').forEach(error => {
                    error.classList.remove('show');
                    setTimeout(() => {
                        if (!error.classList.contains('show')) {
                            error.textContent = '';
                        }
                    }, 300);
                });
                
                // Clear message container
                messageContainer.innerHTML = '';
            }
        });
    </script>
</body>
</html>