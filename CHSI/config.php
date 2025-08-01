<?php
// Enable error reporting for debugging (REMOVE IN PRODUCTION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "chsi_storage_system";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error() . "<br>Please check your database connection details in config.php.");
}

// DO NOT CLOSE THE CONNECTION HERE!
// The connection should remain open for your main script to use it.
// It will automatically close when the PHP script finishes execution.
?>