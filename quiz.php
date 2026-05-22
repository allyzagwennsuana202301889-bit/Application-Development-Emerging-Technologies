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
   FETCH QUIZ QUESTIONS
========================= */
$questions = [];
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
    
    $questions[] = [
        'question_id'    => $q['question_id'],
        'question_text'  => $q['question'],
        'question_image' => $q['question_image'] ?? '',
        'question_type'  => $q['question_type'],
        'choices'        => $choices,
        'correct_answer' => $correct_answer
    ];
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
    
    /* Question card */
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
      margin-bottom: 15px;
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
    
    /* Answer card */
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
    
    /* Option buttons */
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
    
    /* Text input */
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
    
    /* Dots */
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
    
    /* ========== ANALYSIS SCREEN ========== */
    .quiz-analysis {
      display: none;
      flex-direction: column;
      height: 100%;
    }
    
    /* Score card */
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
      color: #333;
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
      opacity: 0.2;
      z-index: 1;
    }
    .quiz-beaker svg {
      width: 100%;
      height: 100%;
      fill: #F4A261;
    }
    
    /* ========== HORIZONTAL SCROLL CORRECTIONS ========== */
    .quiz-corrections-wrapper {
      position: absolute;
      top: 220px;
      bottom: 0;
      left: 0;
      right: 0;
      overflow: hidden;
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
  </style>
</head>
<body>

<div class="container">

  <!-- NAV -->
  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <img src="back.png" class="back-btn" onclick="goBack()">
  </nav>

  <!-- SIDEBAR -->
  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" class="help">
      <img src="back.png" class="back">
    </div>
    
   <?php
// Fetch current user's profile image fresh from DB
$pfp_stmt = $conn->prepare("SELECT profile_image FROM student WHERE student_id = ?");
$pfp_stmt->bind_param("i", $student_id);
$pfp_stmt->execute();
$pfp_result = $pfp_stmt->get_result()->fetch_assoc();
$profile_image = !empty($pfp_result['profile_image']) ? $pfp_result['profile_image'] : 'acc.png';

// Cache bust: append timestamp so browser always fetches fresh
$image_src = $profile_image;
if (strpos($image_src, 'data:') === 0) {
    // base64 — no cache bust needed, but force reload with unique session
    $image_src = $profile_image;
} else {
    $image_src .= '?t=' . time();
}
?>

<!-- Profile Image Upload -->
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
    <a href="#">Leaderboard</a>
    <a href="settings.html">Settings</a>
    <a href="logout.php">Log out</a>
  </div>

  <!-- OVERLAY -->
  <div class="overlay"></div>

  <!-- QUIZ CONTENT -->
  <div class="quiz-page" id="quizPage">

    <!-- QUIZ SCREEN -->
    <div id="quizScreen">
      
      <?php if ($total === 0): ?>
        <div class="quiz-empty">
          <p>No quiz questions available for this subject.</p>
          <a href="subject.php?id=<?= $id ?>&type=<?= htmlspecialchars($type) ?>">Go Back</a>
        </div>
      <?php else: ?>

      <!-- Question Card -->
      <div class="quiz-question-card">
        <div class="quiz-question-header">
          <span class="quiz-subject-tag"><?= htmlspecialchars($subject_name) ?></span>
          <span class="quiz-counter" id="questionCounter">Question 1/<?= $total ?></span>
        </div>
        <div class="quiz-question-text" id="questionText">Loading...</div>
      </div>
      
      <!-- Answer Card -->
      <div class="quiz-answer-card">
        <div id="questionImageContainer"></div>
        <div id="optionsContainer"></div>
        <div id="textInputContainer" class="quiz-hidden">
          <input type="text" class="quiz-text-input" id="textAnswer" placeholder="Your answer">
        </div>
      </div>
      
      <!-- Dots -->
      <div class="quiz-dots" id="dotsContainer"></div>

      <?php endif; ?>
    </div>

    <!-- ANALYSIS SCREEN -->
    <div class="quiz-analysis" id="analysisScreen">
      
      <!-- Score Card -->
      <div class="quiz-score-card">
        <div class="quiz-score-top">
          <span class="subject-tag"><?= htmlspecialchars($subject_name) ?></span>
          <span class="correct-tag">Correct Answers: <span id="correctCount">0</span></span>
        </div>
        <div class="quiz-score-bottom">
          <h2 class="quiz-score-title">Analysis</h2>
          <div class="quiz-beaker">
            <svg viewBox="0 0 24 24"><path d="M9 3L7 17H17L15 3H9M12 7V13H12.5V7H12M11.5 14.5V16H12.5V14.5H11.5M6 19H18V21H6V19Z"/></svg>
          </div>
          <div class="quiz-score-value" id="scorePercent">0<span>%</span></div>
        </div>
      </div>
      
      <!-- Horizontal Scroll Corrections -->
      <div class="quiz-corrections-scroll" id="correctionsScroll"></div>
      
    </div>

  </div>

  <!-- Bottom Bar -->
  <div class="bottom-file-section">
    <div class="item">
      <a href="notes.php" style="text-decoration:none; color:inherit; display:flex; flex-direction:column; align-items:center;">
        <img src="notes.png">
        <p>Note</p>
      </a>
    </div>
    <div class="item">
      <a href="#" onclick="upload()" style="text-decoration:none; color:inherit; display:flex; flex-direction:column; align-items:center;">
        <img src="uploaded.png">
        <p>Uploads</p>
      </a>
    </div>
    <div class="item">
      <button onclick="restartQuiz()" style="background:none; border:none; cursor:pointer; display:flex; flex-direction:column; align-items:center; font-family:inherit; color:inherit;">
        <img src="retry.png">
        <p>Retry</p>
      </button>
    </div>
  </div>

</div>

<script src="script.js"></script>
<script>
const QUIZ_KEY = 'quiz_progress_<?= $id ?>_<?= $type ?>';

const questions = <?= json_encode($questions) ?>;
const totalQuestions = questions.length;
let currentQ = 0;
let correctAnswers = 0;
let userAnswers = [];
let answered = false;

function saveProgress() {
    // Local storage (keep existing behavior)
    const data = {
        currentQ: currentQ,
        correctAnswers: correctAnswers,
        userAnswers: userAnswers,
        completed: document.getElementById('analysisScreen').style.display === 'block'
    };
    localStorage.setItem(QUIZ_KEY, JSON.stringify(data));
    
    // If quiz is complete, save to database
    if (data.completed) {
        const percent = totalQuestions > 0 ? Math.round((correctAnswers / totalQuestions) * 100) : 0;
        
        fetch('api_progress.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=save_quiz&subject_id=<?= $id ?>&type=<?= $type ?>&correct=${correctAnswers}&total=${totalQuestions}&percent=${percent}`
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  console.log('Quiz score saved to database');
              }
          })
          .catch(err => console.error('Error saving quiz:', err));
    }
}

