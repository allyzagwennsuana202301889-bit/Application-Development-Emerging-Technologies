<?php
session_start();
include 'database.php';

$id   = isset($_GET['id'])   ? (int)$_GET['id']   : 0;
$type = $_GET['type'] ?? 'subjects';

if (!$id) {
    exit("Quiz not found.");
}

/* =========================
   FETCH SUBJECT / NOTE
========================= */
if ($type === 'notes') {
    $stmt = $conn->prepare("
        SELECT n.note_id AS subject_id, n.title AS subject_name, n.subject_image
        FROM notes n WHERE n.note_id = ? LIMIT 1
    ");
} else {
    $stmt = $conn->prepare("
        SELECT s.subject_id, s.subject_name, s.subject_image
        FROM subjects s WHERE s.subject_id = ? LIMIT 1
    ");
}
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) exit("Quiz not found.");

$subject_name = $row['subject_name'] ?? 'Untitled';
$subject_image = $row['subject_image'] ?? '';

/* =========================
   FETCH ALL QUIZ QUESTIONS
========================= */
$allQuestions = [];
$stmt = $conn->prepare("
    SELECT * FROM quiz_questions
    WHERE quiz_id = ?
    ORDER BY question_order, question_id
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

while ($q = $result->fetch_assoc()) {
    $choices = [];
    $correct_answer = $q['correct_answer'] ?? '';

    if (!empty($q['choices']) && $q['choices'] !== 'NULL' && $q['choices'] !== '[]') {
        $decoded = json_decode($q['choices'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ($decoded as $choice) {
                if (is_array($choice) && isset($choice['text'])) {
                    $choices[] = [
                        'text' => $choice['text'],
                        'correct' => !empty($choice['correct'])
                    ];
                }
            }
        }
    }

    $allQuestions[] = [
        'question_id'    => $q['question_id'],
        'question_text'  => $q['question'],
        'question_image' => $q['question_image'] ?? '',
        'question_type'  => $q['question_type'],
        'choices'        => $choices,
        'correct_answer' => $correct_answer
    ];
}

$totalAll = count($allQuestions);

/* ════════════════════════════════════════════
   FETCH LAST QUIZ ATTEMPT
════════════════════════════════════════════ */
$last_attempt = null;
$student_id = $_SESSION['student_id'] ?? 0;

if ($student_id) {
    $stmt = $conn->prepare("
        SELECT correct_answers, total_questions, score_percent, answered_question_ids, wrong_question_ids, questions_hash, date_taken
        FROM quiz_results
        WHERE student_id = ? AND subject_id = ? AND source_type = ?
        ORDER BY date_taken DESC, result_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("iis", $student_id, $id, $type);
    $stmt->execute();
    $last_attempt = $stmt->get_result()->fetch_assoc();
}

/* ════════════════════════════════════════════
   DETECT STATE FOR RETAKE
════════════════════════════════════════════ */
$wrongIds = [];
$answeredIds = [];
$editedQuestionIds = [];
$newQuestionIds = [];

if ($last_attempt) {
    if (!empty($last_attempt['wrong_question_ids'])) {
        $wrongIds = array_map('intval', explode(',', $last_attempt['wrong_question_ids']));
        $wrongIds = array_values(array_filter($wrongIds));
    }
    if (!empty($last_attempt['answered_question_ids'])) {
        $answeredIds = array_map('intval', explode(',', $last_attempt['answered_question_ids']));
        $answeredIds = array_values(array_filter($answeredIds));
    }

    $saved_hash = $last_attempt['questions_hash'] ?? '';
    $last_total = (int)($last_attempt['total_questions'] ?? 0);

    // Build current hash
    $hashInput = '';
    foreach ($allQuestions as $q) {
        $hashInput .= $q['question_id'] . '|' . $q['question_text'] . '|' . $q['correct_answer'] . '||';
    }
    $questionsHash = md5($hashInput);

    // Detect edited: hash changed and there was a previous attempt
    $quizEdited = ($last_total > 0 && ($saved_hash === '' || $saved_hash !== $questionsHash));

    // Use snapshot to detect SPECIFIC edited questions
    if ($quizEdited) {
        $snapCheck = $conn->query("SHOW COLUMNS FROM quiz_results LIKE 'question_snapshot'");
        if ($snapCheck->num_rows > 0) {
            $snapStmt = $conn->prepare("SELECT question_snapshot FROM quiz_results WHERE student_id = ? AND subject_id = ? AND source_type = ? ORDER BY date_taken DESC LIMIT 1");
            $snapStmt->bind_param("iis", $student_id, $id, $type);
            $snapStmt->execute();
            $snapRow = $snapStmt->get_result()->fetch_assoc();
            $saved_snapshot = $snapRow['question_snapshot'] ?? '';
            
            if ($saved_snapshot !== '') {
                $old_snapshot = json_decode($saved_snapshot, true);
                if (is_array($old_snapshot)) {
                    $old_map = [];
                    foreach ($old_snapshot as $item) {
                        $old_map[$item['id']] = ['q' => $item['q'], 'a' => $item['a']];
                    }
                    foreach ($allQuestions as $q) {
                        $qid = $q['question_id'];
                        if (isset($old_map[$qid])) {
                            if ($old_map[$qid]['a'] !== $q['correct_answer'] || $old_map[$qid]['q'] !== $q['question_text']) {
                                $editedQuestionIds[] = $qid;
                            }
                        }
                    }
                }
            }
        }
        // Fallback: if snapshot didn't identify specific edits, mark all previously-correct as potentially edited
        if (empty($editedQuestionIds)) {
            $previouslyCorrectIds = array_values(array_diff($answeredIds, $wrongIds));
            $editedQuestionIds = $previouslyCorrectIds;
        }
    }

    // Detect new questions: not in answeredIds and total increased
    if (count($allQuestions) > $last_total) {
        foreach ($allQuestions as $q) {
            if (!in_array($q['question_id'], $answeredIds)) {
                $newQuestionIds[] = $q['question_id'];
            }
        }
    }
} else {
    $hashInput = '';
    foreach ($allQuestions as $q) {
        $hashInput .= $q['question_id'] . '|' . $q['question_text'] . '|' . $q['correct_answer'] . '||';
    }
    $questionsHash = md5($hashInput);
}

// Determine retake targets: WRONG gets highest priority, then NEW + EDITED combined
$retakeTargetIds = [];
$retakeMode = 'full';

if (!empty($wrongIds)) {
    $retakeMode = 'wrong';
    $retakeTargetIds = $wrongIds;
} else {
    // Combine new + edited (both need to be retaken when no wrong answers)
    $combined = array_unique(array_merge($newQuestionIds, $editedQuestionIds));
    if (!empty($combined)) {
        $retakeMode = count($newQuestionIds) > 0 && count($editedQuestionIds) > 0 ? 'new+edited' :
                     (count($newQuestionIds) > 0 ? 'new' : 'edited');
        $retakeTargetIds = array_values($combined);
    }
}

$hasRetakeTarget = !empty($retakeTargetIds);

// Build retake set
function buildRetakeSet($targetIds, $allQuestions) {
    if (empty($targetIds)) return [];
    return array_values(array_filter($allQuestions, fn($q) => in_array($q['question_id'], $targetIds)));
}

$retakeQuestions = buildRetakeSet($retakeTargetIds, $allQuestions);

$questions = $allQuestions;
$total = count($questions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quiz - <?= htmlspecialchars($subject_name) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Itim&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .quiz-page {
      position: absolute;
      top: 90px;
      bottom: 100px;
      left: 0;
      right: 0;
      overflow-y: auto;
      padding: 15px;
      z-index: 5;
    }
    .quiz-page::-webkit-scrollbar { display: none; }
    .quiz-question-card {
      background: #fff;
      border-radius: 20px;
      padding: 20px;
      margin-bottom: 15px;
    }
    .quiz-question-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }
    .quiz-subject-tag {
      color: #888;
      font-size: 14px;
      font-style: italic;
      font-family: 'Itim', cursive;
    }
    .quiz-counter {
      color: #888;
      font-size: 14px;
      font-style: italic;
      font-family: 'Itim', cursive;
    }
    .quiz-question-text {
      font-size: 20px;
      color: #333;
      line-height: 1.5;
      font-family: 'Itim', cursive;
    }
    .quiz-answer-card {
      background: #fff;
      border-radius: 20px;
      padding: 20px;
      margin-bottom: 15px;
    }
    .quiz-question-image {
      max-width: 100%;
      max-height: 250px;
      border-radius: 12px;
      margin: 10px auto;
      display: block;
      object-fit: contain;
    }
    .quiz-option-btn {
      display: block;
      width: 100%;
      padding: 15px 20px;
      margin-bottom: 12px;
      border: none;
      border-radius: 16px;
      background: #7EC8FF;
      color: #333;
      font-size: 16px;
      text-align: left;
      cursor: pointer;
      font-family: 'Itim', cursive;
      transition: all 0.2s;
      line-height: 1.4;
    }
    .quiz-option-btn:hover { background: #5DB8FF; }
    .quiz-option-btn.correct { background: #FFAE71 !important; color: white !important; }
    .quiz-option-btn.wrong { background: #f44336 !important; color: white !important; }
    .quiz-option-btn.disabled { opacity: 0.7; pointer-events: none; }
    .quiz-text-input {
      width: 100%;
      padding: 15px 20px;
      border: 2px solid #7EC8FF;
      border-radius: 16px;
      font-size: 16px;
      font-family: 'Itim', cursive;
      outline: none;
      background: #f5f5f5;
    }
    .quiz-text-input:focus { border-color: #3B8BFF; }
    .quiz-text-input.correct { border-color: #FFAE71; background: #e8f5e9; }
    .quiz-text-input.wrong { border-color: #f44336; background: #ffebee; }
    .quiz-dots {
      display: flex;
      justify-content: center;
      gap: 8px;
      margin: 15px 0;
    }
    .quiz-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: rgba(255,255,255,0.4);
    }
    .quiz-dot.active { background: #fff; }
    .quiz-analysis {
      display: none;
      flex-direction: column;
      height: 100%;
    }
    .quiz-score-card {
      background: #fff;
      border-radius: 20px;
      padding: 20px;
      margin-bottom: 15px;
    }
    .quiz-score-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    .quiz-score-top .subject-tag {
      color: #888;
      font-size: 14px;
      font-style: italic;
      font-family: 'Itim', cursive;
    }
    .quiz-score-top .correct-tag {
      color: #888;
      font-size: 14px;
      font-style: italic;
      font-family: 'Itim', cursive;
    }
    .quiz-score-bottom {
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative;
    }
    .quiz-score-title {
      font-size: 36px;
      color: #333;
      font-family: 'Itim', cursive;
      margin: 0;
    }
    .quiz-score-value {
      font-size: 48px;
      color: #000000;
      font-weight: bold;
      font-family: 'Itim', cursive;
      line-height: 1;
      display: flex;
      align-items: flex-start;
      z-index: 2;
    }
    .quiz-score-value span {
      font-size: 20px;
      margin-top: 6px;
    }
    .quiz-beaker {
      position: absolute;
      right: 20px;
      top: 50%;
      transform: translateY(-60%);
      width: 50px;
      height: 60px;
      z-index: 1;
    }
    .quiz-beaker-img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      filter: brightness(0) saturate(100%) invert(75%) sepia(60%) saturate(500%) hue-rotate(340deg) brightness(1.05);
    }
    .quiz-corrections-scroll {
      display: flex;
      gap: 15px;
      overflow-x: auto;
      scroll-snap-type: x mandatory;
      -webkit-overflow-scrolling: touch;
      height: 100%;
      padding: 0 15px;
      align-items: stretch;
      margin: 0 -15px;
    }
    .quiz-corrections-scroll::-webkit-scrollbar { display: none; }
    .quiz-correction-card {
      min-width: 100%;
      max-width: 100%;
      height: 70%;
      background: #fff;
      border-radius: 20px;
      padding: 20px;
      scroll-snap-align: center;
      flex-shrink: 0;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
    }
    .quiz-correction-card .corrections-label {
      color: #888;
      font-size: 16px;
      font-family: 'Itim', cursive;
      margin-bottom: 15px;
      display: block;
    }
    .quiz-correction-card .q-num {
      color: #888;
      font-size: 14px;
      margin-bottom: 10px;
      font-family: 'Itim', cursive;
    }
    .quiz-correction-card .q-text {
      color: #333;
      font-size: 17px;
      margin-bottom: 15px;
      line-height: 1.5;
      font-family: 'Itim', cursive;
    }
    .quiz-correction-card .correct-ans {
      background: #FFE0B2;
      padding: 14px 16px;
      border-radius: 14px;
      color: #333;
      font-size: 15px;
      font-family: 'Itim', cursive;
      line-height: 1.4;
    }
    .correction-image {
      max-width: 100%;
      max-height: 150px;
      border-radius: 12px;
      margin-bottom: 15px;
      display: block;
      object-fit: contain;
    }
    .quiz-hidden { display: none !important; }
    .quiz-empty {
      text-align: center;
      padding: 60px 20px;
      color: #fff;
      font-family: 'Itim', cursive;
      font-size: 18px;
    }
    .quiz-empty a { color: #fff; text-decoration: underline; }
    .retry-disabled {
      opacity: 0.4 !important;
      pointer-events: none !important;
      filter: grayscale(1);
    }
    .retry-wrapper {
      position: relative;
      display: inline-block;
    }
  </style>
</head>
<body>

<div class="container">
  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <img src="back.png" class="back-btn" onclick="goBack()">
  </nav>

  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" onclick="fax()" class="help">
      <img src="back.png" class="back">
    </div>
    <?php
    $student_id = $_SESSION['student_id'] ?? 0;
    $pfp_stmt = $conn->prepare("SELECT profile_image FROM student WHERE student_id = ?");
    $pfp_stmt->bind_param("i", $student_id);
    $pfp_stmt->execute();
    $pfp_result = $pfp_stmt->get_result()->fetch_assoc();
    $profile_image = !empty($pfp_result['profile_image']) ? $pfp_result['profile_image'] : 'acc.png';
    $image_src = $profile_image;
    if (strpos($image_src, 'data:') !== 0) {
        $image_src .= '?t=' . time();
    }
    ?>
    <form id="pfpForm" enctype="multipart/form-data" style="display: contents;">
      <label for="imageInput" style="cursor: pointer; position: relative;">
        <img id="preview" src="<?= htmlspecialchars($image_src) ?>" 
             style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover;"
             onerror="this.src='acc.png'">
      </label>
      <input type="file" id="imageInput" name="profile_image" accept="image/*" hidden onchange="uploadPFP()">
    </form>
    <h3><?php echo $_SESSION['name'] ?? 'Guest'; ?></h3>
    <p><?php echo $_SESSION['email'] ?? ''; ?></p>
    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="analytics.php">Analytics</a>
    <a href="leaderboard.php">Leaderboard</a>
    <a href="settings.php" onclick="sessionStorage.setItem('settingsFrom', window.location.pathname)">Settings</a>
    <a href="logout.php">Log out</a>
  </div>

  <div class="overlay"></div>

  <div class="quiz-page" id="quizPage">
    <div id="quizScreen">
      <?php if ($total === 0): ?>
        <div class="quiz-empty">
          <p>No quiz questions available for this subject.</p>
          <a href="subject.php?id=<?= $id ?>&type=<?= htmlspecialchars($type) ?>">Go Back</a>
        </div>
      <?php else: ?>
      <div class="quiz-question-card">
        <div class="quiz-question-header">
          <span class="quiz-subject-tag"><?= htmlspecialchars($subject_name) ?></span>
          <span class="quiz-counter" id="questionCounter">Question 1/<?= $total ?></span>
        </div>
        <div class="quiz-question-text" id="questionText">Loading...</div>
      </div>
      <div class="quiz-answer-card">
        <div id="questionImageContainer"></div>
        <div id="optionsContainer"></div>
        <div id="textInputContainer" class="quiz-hidden">
          <input type="text" class="quiz-text-input" id="textAnswer" placeholder="Your answer">
        </div>
      </div>
      <div class="quiz-dots" id="dotsContainer"></div>
      <?php endif; ?>
    </div>

    <div class="quiz-analysis" id="analysisScreen">
      <div class="quiz-score-card">
        <div class="quiz-score-top">
          <span class="subject-tag"><?= htmlspecialchars($subject_name) ?></span>
          <span class="correct-tag">Correct: <span id="correctCount">0</span> / <span id="totalCount">0</span></span>
        </div>
        <div class="quiz-score-bottom">
          <h2 class="quiz-score-title">Analysis</h2>
          <div class="quiz-beaker">
            <img src="<?= htmlspecialchars($subject_image ?: 'file.png') ?>" 
                 class="quiz-beaker-img" 
                 onerror="this.style.display='none'"
                 alt="">
          </div>
          <div class="quiz-score-value" id="scorePercent">0<span>%</span></div>
        </div>
      </div>
      <div class="quiz-corrections-scroll" id="correctionsScroll"></div>
    </div>
  </div>

  <div class="bottom-file-section">
    <div class="item">
      <a href="notes.php" style="text-decoration:none; color:inherit; display:flex; flex-direction:column; align-items:center;">
        <img src="notes.png"><p>Note</p>
      </a>
    </div>
    <div class="item">
      <a href="#" onclick="upload()" style="text-decoration:none; color:inherit; display:flex; flex-direction:column; align-items:center;">
        <img src="uploaded.png"><p>Uploads</p>
      </a>
    </div>
    <div class="item retry-wrapper" id="retryWrapper">
      <button id="retryBtn" onclick="restartQuiz()" style="background:none; border:none; cursor:pointer; display:flex; flex-direction:column; align-items:center; font-family:inherit; color:inherit;">
        <img src="retry.png"><p id="retryText">Retry</p>
      </button>
      
    </div>
  </div>
</div>

<script src="script.js"></script>
<script>
const QUIZ_KEY       = 'quiz_progress_<?= $id ?>_<?= $type ?>_' + (<?= json_encode($_SESSION['student_id'] ?? 0) ?>);
const CURRENT_HASH   = '<?= $questionsHash ?>';
const allQuestions   = <?= json_encode($questions) ?>;
const lastAttempt    = <?= json_encode($last_attempt ?? null) ?>;
const prevWrongIds   = <?= json_encode($wrongIds) ?>;
const prevNewIds     = <?= json_encode($newQuestionIds) ?>;
const prevEditedIds  = <?= json_encode($editedQuestionIds) ?>;
const prevRetakeMode = '<?= $retakeMode ?>';
const totalAll       = allQuestions.length;

let liveWrongIds     = [...prevWrongIds];
let liveNewIds       = [...prevNewIds];
let liveEditedIds    = [...prevEditedIds];
let liveRetakeMode   = prevRetakeMode;
let quizEdited       = false;

let questions        = allQuestions;
let totalQuestions   = questions.length;
let currentQ         = 0;
let correctAnswers   = 0;
let userAnswers      = [];
let answered         = false;

function buildRetakeQuestions() {
    if (liveWrongIds.length > 0) {
        return allQuestions.filter(q => liveWrongIds.includes(q.question_id));
    }
    const combined = [...new Set([...liveNewIds, ...liveEditedIds])];
    if (combined.length > 0) {
        return allQuestions.filter(q => combined.includes(q.question_id));
    }
    return [];
}

function saveProgress() {
    localStorage.setItem(QUIZ_KEY, JSON.stringify({
        currentQ, correctAnswers, userAnswers, completed: false
    }));
}

function loadProgress() {
    const saved = localStorage.getItem(QUIZ_KEY);
    if (!saved) return null;
    try { return JSON.parse(saved); } catch(e) { return null; }
}

function clearProgress() {
    localStorage.removeItem(QUIZ_KEY);
}

async function refreshRetakeState() {
    try {
        const res = await fetch('api_progress.php?action=get_progress&subject_id=<?= $id ?>&type=<?= $type ?>');
        const data = await res.json();
        if (!data.success) return;

        quizEdited = data.quiz_edited ?? false;

        if (data.wrong_question_ids !== undefined) {
            const w = data.wrong_question_ids ? data.wrong_question_ids.split(',').map(Number).filter(id => id > 0) : [];
            liveWrongIds = w;
        }
        if (data.new_question_ids !== undefined) {
            liveNewIds = data.new_question_ids || [];
        }
        if (data.edited_question_ids !== undefined) {
            liveEditedIds = data.edited_question_ids || [];
        }

        if (liveWrongIds.length > 0) liveRetakeMode = 'wrong';
        else {
            const hasNew = liveNewIds.length > 0;
            const hasEdited = liveEditedIds.length > 0;
            if (hasNew && hasEdited) liveRetakeMode = 'new+edited';
            else if (hasNew) liveRetakeMode = 'new';
            else if (hasEdited) liveRetakeMode = 'edited';
            else liveRetakeMode = 'full';
        }

        updateRetryButtonUI();
    } catch(e) {
        console.error('refreshRetakeState failed', e);
    }
}

function updateRetryButtonUI() {
    const btn = document.getElementById('retryBtn');
    const text = document.getElementById('retryText');
    if (!btn || !text) return;

    const hasWrong = liveWrongIds.length > 0;
    const hasNew = liveNewIds.length > 0;
    const hasEdited = liveEditedIds.length > 0;
    const combinedCount = [...new Set([...liveNewIds, ...liveEditedIds])].length;
    const canRetake = hasWrong || hasNew || hasEdited || quizEdited;

    if (canRetake) {
        btn.classList.remove('retry-disabled');
        btn.onclick = restartQuiz;

        if (hasWrong) {
            text.textContent = `Retry (${liveWrongIds.length})`;
        } else if (hasNew && hasEdited) {
            text.textContent = `Retry (${combinedCount})`;
        } else if (hasNew) {
            text.textContent = `Retry (${liveNewIds.length} new)`;
        } else if (hasEdited) {
            text.textContent = `Retry (${liveEditedIds.length} updated)`;
        } else {
            text.textContent = 'Retry';
        }
    } else {
        btn.classList.add('retry-disabled');
        btn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            alert('🎉 You already achieved a perfect score! No retakes needed.');
            return false;
        };
        text.textContent = 'Retry';
    }
}

function calculateCumulativeStats() {
    const existingQuestionIds = new Set(allQuestions.map(q => q.question_id));
    let wrongIds = [];
    let answeredIds = [];

    userAnswers.forEach((ans, idx) => {
        const q = questions[idx];
        if (!q) return;
        const qid = q.question_id;
        if (!existingQuestionIds.has(qid)) return;
        answeredIds.push(qid);
        if (!ans.correct) wrongIds.push(qid);
    });

    if (!lastAttempt) {
        return {
            correct: correctAnswers,
            total: totalAll,
            percent: totalAll > 0 ? Math.round((correctAnswers / totalAll) * 100) : 0,
            wrongIds: wrongIds,
            answeredIds: answeredIds
        };
    }

    const lastWrongIds = lastAttempt.wrong_question_ids ? 
        lastAttempt.wrong_question_ids.split(',').map(Number).filter(id => id > 0 && existingQuestionIds.has(id)) : [];
    const prevAnsweredIds = lastAttempt.answered_question_ids ? 
        lastAttempt.answered_question_ids.split(',').map(Number).filter(id => id > 0 && existingQuestionIds.has(id)) : [];

    const retakeIdToIndex = {};
    questions.forEach((q, i) => retakeIdToIndex[q.question_id] = i);

    const stillWrongFromBefore = [];
    const fixedFromBefore = [];

    lastWrongIds.forEach(pid => {
        const idxInRetake = retakeIdToIndex[pid];
        if (idxInRetake !== undefined) {
            if (userAnswers[idxInRetake]?.correct) {
                fixedFromBefore.push(pid);
            } else {
                stillWrongFromBefore.push(pid);
            }
        } else {
            stillWrongFromBefore.push(pid);
        }
    });

    const allWrongNow = [...new Set([...stillWrongFromBefore, ...wrongIds])];
    const allAnsweredEver = [...new Set([...prevAnsweredIds, ...answeredIds])];
    const validAnsweredEver = allAnsweredEver.filter(id => existingQuestionIds.has(id));
    const validWrongNow = allWrongNow.filter(id => existingQuestionIds.has(id));

    const currentCorrectIds = answeredIds.filter(id => !wrongIds.includes(id));
    const prevCorrectIds = prevAnsweredIds.filter(id => !lastWrongIds.includes(id));
    const relevantEditedIds = liveEditedIds.filter(id => prevCorrectIds.includes(id));
    const unverifiedIds = [...new Set([...liveNewIds, ...relevantEditedIds])]
        .filter(id => existingQuestionIds.has(id) && !currentCorrectIds.includes(id));
    const potentiallyCorrectIds = validAnsweredEver.filter(id => !validWrongNow.includes(id));
    const verifiedCorrectIds = potentiallyCorrectIds.filter(id => !unverifiedIds.includes(id));
    const cumulativeCorrect = Math.min(verifiedCorrectIds.length, totalAll);

    return {
        correct: cumulativeCorrect,
        total: totalAll,
        percent: totalAll > 0 ? Math.round((cumulativeCorrect / totalAll) * 100) : 0,
        wrongIds: validWrongNow,
        answeredIds: validAnsweredEver
    };
}

// FIXED: Now accounts for edited/new questions
function recalculateFromLastAttempt() {
    const existingQuestionIds = new Set(allQuestions.map(q => q.question_id));

    const wrongIds = lastAttempt.wrong_question_ids ? 
        lastAttempt.wrong_question_ids.split(',').map(Number).filter(id => id > 0 && existingQuestionIds.has(id)) : [];
    const answeredIds = lastAttempt.answered_question_ids ? 
        lastAttempt.answered_question_ids.split(',').map(Number).filter(id => id > 0 && existingQuestionIds.has(id)) : [];

    const prevCorrectIds = answeredIds.filter(id => !wrongIds.includes(id));
    
    // SUBTRACT edited and new questions from correct count
    const unverifiedIds = [...new Set([...liveNewIds, ...liveEditedIds])]
        .filter(id => existingQuestionIds.has(id));
    
    const verifiedCorrectIds = prevCorrectIds.filter(id => !unverifiedIds.includes(id));
    
    const correct = verifiedCorrectIds.length;
    const percent = totalAll > 0 ? Math.round((correct / totalAll) * 100) : 0;

    return {
        correct: correct,
        total: totalAll,
        percent: percent,
        wrongIds: wrongIds,
        answeredIds: answeredIds
    };
}

function saveQuizToServer() {
    const stats = calculateCumulativeStats();

    fetch('api_progress.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=save_quiz&subject_id=<?= $id ?>&type=<?= $type ?>&correct=${stats.correct}&total=${stats.total}&percent=${stats.percent}&answered_ids=${encodeURIComponent(stats.answeredIds.join(','))}&wrong_ids=${encodeURIComponent(stats.wrongIds.join(','))}&questions_hash=${encodeURIComponent(CURRENT_HASH)}`
    })
    .then(async r => {
        const text = await r.text();
        try { return JSON.parse(text); }
        catch(e) { throw new Error('Invalid JSON: ' + text.substring(0,200)); }
    })
    .then(data => {
        if (data.success) {
            console.log('Saved:', stats.percent + '%');
            refreshRetakeState();
        } else {
            console.error('Save failed:', data);
            alert('Save failed: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Network error: ' + err.message);
    });
}

function loadOverallProgress() {
    return fetch('api_progress.php?action=get_progress&subject_id=<?= $id ?>&type=<?= $type ?>')
    .then(r => r.json())
    .then(data => {
        const pct = data.percent ?? 0;
        const bar = document.getElementById('overallProgressBar');
        const label = document.getElementById('overallProgressLabel');
        if (bar) bar.style.width = pct + '%';
        if (label) label.textContent = pct + '%';

        quizEdited = data.quiz_edited ?? false;

        if (data.wrong_question_ids !== undefined) {
            const w = data.wrong_question_ids ? data.wrong_question_ids.split(',').map(Number).filter(id => id > 0) : [];
            liveWrongIds = w;
        }
        if (data.new_question_ids !== undefined) {
            liveNewIds = data.new_question_ids || [];
        }
        if (data.edited_question_ids !== undefined) {
            liveEditedIds = data.edited_question_ids || [];
        }

        updateRetryButtonUI();
        
        // Refresh analysis if already visible
        const analysisScreen = document.getElementById('analysisScreen');
        if (analysisScreen && analysisScreen.style.display === 'block') {
            showAnalysis(true);
        }
    })
    .catch(() => {});
}

async function initQuiz() {
    await loadOverallProgress();
    if (totalQuestions === 0) return;

    const saved = loadProgress();

    if (saved && saved.completed) {
        currentQ = saved.currentQ;
        correctAnswers = saved.correctAnswers;
        userAnswers = saved.userAnswers || [];
        showAnalysis(true);
        return;
    }

    if (!saved && lastAttempt) {
        showAnalysis(true);
        return;
    }

    if (saved && saved.currentQ > 0) {
        currentQ = saved.currentQ;
        correctAnswers = saved.correctAnswers;
        userAnswers = saved.userAnswers || [];
    }

    renderDots();
    loadQuestion(currentQ);
}

function renderDots() {
    const container = document.getElementById('dotsContainer');
    if (!container) return;
    container.innerHTML = '';
    for (let i = 0; i < totalQuestions; i++) {
        const dot = document.createElement('div');
        dot.className = 'quiz-dot' + (i === currentQ ? ' active' : '');
        dot.id = 'dot' + i;
        container.appendChild(dot);
    }
}

function loadQuestion(index) {
    currentQ = index;
    answered = false;
    const q = questions[index];

    const counter = document.getElementById('questionCounter');
    if (counter) counter.textContent = 'Question ' + (index + 1) + '/' + totalQuestions;

    document.querySelectorAll('.quiz-dot').forEach((d, i) => {
        d.classList.toggle('active', i === index);
    });

    document.getElementById('questionText').textContent = q.question_text;

    const imgContainer = document.getElementById('questionImageContainer');
    imgContainer.innerHTML = '';
    if (q.question_image && q.question_image !== 'NULL' && q.question_image !== '') {
        const img = document.createElement('img');
        img.src = q.question_image;
        img.className = 'quiz-question-image';
        img.onerror = () => img.style.display = 'none';
        imgContainer.appendChild(img);
    }

    const optsContainer = document.getElementById('optionsContainer');
    const textContainer = document.getElementById('textInputContainer');

    if (q.question_type === 'identification') {
        optsContainer.classList.add('quiz-hidden');
        textContainer.classList.remove('quiz-hidden');
        const input = document.getElementById('textAnswer');
        input.value = '';
        input.className = 'quiz-text-input';
        input.disabled = false;
        input.onkeydown = (e) => { if (e.key === 'Enter') submitTextAnswer(); };
    } else {
        textContainer.classList.add('quiz-hidden');
        optsContainer.classList.remove('quiz-hidden');
        optsContainer.innerHTML = '';

        const labels = ['A', 'B', 'C', 'D'];
        q.choices.forEach((choice, i) => {
            if (!choice.text) return;
            const btn = document.createElement('button');
            btn.className = 'quiz-option-btn';
            btn.textContent = labels[i] + '. ' + choice.text;
            btn.dataset.text = choice.text;
            btn.onclick = () => selectAnswer(choice.text);
            optsContainer.appendChild(btn);
        });
    }
}

function selectAnswer(choiceText) {
    if (answered) return;
    answered = true;

    const q = questions[currentQ];
    const isCorrect = choiceText === q.correct_answer;
    if (isCorrect) correctAnswers++;

    userAnswers.push({
        question: q.question_text,
        correct: isCorrect,
        correctAnswer: q.correct_answer,
        userAnswer: choiceText,
        questionImage: q.question_image || ''
    });

    document.querySelectorAll('.quiz-option-btn').forEach(btn => {
        btn.disabled = true;
        btn.classList.add('disabled');
        const btnText = btn.dataset.text;
        if (btnText === q.correct_answer) btn.classList.add('correct');
        else if (btnText === choiceText && !isCorrect) btn.classList.add('wrong');
    });

    saveProgress();

    setTimeout(() => {
        if (currentQ + 1 < totalQuestions) {
            loadQuestion(currentQ + 1);
            saveProgress();
        } else {
            showAnalysis();
        }
    }, 1500);
}

function submitTextAnswer() {
    if (answered) return;
    answered = true;

    const input = document.getElementById('textAnswer');
    const answer = input.value.trim();
    const q = questions[currentQ];
    const isCorrect = answer.toLowerCase() === (q.correct_answer || '').toLowerCase();
    if (isCorrect) correctAnswers++;

    userAnswers.push({
        question: q.question_text,
        correct: isCorrect,
        correctAnswer: q.correct_answer,
        userAnswer: answer,
        questionImage: q.question_image || ''
    });

    input.classList.add(isCorrect ? 'correct' : 'wrong');
    input.disabled = true;

    saveProgress();

    setTimeout(() => {
        input.disabled = false;
        if (currentQ + 1 < totalQuestions) {
            loadQuestion(currentQ + 1);
            saveProgress();
        } else {
            showAnalysis();
        }
    }, 1500);
}

function showAnalysis(fromSaved = false) {
    if (!fromSaved) {
        saveQuizToServer();
        localStorage.setItem(QUIZ_KEY, JSON.stringify({
            currentQ, correctAnswers, userAnswers, completed: true
        }));
    }

    document.getElementById('quizScreen').classList.add('quiz-hidden');
    document.getElementById('analysisScreen').style.display = 'block';

    let displayPercent, displayCorrect, displayTotal, displayWrongAnswers;

    if (userAnswers.length === 0 && lastAttempt) {
        // FIXED: Use recalculateFromLastAttempt which now accounts for edits
        const stats = recalculateFromLastAttempt();
        
        displayPercent  = stats.percent;
        displayCorrect  = stats.correct;
        displayTotal    = stats.total;

        displayWrongAnswers = stats.wrongIds.map(wid => {
            const q = allQuestions.find(q => q.question_id === wid);
            return q ? {
                question: q.question_text,
                correctAnswer: q.correct_answer,
                questionImage: q.question_image || ''
            } : null;
        }).filter(Boolean);

    } else {
        const stats = calculateCumulativeStats();
        displayPercent  = stats.percent;
        displayCorrect  = stats.correct;
        displayTotal    = stats.total;

        displayWrongAnswers = userAnswers
            .map((ans, i) => {
                if (ans.correct) return null;
                const q = questions[i];
                if (!q) return null;
                return {
                    question: ans.question,
                    correctAnswer: q.correct_answer ?? ans.correctAnswer,
                    questionImage: ans.questionImage || ''
                };
            })
            .filter(Boolean);
    }

    document.getElementById('scorePercent').innerHTML = displayPercent + '<span>%</span>';
    document.getElementById('correctCount').textContent = displayCorrect;
    document.getElementById('totalCount').textContent = displayTotal;

    const scroll = document.getElementById('correctionsScroll');
    scroll.innerHTML = '';

    displayWrongAnswers.forEach((ans, i) => {
        const card = document.createElement('div');
        card.className = 'quiz-correction-card';
        let imageHtml = '';
        if (ans.questionImage && ans.questionImage !== 'NULL' && ans.questionImage !== '') {
            imageHtml = `<img src="${ans.questionImage}" class="correction-image" onerror="this.style.display='none'">`;
        }
        card.innerHTML = `
            <span class="corrections-label">Corrections:</span>
            ${imageHtml}
            <div class="q-num">Question ${i + 1}:</div>
            <div class="q-text">${ans.question}</div>
            <div class="correct-ans">${ans.correctAnswer}</div>
        `;
        scroll.appendChild(card);
    });

    if (scroll.children.length === 0) {
        scroll.innerHTML = '<div style="color:#fff; text-align:center; padding:40px; font-family:Itim,cursive;">Perfect score! No corrections needed.</div>';
    }
}

function restartQuiz() {
    clearProgress();

    questions = buildRetakeQuestions();
    totalQuestions = questions.length;

    if (totalQuestions === 0) {
        alert('No questions to retake. Great job!');
        return;
    }

    currentQ = 0;
    correctAnswers = 0;
    userAnswers = [];
    answered = false;

    document.getElementById('analysisScreen').style.display = 'none';
    document.getElementById('quizScreen').classList.remove('quiz-hidden');

    renderDots();
    loadQuestion(0);
}

function goBack() {
    window.history.back();
}

initQuiz();
</script>

</body>
</html>