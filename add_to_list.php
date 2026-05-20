<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
$subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
$source_type = $_POST['source_type'] ?? 'subjects';

if (!$student_id || !$subject_id) {
    echo "missing_data";
    exit;
}

/* CHECK IF ALREADY EXISTS */
$check = $conn->prepare("
    SELECT 1 FROM student_subjects
    WHERE student_id = ? AND subject_id = ? AND source_type = ?
");
$check->bind_param("iis", $student_id, $subject_id, $source_type);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    echo "already";
    exit;
}

/* INSERT */
$stmt = $conn->prepare("
    INSERT INTO student_subjects (student_id, subject_id, source_type)
    VALUES (?, ?, ?)
");
$stmt->bind_param("iis", $student_id, $subject_id, $source_type);

if ($stmt->execute()) {

    // === NOTIFY SELF IF SUBJECT IS ALREADY PUBLISHED ===
    if ($source_type === 'notes') {
        $checkPub = $conn->prepare("SELECT title, type FROM notes WHERE note_id = ?");
        $checkPub->bind_param("i", $subject_id);
        $checkPub->execute();
        $pub = $checkPub->get_result()->fetch_assoc();

        if ($pub && $pub['type'] === 'subject') {
            $insertNotif = $conn->prepare("
                INSERT INTO notification 
                (type, status, date_sent, student_id, subject_id, triggered_by, section, title, message, action_url)
                VALUES ('subject_added', 'unread', NOW(), ?, ?, NULL, 'updates', ?, ?, ?)
            ");
            $title = $pub['title'] ?? 'Untitled';
            $notifTitle = "Added to Homepage: " . $title;
            $notifMsg = "You added \"" . $title . "\" to your homepage. Start studying!";
            $actionUrl = "subject.php?id=" . $subject_id . "&type=notes";

            $insertNotif->bind_param("iisss", 
                $student_id, $subject_id, $notifTitle, $notifMsg, $actionUrl
            );
            $insertNotif->execute();
        }
    } elseif ($source_type === 'subjects') {
        $checkPub = $conn->prepare("SELECT subject_name FROM subjects WHERE subject_id = ?");
        $checkPub->bind_param("i", $subject_id);
        $checkPub->execute();
        $pub = $checkPub->get_result()->fetch_assoc();

        if ($pub) {
            $insertNotif = $conn->prepare("
                INSERT INTO notification 
                (type, status, date_sent, student_id, subject_id, triggered_by, section, title, message, action_url)
                VALUES ('subject_added', 'unread', NOW(), ?, ?, NULL, 'updates', ?, ?, ?)
            ");
            $title = $pub['subject_name'] ?? 'Untitled';
            $notifTitle = "Added to Homepage: " . $title;
            $notifMsg = "You added \"" . $title . "\" to your homepage. Start studying!";
            $actionUrl = "subject.php?id=" . $subject_id . "&type=subjects";

            $insertNotif->bind_param("iisss", 
                $student_id, $subject_id, $notifTitle, $notifMsg, $actionUrl
            );
            $insertNotif->execute();
        }
    }

    echo "added";
} else {
    echo "error: " . $stmt->error;
}
?>