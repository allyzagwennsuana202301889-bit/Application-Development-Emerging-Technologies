<?php
// Note: session_start() already called by notification.php
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
if ($student_id === 0) return;

/* ============================================================
   FIX: Respect user's notification preference
   ============================================================ */
$notif_check = $conn->prepare("SELECT notifications_enabled FROM student WHERE student_id = ? LIMIT 1");
$notif_check->bind_param("i", $student_id);
$notif_check->execute();
$notif_result = $notif_check->get_result()->fetch_assoc();

// If notifications are disabled (0), clear existing missing notifs and exit
if (isset($notif_result['notifications_enabled']) && (int)$notif_result['notifications_enabled'] === 0) {
    $clear = $conn->prepare("DELETE FROM notification WHERE student_id = ? AND section = 'missing'");
    $clear->bind_param("i", $student_id);
    $clear->execute();
    return; // Don't generate any missing notifications
}

/* ============================================================
   MISSING NOTIFICATIONS GENERATOR
   Runs checks and inserts 'missing' section notifications
   ============================================================ */

// Clear old missing notifications first (we regenerate them fresh)
$clear = $conn->prepare("DELETE FROM notification WHERE student_id = ? AND section = 'missing'");
$clear->bind_param("i", $student_id);
$clear->execute();

$now = date('Y-m-d H:i:s');
$oneWeekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
$threeDaysAgo = date('Y-m-d H:i:s', strtotime('-3 days'));

/* -----------------------------------------------------------
   CHECK 1: Subjects with NO reading progress (FIXED)
   Only notifies if subject exists, has a name, and has content
   ----------------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT ss.student_id, ss.subject_id, ss.source_type,
           COALESCE(s.subject_name, n.title) as subject_name,
           COALESCE(s.subject_image, n.subject_image) as subject_image
    FROM student_subjects ss
    LEFT JOIN subjects s ON ss.subject_id = s.subject_id AND ss.source_type = 'subjects'
    LEFT JOIN notes n ON ss.subject_id = n.note_id AND ss.source_type = 'notes' AND n.type = 'subject'
    WHERE ss.student_id = ?
    AND ss.subject_id NOT IN (
        SELECT subject_id FROM reading_progress WHERE student_id = ?
    )
    /* FIX: Only include subjects that actually exist and have content */
    AND (
        (ss.source_type = 'subjects' AND s.subject_id IS NOT NULL AND s.subject_name IS NOT NULL AND s.subject_name != '' AND s.content IS NOT NULL AND s.content != '' AND s.content != '[]')
        OR
        (ss.source_type = 'notes' AND n.note_id IS NOT NULL AND n.title IS NOT NULL AND n.title != '' AND n.content IS NOT NULL AND n.content != '' AND n.content != '[]')
    )
");
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

