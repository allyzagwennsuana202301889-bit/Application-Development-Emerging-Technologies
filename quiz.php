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
        SELECT n.note_id AS subject_id, n.title AS subject_name
        FROM notes n WHERE n.note_id = ? LIMIT 1
    ");
} else {
    $stmt = $conn->prepare("
        SELECT s.subject_id, s.subject_name
        FROM subjects s WHERE s.subject_id = ? LIMIT 1
    ");
}
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) exit("Quiz not found.");

$subject_name = $row['subject_name'] ?? 'Untitled';

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
        SELECT correct_answers, total_questions, score_percent, answered_question_ids, wrong_question_ids
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
   BUILD SMART RETAKE QUESTION LIST
════════════════════════════════════════════ */
$questions = [];
$isSmartRetake = false;
$wrongIds = [];
$answeredIds = [];

if ($last_attempt) {
    if (!empty($last_attempt['wrong_question_ids'])) {
        $wrongIds = array_map('intval', explode(',', $last_attempt['wrong_question_ids']));
        $wrongIds = array_filter($wrongIds);
    }
    if (!empty($last_attempt['answered_question_ids'])) {
        $answeredIds = array_map('intval', explode(',', $last_attempt['answered_question_ids']));
        $answeredIds = array_filter($answeredIds);
    }
}

$hasWrongAnswers = !empty($wrongIds);
$hasNewQuestions = false;
$newQuestionIds = [];

foreach ($allQuestions as $q) {
    if (!in_array($q['question_id'], $answeredIds)) {
        $hasNewQuestions = true;
        $newQuestionIds[] = $q['question_id'];
    }
}

