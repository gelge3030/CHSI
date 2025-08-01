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

// Pagination variables
$files_per_page = 10; // Number of files to display per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $files_per_page;

// Handle AJAX requests
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// Handle file deletion
if (isset($_GET['delete_file_id'])) {
    $file_id = $_GET['delete_file_id'];
    
    // First, get the filepath to delete the physical file
    $sql_path = "SELECT filepath FROM files WHERE id = ?";
    if ($stmt_path = $conn->prepare($sql_path)) {
        $stmt_path->bind_param("i", $file_id);
        $stmt_path->execute();
        $stmt_path->bind_result($filepath);
        if ($stmt_path->fetch()) {
            $stmt_path->close();
            
            // Now, delete the record from the database
            $sql_delete = "DELETE FROM files WHERE id = ?";
            if ($stmt_delete = $conn->prepare($sql_delete)) {
                $stmt_delete->bind_param("i", $file_id);
                if ($stmt_delete->execute()) {
                    // Finally, delete the physical file from the server
                    if (file_exists($filepath)) {
                        unlink($filepath); 
                    }
                    $message = "File deleted successfully.";
                    $message_type = "success";
                } else {
                    $message = "Error deleting file from database: " . $stmt_delete->error;
                    $message_type = "error";
                }
                $stmt_delete->close();
            }
        } else {
            $message = "File not found.";
            $message_type = "error";
        }
    }
    
    // For AJAX requests, return JSON response
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $message_type === 'success',
            'message' => $message,
            'message_type' => $message_type
        ]);
        exit();
    } else {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $message_type;
        header("location: admin_dashboard.php?page=" . $current_page);
        exit();
    }
}

// Fetch total number of files for pagination
$total_files = 0;
$sql_count_files = "SELECT COUNT(*) FROM files";
if ($stmt_count = $conn->prepare($sql_count_files)) {
    $stmt_count->execute();
    $stmt_count->bind_result($total_files);
    $stmt_count->fetch();
    $stmt_count->close();
}
$total_pages = ceil($total_files / $files_per_page);

// Fetch all files for admin view with pagination
$all_files = [];
$sql_all_files = "SELECT f.id, f.filename, f.upload_date, f.filepath, u.username, d.department_name 
                  FROM files f
                  JOIN users u ON f.uploaded_by = u.id
                  LEFT JOIN departments d ON u.department_id = d.id
                  ORDER BY f.upload_date DESC
                  LIMIT ? OFFSET ?";
if ($stmt_files = $conn->prepare($sql_all_files)) {
    $stmt_files->bind_param("ii", $files_per_page, $offset);
    $stmt_files->execute();
    $result_files = $stmt_files->get_result();
    while ($row = $result_files->fetch_assoc()) {
        $all_files[] = $row;
    }
    $stmt_files->close();
}

// Fetch all departments for the "View Files by Department" section
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

