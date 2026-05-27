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
        $answered_ids   = $_POST['answered_ids']   ?? '';
        $wrong_ids      = $_POST['wrong_ids']      ?? '';
        $questions_hash = $_POST['questions_hash'] ?? '';

        if (!$subject_id) {
            echo json_encode(['success' => false, 'error' => 'missing subject_id']);
            exit;
        }

        // Build per-question snapshot for future edit detection
        $stmt = $conn->prepare("
            SELECT question_id, question, correct_answer 
            FROM quiz_questions 
            WHERE quiz_id = ? 
            ORDER BY question_order, question_id
        ");
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $qrows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $question_snapshot = [];
        foreach ($qrows as $qr) {
            $question_snapshot[] = [
                'id' => $qr['question_id'],
                'q' => $qr['question'],
                'a' => $qr['correct_answer']
            ];
        }
        $snapshot_json = json_encode($question_snapshot);

        // Check if question_snapshot column exists
        $colCheck = $conn->query("SHOW COLUMNS FROM quiz_results LIKE 'question_snapshot'");
        $hasSnapshotCol = $colCheck->num_rows > 0;

        if ($hasSnapshotCol) {
            $stmt = $conn->prepare("
                INSERT INTO quiz_results
                    (student_id, subject_id, source_type, correct_answers, total_questions, score_percent, answered_question_ids, wrong_question_ids, questions_hash, question_snapshot, date_taken)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->bind_param("iisiiissss", $student_id, $subject_id, $source_type, $correct, $total, $percent, $answered_ids, $wrong_ids, $questions_hash, $snapshot_json);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO quiz_results
                    (student_id, subject_id, source_type, correct_answers, total_questions, score_percent, answered_question_ids, wrong_question_ids, questions_hash, date_taken)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->bind_param("iisiiisss", $student_id, $subject_id, $source_type, $correct, $total, $percent, $answered_ids, $wrong_ids, $questions_hash);
        }
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

    // Check if question_snapshot column exists
    $colCheck = $conn->query("SHOW COLUMNS FROM quiz_results LIKE 'question_snapshot'");
    $hasSnapshotCol = $colCheck->num_rows > 0;

    $snapshotSelect = $hasSnapshotCol ? ', question_snapshot' : '';
    $stmt = $conn->prepare("
        SELECT total_questions, wrong_question_ids, questions_hash $snapshotSelect
        FROM quiz_results
        WHERE student_id = ? AND subject_id = ? AND source_type = ?
        ORDER BY date_taken DESC, result_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("iis", $student_id, $subject_id, $source_type);
    $stmt->execute();
    $last_result = $stmt->get_result()->fetch_assoc();
    $last_total = (int)($last_result['total_questions'] ?? 0);
    $has_wrong = !empty($last_result['wrong_question_ids']);
    $saved_hash = $last_result['questions_hash'] ?? '';
    $saved_snapshot = $hasSnapshotCol ? ($last_result['question_snapshot'] ?? '') : '';

    // Build current hash of all questions for this quiz
    $stmt = $conn->prepare("SELECT question_id, question, correct_answer FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order, question_id");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $qrows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $hash_input = '';
    foreach ($qrows as $qr) {
        $hash_input .= $qr['question_id'] . '|' . $qr['question'] . '|' . $qr['correct_answer'] . '||';
    }
    $current_hash = md5($hash_input);

    // Detect specifically which questions had their answers edited
    $edited_question_ids = [];
    if ($last_total > 0 && $saved_hash !== '' && $saved_hash !== $current_hash && $saved_snapshot !== '') {
        $old_snapshot = json_decode($saved_snapshot, true);
        if (is_array($old_snapshot)) {
            $old_map = [];
            foreach ($old_snapshot as $item) {
                $old_map[$item['id']] = ['q' => $item['q'], 'a' => $item['a']];
            }
            foreach ($qrows as $qr) {
                $qid = $qr['question_id'];
                if (isset($old_map[$qid])) {
                    // Question existed before - check if answer or text changed
                    if ($old_map[$qid]['a'] !== $qr['correct_answer'] || $old_map[$qid]['q'] !== $qr['question']) {
                        $edited_question_ids[] = $qid;
                    }
                }
            }
        }
    }

    // Edited = there's a past attempt, and the hash doesn't match
    $quiz_edited = $last_total > 0 && ($saved_hash === '' || $saved_hash !== $current_hash);

    // Detect new questions
    $new_question_ids = [];
    if ($saved_snapshot !== '') {
        $old_snapshot = json_decode($saved_snapshot, true);
        $old_ids = [];
        if (is_array($old_snapshot)) {
            foreach ($old_snapshot as $item) {
                $old_ids[] = $item['id'];
            }
        }
        foreach ($qrows as $qr) {
            if (!in_array($qr['question_id'], $old_ids)) {
                $new_question_ids[] = $qr['question_id'];
            }
        }
    } else if ($last_total > 0 && $current_quiz_total > $last_total) {
        // Fallback: no snapshot but count increased
        $stmt = $conn->prepare("
            SELECT answered_question_ids FROM quiz_results
            WHERE student_id = ? AND subject_id = ? AND source_type = ?
            ORDER BY date_taken DESC LIMIT 1
        ");
        $stmt->bind_param("iis", $student_id, $subject_id, $source_type);
        $stmt->execute();
        $ans_row = $stmt->get_result()->fetch_assoc();
        $answered_ids = [];
        if (!empty($ans_row['answered_question_ids'])) {
            $answered_ids = array_map('intval', explode(',', $ans_row['answered_question_ids']));
        }
        foreach ($qrows as $qr) {
            if (!in_array($qr['question_id'], $answered_ids)) {
                $new_question_ids[] = $qr['question_id'];
            }
        }
    }

    if ($current_quiz_total > $last_total && $last_total > 0) {
        $quiz_updated = true;
    }

    // ═══════════════════════════════════════════════
    // LIVE RECALCULATION of effective quiz stats
    // (handles edits/additions without requiring a new save)
    // ═══════════════════════════════════════════════
    $has_quiz = $current_quiz_total > 0;
    $effective_correct = 0;
    $effective_total   = $current_quiz_total;
    $quiz_percent_live = (int)($row['quiz_percent'] ?? 0);

    if ($has_quiz && $last_result && $last_total > 0) {
        $existing_ids = array_column($qrows, 'question_id');
        $existing_ids_set = array_flip($existing_ids);

        $prev_answered_ids = [];
        if (!empty($last_result['answered_question_ids'])) {
            $prev_answered_ids = array_filter(
                array_map('intval', explode(',', $last_result['answered_question_ids'])),
                fn($id) => $id > 0 && isset($existing_ids_set[$id])
            );
        }
        $prev_wrong_ids = [];
        if (!empty($last_result['wrong_question_ids'])) {
            $prev_wrong_ids = array_filter(
                array_map('intval', explode(',', $last_result['wrong_question_ids'])),
                fn($id) => $id > 0 && isset($existing_ids_set[$id])
            );
        }

        $prev_correct_ids = array_values(array_diff($prev_answered_ids, $prev_wrong_ids));
        $edited_and_prev_correct = array_values(array_intersect($edited_question_ids, $prev_correct_ids));
        $unverified_ids = array_values(array_unique(array_merge($new_question_ids, $edited_and_prev_correct)));

        if (!empty($unverified_ids)) {
            $effective_correct_ids = array_values(array_diff($prev_correct_ids, $unverified_ids));
            $effective_correct = count($effective_correct_ids);
            $quiz_percent_live = $current_quiz_total > 0 ? round(($effective_correct / $current_quiz_total) * 100) : 0;
        } else {
            $effective_correct = count($prev_correct_ids);
            $quiz_percent_live = (int)($last_result['score_percent'] ?? 0);
        }
    }

    echo json_encode([
        'success'              => true,
        'percent'              => $row['overall_percent']  ?? 0,
        'reading_percent'      => $row['reading_percent']  ?? 0,
        'quiz_percent'         => $quiz_percent_live,
        'quiz_updated'         => $quiz_updated,
        'quiz_edited'          => $quiz_edited,
        'edited_question_ids'  => $edited_question_ids,
        'new_question_ids'     => $new_question_ids,
        'wrong_question_ids'   => $last_result['wrong_question_ids'] ?? '',
        'current_quiz_total'   => $current_quiz_total,
        'last_quiz_total'      => $last_total,
        'has_wrong_answers'    => $has_wrong,
        'current_hash'         => $current_hash,
        'effective_correct'    => $effective_correct,
        'effective_total'      => $effective_total,
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
    // --- READING PROGRESS ---
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

    // --- QUIZ PROGRESS ---
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
            SELECT score_percent, total_questions, correct_answers, answered_question_ids, wrong_question_ids, questions_hash, question_snapshot
            FROM quiz_results
            WHERE student_id = ? AND subject_id = ? AND source_type = ?
            ORDER BY date_taken DESC, result_id DESC
            LIMIT 1
        ");
        $stmt->bind_param("iis", $student_id, $subject_id, $source_type);
        $stmt->execute();
        $last_result = $stmt->get_result()->fetch_assoc();

        $last_total = (int)($last_result['total_questions'] ?? 0);
        $last_percent = (int)($last_result['score_percent'] ?? 0);
        $saved_hash = $last_result['questions_hash'] ?? '';

        // Build current hash of all questions (also used for edit detection below)
        $stmt = $conn->prepare("SELECT question_id, question, correct_answer FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order, question_id");
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $qrows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $hash_input = '';
        foreach ($qrows as $qr) {
            $hash_input .= $qr['question_id'] . '|' . $qr['question'] . '|' . $qr['correct_answer'] . '||';
        }
        $current_hash = md5($hash_input);

        // Parse answered/wrong IDs from last result — filter to questions that still exist
        $existing_ids = array_column($qrows, 'question_id');
        $existing_ids_set = array_flip($existing_ids);

        $prev_answered_ids = [];
        if (!empty($last_result['answered_question_ids'])) {
            $prev_answered_ids = array_filter(
                array_map('intval', explode(',', $last_result['answered_question_ids'])),
                fn($id) => $id > 0 && isset($existing_ids_set[$id])
            );
        }
        $prev_wrong_ids = [];
        if (!empty($last_result['wrong_question_ids'])) {
            $prev_wrong_ids = array_filter(
                array_map('intval', explode(',', $last_result['wrong_question_ids'])),
                fn($id) => $id > 0 && isset($existing_ids_set[$id])
            );
        }

        // Detect quiz state changes
        $new_questions_added = ($current_quiz_total > $last_total && $last_total > 0);
        $quiz_edited = ($last_total > 0 && $saved_hash !== '' && $saved_hash !== $current_hash);

        // Identify edited question IDs using snapshot comparison
        $edited_question_ids_recalc = [];
        if ($quiz_edited) {
            $colCheck = $conn->query("SHOW COLUMNS FROM quiz_results LIKE 'question_snapshot'");
            $hasSnapshotCol = $colCheck->num_rows > 0;

            if ($hasSnapshotCol) {
                $stmt = $conn->prepare("SELECT question_snapshot FROM quiz_results WHERE student_id = ? AND subject_id = ? AND source_type = ? ORDER BY date_taken DESC LIMIT 1");
                $stmt->bind_param("iis", $student_id, $subject_id, $source_type);
                $stmt->execute();
                $snap = $stmt->get_result()->fetch_assoc();
                $saved_snapshot = $snap['question_snapshot'] ?? '';

                if ($saved_snapshot !== '') {
                    $old_snapshot = json_decode($saved_snapshot, true);
                    if (is_array($old_snapshot)) {
                        $old_map = [];
                        foreach ($old_snapshot as $item) {
                            $old_map[$item['id']] = ['q' => $item['q'], 'a' => $item['a']];
                        }
                        foreach ($qrows as $qr) {
                            $qid = $qr['question_id'];
                            if (isset($old_map[$qid])) {
                                if ($old_map[$qid]['a'] !== $qr['correct_answer'] || $old_map[$qid]['q'] !== $qr['question']) {
                                    $edited_question_ids_recalc[] = $qid;
                                }
                            }
                        }
                    }
                }
            }

            // Fallback: no snapshot — treat all previously-correct answered IDs as potentially edited
            if (empty($edited_question_ids_recalc)) {
                $prev_correct_ids = array_values(array_diff($prev_answered_ids, $prev_wrong_ids));
                $edited_question_ids_recalc = $prev_correct_ids;
            }
        }

        // Identify new question IDs (not in prev_answered_ids)
        $new_question_ids_recalc = [];
        if ($new_questions_added) {
            foreach ($existing_ids as $qid) {
                if (!in_array($qid, $prev_answered_ids)) {
                    $new_question_ids_recalc[] = $qid;
                }
            }
        }

        // IDs that must be re-answered: edited ones that were previously answered correctly,
        // plus new questions never answered before
        $prev_correct_ids = array_values(array_diff($prev_answered_ids, $prev_wrong_ids));
        $edited_and_prev_correct = array_values(array_intersect($edited_question_ids_recalc, $prev_correct_ids));
        $unverified_ids = array_values(array_unique(array_merge($new_question_ids_recalc, $edited_and_prev_correct)));

        // Recalculate percentage: treat unverified IDs as not-yet-correct
        if (!empty($unverified_ids) && $last_total > 0) {
            // Remove unverified from the "correctly answered" pool
            $effective_correct_ids = array_values(array_diff($prev_correct_ids, $unverified_ids));
            $effective_correct = count($effective_correct_ids);
            $quiz_percent = round(($effective_correct / $current_quiz_total) * 100);
        } else {
            $quiz_percent = $last_percent;
        }

        $overall = round(($reading_percent * 0.4) + ($quiz_percent * 0.6));
    }

    // Save to subject_progress
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