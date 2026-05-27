<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
$action = $_POST['action'] ?? $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {

    case 'mark_read':
        $notif_id = (int)($_POST['notification_id'] ?? 0);
        $stmt = $conn->prepare("
            UPDATE notification SET status = 'read' 
            WHERE notification_id = ? AND student_id = ?
        ");
        $stmt->bind_param("ii", $notif_id, $student_id);
        echo json_encode(['success' => $stmt->execute()]);
        break;

    case 'mark_all_read':
        $stmt = $conn->prepare("
            UPDATE notification SET status = 'read' WHERE student_id = ?
        ");
        $stmt->bind_param("i", $student_id);
        echo json_encode(['success' => $stmt->execute()]);
        break;

    case 'delete_selected':
        $ids = $_POST['ids'] ?? '';
        if (empty($ids)) {
            echo json_encode(['success' => false, 'error' => 'No IDs provided']);
            break;
        }
        $idArray = array_filter(explode(',', $ids), 'is_numeric');
        if (empty($idArray)) {
            echo json_encode(['success' => false, 'error' => 'Invalid IDs']);
            break;
        }

        // For 'missing' section: record dismissal in dismissed_missing, then hard delete
        // For 'updates' section: just hard delete
        $placeholders = implode(',', array_fill(0, count($idArray), '?'));
        $types = str_repeat('i', count($idArray));

        // Get missing notifs to record their dismissal
        $getMissing = $conn->prepare("
            SELECT notification_id, subject_id, type 
            FROM notification 
            WHERE notification_id IN ($placeholders) 
            AND student_id = ? AND section = 'missing'
        ");
        $params = array_merge($idArray, [$student_id]);
        $getMissing->bind_param($types . 'i', ...$params);
        $getMissing->execute();
        $missingResult = $getMissing->get_result();

        $dismissStmt = $conn->prepare("
            INSERT IGNORE INTO dismissed_missing (student_id, subject_id, type) 
            VALUES (?, ?, ?)
        ");
        while ($m = $missingResult->fetch_assoc()) {
            $dismissStmt->bind_param("iis", $student_id, $m['subject_id'], $m['type']);
            $dismissStmt->execute();
        }

        // Hard delete ALL selected notifs (both missing and updates)
        $deleteStmt = $conn->prepare("
            DELETE FROM notification 
            WHERE notification_id IN ($placeholders) 
            AND student_id = ?
        ");
        $deleteStmt->bind_param($types . 'i', ...$params);
        $deleteStmt->execute();

        echo json_encode(['success' => true]);
        break;

    case 'delete_all':
        // Record all missing notifs as dismissed, then hard delete everything
        $getAllMissing = $conn->prepare("
            SELECT subject_id, type FROM notification 
            WHERE student_id = ? AND section = 'missing'
        ");
        $getAllMissing->bind_param("i", $student_id);
        $getAllMissing->execute();
        $allMissing = $getAllMissing->get_result();

        $dismissStmt = $conn->prepare("
            INSERT IGNORE INTO dismissed_missing (student_id, subject_id, type) 
            VALUES (?, ?, ?)
        ");
        while ($m = $allMissing->fetch_assoc()) {
            $dismissStmt->bind_param("iis", $student_id, $m['subject_id'], $m['type']);
            $dismissStmt->execute();
        }

        // Hard delete all notifs for this student
        $stmt = $conn->prepare("DELETE FROM notification WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();

        echo json_encode(['success' => true]);
        break;

    case 'unread_count':
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM notification 
            WHERE student_id = ? AND status = 'unread'
        ");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
        echo json_encode(['count' => $count]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
?>