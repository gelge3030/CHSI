<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

require_once 'config.php';

$user_id = $_SESSION["id"];
$username = htmlspecialchars($_SESSION["username"]);
$role = htmlspecialchars($_SESSION["role"]);

// The Fix: Fetch the department NAME from the `departments` table using a JOIN
$department_name = 'N/A'; // Default value if no department is found
$department_id = null;

$sql_user_details = "SELECT u.department_id, d.department_name 
                     FROM users u
                     LEFT JOIN departments d ON u.department_id = d.id
                     WHERE u.id = ?";
if ($stmt_user_details = $conn->prepare($sql_user_details)) {
    $stmt_user_details->bind_param("i", $user_id);
    $stmt_user_details->execute();
    $stmt_user_details->bind_result($fetched_department_id, $fetched_department_name);
    if ($stmt_user_details->fetch()) {
        $department_id = $fetched_department_id;
        // If a department name was found, use it. Otherwise, use the default.
        if (!empty($fetched_department_name)) {
            $department_name = htmlspecialchars($fetched_department_name);
        }
    }
    $stmt_user_details->close();
}

// Update the session variables with the fetched data for future use
$_SESSION['department_name'] = $department_name;
$_SESSION['department_id'] = $department_id;


// Handle file upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_file'])) {
    $uploaded_files_count = 0;
    $failed_files_count = 0;
    $error_messages = [];

    // Check if files were uploaded
    if (isset($_FILES["fileToUpload"]) && is_array($_FILES["fileToUpload"]["name"])) {
        $target_dir = "uploads/";

        // Loop through each uploaded file
        foreach ($_FILES["fileToUpload"]["name"] as $key => $name) {
            $tmp_name = $_FILES["fileToUpload"]["tmp_name"][$key];
            $error = $_FILES["fileToUpload"]["error"][$key];
            $size = $_FILES["fileToUpload"]["size"][$key];

            // If a file was selected and there were no errors
            if ($error == 0) {
                $original_filename = basename($name);

                // Generate a unique file name to prevent overwrites
                $file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
                $unique_filename = uniqid('file_', true) . '.' . $file_extension;
                $target_file = $target_dir . $unique_filename;

                // Check file size (50MB limit)
                if ($size > 50000000) {
                    $error_messages[] = "Sorry, the file '{$original_filename}' is too large.";
                    $failed_files_count++;
                    continue;
                }

                if (move_uploaded_file($tmp_name, $target_file)) {
                    // Insert file details into the database
                    $sql = "INSERT INTO files (filename, filepath, uploaded_by, department_id) VALUES (?, ?, ?, ?)";

                    if ($stmt = $conn->prepare($sql)) {
                        // Use the department_id variable we fetched earlier
                        $stmt->bind_param("ssii", $original_filename, $target_file, $user_id, $department_id);
                        if ($stmt->execute()) {
                            $uploaded_files_count++;
                        } else {
                            $error_messages[] = "Error inserting '{$original_filename}' into database: " . $stmt->error;
                            $failed_files_count++;
                        }
                        $stmt->close();
                    }
                } else {
                    $error_messages[] = "Sorry, there was an error uploading '{$original_filename}'.";
                    $failed_files_count++;
                }
            } else if ($error != 4) { // Error 4 means no file was selected
                $error_messages[] = "An upload error occurred for one of the files.";
                $failed_files_count++;
            }
        }

        // Construct a summary message for the user
        $message = '';
        if ($uploaded_files_count > 0) {
            $message = "Successfully uploaded {$uploaded_files_count} file(s).";
            $_SESSION['message_type'] = "success";
        }
        if ($failed_files_count > 0) {
            $error_summary = implode("<br>", $error_messages);
            $message .= ($message ? "<br>" : "") . "{$failed_files_count} file(s) failed to upload.<br>" . $error_summary;
            $_SESSION['message_type'] = ($uploaded_files_count > 0) ? "info" : "error";
        }

        if (empty($message)) {
            $message = "No file selected.";
            $_SESSION['message_type'] = "error";
        }

        $_SESSION['message'] = $message;
    } else {
        $_SESSION['message'] = "No file selected or an upload error occurred.";
        $_SESSION['message_type'] = "error";
    }

    header("location: dashboard.php");
    exit();
}

