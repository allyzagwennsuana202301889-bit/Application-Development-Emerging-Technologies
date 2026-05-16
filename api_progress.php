<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
if (!$student_id) { echo json_encode(['error' => 'Not logged in']); exit; }

$action = $_POST['action'] ?? '';

if ($action === 'mark_read') {
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $source_type = in_array($_POST['type'] ?? '', ['subjects', 'notes']) ? $_POST['type'] : 'subjects';
    $lesson_index = (int)($_POST['lesson_index'] ?? 0);
    
    $stmt = $conn->prepare("
        INSERT INTO reading_progress (student_id, subject_id, source_type, lesson_index, completed)
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE completed = 1, updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param("iisi", $student_id, $subject_id, $source_type, $lesson_index);
    $stmt->execute();
    
    // Add points for reading
    addPoints($conn, $student_id, 10);
    
    // Recalculate overall progress
    recalculateProgress($conn, $student_id, $subject_id, $source_type);
    
    echo json_encode(['success' => true]);
}

elseif ($action === 'save_quiz') {
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $source_type = in_array($_POST['type'] ?? '', ['subjects', 'notes']) ? $_POST['type'] : 'subjects';
    $correct = (int)($_POST['correct'] ?? 0);
    $total = (int)($_POST['total'] ?? 0);
    $percent = (int)($_POST['percent'] ?? 0);
    
    $stmt = $conn->prepare("
        INSERT INTO quiz_results (student_id, subject_id, source_type, correct_answers, total_questions, score_percent)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            correct_answers = VALUES(correct_answers),
            total_questions = VALUES(total_questions),
            score_percent = VALUES(score_percent),
            date_taken = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param("iisiii", $student_id, $subject_id, $source_type, $correct, $total, $percent);
    $stmt->execute();
    
    // Add points for quiz completion
    $quiz_points = ($correct * 20) + 50; // 20 per correct + 50 bonus
    addPoints($conn, $student_id, $quiz_points);
    
    // Recalculate overall progress
    recalculateProgress($conn, $student_id, $subject_id, $source_type);
    
    echo json_encode(['success' => true, 'points_earned' => $quiz_points]);
}

elseif ($action === 'add_points') {
    $points = (int)($_POST['points'] ?? 0);
    addPoints($conn, $student_id, $points);
    echo json_encode(['success' => true]);
}

elseif ($action === 'get_leaderboard') {
    $stmt = $conn->prepare("
        SELECT 
            st.student_id,
            st.name,
            st.profile_image,
            ss.total_points,
            ss.quizzes_completed,
            ss.lessons_read
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
    $stmt = $conn->prepare("
        SELECT * FROM student_stats WHERE student_id = ?
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    if (!$stats) {
        $stats = [
            'student_id' => $student_id,
            'total_points' => 0,
            'quizzes_completed' => 0,
            'lessons_read' => 0,
            'streak_days' => 0
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
            last_active = CURDATE()
    ");
    $stmt->bind_param("ii", $student_id, $points);
    $stmt->execute();
}

function recalculateProgress($conn, $student_id, $subject_id, $source_type) {
    // Get reading progress
    $stmt = $conn->prepare("
        SELECT COUNT(*) as read_count FROM reading_progress 
        WHERE student_id = ? AND subject_id = ? AND source_type = ? AND completed = 1
    ");
    $stmt->bind_param("iis", $student_id, $subject_id, $source_type);
    $stmt->execute();
    $read_count = $stmt->get_result()->fetch_assoc()['read_count'] ?? 0;
    
    // Get total lessons
    if ($source_type === 'notes') {
        $stmt = $conn->prepare("SELECT content FROM notes WHERE note_id = ?");
    } else {
        $stmt = $conn->prepare("SELECT description FROM subjects WHERE subject_id = ?");
    }
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $desc = $stmt->get_result()->fetch_assoc();
    $lessons = json_decode($desc['content'] ?? $desc['description'] ?? '[]', true);
    $total_lessons = is_array($lessons) ? count($lessons) : 1;
    
    $reading_percent = $total_lessons > 0 ? round(($read_count / $total_lessons) * 100) : 0;
    
    // Get quiz progress
    $stmt = $conn->prepare("
        SELECT score_percent FROM quiz_results 
        WHERE student_id = ? AND subject_id = ? AND source_type = ?
    ");
    $stmt->bind_param("iis", $student_id, $subject_id, $source_type);
    $stmt->execute();
    $quiz = $stmt->get_result()->fetch_assoc();
    $quiz_percent = $quiz['score_percent'] ?? 0;
    
    // Calculate overall: 40% reading + 60% quiz
    $overall = round(($reading_percent * 0.4) + ($quiz_percent * 0.6));
    
    // Save/update cached progress
    $stmt = $conn->prepare("
        INSERT INTO subject_progress (student_id, subject_id, source_type, reading_percent, quiz_percent, overall_percent)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            reading_percent = VALUES(reading_percent),
            quiz_percent = VALUES(quiz_percent),
            overall_percent = VALUES(overall_percent)
    ");
    $stmt->bind_param("iisiii", $student_id, $subject_id, $source_type, $reading_percent, $quiz_percent, $overall);
    $stmt->execute();
}
?>