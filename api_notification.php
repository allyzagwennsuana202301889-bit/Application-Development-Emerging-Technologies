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
        $placeholders = implode(',', array_fill(0, count($idArray), '?'));
        $types = str_repeat('i', count($idArray));
        $stmt = $conn->prepare("
            DELETE FROM notification 
            WHERE notification_id IN ($placeholders) AND student_id = ?
        ");
        $params = array_merge($idArray, [$student_id]);
        $types .= 'i';
        $stmt->bind_param($types, ...$params);
        echo json_encode(['success' => $stmt->execute()]);
        break;

    case 'delete_all':
        $stmt = $conn->prepare("DELETE FROM notification WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        echo json_encode(['success' => $stmt->execute()]);
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