// For AJAX requests, return only the table content and pagination
if ($is_ajax) {
    header('Content-Type: application/json');
    
    ob_start();
    ?>
    <table class="files-table">
        <thead>
            <tr>
                <th>File Name</th>
                <th>Uploaded By</th>
                <th>Department</th>
                <th>Upload Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($all_files)): ?>
                <?php foreach ($all_files as $file): ?>
                    <tr>
                        <td class="filename-cell"><?= htmlspecialchars($file['filename']) ?></td>
                        <td class="username-cell"><?= htmlspecialchars($file['username']) ?></td>
                        <td class="department-cell"><?= htmlspecialchars($file['department_name'] ?? 'N/A') ?></td>
                        <td class="date-cell"><?= formatDateTime($file['upload_date']) ?></td>
                        <td class="actions-cell">
                            <a href="<?= htmlspecialchars($file['filepath']) ?>" target="_blank" class="action-btn view">View</a>
                            <a href="<?= htmlspecialchars($file['filepath']) ?>" download class="action-btn download">Download</a>
                            <a href="javascript:void(0);" class="action-btn delete" onclick="deleteFile(<?= $file['id'] ?>, <?= $current_page ?>);">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="empty-state">No files have been uploaded yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    $table_html = ob_get_clean();
    
    ob_start();
    if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($current_page > 1): ?>
                <a href="javascript:void(0);" onclick="loadPage(<?= $current_page - 1 ?>)">Previous</a>
            <?php else: ?>
                <span class="disabled">Previous</span>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <?php if ($p == $current_page): ?>
                    <span class="current-page"><?= $p ?></span>
                <?php else: ?>
                    <a href="javascript:void(0);" onclick="loadPage(<?= $p ?>)"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="javascript:void(0);" onclick="loadPage(<?= $current_page + 1 ?>)">Next</a>
            <?php else: ?>
                <span class="disabled">Next</span>
            <?php endif; ?>
        </div>
    <?php endif;
    $pagination_html = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'table_html' => $table_html,
        'pagination_html' => $pagination_html,
        'current_page' => $current_page,
        'total_pages' => $total_pages
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CHSI Storage System</title>
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
            max-width: 1400px;
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

        /* Loading spinner and transitions */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            border-radius: 0.75rem;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e2e8f0;
            border-top: 4px solid #4CAF50;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .content-transition {
            transition: all 0.3s ease;
        }

        .content-transition.fade-out {
            opacity: 0.5;
            transform: translateY(10px);
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

        .logout-btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
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
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
        }

        /* Admin Navigation Section */
        .admin-navigation {
            margin-bottom: 2.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            color: #2d3748;
            font-weight: 700;
            margin-bottom: 1.25rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .primary-btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 0.875rem 1.75rem;
            border: none;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.25);
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .primary-btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.35);
        }

        .primary-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }

        /* Content Sections */
        .content-section {
            margin-bottom: 2.5rem;
            position: relative;
        }

        .content-section:last-child {
            margin-bottom: 0;
        }

        /* Tables */
        .table-container {
            overflow-x: auto;
            margin-top: 1rem;
            position: relative;
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

        /* Department table column widths */
        .department-table {
            table-layout: fixed;
        }

        .department-table th:nth-child(1),
        .department-table td:nth-child(1) {
            width: 70%;
        }

        .department-table th:nth-child(2),
        .department-table td:nth-child(2) {
            width: 30%;
        }

        /* Files table column widths */
        .files-table {
            table-layout: fixed;
        }

        .files-table th:nth-child(1),
        .files-table td:nth-child(1) {
            width: 30%;
        }

        .files-table th:nth-child(2),
        .files-table td:nth-child(2) {
            width: 15%;
        }

        .files-table th:nth-child(3),
        .files-table td:nth-child(3) {
            width: 15%;
        }

        .files-table th:nth-child(4),
        .files-table td:nth-child(4) {
            width: 20%;
        }

        .files-table th:nth-child(5),
        .files-table td:nth-child(5) {
            width: 20%;
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

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background-color: #f7fafc;
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
            min-width: 60px;
            text-align: center;
            cursor: pointer;
        }

        .action-btn:last-child {
            margin-right: 0;
        }

        .action-btn:hover {
            background-color: #e6ffed;
            color: #2d5a3d;
        }

        .action-btn.view {
            color: #3182ce;
        }

        .action-btn.view:hover {
            background-color: #ebf8ff;
            color: #2c5282;
        }

        .action-btn.download {
            color: #4CAF50;
        }

        .action-btn.download:hover {
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

        /* Cell styling */
        .filename-cell {
            font-weight: 500;
            color: #2d3748;
        }

        .username-cell {
            font-weight: 500;
            color: #4a5568;
        }

        .department-cell {
            font-style: italic;
            color: #718096;
        }

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
            opacity: 0;
            transform: translateY(-20px);
            animation: slideInMessage 0.5s ease forwards;
        }

        @keyframes slideInMessage {
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }

        .pagination a, .pagination span {
            padding: 0.6rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.5rem;
            text-decoration: none;
            color: #4a5568;
            font-weight: 500;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .pagination a:hover {
            background-color: #4CAF50;
            color: white;
            border-color: #4CAF50;
            transform: translateY(-2px);
        }

        .pagination span.current-page {
            background-color: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }

        .pagination span.disabled {
            background-color: #f7fafc;
            color: #a0aec0;
            cursor: not-allowed;
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

            .nav-buttons {
                flex-direction: column;
            }

            .primary-btn, .logout-btn {
                width: 100%;
                justify-content: center;
            }

            .section-title {
                font-size: 1.25rem;
                text-align: center;
            }

            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                min-width: 600px;
            }

            th, td {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
            }

            .action-btn {
                padding: 0.3rem 0.5rem;
                font-size: 0.75rem;
                margin-right: 0.25rem;
                min-width: 50px;
            }

            .pagination {
                flex-wrap: wrap;
                gap: 0.25rem;
            }

            .pagination a, .pagination span {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .welcome-heading {
                font-size: 1.5rem;
            }

            .section-title {
                font-size: 1.1rem;
            }

            .primary-btn, .logout-btn {
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
                    <h1 class="welcome-heading">Welcome, <span class="username">admin</span>!</h1>
                </div>
            </div>
            <div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div id="message-container">
            <?php if (!empty($message)): ?>
                <div class="message-box <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="admin-navigation content-section">
            <h2 class="section-title">Admin Navigation</h2>
            <div class="nav-buttons">
                <a href="manage_users.php" class="primary-btn">Manage Users</a>
                <a href="manage_department.php" class="primary-btn">Manage Departments</a>
            </div>
        </div>

        <div class="department-files-section content-section">
            <h2 class="section-title">View Files by Department</h2>
            <div class="table-container">
                <table class="department-table">
                    <thead>
                        <tr>
                            <th>Department Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($departments)): ?>
                            <?php foreach ($departments as $department): ?>
                                <tr>
                                    <td class="department-cell"><?= htmlspecialchars($department['department_name']) ?></td>
                                    <td class="actions-cell">
                                        <a href="view_department_files.php?department_id=<?= $department['id'] ?>" class="action-btn view">View Files</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="empty-state">No departments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="files-section content-section">
            <div class="loading-overlay" id="loadingOverlay">
                <div class="spinner"></div>
            </div>
            
            <h2 class="section-title">All Uploaded Files</h2>
            <div class="table-container content-transition" id="filesTableContainer">
                <table class="files-table">
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Uploaded By</th>
                            <th>Department</th>
                            <th>Upload Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($all_files)): ?>
                            <?php foreach ($all_files as $file): ?>
                                <tr>
                                    <td class="filename-cell"><?= htmlspecialchars($file['filename']) ?></td>
                                    <td class="username-cell"><?= htmlspecialchars($file['username']) ?></td>
                                    <td class="department-cell"><?= htmlspecialchars($file['department_name'] ?? 'N/A') ?></td>
                                    <td class="date-cell"><?= formatDateTime($file['upload_date']) ?></td>
                                    <td class="actions-cell">
                                        <a href="<?= htmlspecialchars($file['filepath']) ?>" target="_blank" class="action-btn view">View</a>
                                        <a href="<?= htmlspecialchars($file['filepath']) ?>" download class="action-btn download">Download</a>
                                        <a href="javascript:void(0);" class="action-btn delete" onclick="deleteFile(<?= $file['id'] ?>, <?= $current_page ?>);">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-state">No files have been uploaded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="paginationContainer">
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="javascript:void(0);" onclick="loadPage(<?= $current_page - 1 ?>)">Previous</a>
                        <?php else: ?>
                            <span class="disabled">Previous</span>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <?php if ($p == $current_page): ?>
                                <span class="current-page"><?= $p ?></span>
                            <?php else: ?>
                                <a href="javascript:void(0);" onclick="loadPage(<?= $p ?>)"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="javascript:void(0);" onclick="loadPage(<?= $current_page + 1 ?>)">Next</a>
                        <?php else: ?>
                            <span class="disabled">Next</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentPage = <?= $current_page ?>;
        let isLoading = false;

        // Show loading overlay
        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            const container = document.getElementById('filesTableContainer');
            
            overlay.classList.add('show');
            container.classList.add('fade-out');
        }

        // Hide loading overlay
        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            const container = document.getElementById('filesTableContainer');
            
            overlay.classList.remove('show');
            container.classList.remove('fade-out');
        }

        // Show message
        function showMessage(message, type) {
            const messageContainer = document.getElementById('message-container');
            
            // Remove existing message
            const existingMessage = messageContainer.querySelector('.message-box');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            // Create new message
            const messageBox = document.createElement('div');
            messageBox.className = `message-box ${type}`;
            messageBox.textContent = message;
            messageContainer.appendChild(messageBox);
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    if (messageBox.parentNode) {
                        messageBox.style.opacity = '0';
                        messageBox.style.transform = 'translateY(-20px)';
                        setTimeout(() => {
                            if (messageBox.parentNode) {
                                messageBox.remove();
                            }
                        }, 300);
                    }
                }, 5000);
            }
        }

        // Load page via AJAX
        function loadPage(page) {
            if (isLoading || page === currentPage) {
                return;
            }
            
            isLoading = true;
            showLoading();
            
            fetch(`admin_dashboard.php?page=${page}&ajax=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update table content
                        document.getElementById('filesTableContainer').innerHTML = data.table_html;
                        
                        // Update pagination
                        document.getElementById('paginationContainer').innerHTML = data.pagination_html;
                        
                        // Update current page
                        currentPage = data.current_page;
                        
                        // Update URL without page reload
                        const newUrl = new URL(window.location);
                        newUrl.searchParams.set('page', page);
                        window.history.pushState({page: page}, '', newUrl);
                        
                        // Scroll to top of files section smoothly
                        document.querySelector('.files-section').scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    } else {
                        showMessage('Error loading page. Please try again.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Network error. Please check your connection and try again.', 'error');
                })
                .finally(() => {
                    isLoading = false;
                    hideLoading();
                });
        }

        // Delete file via AJAX
        function deleteFile(fileId, page) {
            if (!confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
                return;
            }
            
            if (isLoading) {
                return;
            }
            
            isLoading = true;
            showLoading();
            
            fetch(`admin_dashboard.php?delete_file_id=${fileId}&page=${page}&ajax=1`)
                .then(response => response.json())
                .then(data => {
                    showMessage(data.message, data.message_type);
                    
                    if (data.success) {
                        // Reload current page to reflect changes
                        setTimeout(() => {
                            loadPage(currentPage);
                        }, 1000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Network error. Please check your connection and try again.', 'error');
                })
                .finally(() => {
                    isLoading = false;
                    hideLoading();
                });
        }

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.page) {
                currentPage = event.state.page;
                loadPage(event.state.page);
            }
        });

        // Initialize page state for browser navigation
        window.history.replaceState({page: currentPage}, '', window.location);

        // Add smooth transitions to table rows
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to pagination links
            const paginationLinks = document.querySelectorAll('.pagination a');
            paginationLinks.forEach(link => {
                link.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                link.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Add ripple effect to action buttons
            const actionButtons = document.querySelectorAll('.action-btn');
            actionButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    ripple.style.position = 'absolute';
                    ripple.style.borderRadius = '50%';
                    ripple.style.background = 'rgba(255, 255, 255, 0.6)';
                    ripple.style.transform = 'scale(0)';
                    ripple.style.animation = 'ripple 0.6s linear';
                    ripple.style.pointerEvents = 'none';
                    
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
                    ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        if (ripple.parentNode) {
                            ripple.remove();
                        }
                    }, 600);
                });
            });
        });

        // Add keyboard navigation support
        document.addEventListener('keydown', function(event) {
            if (event.ctrlKey || event.metaKey) {
                return; // Allow browser shortcuts
            }
            
            switch(event.key) {
                case 'ArrowLeft':
                    if (currentPage > 1) {
                        event.preventDefault();
                        loadPage(currentPage - 1);
                    }
                    break;
                case 'ArrowRight':
                    if (currentPage < <?= $total_pages ?>) {
                        event.preventDefault();
                        loadPage(currentPage + 1);
                    }
                    break;
            }
        });

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            .table-container tbody tr {
                transform: translateY(0);
                transition: all 0.3s ease;
            }
            
            .table-container tbody tr:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }
            
            .pagination a {
                position: relative;
                overflow: hidden;
            }
            
            .action-btn {
                position: relative;
                overflow: hidden;
            }
        `;
        document.head.appendChild(style);

        // Performance optimization: Debounce rapid clicks
        let debounceTimer;
        function debounce(func, wait) {
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(debounceTimer);
                    func(...args);
                };
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(later, wait);
            };
        }

        // Optimized load page function with debouncing
        const debouncedLoadPage = debounce(loadPage, 300);

        // Add loading states to buttons
        function setButtonLoading(button, isLoading) {
            if (isLoading) {
                button.style.opacity = '0.6';
                button.style.pointerEvents = 'none';
                button.innerHTML = button.innerHTML.replace(/^/, '⏳ ');
            } else {
                button.style.opacity = '1';
                button.style.pointerEvents = 'auto';
                button.innerHTML = button.innerHTML.replace('⏳ ', '');
            }
        }

        // Enhanced error handling
        function handleError(error, context = '') {
            console.error(`Error ${context}:`, error);
            
            let message = 'An unexpected error occurred. ';
            
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                message += 'Please check your internet connection.';
            } else if (error.name === 'SyntaxError') {
                message += 'Server response was invalid.';
            } else {
                message += 'Please try again or contact support if the problem persists.';
            }
            
            showMessage(message, 'error');
        }

        // Add connection status indicator
        function updateConnectionStatus() {
            const isOnline = navigator.onLine;
            if (!isOnline) {
                showMessage('You are currently offline. Some features may not work properly.', 'error');
            }
        }

        window.addEventListener('online', () => {
            showMessage('Connection restored.', 'success');
        });

        window.addEventListener('offline', updateConnectionStatus);

        // Initial connection check
        updateConnectionStatus();
    </script>
</body>
</html>