// Handle file deletion
if (isset($_GET['delete_file_id'])) {
    $file_id = $_GET['delete_file_id'];
    
    // Get file path and check ownership
    $sql_path = "SELECT filepath, uploaded_by FROM files WHERE id = ?";
    if ($stmt_path = $conn->prepare($sql_path)) {
        $stmt_path->bind_param("i", $file_id);
        $stmt_path->execute();
        $stmt_path->bind_result($filepath, $uploader_id);
        if ($stmt_path->fetch()) {
            $stmt_path->close();
            
            // Allow deletion only if the user is the uploader or an admin
            if ($uploader_id == $user_id || $role === 'admin') {
                $sql_delete = "DELETE FROM files WHERE id = ?";
                if ($stmt_delete = $conn->prepare($sql_delete)) {
                    $stmt_delete->bind_param("i", $file_id);
                    if ($stmt_delete->execute()) {
                        if (file_exists($filepath)) {
                            unlink($filepath); // Delete the physical file
                        }
                        $_SESSION['message'] = "File deleted successfully.";
                        $_SESSION['message_type'] = "success";
                    } else {
                        $_SESSION['message'] = "Error deleting file from database: " . $stmt_delete->error;
                        $_SESSION['message_type'] = "error";
                    }
                    $stmt_delete->close();
                }
            } else {
                $_SESSION['message'] = "You do not have permission to delete this file.";
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "File not found.";
            $_SESSION['message_type'] = "error";
        }
    }
    header("location: dashboard.php");
    exit();
}

// Display messages from session if any
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Fetch files for the user
$user_files = [];
$sql_files = "SELECT id, filename, upload_date, filepath FROM files WHERE uploaded_by = ? ORDER BY upload_date DESC";
if ($stmt_files = $conn->prepare($sql_files)) {
    $stmt_files->bind_param("i", $user_id);
    $stmt_files->execute();
    $result_files = $stmt_files->get_result();
    while ($row = $result_files->fetch_assoc()) {
        $user_files[] = $row;
    }
    $stmt_files->close();
}

$conn->close();

// Function to safely format datetime
function formatDateTime($datetime) {
    if (empty($datetime)) {
        return 'N/A';
    }
    
    try {
        $date = new DateTime($datetime);
        return $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        $timestamp = strtotime($datetime);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        return htmlspecialchars($datetime);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - CHSI Storage System</title>
    <link rel="icon" type="image/x-icon" href="logo.jpg">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
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
            padding: 2rem;
            overflow: auto;
        }

        .dashboard-container {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            padding: 2.5rem;
            width: 100%;
            max-width: 1000px;
            animation: fadeInScale 0.8s ease-out forwards;
            opacity: 0;
            transform: scale(0.98);
            margin: 1rem auto;
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

        /* Header Section */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #e5f3e8;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-container {
            display: flex;
            align-items: center;
        }

        .logo-container img {
            width: 65px;
            height: 65px;
            object-fit: contain;
            filter: drop-shadow(0 2px 8px rgba(0,0,0,0.1));
        }

        .welcome-content {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .welcome-heading {
            font-size: 2rem;
            color: #1a202c;
            font-weight: 700;
            letter-spacing: -0.025em;
            margin: 0;
            line-height: 1.1;
        }

        .welcome-heading .username {
            color: #4CAF50;
            font-weight: 700;
        }

        .role-department-text {
            font-size: 0.95rem;
            color: #718096;
            margin-top: 0.25rem;
        }

        .role-department-text .detail {
            font-weight: 600;
            color: #4a5568;
        }

        .header-buttons {
            display: flex;
            gap: 0.75rem;
        }

        /* Buttons */
        .primary-btn, .secondary-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .primary-btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .primary-btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        .secondary-btn {
            background-color: #f7fafc;
            color: #4CAF50;
            border: 2px solid #4CAF50;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.1);
        }

        .secondary-btn:hover {
            background-color: #e6ffed;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.2);
        }

        /* Section Titles */
        .section-title {
            font-size: 1.5rem;
            color: #2d3748;
            font-weight: 700;
            margin-bottom: 1.5rem;
            margin-top: 2rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-title:first-of-type {
            margin-top: 0;
        }

        /* Content Sections */
        .content-section {
            margin-bottom: 2.5rem;
        }

        .content-section:last-child {
            margin-bottom: 0;
        }

        /* Upload Section */
        .upload-form {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .upload-form-group {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .file-input-container {
            flex: 1;
            min-width: 300px;
        }

        .upload-form input[type="file"] {
            width: 100%;
            padding: 1rem;
            border: 2px dashed #cbd5e0;
            border-radius: 0.75rem;
            background-color: #f7fafc;
            color: #4a5568;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .upload-form input[type="file"]:hover {
            border-color: #4CAF50;
            background-color: #f0fff4;
        }

        .upload-form input[type="file"]:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        /* Table Styling */
        .table-container {
            overflow-x: auto;
            margin-top: 1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            background: white;
            table-layout: fixed;
        }

        /* User table column widths */
        .user-files-table th:nth-child(1),
        .user-files-table td:nth-child(1) {
            width: 50%;
        }

        .user-files-table th:nth-child(2),
        .user-files-table td:nth-child(2) {
            width: 25%;
        }

        .user-files-table th:nth-child(3),
        .user-files-table td:nth-child(3) {
            width: 25%;
        }

        thead {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        }

        th {
            padding: 1rem 1.25rem;
            color: white;
            font-weight: 600;
            text-align: left;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            vertical-align: middle;
        }

        td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            color: #4a5568;
            font-size: 0.9rem;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        tbody tr:nth-child(even) {
            background-color: #f8fcf9;
        }

        tbody tr:hover {
            background-color: #f0fff4;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Action Buttons */
        .actions-cell {
            text-align: left;
            white-space: nowrap;
        }

        .action-btn {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 500;
            padding: 0.4rem 0.8rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            margin-right: 0.5rem;
            font-size: 0.85rem;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }

        .action-btn:last-child {
            margin-right: 0;
        }

        .action-btn:hover {
            background-color: #e6ffed;
            color: #2d5a3d;
        }

        .action-btn.delete {
            color: #e53e3e;
        }

        .action-btn.delete:hover {
            background-color: #fed7d7;
            color: #c53030;
        }

        /* File name styling */
        .filename-cell {
            font-weight: 500;
            color: #2d3748;
        }

        /* Date styling */
        .date-cell {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #718096;
        }

        /* Message Box */
        .message-box {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0.75rem;
            font-weight: 500;
            font-size: 0.9rem;
            line-height: 1.5;
            border-left: 4px solid;
        }

        .message-box.success {
            background-color: #f0fff4;
            color: #22543d;
            border-left-color: #38a169;
        }

        .message-box.error {
            background-color: #fff5f5;
            color: #742a2a;
            border-left-color: #e53e3e;
        }

        .message-box.info {
            background-color: #ebf8ff;
            color: #2a4365;
            border-left-color: #3182ce;
        }

        /* Empty state styling */
        .empty-state {
            text-align: center;
            color: #718096;
            font-style: italic;
            padding: 2rem;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .dashboard-container {
                padding: 1.5rem;
                border-radius: 1rem;
                margin: 0;
                max-width: 100%;
            }

            .header-section {
                flex-direction: column;
                align-items: stretch;
                gap: 1.5rem;
                text-align: center;
            }

            .header-left {
                justify-content: center;
                flex-direction: column;
                gap: 1rem;
            }

            .welcome-content {
                align-items: center;
            }

            .welcome-heading {
                font-size: 1.75rem;
                text-align: center;
            }

            .header-buttons {
                flex-direction: column;
                width: 100%;
            }

            .primary-btn, .secondary-btn {
                width: 100%;
                justify-content: center;
            }

            .section-title {
                font-size: 1.25rem;
                text-align: center;
            }

            .upload-form-group {
                flex-direction: column;
                align-items: stretch;
            }

            .file-input-container {
                min-width: auto;
            }

            /* Mobile table styles */
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                min-width: 500px;
            }

            th, td {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
            }

            .action-btn {
                padding: 0.3rem 0.5rem;
                font-size: 0.75rem;
                margin-right: 0.25rem;
                min-width: 60px;
            }
        }

        @media (max-width: 480px) {
            .welcome-heading {
                font-size: 1.5rem;
            }

            .section-title {
                font-size: 1.1rem;
            }

            .primary-btn, .secondary-btn {
                font-size: 0.85rem;
                padding: 0.75rem 1.25rem;
            }

            .dashboard-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header-section">
            <div class="header-left">
                <div class="logo-container">
                    <img src="logoo.png" alt="CHSI Logo">
                </div>
                <div class="welcome-content">
                    <h1 class="welcome-heading">Welcome, <span class="username"><?= $username ?></span>!</h1>
                    <p class="role-department-text">Department: <span class="detail"><?= $department_name ?></span></p>
                </div>
            </div>
            <div class="header-buttons">
                <?php if ($role === 'admin'): ?>
                    <a href="admin_dashboard.php" class="secondary-btn">Admin Dashboard</a>
                <?php endif; ?>
                <a href="logout.php" class="primary-btn">Logout</a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message-box <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="upload-section content-section">
            <h2 class="section-title">Upload New File</h2>
            <form action="dashboard.php" method="post" enctype="multipart/form-data" class="upload-form">
                <div class="upload-form-group">
                    <div class="file-input-container">
                        <input type="file" name="fileToUpload[]" id="fileToUpload" multiple required>
                    </div>
                    <button type="submit" name="upload_file" class="primary-btn">Upload File</button>
                </div>
            </form>
        </div>

        <div class="files-section content-section">
            <h2 class="section-title">My Uploaded Files</h2>
            <div class="table-container">
                <table class="user-files-table">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Upload Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($user_files)): ?>
                            <?php foreach ($user_files as $file): ?>
                                <tr>
                                    <td class="filename-cell"><?= htmlspecialchars($file['filename']) ?></td>
                                    <td class="date-cell"><?= formatDateTime($file['upload_date']) ?></td>
                                    <td class="actions-cell">
                                        <a href="<?= htmlspecialchars($file['filepath']) ?>" target="_blank" class="action-btn">View/Download</a>
                                        <a href="dashboard.php?delete_file_id=<?= $file['id'] ?>" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this file? This cannot be undone.');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="empty-state">You have not uploaded any files yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>