<?php
session_start();
include 'database.php';

if (!isset($_SESSION['student_id'])) {
    die("Not logged in");
}

$student_id = $_SESSION['student_id'];
$note_id = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;
$type = $_POST['type'] ?? '';

if (!$note_id || !$type) {
    die("Invalid request");
}

// Helper: Check if user wants notifications
function userWantsNotifications($conn, $student_id) {
    $check = $conn->prepare("SELECT notifications_enabled FROM student WHERE student_id = ?");
    $check->bind_param("i", $student_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    return ($result['notifications_enabled'] ?? 1) == 1;
}

/* ===============================
   PUBLISH — Notify ALL students except self
================================= */
if ($type === "subject") {

    $get = $conn->prepare("SELECT title, content FROM notes WHERE note_id=? AND student_id=?");
    $get->bind_param("ii", $note_id, $student_id);
    $get->execute();
    $res = $get->get_result();
    $note = $res->fetch_assoc();

    if (!$note) {
        die("Note not found");
    }

    $stmt = $conn->prepare("UPDATE notes SET type='subject' WHERE note_id=? AND student_id=?");
    $stmt->bind_param("ii", $note_id, $student_id);
    $stmt->execute();

    // === NOTIFY ALL OTHER STUDENTS ===
    $allStudents = $conn->query("SELECT student_id FROM student WHERE student_id != $student_id");

    if ($allStudents && $allStudents->num_rows > 0) {
        $insertNotif = $conn->prepare("
            INSERT INTO notification 
            (type, status, date_sent, student_id, subject_id, triggered_by, section, title, message, action_url)
            VALUES ('subject_published', 'unread', NOW(), ?, ?, ?, 'updates', ?, ?, ?)
        ");

        $title = $note['title'] ?? 'Untitled';
        $notifTitle = "Subject Published: " . $title;
        $notifMsg = "\"" . $title . "\"  subject has been published! Check out its content!";
        $actionUrl = "subject.php?id=" . $note_id . "&type=notes";

        while ($row = $allStudents->fetch_assoc()) {
            $targetStudent = $row['student_id'];

            // CHECK: Skip if notifications disabled
            if (!userWantsNotifications($conn, $targetStudent)) continue;

            // Check if already notified in last 24h to avoid spam
            $check = $conn->prepare("
                SELECT notification_id FROM notification 
                WHERE student_id = ? AND subject_id = ? AND type = 'subject_published'
                AND date_sent >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $check->bind_param("ii", $targetStudent, $note_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) continue;

            $insertNotif->bind_param("iiisss", 
                $targetStudent, $note_id, $student_id, $notifTitle, $notifMsg, $actionUrl
            );
            $insertNotif->execute();
        }
    }

}
/* ===============================
   UNPUBLISH — Notify ALL students except self
================================= */
else {

    $get = $conn->prepare("SELECT title FROM notes WHERE note_id=? AND student_id=?");
    $get->bind_param("ii", $note_id, $student_id);
    $get->execute();
    $res = $get->get_result();
    $note = $res->fetch_assoc();
    $title = $note['title'] ?? 'Untitled';

    $stmt = $conn->prepare("UPDATE notes SET type='subject_draft' WHERE note_id=? AND student_id=?");
    $stmt->bind_param("ii", $note_id, $student_id);
    $stmt->execute();

    // === NOTIFY ALL OTHER STUDENTS ===
    $allStudents = $conn->query("SELECT student_id FROM student WHERE student_id != $student_id");

    if ($allStudents && $allStudents->num_rows > 0) {
        $insertNotif = $conn->prepare("
            INSERT INTO notification 
            (type, status, date_sent, student_id, subject_id, triggered_by, section, title, message, action_url)
            VALUES ('subject_unpublished', 'unread', NOW(), ?, ?, ?, 'updates', ?, ?, 'homepage.php')
        ");

        $notifTitle = "Subject Unpublished: " . $title;
        $notifMsg = "\"" . $title . "\" is no longer accessible. It may return soon.";

        while ($row = $allStudents->fetch_assoc()) {
            $targetStudent = $row['student_id'];

            // CHECK: Skip if notifications disabled
            if (!userWantsNotifications($conn, $targetStudent)) continue;

            // Check if already notified in last 24h
            $check = $conn->prepare("
                SELECT notification_id FROM notification 
                WHERE student_id = ? AND subject_id = ? AND type = 'subject_unpublished'
                AND date_sent >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $check->bind_param("ii", $targetStudent, $note_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) continue;

            $insertNotif->bind_param("iiiss", 
                $targetStudent, $note_id, $student_id, $notifTitle, $notifMsg
            );
            $insertNotif->execute();
        }
    }
}

echo "updated";
?>