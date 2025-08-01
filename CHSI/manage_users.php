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

// Handle user deletion via POST request for better security
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $user_id_to_delete = intval($_POST['delete_user_id']);

    // Check if the user to be deleted is an admin
    $sql_check_role = "SELECT role FROM users WHERE id = ?";
    if ($stmt_check = $conn->prepare($sql_check_role)) {
        $stmt_check->bind_param("i", $user_id_to_delete);
        $stmt_check->execute();
        $stmt_check->bind_result($user_role);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($user_role === 'admin') {
            $_SESSION['message'] = "Cannot delete an administrator account.";
            $_SESSION['message_type'] = "error";
        } else {
            // First, delete all files uploaded by this user
            $sql_delete_files = "DELETE FROM files WHERE uploaded_by = ?";
            if ($stmt_delete_files = $conn->prepare($sql_delete_files)) {
                $stmt_delete_files->bind_param("i", $user_id_to_delete);
                if ($stmt_delete_files->execute()) {
                    // Files deleted successfully, now delete the user
                    $stmt_delete_files->close();

                    $sql_delete_user = "DELETE FROM users WHERE id = ?";
                    if ($stmt_delete_user = $conn->prepare($sql_delete_user)) {
                        $stmt_delete_user->bind_param("i", $user_id_to_delete);
                        if ($stmt_delete_user->execute()) {
                            $_SESSION['message'] = "User and their files deleted successfully.";
                            $_SESSION['message_type'] = "success";
                        } else {
                            $_SESSION['message'] = "Error deleting user: " . $stmt_delete_user->error;
                            $_SESSION['message_type'] = "error";
                        }
                        $stmt_delete_user->close();
                    }
                } else {
                    $_SESSION['message'] = "Error deleting user's files: " . $stmt_delete_files->error;
                    $_SESSION['message_type'] = "error";
                }
            } else {
                $_SESSION['message'] = "Error preparing file deletion query: " . $conn->error;
                $_SESSION['message_type'] = "error";
            }
        }
    } else {
        $_SESSION['message'] = "Error preparing user role check query: " . $conn->error;
        $_SESSION['message_type'] = "error";
    }
    header("Location: manage_users.php");
    exit;
}

// Fetch all users for display, EXCLUDING admins
$all_users = [];
$sql_users = "SELECT u.id, u.username, u.role, d.department_name 
              FROM users u
              LEFT JOIN departments d ON u.department_id = d.id
              WHERE u.role != 'admin'
              ORDER BY u.username";
if ($stmt_users = $conn->prepare($sql_users)) {
    $stmt_users->execute();
    $result_users = $stmt_users->get_result();
    while ($row = $result_users->fetch_assoc()) {
        $all_users[] = $row;
    }
    $stmt_users->close();
}

$conn->close();

