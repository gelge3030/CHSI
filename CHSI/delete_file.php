<?php
session_start();
require_once 'config.php';


if (!isset($_SESSION['id'])) {
    $_SESSION['upload_message'] = "Please log in to perform this action.";
    $_SESSION['upload_type'] = "error";
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['id'];
$userRole = $_SESSION['role'];

$fileIdsToDelete = [];

// Determine if it's a single file delete (GET request) or batch delete (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $fileIdsToDelete[] = (int)$_GET['id'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_ids']) && is_array($_POST['file_ids'])) {
    // Sanitize and cast all received IDs to integers
    foreach ($_POST['file_ids'] as $id) {
        if (is_numeric($id)) {
            $fileIdsToDelete[] = (int)$id;
        }
    }
}

if (empty($fileIdsToDelete)) {
    $_SESSION['upload_message'] = "No valid file(s) selected for deletion.";
    $_SESSION['upload_type'] = "error";
    header('Location: dashboard.php');
    exit();
}

$deletionStatus = [
    'success_count' => 0,
    'failed_count' => 0,
    'messages' => []
];

foreach ($fileIdsToDelete as $fileId) {
    // Start Transaction for each file deletion (or you could wrap the whole loop in one transaction)
    // For simplicity and to report individual failures, we'll do per-file transaction
    $conn->begin_transaction();
    
    try {
        // 1. Fetch file information for the current file ID
        $stmt = $conn->prepare("SELECT filepath, uploaded_by FROM files WHERE id = ?");
        if (!$stmt) {
            throw new Exception("DB prepare failed for fetching file details (ID: {$fileId}): " . $conn->error);
        }
        $stmt->bind_param("i", $fileId);
        $stmt->execute();
        $result = $stmt->get_result();
        $file = $result->fetch_assoc();
        $stmt->close();

        if (!$file) {
            throw new Exception("File with ID {$fileId} not found or already deleted.");
        }

        // Security check: Only the uploader or an admin can delete the file
        if ($file['uploaded_by'] !== $userId && $userRole !== 'admin') {
            throw new Exception("You do not have permission to delete file ID: {$fileId}.");
        }

        $filePath = $file['filepath']; 

        $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
        if (!$stmt) {
            throw new Exception("DB prepare failed for deleting file (ID: {$fileId}): " . $conn->error);
        }
        $stmt->bind_param("i", $fileId);
        if (!$stmt->execute()) {
            throw new Exception("Error deleting file from database (ID: {$fileId}): " . $stmt->error);
        }
        $stmt->close();

        
        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                error_log("Failed to delete file from filesystem: " . $filePath);
                $deletionStatus['messages'][] = "File (ID: {$fileId}) deleted from database, but failed to delete from server storage.";
                $deletionStatus['failed_count']++;
            } else {
                $deletionStatus['messages'][] = "File (ID: {$fileId}) deleted successfully!";
                $deletionStatus['success_count']++;
            }
        } else {
            $deletionStatus['messages'][] = "File (ID: {$fileId}) deleted from database, but not found on server storage.";
            $deletionStatus['success_count']++; 
        }

        $conn->commit(); 
    } catch (Exception $e) {
        $conn->rollback(); 
        $deletionStatus['messages'][] = "Failed to delete file (ID: {$fileId}): " . $e->getMessage();
        $deletionStatus['failed_count']++;
    }
}


if ($deletionStatus['success_count'] > 0 && $deletionStatus['failed_count'] === 0) {
    $_SESSION['upload_message'] = "Successfully deleted " . $deletionStatus['success_count'] . " file(s).";
    $_SESSION['upload_type'] = "success";
} elseif ($deletionStatus['success_count'] > 0 && $deletionStatus['failed_count'] > 0) {
    $_SESSION['upload_message'] = "Deleted " . $deletionStatus['success_count'] . " file(s) with " . $deletionStatus['failed_count'] . " failure(s). Check server logs for details.";
    $_SESSION['upload_type'] = "error"; 
} elseif ($deletionStatus['failed_count'] > 0) {
   
    $_SESSION['upload_message'] = "Failed to delete " . $deletionStatus['failed_count'] . " file(s). Check server logs for details.";
    $_SESSION['upload_type'] = "error";
} else {
   
    $_SESSION['upload_message'] = "No files were deleted.";
    $_SESSION['upload_type'] = "error";
}


foreach ($deletionStatus['messages'] as $msg) {
    error_log("Delete Status: " . $msg);
}


$conn->close();

header('Location: dashboard.php');
exit();
?>