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

    // Check if password is hashed or plain text
    $passwordValid = false;
    $needsRehash = false;
    
    if (password_get_info($user['password'])['algo']) {
        // It's a hash — use password_verify
        $passwordValid = password_verify($password, $user['password']);
        $needsRehash = password_needs_rehash($user['password'], PASSWORD_DEFAULT);
    } else {
        // It's plain text — compare directly, then rehash
        $passwordValid = ($user['password'] === $password);
        $needsRehash = $passwordValid; // Only rehash if correct
    }

    if ($passwordValid) {
        // Rehash plain text passwords on successful login
        if ($needsRehash) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE student SET password = ? WHERE student_id = ?");
            $update->bind_param("si", $newHash, $user['student_id']);
            $update->execute();
        }

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
    
    // Hash password for new registrations
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO student (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $hashedPassword);
    
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