// Handle flash messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message'], $_SESSION['message_type']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - CHSI Storage System</title>
    <link rel="icon" type="image/x-icon" href="logo.jpg">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
            padding: 2rem 1.5rem;
            line-height: 1.5;
        }

        .dashboard-container {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.15);
            padding: 2.5rem;
            width: 100%;
            max-width: 1000px;
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

        /* Header Section */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .main-heading-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .main-heading-content img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .header-buttons {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        /* Page Title Section */
        .page-title-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            color: #1a202c;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .page-description {
            font-size: 1.1rem;
            color: #64748b;
            font-weight: 500;
        }

        /* Button Styles */
        .primary-btn, .secondary-btn, .danger-btn, .action-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            min-height: 44px;
        }

        .primary-btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }
        .primary-btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
            transform: translateY(-1px);
        }

        .secondary-btn {
            background-color: #f8fafc;
            color: #4CAF50;
            border: 2px solid #4CAF50;
        }
        .secondary-btn:hover {
            background-color: #4CAF50;
            color: white;
            transform: translateY(-1px);
        }

        .danger-btn, .action-btn.delete {
            background-color: #ef4444;
            color: white;
            border: none;
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
        }
        .danger-btn:hover, .action-btn.delete:hover {
            background-color: #dc2626;
            transform: translateY(-1px);
        }

        .action-btn {
            background-color: #3b82f6;
            color: white;
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            margin-right: 0.5rem;
        }
        .action-btn:hover {
            background-color: #2563eb;
            transform: translateY(-1px);
        }

        /* Message Box */
        .message-box {
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0.75rem;
            font-weight: 500;
            font-size: 0.95rem;
            text-align: center;
            border: 1px solid transparent;
        }
        .message-box.success {
            background-color: #dcfce7;
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
            border-color: #dbeafe;
        }

        /* Table Styles */
        .table-container {
            background-color: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }

        th {
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            text-align: left;
            font-size: 0.95rem;
            letter-spacing: 0.025em;
            text-transform: uppercase;
        }

        td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.95rem;
            color: #374151;
            vertical-align: middle;
        }

        tbody tr {
            transition: background-color 0.15s ease;
        }

        tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        tbody tr:hover {
            background-color: #f0fdf4;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Action Column */
        .action-cell {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .delete-form {
            display: inline-block;
            margin: 0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
            font-size: 1.1rem;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .dashboard-container {
                padding: 1.5rem;
                border-radius: 1rem;
            }

            .header-section {
                flex-direction: column;
                gap: 1.5rem;
                align-items: stretch;
                margin-bottom: 2rem;
            }

            .main-heading-content {
                justify-content: center;
            }

            .header-buttons {
                justify-content: center;
                flex-wrap: wrap;
            }

            .page-title {
                font-size: 2rem;
            }

            .page-description {
                font-size: 1rem;
            }

            .primary-btn, .secondary-btn {
                flex: 1;
                min-width: 140px;
            }

            /* Mobile Table */
            .table-container {
                border-radius: 0.5rem;
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
                border-radius: 0.5rem;
                padding: 1rem;
                background-color: white;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }

            td {
                border: none;
                border-bottom: 1px solid #f1f5f9;
                position: relative;
                padding: 0.75rem 0;
                padding-left: 45%;
                text-align: right;
            }

            td:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }

            td:before {
                content: attr(data-label);
                position: absolute;
                left: 0;
                top: 0.75rem;
                width: 40%;
                padding-right: 1rem;
                white-space: nowrap;
                text-align: left;
                font-weight: 600;
                color: #374151;
            }

            .action-cell {
                justify-content: flex-end;
                gap: 0.25rem;
            }

            .action-btn, .danger-btn {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 1.75rem;
            }

            .header-buttons {
                flex-direction: column;
            }

            .primary-btn, .secondary-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Header Section -->
    <div class="header-section">
        <div class="main-heading-content">
            <img src="logoo.png" alt="CHSI Logo">
        </div>
        <div class="header-buttons">
            <a href="admin_dashboard.php" class="secondary-btn">Back to Admin Dashboard</a>
            <a href="logout.php" class="primary-btn">Logout</a>
        </div>
    </div>

    <!-- Page Title Section -->
    <div class="page-title-section">
        <h1 class="page-title">Manage Users</h1>
        <p class="page-description">View and manage all user accounts</p>
    </div>

    <!-- Message Display -->
    <?php if (!empty($message)): ?>
        <div class="message-box <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Users Table -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_users)): ?>
                    <?php foreach ($all_users as $user): ?>
                        <tr>
                            <td data-label="Username"><?= htmlspecialchars($user['username']) ?></td>
                            <td data-label="Role"><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                            <td data-label="Department"><?= htmlspecialchars($user['department_name'] ?? 'N/A') ?></td>
                            <td data-label="Actions">
                                <div class="action-cell">
                                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="action-btn">Edit</a>
                                    <form method="post" action="manage_users.php" class="delete-form" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                        <input type="hidden" name="delete_user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="action-btn delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="empty-state">No users found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>