if ($hasWrongAnswers || $hasNewQuestions) {
    $isSmartRetake = true;
    $targetIds = array_unique(array_merge($wrongIds, $newQuestionIds));
    
    foreach ($allQuestions as $q) {
        if (in_array($q['question_id'], $targetIds)) {
            $questions[] = $q;
        }
    }
} else {
    $questions = $allQuestions;
}

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
    .quiz-option-btn.correct { background: #4CAF50 !important; color: white !important; }
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
    .quiz-text-input.correct { border-color: #4CAF50; background: #e8f5e9; }
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
    .quiz-beaker svg {
      width: 100%;
      height: 100%;
      fill: #FFAE71;
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
            <svg viewBox="0 0 24 24"><path d="M9 3L7 17H17L15 3H9M12 7V13H12.5V7H12M11.5 14.5V16H12.5V14.5H11.5M6 19H18V21H6V19Z"/></svg>
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
const QUIZ_KEY = 'quiz_progress_<?= $id ?>_<?= $type ?>';
const questions = <?= json_encode($questions) ?>;
const lastAttempt = <?= json_encode($last_attempt ?? null) ?>;
const isSmartRetake = <?= $isSmartRetake ? 'true' : 'false' ?>;
const totalAll = <?= $totalAll ?>;
const totalQuestions = questions.length;
let currentQ = 0;
let correctAnswers = 0;
let userAnswers = [];
let answered = false;

function saveProgress() {
    localStorage.setItem(QUIZ_KEY, JSON.stringify({
        currentQ, correctAnswers, userAnswers, completed: false
    }));
}

function saveQuizToServer() {
    let wrongIds = [];
    let answeredIds = [];
    
    userAnswers.forEach((ans, idx) => {
        const qid = questions[idx]?.question_id ?? idx;
        answeredIds.push(qid);
        if (!ans.correct) wrongIds.push(qid);
    });

    let finalCorrect = correctAnswers;
    let finalTotal = totalQuestions;
    let finalPercent = totalQuestions > 0 ? Math.round((correctAnswers / totalQuestions) * 100) : 0;
    let allAnsweredIds = answeredIds.join(',');
    let allWrongIds = wrongIds.join(',');

    if (lastAttempt && totalQuestions < totalAll) {
        const prevWrongIds = lastAttempt.wrong_question_ids ? 
            lastAttempt.wrong_question_ids.split(',').map(Number).filter(id => id > 0) : [];
        const prevAnsweredIds = lastAttempt.answered_question_ids ? 
            lastAttempt.answered_question_ids.split(',').map(Number).filter(id => id > 0) : [];
        
        const thisRetakeQuestionIds = questions.map(q => q.question_id);
        
        const stillWrongFromBefore = prevWrongIds.filter(pid => {
            const idxInRetake = thisRetakeQuestionIds.indexOf(pid);
            if (idxInRetake !== -1) {
                return !userAnswers[idxInRetake]?.correct;
            }
            return true;
        });
        
        const mergedWrong = [...new Set([...stillWrongFromBefore, ...wrongIds])];
        allWrongIds = mergedWrong.join(',');
        
        const mergedAnswered = [...new Set([...prevAnsweredIds, ...answeredIds])];
        allAnsweredIds = mergedAnswered.join(',');
        
        const fixedFromBefore = prevWrongIds.filter(pid => {
            const idxInRetake = thisRetakeQuestionIds.indexOf(pid);
            return idxInRetake !== -1 && userAnswers[idxInRetake]?.correct;
        }).length;
        
        const prevCorrect = parseInt(lastAttempt.correct_answers) || 0;
        finalCorrect = prevCorrect + fixedFromBefore;
        finalTotal = totalAll;
        finalPercent = totalAll > 0 ? Math.round((finalCorrect / totalAll) * 100) : 0;
    }

    fetch('api_progress.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=save_quiz&subject_id=<?= $id ?>&type=<?= $type ?>&correct=${finalCorrect}&total=${finalTotal}&percent=${finalPercent}&answered_ids=${encodeURIComponent(allAnsweredIds)}&wrong_ids=${encodeURIComponent(allWrongIds)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            console.log('✅ Saved:', finalPercent + '%');
            loadOverallProgress();
            updateRetryButtonState(allWrongIds, finalTotal, finalPercent);
        } else {
            console.error('❌ Save failed:', data);
        }
    })
    .catch(err => console.error('❌ Error:', err));
}

function loadProgress() {
    const saved = localStorage.getItem(QUIZ_KEY);
    if (!saved) return null;
    try { return JSON.parse(saved); } catch(e) { return null; }
}

function clearProgress() {
    localStorage.removeItem(QUIZ_KEY);
}

function loadOverallProgress() {
    fetch('api_progress.php?action=get_progress&subject_id=<?= $id ?>&type=<?= $type ?>')
    .then(r => r.json())
    .then(data => {
        const pct = data.percent ?? 0;
        const bar = document.getElementById('overallProgressBar');
        const label = document.getElementById('overallProgressLabel');
        if (bar) bar.style.width = pct + '%';
        if (label) label.textContent = pct + '%';
    })
    .catch(() => {});
}

function updateRetryButtonState(wrongIdsStr, totalAnswered, percent) {
    const btn = document.getElementById('retryBtn');
    const text = document.getElementById('retryText');
    if (!btn || !text) return;

    const hasWrong = wrongIdsStr && wrongIdsStr.trim() !== '';
    const hasNewQuestions = totalAll > totalAnswered && totalAnswered > 0;
    const canRetake = hasWrong || hasNewQuestions;

    if (canRetake) {
        btn.classList.remove('retry-disabled');
        btn.onclick = restartQuiz;
        
        if (hasWrong) {
            const wrongCount = wrongIdsStr.split(',').filter(id => id.trim() !== '').length;
            text.textContent = `Retry (${wrongCount})`;
        } else if (hasNewQuestions) {
            text.textContent = `Retry (${totalAll - totalAnswered} new)`;
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

function setupRetryButton() {
    const hasWrong = lastAttempt && lastAttempt.wrong_question_ids ? 
        lastAttempt.wrong_question_ids.trim() !== '' : false;
    const lastTotal = lastAttempt ? parseInt(lastAttempt.total_questions) : 0;
    
    updateRetryButtonState(
        lastAttempt?.wrong_question_ids ?? '',
        lastTotal,
        lastAttempt ? parseInt(lastAttempt.score_percent) : 0
    );
}

function initQuiz() {
    loadOverallProgress();
    setupRetryButton();
    if (totalQuestions === 0) return;

    const saved = loadProgress();
    const quizUpdated = totalQuestions > (lastAttempt ? parseInt(lastAttempt.total_questions) : 0);

    if (quizUpdated) {
        clearProgress();
        currentQ = 0; correctAnswers = 0; userAnswers = [];
        renderDots();
        loadQuestion(0);
        return;
    }

    if (saved && saved.completed) {
        currentQ = saved.currentQ;
        correctAnswers = saved.correctAnswers;
        userAnswers = saved.userAnswers || [];
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

    // Calculate overall percent (not just retake subset)
    let overallCorrect = correctAnswers;
    let overallTotal = totalAll;

    if (lastAttempt && totalQuestions < totalAll) {
        const prevWrongIds = lastAttempt.wrong_question_ids ? 
            lastAttempt.wrong_question_ids.split(',').map(Number).filter(id => id > 0) : [];
        const thisRetakeQuestionIds = questions.map(q => q.question_id);
        
        const fixedFromBefore = prevWrongIds.filter(pid => {
            const idxInRetake = thisRetakeQuestionIds.indexOf(pid);
            return idxInRetake !== -1 && userAnswers[idxInRetake]?.correct;
        }).length;
        
        const prevCorrect = parseInt(lastAttempt.correct_answers) || 0;
        overallCorrect = prevCorrect + fixedFromBefore;
    } else if (!lastAttempt) {
        overallCorrect = correctAnswers;
    }

    const displayPercent = overallTotal > 0 ? Math.round((overallCorrect / overallTotal) * 100) : 0;

    document.getElementById('scorePercent').innerHTML = displayPercent + '<span>%</span>';
    document.getElementById('correctCount').textContent = overallCorrect;
    document.getElementById('totalCount').textContent = overallTotal;

    const scroll = document.getElementById('correctionsScroll');
    scroll.innerHTML = '';

    userAnswers.forEach((ans, i) => {
        if (ans.correct) return;
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
    window.location.reload();
}

function goBack() {
    window.history.back();
}

initQuiz();
</script>

</body>
</html>