function loadProgress() {
    const saved = localStorage.getItem(QUIZ_KEY);
    if (!saved) return null;
    try {
        return JSON.parse(saved);
    } catch(e) {
        return null;
    }
}

function clearProgress() {
    localStorage.removeItem(QUIZ_KEY);
}

function initQuiz() {
    if (totalQuestions === 0) return;
    
    const saved = loadProgress();
    if (saved && saved.completed) {
        currentQ = saved.currentQ;
        correctAnswers = saved.correctAnswers;
        userAnswers = saved.userAnswers || [];
        showAnalysis();
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
        input.onkeydown = (e) => {
            if (e.key === 'Enter') submitTextAnswer();
        };
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
        if (btnText === q.correct_answer) {
            btn.classList.add('correct');
        } else if (btnText === choiceText && !isCorrect) {
            btn.classList.add('wrong');
        }
    });
    
    saveProgress();
    
    setTimeout(() => {
        if (currentQ + 1 < totalQuestions) {
            loadQuestion(currentQ + 1);
            saveProgress();
        } else {
            showAnalysis();
            saveProgress();
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
            saveProgress();
        }
    }, 1500);
}

function showAnalysis() {
    document.getElementById('quizScreen').classList.add('quiz-hidden');
    document.getElementById('analysisScreen').style.display = 'block';
    
    const percent = totalQuestions > 0 ? Math.round((correctAnswers / totalQuestions) * 100) : 0;
    document.getElementById('scorePercent').innerHTML = percent + '<span>%</span>';
    document.getElementById('correctCount').textContent = correctAnswers;
    
    const scroll = document.getElementById('correctionsScroll');
    scroll.innerHTML = '';
    
    userAnswers.forEach((ans, i) => {
        if (ans.correct) return;
        
        const card = document.createElement('div');
        card.className = 'quiz-correction-card';
        
        // Build image HTML if question had an image
        let imageHtml = '';
        if (ans.questionImage && ans.questionImage !== 'NULL' && ans.questionImage !== '') {
            imageHtml = `<img src="${ans.questionImage}" class="correction-image" alt="Question image" onerror="this.style.display='none'">`;
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
    currentQ = 0;
    correctAnswers = 0;
    userAnswers = [];
    document.getElementById('analysisScreen').style.display = 'none';
    document.getElementById('quizScreen').classList.remove('quiz-hidden');
    initQuiz();
}

function goBack() {
    window.history.back();
}

initQuiz();
</script>

</body>
</html>