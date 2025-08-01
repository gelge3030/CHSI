<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin')) {
    header("location: login.php");
    exit();
}

$file_id = null;
$filename = '';
$department = ''; // To display current department, though typically not editable directly here

$message = '';
$error = '';

// Handle POST request for updating file
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $file_id = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
    $new_filename = trim($_POST['filename']);

    if ($file_id === false || $file_id === null || empty($new_filename)) {
        $error = "Invalid file ID or filename provided.";
    } else {
        // First, get the current filename and filepath to check for changes and update path if needed
        $stmt_check = $conn->prepare("SELECT filename, filepath FROM files WHERE id = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("i", $file_id);
            $stmt_check->execute();
            $check_result = $stmt_check->get_result();
            if ($check_result->num_rows == 1) {
                $current_file_data = $check_result->fetch_assoc();
                $current_filename_full = basename($current_file_data['filepath']);
                $current_dir = dirname($current_file_data['filepath']);
                $file_extension = pathinfo($current_filename_full, PATHINFO_EXTENSION);

                // Construct new file path if filename is changing
                $new_filepath = $current_file_data['filepath'];
                if ($new_filename . '.' . $file_extension !== $current_filename_full) {
                    $new_filepath = $current_dir . '/' . $new_filename . '.' . $file_extension;
                }

                // Update database
                $stmt = $conn->prepare("UPDATE files SET filename = ?, filepath = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("ssi", $new_filename, $new_filepath, $file_id);
                    if ($stmt->execute()) {
                        // If file path changed, attempt to rename the actual file on disk
                        if ($new_filepath !== $current_file_data['filepath']) {
                            if (rename($current_file_data['filepath'], $new_filepath)) {
                                $message = "File updated successfully and renamed on disk.";
                            } else {
                                $error = "File updated in database, but failed to rename file on disk. Manual intervention may be needed.";
                                error_log("Failed to rename file from " . $current_file_data['filepath'] . " to " . $new_filepath);
                            }
                        } else {
                            $message = "File updated successfully.";
                        }
                    } else {
                        $error = "Error updating file: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Database prepare error: " . $conn->error;
                }
            } else {
                $error = "File not found.";
            }
            $stmt_check->close();
        } else {
            $error = "Database check prepare error: " . $conn->error;
        }
    }
} else {
    // Handle GET request for displaying the form
    $file_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($file_id === false || $file_id === null) {
        $error = "Invalid file ID.";
    } else {
        $stmt = $conn->prepare("SELECT filename, u.department FROM files f JOIN users u ON f.uploaded_by = u.id WHERE f.id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $file_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $file_data = $result->fetch_assoc();
                $filename = htmlspecialchars($file_data['filename']);
                $department = htmlspecialchars($file_data['department']);
            } else {
                $error = "File not found.";
            }
            $stmt->close();
        } else {
            $error = "Database prepare error: " . $conn->error;
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
    <title>Edit File - CHSI Storage System</title>
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
            overflow: auto;
        }

        .edit-container {
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
            position: relative;
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

        h2 {
            font-size: 2.25rem;
            color: #333;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 600;
        }

        .form-group input[type="text"] {
            width: calc(100% - 22px); /* Account for padding and border */
            padding: 0.9rem 1rem;
            border: 1px solid #ccc;
            border-radius: 0.6rem;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-group input[type="text"]:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
            outline: none;
        }

        .message.success {
            color: #2E8B57;
            background-color: #e6ffed;
            border: 1px solid #4CAF50;
            padding: 1rem;
            border-radius: 0.6rem;
            margin-bottom: 1.5rem;
        }

        .message.error {
            color: #e74c3c;
            background-color: #ffe6e6;
            border: 1px solid #e74c3c;
            padding: 1rem;
            border-radius: 0.6rem;
            margin-bottom: 1.5rem;
        }

        .primary-btn, .secondary-btn {
            background-image: linear-gradient(to right, #4CAF50 0%, #2E8B57 100%);
            color: white;
            padding: 0.9rem 1.5rem;
            border: none;
            border-radius: 0.6rem;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(76, 175, 80, 0.4);
            transition: all 0.3s ease;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            text-decoration: none;
            display: inline-block;
            margin: 0.5rem;
        }
        .primary-btn:hover, .secondary-btn:hover {
            background-image: linear-gradient(to right, #2E8B57 0%, #4CAF50 100%);
            box-shadow: 0 6px 15px rgba(76, 175, 80, 0.6);
            transform: translateY(-4px);
        }
        .primary-btn:active, .secondary-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(76, 175, 80, 0.3);
        }

        .secondary-btn {
            background-image: none;
            background-color: #ccc;
            color: #333;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .secondary-btn:hover {
            background-color: #bbb;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>

<div class="edit-container">
    <h2>Edit File</h2>

    <?php if (!empty($message)): ?>
        <div class="message success"><?= $message ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="message error"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($file_id !== null && empty($error)): ?>
        <form action="edit_file.php" method="post">
            <input type="hidden" name="file_id" value="<?= $file_id ?>">
            <div class="form-group">
                <label for="filename">Filename (without extension):</label>
                <input type="text" id="filename" name="filename" value="<?= $filename ?>" required>
            </div>
            <div class="form-group">
                <label>Department:</label>
                <input type="text" value="<?= $department ?>" readonly>
            </div>
            <button type="submit" class="primary-btn">Update File</button>
            <a href="admin_dashboard.php" class="secondary-btn">Cancel</a>
        </form>
    <?php else: ?>
        <p>File details could not be loaded. Please return to the <a href="admin_dashboard.php">dashboard</a>.</p>
    <?php endif; ?>
</div>

</body>
</html>