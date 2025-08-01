<?php
session_start();

require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

$uploadDirectory = 'uploads/';

// Create the uploads directory if it doesn't exist
if (!is_dir($uploadDirectory)) {
    mkdir($uploadDirectory, 0777, true); // Ensure write permissions
}

// Array to store messages for each file upload attempt
$upload_messages = [];
$overall_upload_type = "success"; // Will be set to 'error' if any file upload fails
$successful_uploads_count = 0; // New counter for successful uploads

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['uploaded_files'])) {
    // Loop through each file in the $_FILES['uploaded_files'] array
    foreach ($_FILES['uploaded_files']['name'] as $key => $fileName) {
        // Skip empty file inputs (e.g., if user didn't select files for all available slots)
        if (empty($fileName)) {
            continue;
        }

        $fileTmpName = $_FILES['uploaded_files']['tmp_name'][$key];
        $fileSize = $_FILES['uploaded_files']['size'][$key];
        $fileError = $_FILES['uploaded_files']['error'][$key];
        $fileType = $_FILES['uploaded_files']['type'][$key];

        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $uniqueFileName = uniqid('file_', true) . '.' . $fileExtension;
        $destinationPath = $uploadDirectory . $uniqueFileName;

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];

        $current_file_message = '';
        
        // --- File Validation for the current file ---
        if ($fileError !== UPLOAD_ERR_OK) {
            $overall_upload_type = "error"; // Mark overall upload as error
            switch ($fileError) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $current_file_message = "File '" . htmlspecialchars($fileName) . "' is too large (server limit exceeded).";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $current_file_message = "File '" . htmlspecialchars($fileName) . "' was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $current_file_message = "No file was uploaded for slot '" . htmlspecialchars($fileName) . "'.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $current_file_message = "Missing a temporary folder for '" . htmlspecialchars($fileName) . "'.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $current_file_message = "Failed to write file '" . htmlspecialchars($fileName) . "' to disk.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $current_file_message = "A PHP extension stopped the upload of '" . htmlspecialchars($fileName) . "'.";
                    break;
                default:
                    $current_file_message = "File '" . htmlspecialchars($fileName) . "' upload failed with unknown error code: " . $fileError;
                    break;
            }
        } elseif (!in_array($fileExtension, $allowedExtensions)) {
            $current_file_message = "File '" . htmlspecialchars($fileName) . "': Invalid file type. Only JPG, JPEG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT are allowed.";
            $overall_upload_type = "error";
        } elseif ($fileSize > 20000000) { // 20MB limit
            $current_file_message = "File '" . htmlspecialchars($fileName) . "' is too large. Maximum 20MB allowed.";
            $overall_upload_type = "error";
        } else {
            // Attempt to move the uploaded file
            if (move_uploaded_file($fileTmpName, $destinationPath)) {
                $userId = $_SESSION['id']; // Correct session key for user ID
                $uploadDate = date('Y-m-d H:i:s');

                // Insert file information into the database
                $stmt = $conn->prepare("INSERT INTO files (filename, filepath, size, upload_date, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssisi", $fileName, $destinationPath, $fileSize, $uploadDate, $userId);
                    if ($stmt->execute()) {
                        // Successfully uploaded and recorded
                        $successful_uploads_count++; // Increment counter
                        // We'll combine messages later, for now just note success for this file if detailed messages are needed.
                    } else {
                        $current_file_message = "File '" . htmlspecialchars($fileName) . "': Error recording in database: " . $stmt->error;
                        $overall_upload_type = "error";
                        unlink($destinationPath); // Delete file from server if DB insert fails
                    }
                    $stmt->close();
                } else {
                    $current_file_message = "File '" . htmlspecialchars($fileName) . "': Database statement preparation failed: " . $conn->error;
                    $overall_upload_type = "error";
                    unlink($destinationPath); // Delete file from server if statement fails
                }
            } else {
                $current_file_message = "File '" . htmlspecialchars($fileName) . "': Failed to move file. Check server 'uploads' folder permissions.";
                $overall_upload_type = "error";
            }
        }
        // Only add messages for failed uploads to the detailed list.
        // Successful messages will be summarized at the end.
        if (!empty($current_file_message)) {
            $upload_messages[] = $current_file_message;
        }
    } // End foreach

    // Formulate the overall success message based on the count
    $final_success_message = '';
    if ($successful_uploads_count > 0) {
        if ($successful_uploads_count === 1) {
            $final_success_message = "File uploaded successfully!";
        } else {
            $final_success_message = "Files uploaded successfully!";
        }
    }

    // Combine any detailed error messages with the general success message
    if (!empty($final_success_message) && !empty($upload_messages)) {
        array_unshift($upload_messages, $final_success_message); // Add general success to the beginning
        $_SESSION['upload_message'] = implode('<br>', $upload_messages);
        $_SESSION['upload_type'] = "error"; // If there were errors, even with some successes, mark as error overall
    } elseif (!empty($final_success_message)) {
        $_SESSION['upload_message'] = $final_success_message;
        $_SESSION['upload_type'] = "success";
    } elseif (!empty($upload_messages)) {
        $_SESSION['upload_message'] = implode('<br>', $upload_messages);
        $_SESSION['upload_type'] = "error";
    } else {
        $_SESSION['upload_message'] = "No files processed or unknown error.";
        $_SESSION['upload_type'] = "error";
    }

} else {
    // If no files were selected or it wasn't a POST request from the form
    $_SESSION['upload_message'] = "No files selected for upload or invalid request.";
    $_SESSION['upload_type'] = "error";
}

$conn->close();

// Redirect back to the dashboard to display the message(s)
header('Location: dashboard.php');
exit();
?>