$insert = $conn->prepare("
    INSERT INTO notification 
    (student_id, subject_id, section, type, title, message, status, date_sent, action_url)
    VALUES (?, ?, 'missing', 'no_progress', ?, ?, 'unread', ?, ?)
");

while ($row = $result->fetch_assoc()) {
    $title = "Start Learning!";
    $message = "You haven't started '" . ($row["subject_name"] ?? "Untitled") . "' yet. Tap to begin.";
    $actionUrl = 'subject.php?id=' . $row['subject_id'] . '&type=' . $row['source_type'];
    $insert->bind_param("iissss", 
        $student_id, 
        $row['subject_id'],
        $title,
        $message,
        $now,
        $actionUrl
    );
    $insert->execute();
}

/* -----------------------------------------------------------
   CHECK 2: Subjects with reading progress but few entries
   (simplified - just checks if progress count is low)
   ----------------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT ss.student_id, ss.subject_id, ss.source_type,
           COALESCE(s.subject_name, n.title) as subject_name,
           COUNT(rp.subject_id) as read_count
    FROM student_subjects ss
    LEFT JOIN subjects s ON ss.subject_id = s.subject_id AND ss.source_type = 'subjects'
    LEFT JOIN notes n ON ss.subject_id = n.note_id AND ss.source_type = 'notes' AND n.type = 'subject'
    LEFT JOIN reading_progress rp ON ss.student_id = rp.student_id 
        AND ss.subject_id = rp.subject_id
    WHERE ss.student_id = ?
    GROUP BY ss.student_id, ss.subject_id, ss.source_type
    HAVING read_count > 0 AND read_count < 3
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $title = "Keep Going!";
    $message = "You've only read '" . ($row["subject_name"] ?? "Untitled") . "' " . $row['read_count'] . " time(s). Keep it up!";
    $actionUrl = 'subject.php?id=' . $row['subject_id'] . '&type=' . $row['source_type'];
    $insert->bind_param("iissss", 
        $student_id, 
        $row['subject_id'],
        $title,
        $message,
        $now,
        $actionUrl
    );
    $insert->execute();
}

/* -----------------------------------------------------------
   CHECK 3: Subjects with quizzes but no quiz attempt
   ----------------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT DISTINCT ss.student_id, ss.subject_id, ss.source_type,
           COALESCE(s.subject_name, n.title) as subject_name
    FROM student_subjects ss
    LEFT JOIN subjects s ON ss.subject_id = s.subject_id AND ss.source_type = 'subjects'
    LEFT JOIN notes n ON ss.subject_id = n.note_id AND ss.source_type = 'notes' AND n.type = 'subject'
    INNER JOIN quiz_questions qq ON ss.subject_id = qq.quiz_id
    LEFT JOIN quiz_results qr ON ss.student_id = qr.student_id 
        AND ss.subject_id = qr.subject_id
    WHERE ss.student_id = ?
    AND qr.result_id IS NULL
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $title = "Quiz Ready!";
    $message = "'" . ($row["subject_name"] ?? "Untitled") . "' has flashcards waiting. Test yourself?";
    $actionUrl = 'quiz.php?id=' . $row['subject_id'] . '&type=' . $row['source_type'];
    $insert->bind_param("iissss", 
        $student_id, 
        $row['subject_id'],
        $title,
        $message,
        $now,
        $actionUrl
    );
    $insert->execute();
}

/* -----------------------------------------------------------
   CHECK 4: Low quiz score (< 60%) - needs more practice
   ----------------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT qr.student_id, qr.subject_id, ss.source_type,
           COALESCE(s.subject_name, n.title) as subject_name,
           qr.score_percent
    FROM quiz_results qr
    LEFT JOIN subjects s ON qr.subject_id = s.subject_id
    LEFT JOIN notes n ON qr.subject_id = n.note_id AND n.type = 'subject'
    INNER JOIN student_subjects ss ON qr.student_id = ss.student_id AND qr.subject_id = ss.subject_id
    WHERE qr.student_id = ?
    AND qr.score_percent < 60
    ORDER BY qr.date_taken DESC
    LIMIT 10
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $title = "Keep Practicing!";
    $message = "You scored " . $row["score_percent"] . "% on '" . ($row["subject_name"] ?? "Untitled") . "'. Try again to improve!";
    $actionUrl = 'quiz.php?id=' . $row['subject_id'] . '&type=' . $row['source_type'];
    $insert->bind_param("iissss", 
        $student_id, 
        $row['subject_id'],
        $title,
        $message,
        $now,
        $actionUrl
    );
    $insert->execute();
}

/* -----------------------------------------------------------
   CHECK 5: Subject has content but no flashcards made
   (only for notes-type subjects)
   ----------------------------------------------------------- */
$stmt = $conn->prepare("
    SELECT ss.student_id, ss.subject_id, ss.source_type,
           n.title as subject_name
    FROM student_subjects ss
    INNER JOIN notes n ON ss.subject_id = n.note_id AND ss.source_type = 'notes'
    LEFT JOIN quiz_questions qq ON ss.subject_id = qq.quiz_id
    WHERE ss.student_id = ?
    AND n.content IS NOT NULL 
    AND n.content != ''
    AND n.content != '[]'
    AND qq.question_id IS NULL
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $title = "Make Flashcards!";
    $message = "'" . ($row["subject_name"] ?? "Untitled") . "' has notes but no flashcards. Create some to study smarter.";
    $actionUrl = 'readflashcards.php?note_id=' . $row['subject_id'];
    $insert->bind_param("iissss", 
        $student_id, 
        $row['subject_id'],
        $title,
        $message,
        $now,
        $actionUrl
    );
    $insert->execute();
}

?>