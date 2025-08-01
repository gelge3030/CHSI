<?php

$host = 'localhost';
$db   = 'chsi_Storage_System';
$user = 'root'; 
$conn = new mysqli($host, $user, $pass, $db);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username']);
    $password   = trim($_POST['password']);
    $department = trim($_POST['department']);
    $role_id    = 1; 

    
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "Username already exists.";
    } else {
       
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        
        $stmt = $conn->prepare("INSERT INTO users (username, password, role_id, department) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $username, $hashed_password, $role_id, $department);

        if ($stmt->execute()) {
            echo "Registration successful.";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }

    $check->close();
}

$conn->close();
?>