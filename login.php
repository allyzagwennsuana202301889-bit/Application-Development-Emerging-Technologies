<?php
session_start();

$conn = new mysqli("localhost", "root", "", "insdatabase");

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$email = trim($_POST['email']);
$password = trim($_POST['password']);

// check if user exists
$sql = "SELECT * FROM student WHERE email='$email'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {

    $user = $result->fetch_assoc();

    if ($user['password'] === $password) {

        $_SESSION['student_id'] = $user['student_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email']; // ✅ FIX

        header("Location: homepage.php");
        exit();

    } else {
        echo "Wrong password";
    }

} else {

    $name = explode("@", $email)[0];

    $insert = "INSERT INTO student (name, email, password)
               VALUES ('$name', '$email', '$password')";

    if ($conn->query($insert) === TRUE) {

        $new_id = $conn->insert_id;

        $_SESSION['student_id'] = $new_id;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email; 

        header("Location: homepage.php"); 
        exit();

    } else {
        echo "Error: " . $conn->error;
    }
}

$conn->close();
?>