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
$department_name = "Unknown Department"; // Default value

// Get department ID from URL with proper validation
$department_id = isset($_GET['department_id']) ? filter_var($_GET['department_id'], FILTER_VALIDATE_INT) : 0;

if ($department_id === false || $department_id <= 0) {
    $_SESSION['message'] = "Invalid department ID provided.";
    $_SESSION['message_type'] = "error";
    header("location: admin_dashboard.php");
    exit();
}

// Fetch department name for display
$sql_dept_name = "SELECT department_name FROM departments WHERE id = ?";
if ($stmt_dept_name = $conn->prepare($sql_dept_name)) {
    $stmt_dept_name->bind_param("i", $department_id);
    if ($stmt_dept_name->execute()) {
        $stmt_dept_name->bind_result($fetched_dept_name);
        if ($stmt_dept_name->fetch()) {
            $department_name = htmlspecialchars($fetched_dept_name, ENT_QUOTES, 'UTF-8');
        } else {
            // Department not found
            $_SESSION['message'] = "Department not found.";
            $_SESSION['message_type'] = "error";
            $stmt_dept_name->close();
            $conn->close();
            header("location: admin_dashboard.php");
            exit();
        }
    }
    $stmt_dept_name->close();
} else {
    die("Error preparing department query: " . $conn->error);
}

// Fetch files uploaded by users in this department
$department_files = [];
$sql_files = "SELECT f.id, f.filename, f.filepath, f.upload_date, u.username, d.department_name
              FROM files f
              JOIN users u ON f.uploaded_by = u.id
              LEFT JOIN departments d ON f.department_id = d.id
              WHERE f.department_id = ?
              ORDER BY f.upload_date DESC";

if ($stmt_files = $conn->prepare($sql_files)) {
    $stmt_files->bind_param("i", $department_id);
    if ($stmt_files->execute()) {
        $result_files = $stmt_files->get_result();
        while ($row = $result_files->fetch_assoc()) {
            // Sanitize data for display
            $row['filename'] = htmlspecialchars($row['filename'], ENT_QUOTES, 'UTF-8');
            $row['username'] = htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8');
            $row['department_name'] = htmlspecialchars($row['department_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $row['filepath'] = htmlspecialchars($row['filepath'], ENT_QUOTES, 'UTF-8');
            $department_files[] = $row;
        }
    }
    $stmt_files->close();
} else {
    die("Error preparing files query: " . $conn->error);
}

$conn->close();

// Display messages from session if any
if (isset($_SESSION['message'])) {
    $message = htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8');
    $message_type = isset($_SESSION['message_type']) ? htmlspecialchars($_SESSION['message_type'], ENT_QUOTES, 'UTF-8') : 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Files for <?= $department_name ?> - CHSI Storage System</title>
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
            max-width: 1100px;
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

        h3.section-title {
            font-size: 1.8rem;
            color: #333;
            font-weight: 700;
            margin-top: 2.5rem;
            margin-bottom: 1.5rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.8rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
            border-radius: 0.6rem;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        thead {
            background-image: linear-gradient(to right, #4CAF50 0%, #2E8B57 100%);
            color: #fff;
        }

        th, td {
            padding: 1rem 1.2rem;
            border: 1px solid #e2e8f0;
            text-align: left;
            font-size: 0.95rem;
            color: #333;
        }

        th {
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        tbody tr:nth-child(even) {
            background-color: #f8fcf9;
        }
        tbody tr:hover {
            background-color: #e6ffed;
        }
        
        .action-btn {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease-in-out, text-decoration 0.2s ease-in-out;
            margin-right: 0.5rem;
            padding: 0.5rem 1rem;
            border: 1px solid transparent;
            border-radius: 0.4rem;
            display: inline-block;
        }
        .action-btn:hover {
            text-decoration: underline;
            color: #2E8B57;
        }
        .action-btn.delete {
            color: #e74c3c;
        }
        .action-btn.delete:hover {
            color: #c0392b;
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

        /* File type icons */
        .file-icon {
            margin-right: 0.5rem;
            color: #666;
        }

        /* Loading state for table */
        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }

        .empty-state h3 {
            color: #999;
            margin-bottom: 0.5rem;
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

            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            tr {
                border: 1px solid #e2e8f0;
                margin-bottom: 1rem;
                border-radius: 0.6rem;
                overflow: hidden;
            }
            td {
                border: none;
                border-bottom: 1px solid #e9eef5;
                position: relative;
                padding-left: 50%;
                text-align: right;
                font-size: 0.9rem;
            }
            td:last-child {
                border-bottom: none;
            }
            td:before {
                position: absolute;
                top: 0;
                left: 6px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: 600;
                color: #555;
            }
            td:nth-of-type(1):before { content: "File Name:"; }
            td:nth-of-type(2):before { content: "Uploaded By:"; }
            td:nth-of-type(3):before { content: "Department:"; }
            td:nth-of-type(4):before { content: "Upload Date:"; }
            td:nth-of-type(5):before { content: "Action:"; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <div class="main-heading-content">
                <img src="logoo.png" alt="CHSI Logo">
                <h2 class="main-heading">Files for <?= $department_name ?></h2>
            </div>
            <div>
                <a href="admin_dashboard.php" class="secondary-btn">Back to Dashboard</a>
                <a href="logout.php" class="primary-btn">Logout</a>
            </div>
        </div>

        <p style="text-align: left; margin-top: -1rem; margin-bottom: 2rem; color: #666;">
            Viewing files uploaded by users in the <?= $department_name ?> department.
        </p>

        <?php if (!empty($message)): ?>
            <div class="message-box <?= $message_type ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($department_files)): ?>
            <table>
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Uploaded By</th>
                        <th>Department</th>
                        <th>Upload Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($department_files as $file): ?>
                        <tr>
                            <td>
                                <span class="file-icon">📄</span>
                                <?= $file['filename'] ?>
                            </td>
                            <td><?= $file['username'] ?></td>
                            <td><?= $file['department_name'] ?></td>
                            <td><?= date("M j, Y g:i A", strtotime($file['upload_date'])) ?></td>
                            <td>
                                <a href="<?= $file['filepath'] ?>" target="_blank" class="action-btn" rel="noopener noreferrer">
                                    View/Download
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <h3>No Files Found</h3>
                <p>There are currently no files uploaded for the <?= $department_name ?> department.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Add some basic JavaScript for enhanced UX
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide messages after 5 seconds
            const messageBox = document.querySelector('.message-box');
            if (messageBox) {
                setTimeout(function() {
                    messageBox.style.opacity = '0';
                    messageBox.style.transform = 'translateY(-10px)';
                    setTimeout(function() {
                        messageBox.style.display = 'none';
                    }, 300);
                }, 5000);
            }

            // Add loading state for file downloads
            const downloadLinks = document.querySelectorAll('.action-btn');
            downloadLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    this.innerHTML = 'Opening...';
                    const originalText = 'View/Download';
                    setTimeout(() => {
                        this.innerHTML = originalText;
                    }, 2000);
                });
            });
        });
    </script>
</body>
</html>