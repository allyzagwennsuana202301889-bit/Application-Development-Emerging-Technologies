<?php
session_start();
include 'database.php';

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$student_id = $_SESSION['student_id'] ?? 0;
if (!$student_id) { 
    echo json_encode(['error' => 'Not logged in']); 
    exit; 
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'mark_read') {
    $subject_id  = (int)($_POST['subject_id'] ?? 0);
    $source_type = in_array($_POST['type'] ?? '', ['subjects', 'notes']) ? $_POST['type'] : 'subjects';
    $lesson_index = (int)($_POST['lesson_index'] ?? 0);

    $stmt = $conn->prepare("
        INSERT INTO reading_progress (student_id, subject_id, source_type, lesson_index, completed)
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE completed = 1, updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param("iisi", $student_id, $subject_id, $source_type, $lesson_index);
    $stmt->execute();

    addPoints($conn, $student_id, 10);
    recalculateProgress($conn, $student_id, $subject_id, $source_type);

    echo json_encode(['success' => true]);
}

elseif ($action === 'save_quiz') {
    try {
        $subject_id  = (int)($_POST['subject_id'] ?? 0);
        $source_type = in_array($_POST['type'] ?? '', ['subjects', 'notes']) ? $_POST['type'] : 'subjects';
        $correct     = (int)($_POST['correct']  ?? 0);
        $total       = (int)($_POST['total']    ?? 0);
        $percent     = (int)($_POST['percent']  ?? 0);
        $answered_ids = $_POST['answered_ids'] ?? '';
        $wrong_ids = $_POST['wrong_ids'] ?? '';

        if (!$subject_id) {
            echo json_encode(['success' => false, 'error' => 'missing subject_id']);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO quiz_results
                (student_id, subject_id, source_type, correct_answers, total_questions, score_percent, answered_question_ids, wrong_question_ids, date_taken)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->bind_param("iisiiiss", $student_id, $subject_id, $source_type, $correct, $total, $percent, $answered_ids, $wrong_ids);
        $stmt->execute();

        $quiz_points = ($correct * 20) + 50;
        addPoints($conn, $student_id, $quiz_points);
        recalculateProgress($conn, $student_id, $subject_id, $source_type);

        echo json_encode(['success' => true, 'points_earned' => $quiz_points, 'percent' => $percent]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

elseif ($action === 'get_progress') {
    $subject_id  = (int)($_GET['subject_id'] ?? 0);
    $source_type = in_array($_GET['type'] ?? '', ['subjects', 'notes']) ? $_GET['type'] : 'subjects';

    if (!$subject_id) {
        echo json_encode(['success' => false, 'percent' => 0, 'reading_percent' => 0, 'quiz_percent' => 0]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT overall_percent, reading_percent, quiz_percent
        FROM subject_progress
        WHERE student_id = ? AND subject_id = ? AND source_type = ?
    ");
    $stmt->bind_param("iis", $student_id, $subject_id, $source_type);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $quiz_updated = false;
    $stmt = $conn->prepare("SELECT COUNT(*) as q_count FROM quiz_questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $current_quiz_total = (int)($stmt->get_result()->fetch_assoc()['q_count'] ?? 0);

    $stmt = $conn->prepare("
        SELECT total_questions, wrong_question_ids FROM quiz_results
        WHERE student_id = ? AND subject_id = ? AND source_type = ?
        ORDER BY date_taken DESC, result_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("iis", $student_id, $subject_id, $source_type);
    $stmt->execute();
    $last_result = $stmt->get_result()->fetch_assoc();
    $last_total = (int)($last_result['total_questions'] ?? 0);
    $has_wrong = !empty($last_result['wrong_question_ids']);

    if ($current_quiz_total > $last_total && $last_total > 0) {
        $quiz_updated = true;
    }

    echo json_encode([
        'success'         => true,
        'percent'         => $row['overall_percent']  ?? 0,
        'reading_percent' => $row['reading_percent']  ?? 0,
        'quiz_percent'    => $row['quiz_percent']     ?? 0,
        'quiz_updated'    => $quiz_updated,
        'current_quiz_total' => $current_quiz_total,
        'last_quiz_total'    => $last_total,
        'has_wrong_answers'  => $has_wrong,
    ]);
}

elseif ($action === 'add_points') {
    $points = (int)($_POST['points'] ?? 0);
    addPoints($conn, $student_id, $points);
    echo json_encode(['success' => true]);
}

elseif ($action === 'get_leaderboard') {
    $stmt = $conn->prepare("
        SELECT st.student_id, st.name, st.profile_image, ss.total_points, ss.quizzes_completed, ss.lessons_read
        FROM student_stats ss
        JOIN student st ON ss.student_id = st.student_id
        ORDER BY ss.total_points DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $leaderboard = [];
    while ($row = $result->fetch_assoc()) {
        $leaderboard[] = $row;
    }
    echo json_encode(['success' => true, 'leaderboard' => $leaderboard]);
}

elseif ($action === 'get_stats') {
    $stmt = $conn->prepare("SELECT * FROM student_stats WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    if (!$stats) {
        $stats = [
            'student_id'        => $student_id,
            'total_points'      => 0,
            'quizzes_completed' => 0,
            'lessons_read'      => 0,
            'streak_days'       => 0
        ];
    }

    echo json_encode(['success' => true, 'stats' => $stats]);
}

function addPoints($conn, $student_id, $points) {
    $stmt = $conn->prepare("
        INSERT INTO student_stats (student_id, total_points, last_active)
        VALUES (?, ?, CURDATE())
        ON DUPLICATE KEY UPDATE
            total_points = total_points + VALUES(total_points),
            last_active  = CURDATE()
    ");
    $stmt->bind_param("ii", $student_id, $points);
    $stmt->execute();
}

function recalculateProgress($conn, $student_id, $subject_id, $source_type) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as read_count FROM reading_progress
        WHERE student_id = ? AND subject_id = ? AND source_type = ? AND completed = 1
    ");
    $stmt->bind_param("iis", $student_id, $subject_id, $source_type);
    $stmt->execute();
    $read_count = $stmt->get_result()->fetch_assoc()['read_count'] ?? 0;

    if ($source_type === 'notes') {
        $stmt = $conn->prepare("SELECT content FROM notes WHERE note_id = ?");
    } else {
        $stmt = $conn->prepare("SELECT description FROM subjects WHERE subject_id = ?");
    }
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $desc = $stmt->get_result()->fetch_assoc();

    $raw = '';
    if ($desc !== null) {
        $raw = $desc['content'] ?? $desc['description'] ?? '';
    }
    $lessons = json_decode($raw ?: '[]', true);
    $total_lessons = (is_array($lessons) && count($lessons) > 0) ? count($lessons) : 1;

    $reading_percent = $total_lessons > 0 ? round(($read_count / $total_lessons) * 100) : 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as q_count FROM quiz_questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $current_quiz_total = (int)($stmt->get_result()->fetch_assoc()['q_count'] ?? 0);
    $has_quiz = $current_quiz_total > 0;

    if (!$has_quiz) {
        $overall = $reading_percent;
        $quiz_percent = 0;
    } else {
        $stmt = $conn->prepare("
            SELECT score_percent, total_questions, correct_answers, answered_question_ids, wrong_question_ids
            FROM quiz_results
            WHERE student_id = ? AND subject_id = ? AND source_type = ?
            ORDER BY date_taken DESC, result_id DESC
            LIMIT 1
        ");
        $stmt->bind_param("iis", $student_id, $subject_id, $source_type);
        $stmt->execute();
        $last_result = $stmt->get_result()->fetch_assoc();

        $last_total = (int)($last_result['total_questions'] ?? 0);
        $last_correct = (int)($last_result['correct_answers'] ?? 0);
        $last_percent = (int)($last_result['score_percent'] ?? 0);

        if ($current_quiz_total > $last_total && $last_total > 0) {
            $quiz_percent = round(($last_correct / $current_quiz_total) * 100);
        } else {
            $quiz_percent = $last_percent;
        }

        $overall = round(($reading_percent * 0.4) + ($quiz_percent * 0.6));
    }

    $stmt = $conn->prepare("
        INSERT INTO subject_progress
            (student_id, subject_id, source_type, reading_percent, quiz_percent, overall_percent)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            reading_percent = VALUES(reading_percent),
            quiz_percent    = VALUES(quiz_percent),
            overall_percent = VALUES(overall_percent)
    ");
    $stmt->bind_param("iisiii", $student_id, $subject_id, $source_type,
                                $reading_percent, $quiz_percent, $overall);
    $stmt->execute();
}
?>