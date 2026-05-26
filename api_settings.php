<?php
session_start();
include 'database.php';

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

$student_id = $_SESSION['student_id'] ?? 0;
if (!$student_id) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'get_user') {
    $stmt = $conn->prepare("SELECT name, email FROM student WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        echo json_encode([
            'success' => true,
            'name' => $user['name'],
            'email' => $user['email']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
}

elseif ($action === 'get_notifications_state') {
    $stmt = $conn->prepare("SELECT notifications_enabled FROM student WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'enabled' => (bool)($result['notifications_enabled'] ?? 1)
    ]);
}

elseif ($action === 'toggle_notifications') {
    $enabled = (int)($_POST['enabled'] ?? 1);
    
    $stmt = $conn->prepare("UPDATE student SET notifications_enabled = ? WHERE student_id = ?");
    $stmt->bind_param("ii", $enabled, $student_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'enabled' => (bool)$enabled]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update']);
    }
}

elseif ($action === 'update_username') {
    $name = trim($_POST['name'] ?? '');
    
    if (strlen($name) < 3) {
        echo json_encode(['success' => false, 'error' => 'Username too short']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE student SET name = ? WHERE student_id = ?");
    $stmt->bind_param("si", $name, $student_id);
    
    if ($stmt->execute()) {
        $_SESSION['name'] = $name;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

elseif ($action === 'update_email') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT password FROM student WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'Incorrect password']);
        exit;
    }
    
    $check = $conn->prepare("SELECT student_id FROM student WHERE email = ? AND student_id != ?");
    $check->bind_param("si", $email, $student_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Email already in use']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE student SET email = ? WHERE student_id = ?");
    $stmt->bind_param("si", $email, $student_id);
    
    if ($stmt->execute()) {
        $_SESSION['email'] = $email;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
}

elseif ($action === 'update_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT password FROM student WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user || !password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
        exit;
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE student SET password = ? WHERE student_id = ?");
    $stmt->bind_param("si", $hashed_password, $student_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update password']);
    }
}

elseif ($action === 'delete_account') {
    $password = $_POST['password'] ?? '';
    
    $stmt = $conn->prepare("SELECT password FROM student WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'Incorrect password']);
        exit;
    }
    
    try {
        $conn->begin_transaction();
        
        // Disable FK checks to allow deletion in any order
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Delete all related data
        $conn->query("DELETE FROM quiz_results WHERE student_id = $student_id");
        $conn->query("DELETE FROM reading_progress WHERE student_id = $student_id");
        $conn->query("DELETE FROM subject_progress WHERE student_id = $student_id");
        $conn->query("DELETE FROM student_stats WHERE student_id = $student_id");
        $conn->query("DELETE FROM student_subjects WHERE student_id = $student_id");
        $conn->query("DELETE FROM analytics WHERE student_id = $student_id");
        $conn->query("DELETE FROM analytics_insights WHERE student_id = $student_id");
        $conn->query("DELETE FROM folders WHERE student_id = $student_id");
        $conn->query("DELETE FROM leaderboard WHERE student_id = $student_id");
        $conn->query("DELETE FROM notification WHERE student_id = $student_id OR triggered_by = $student_id");
        $conn->query("DELETE FROM notes WHERE student_id = $student_id");
        
        // Finally delete the student
        $stmt = $conn->prepare("DELETE FROM student WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        
        // Re-enable FK checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        $conn->commit();
        
        session_destroy();
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Delete failed: ' . $e->getMessage()]);
    }
}
?>