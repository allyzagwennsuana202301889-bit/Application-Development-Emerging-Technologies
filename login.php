<?php
session_start();

// Clear any previous session data at the start
$_SESSION = [];

$conn = new mysqli("localhost", "root", "", "insdatabase");

if ($conn->connect_errno) {
    die("Connection failed: " . $conn->connect_error);
}

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    die("Email and password required");
}

$stmt = $conn->prepare("SELECT * FROM student WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();

    if ($user['password'] === $password) {
        session_regenerate_id(true);
        
        $_SESSION['student_id'] = $user['student_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['profile_image'] = $user['profile_image'] ?? '';

        header("Location: homepage.php");
        exit();
    } else {
        echo "Wrong password";
    }
} else {
    $name = explode("@", $email)[0];

    $stmt = $conn->prepare("INSERT INTO student (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $password);
    
    if ($stmt->execute()) {
        session_regenerate_id(true);
        
        $new_id = $stmt->insert_id;
        $_SESSION['student_id'] = $new_id;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        $_SESSION['profile_image'] = '';

        header("Location: homepage.php"); 
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}

$conn->close();
?>