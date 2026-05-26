<?php
// Run via cron: php cron_notification.php
include 'database.php';

$now = date('Y-m-d H:i:s');

// Helper: Check if user wants notifications
function userWantsNotifications($conn, $student_id) {
    $check = $conn->prepare("SELECT notifications_enabled FROM student WHERE student_id = ?");
    $check->bind_param("i", $student_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    return ($result['notifications_enabled'] ?? 1) == 1;
}

// =========================
// 1. UPDATES SECTION
// =========================

// A. Subject updated in last 24h
$stmt = $conn->prepare("
    SELECT s.subject_id, s.subject_name, s.student_id as updater_id, st.name as updater_name
    FROM subjects s
    LEFT JOIN student st ON s.student_id = st.student_id
    WHERE s.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND s.is_preset = 0
");
$stmt->execute();
$results = $stmt->get_result();

while ($sub = $results->fetch_assoc()) {
    $users = $conn->prepare("
        SELECT DISTINCT student_id FROM student_subjects 
        WHERE subject_id = ? AND source_type = 'subjects'
    ");
    $users->bind_param("i", $sub['subject_id']);
    $users->execute();
    $user_list = $users->get_result();

    while ($user = $user_list->fetch_assoc()) {
        $uid = $user['student_id'];
        if ($uid == $sub['updater_id']) continue;
        
        // CHECK: Skip if notifications disabled
        if (!userWantsNotifications($conn, $uid)) continue;

        $check = $conn->prepare("
            SELECT notification_id FROM notification 
            WHERE student_id = ? AND subject_id = ? AND type = 'subject_updated'
            AND date_sent >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $check->bind_param("ii", $uid, $sub['subject_id']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) continue;

        $title = $sub['subject_name'] . ' updated';
        $msg = $sub['updater_name'] . ' updated ' . $sub['subject_name'];
        $url = 'subject.php?id=' . $sub['subject_id'] . '&type=subjects';

        $ins = $conn->prepare("
            INSERT INTO notification (student_id, subject_id, triggered_by, type, section, title, message, action_url, status, date_sent)
            VALUES (?, ?, ?, 'subject_updated', 'updates', ?, ?, ?, 'unread', ?)
        ");
        $ins->bind_param("iiissss", $uid, $sub['subject_id'], $sub['updater_id'], $title, $msg, $url, $now);
        $ins->execute();
    }
}

// B. Quiz added/updated
$quiz_stmt = $conn->prepare("
    SELECT q.quiz_id, s.subject_id, s.subject_name
    FROM quiz_questions q
    JOIN subjects s ON q.quiz_id = s.subject_id
    WHERE q.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$quiz_stmt->execute();
$quizzes = $quiz_stmt->get_result();

while ($quiz = $quizzes->fetch_assoc()) {
    $users = $conn->prepare("SELECT DISTINCT student_id FROM student_subjects WHERE subject_id = ?");
    $users->bind_param("i", $quiz['subject_id']);
    $users->execute();
    $user_list = $users->get_result();

    while ($user = $user_list->fetch_assoc()) {
        $uid = $user['student_id'];
        
        // CHECK: Skip if notifications disabled
        if (!userWantsNotifications($conn, $uid)) continue;

        $title = 'New quiz: ' . $quiz['subject_name'];
        $msg = 'A new quiz has been added to ' . $quiz['subject_name'];
        $url = 'quiz.php?id=' . $quiz['subject_id'] . '&type=subjects';

        $ins = $conn->prepare("
            INSERT INTO notification (student_id, subject_id, type, section, title, message, action_url, status, date_sent)
            VALUES (?, ?, 'quiz_added', 'updates', ?, ?, ?, 'unread', ?)
        ");
        $ins->bind_param("iissss", $uid, $quiz['subject_id'], $title, $msg, $url, $now);
        $ins->execute();
    }
}

// C. User uploaded a subject
$upload_stmt = $conn->prepare("
    SELECT s.subject_id, s.subject_name, s.student_id as uploader_id, st.name as uploader_name
    FROM subjects s
    LEFT JOIN student st ON s.student_id = st.student_id
    WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND s.is_preset = 0
    AND s.student_id IS NOT NULL
");
$upload_stmt->execute();
$uploads = $upload_stmt->get_result();

while ($up = $uploads->fetch_assoc()) {
    $all = $conn->prepare("SELECT student_id FROM student WHERE student_id != ?");
    $all->bind_param("i", $up['uploader_id']);
    $all->execute();
    $students = $all->get_result();

    while ($stu = $students->fetch_assoc()) {
        $uid = $stu['student_id'];

        // CHECK: Skip if notifications disabled
        if (!userWantsNotifications($conn, $uid)) continue;

        $check = $conn->prepare("
            SELECT notification_id FROM notification 
            WHERE student_id = ? AND subject_id = ? AND type = 'subject_uploaded'
            AND date_sent >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $check->bind_param("ii", $uid, $up['subject_id']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) continue;

        $title = $up['uploader_name'] . ' uploaded ' . $up['subject_name'];
        $msg = 'Check out ' . $up['subject_name'] . ' and try it out!';
        $url = 'subject.php?id=' . $up['subject_id'] . '&type=subjects';

        $ins = $conn->prepare("
            INSERT INTO notification (student_id, subject_id, triggered_by, type, section, title, message, action_url, status, date_sent)
            VALUES (?, ?, ?, 'subject_uploaded', 'updates', ?, ?, ?, 'unread', ?)
        ");
        $ins->bind_param("iiissss", $uid, $up['subject_id'], $up['uploader_id'], $title, $msg, $url, $now);
        $ins->execute();
    }
}

// D. User uploaded from notes table
$notes_stmt = $conn->prepare("
    SELECT n.note_id, n.title as subject_name, n.student_id as uploader_id, st.name as uploader_name
    FROM notes n
    LEFT JOIN student st ON n.student_id = st.student_id
    WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND n.type = 'subject'
    AND n.student_id IS NOT NULL
");
$notes_stmt->execute();
$notes = $notes_stmt->get_result();

while ($note = $notes->fetch_assoc()) {
    $all = $conn->prepare("SELECT student_id FROM student WHERE student_id != ?");
    $all->bind_param("i", $note['uploader_id']);
    $all->execute();
    $students = $all->get_result();

    while ($stu = $students->fetch_assoc()) {
        $uid = $stu['student_id'];

        // CHECK: Skip if notifications disabled
        if (!userWantsNotifications($conn, $uid)) continue;

        $check = $conn->prepare("
            SELECT notification_id FROM notification 
            WHERE student_id = ? AND subject_id = ? AND type = 'subject_uploaded'
            AND date_sent >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $check->bind_param("ii", $uid, $note['note_id']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) continue;

        $title = $note['uploader_name'] . ' uploaded ' . $note['subject_name'];
        $msg = 'Check out ' . $note['subject_name'] . ' and try it out!';
        $url = 'subject.php?id=' . $note['note_id'] . '&type=notes';

        $ins = $conn->prepare("
            INSERT INTO notification (student_id, subject_id, triggered_by, type, section, title, message, action_url, status, date_sent)
            VALUES (?, ?, ?, 'subject_uploaded', 'updates', ?, ?, ?, 'unread', ?)
        ");
        $ins->bind_param("iiissss", $uid, $note['note_id'], $note['uploader_id'], $title, $msg, $url, $now);
        $ins->execute();
    }
}

// =========================
// 2. MISSING SECTION
// =========================

// A. No progress in 7 days
$progress_stmt = $conn->prepare("
    SELECT DISTINCT ss.student_id, ss.subject_id, s.subject_name
    FROM student_subjects ss
    JOIN subjects s ON ss.subject_id = s.subject_id
    LEFT JOIN subject_progress sp ON ss.student_id = sp.student_id 
        AND ss.subject_id = sp.subject_id AND ss.source_type = sp.source_type
    WHERE (sp.overall_percent IS NULL OR sp.overall_percent < 10)
    AND NOT EXISTS (
        SELECT 1 FROM notification n2
        WHERE n2.student_id = ss.student_id 
        AND n2.subject_id = ss.subject_id
        AND n2.type = 'no_progress'
        AND n2.date_sent >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    )
");
$progress_stmt->execute();
$no_progress = $progress_stmt->get_result();

while ($row = $no_progress->fetch_assoc()) {
    $uid = $row['student_id'];
    
    // CHECK: Skip if notifications disabled
    if (!userWantsNotifications($conn, $uid)) continue;

    $title = 'Keep going with ' . $row['subject_name'];
    $msg = 'You haven\'t made progress in ' . $row['subject_name'] . '. Continue studying!';
    $url = 'subject.php?id=' . $row['subject_id'] . '&type=subjects';

    $ins = $conn->prepare("
        INSERT INTO notification (student_id, subject_id, type, section, title, message, action_url, status, date_sent)
        VALUES (?, ?, 'no_progress', 'missing', ?, ?, ?, 'unread', ?)
    ");
    $ins->bind_param("iissss", $uid, $row['subject_id'], $title, $msg, $url, $now);
    $ins->execute();
}

// B. No quiz attempted in 7 days
$quiz_missing = $conn->prepare("
    SELECT DISTINCT ss.student_id, ss.subject_id, s.subject_name
    FROM student_subjects ss
    JOIN subjects s ON ss.subject_id = s.subject_id
    LEFT JOIN quiz_results qa ON ss.student_id = qa.student_id AND ss.subject_id = qa.subject_id
    WHERE qa.result_id IS NULL
    AND NOT EXISTS (
        SELECT 1 FROM notification n2
        WHERE n2.student_id = ss.student_id 
        AND n2.subject_id = ss.subject_id
        AND n2.type = 'no_quiz'
        AND n2.date_sent >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    )
");
$quiz_missing->execute();
$no_quiz = $quiz_missing->get_result();

while ($row = $no_quiz->fetch_assoc()) {
    $uid = $row['student_id'];
    
    // CHECK: Skip if notifications disabled
    if (!userWantsNotifications($conn, $uid)) continue;

    $title = 'Try the quiz for ' . $row['subject_name'];
    $msg = 'Test your knowledge with the ' . $row['subject_name'] . ' quiz!';
    $url = 'quiz.php?id=' . $row['subject_id'] . '&type=subjects';

    $ins = $conn->prepare("
        INSERT INTO notification (student_id, subject_id, type, section, title, message, action_url, status, date_sent)
        VALUES (?, ?, 'no_quiz', 'missing', ?, ?, ?, 'unread', ?)
    ");
    $ins->bind_param("iissss", $uid, $row['subject_id'], $title, $msg, $url, $now);
    $ins->execute();
}

echo "Notifications generated at " . $now . "\n";
?>