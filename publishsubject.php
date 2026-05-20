<?php
session_start();
include 'database.php';

$note_id = $_POST['note_id'];

// Get note info
$stmt = $conn->prepare("SELECT title, content, student_id FROM notes WHERE note_id = ?");
$stmt->bind_param("i", $note_id);
$stmt->execute();
$result = $stmt->get_result();
$note = $result->fetch_assoc();

if (!$note) {
    die("Note not found");
}

$title = $note['title'];
$body = $note['content']; // Content is already JSON or text
$student_id = $note['student_id'];

/* INSERT INTO SUBJECTS */
$stmt = $conn->prepare("INSERT INTO subjects (subject_name, description, student_id, is_preset) VALUES (?, ?, ?, 0)");
$stmt->bind_param("ssi", $title, $body, $student_id);
$stmt->execute();
$new_subject_id = $conn->insert_id;

/* NOTIFY ALL OTHER STUDENTS */
$students = $conn->query("SELECT student_id FROM student WHERE student_id != $student_id");

if ($students && $students->num_rows > 0) {
    $insertNotif = $conn->prepare("
        INSERT INTO notification 
        (type, status, date_sent, student_id, subject_id, triggered_by, section, title, message, action_url)
        VALUES ('subject_uploaded', 'unread', NOW(), ?, ?, ?, 'updates', ?, ?, ?)
    ");

    $notifTitle = "New Subject: " . $title;
    $notifMsg = "A new subject \"" . $title . "\" has been uploaded. Check it out!";
    $actionUrl = "subject.php?id=" . $new_subject_id . "&type=subjects";

    while ($row = $students->fetch_assoc()) {
        $targetStudent = $row['student_id'];

        // Check if already notified in last 24h
        $check = $conn->prepare("
            SELECT notification_id FROM notification 
            WHERE student_id = ? AND subject_id = ? AND type = 'subject_uploaded'
            AND date_sent >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $check->bind_param("ii", $targetStudent, $new_subject_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) continue;

        $insertNotif->bind_param("iiisss", 
            $targetStudent, $new_subject_id, $student_id, $notifTitle, $notifMsg, $actionUrl
        );
        $insertNotif->execute();
    }
}

/* DELETE DRAFT */
$conn->query("DELETE FROM notes WHERE note_id = $note_id");

echo "success";
?>