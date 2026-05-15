<?php
session_start();
include 'database.php';

$subject_title = isset($_GET['subject']) ? htmlspecialchars($_GET['subject']) : '';
$note_id = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;

$existing_questions = [];
if ($note_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order ASC");
    $stmt->bind_param("i", $note_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['choices'] = json_decode($row['choices'], true) ?? [];
        $row['question_image'] = $row['question_image'] ?? '';
        $existing_questions[] = $row;
    }
}

$username = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Insert Username';
$useremail = isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'username@gmail.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Create Quiz</title>
    <link href="https://fonts.googleapis.com/css2?family=Inria+Sans:wght@400&family=Itim&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .flashcards-page .container {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .flashcards-page .card-deck-wrapper {
            position: relative;
            flex: 1;
            overflow: hidden;
            width: 100%;
        }

        .flashcards-page .cards-track {
            display: flex;
            height: 100%;
            width: 100%;
            transition: transform 0.35s ease-out;
            will-change: transform;
        }

        .flashcards-page .question-slide {
            width: 100%;
            height: 100%;
            flex: 0 0 100%;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 10px 12px 20px;
            box-sizing: border-box;
        }

        .flashcards-page .question-slide::-webkit-scrollbar {
            display: none;
        }

        .flashcards-page .progress-dots {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 8px 0;
            flex-shrink: 0;
        }

        .flashcards-page .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            transition: 0.3s;
            cursor: pointer;
            border: 2px solid transparent;
            flex-shrink: 0;
        }

        .flashcards-page .dot.active {
            background: #fff;
            transform: scale(1.2);
            border-color: #1a1a2e;
        }

        .flashcards-page .bottom-file-section {
            position: relative;
            width: 100%;
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 12px 20px;
            background: #3B8BFF;
            z-index: 100;
            flex-shrink: 0;
        }

        .flashcards-page .q-card {
            position: relative;
            background: #e9e9e9;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex-shrink: 0;
            margin-top: 14px;
        }

        .flashcards-page .question-counter {
            position: absolute;
            top: 16px;
            right: 16px;
            color: #888;
            font-size: 12px;
            font-family: 'Inria Sans', sans-serif;
            font-style: italic;
            z-index: 5;
        }

        .flashcards-page .delete-q-btn {
            position: absolute;
            top: -14px;
            right: 8px;
            width: 28px;
            height: 28px;
            background: #ff4444;
            color: white;
            border: 2px solid #e9e9e9;
            border-radius: 50%;
            font-size: 18px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 20;
            line-height: 1;
            padding-bottom: 2px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .flashcards-page .delete-q-btn:active {
            background: #cc0000;
            transform: scale(0.95);
        }

        .flashcards-page .delete-q-btn.hidden {
            display: none !important;
        }

        /* ===== FILE ATTACHMENT AREA (in morphing card, ABOVE type selector) ===== */
        .flashcards-page .file-attachment-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin-bottom: 10px;
        }

        .flashcards-page .file-attachment-area .file-icon-btn {
            width: 60px;
            height: 70px;
            border: 2px solid #999;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            background: transparent;
            position: relative;
        }

        .flashcards-page .file-attachment-area .file-icon-btn svg {
            width: 32px;
            height: 32px;
            stroke: #888;
            fill: none;
            stroke-width: 1.5;
        }

        .flashcards-page .file-attachment-area .file-icon-btn .file-plus {
            position: absolute;
            top: 18px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 20px;
            color: #888;
            font-weight: 300;
        }

        .flashcards-page .file-attachment-area .file-label {
            margin-top: 6px;
            font-size: 13px;
            color: #888;
        }

        /* ===== IMAGE PREVIEW (replaces file icon when image attached) ===== */
        .flashcards-page .q-image-box {
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            display: none;
            background: #ddd;
            min-height: 60px;
            margin-bottom: 10px;
        }
        .flashcards-page .q-image-box.show {
            display: block;
        }
        .flashcards-page .q-image-box img {
            width: 100%;
            height: auto;
            max-height: 200px;
            object-fit: contain;
            border-radius: 10px;
            display: block;
            background: #f5f5f5;
        }
        .flashcards-page .q-image-box .rm-img {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            background: #ff4444;
            color: white;
            border: 2px solid white;
            border-radius: 50%;
            font-size: 18px;
            font-weight: bold;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            padding-bottom: 2px;
        }
        .flashcards-page .q-image-box .rm-img:active {
            transform: scale(0.9);
        }

        /* ===== TYPE BUTTONS ===== */
        .flashcards-page .type-btn {
            width: 100%;
            padding: 14px;
            margin: 6px 0;
            border: none;
            border-radius: 10px;
            background: #A0E8FF;
            color: #333;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .flashcards-page .type-btn:active {
            background: #7DD3EA;
        }

        /* ===== CORRECT ANSWER HIGHLIGHT ===== */
        .flashcards-page .choice-field.correct {
            background: #4CAF50 !important;
            color: white !important;
            border-color: #4CAF50 !important;
        }

        /* ===== ANSWER FIELD ===== */
        .flashcards-page .answer-field {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: #A0E8FF;
            color: #333;
            font-size: 16px;
            box-sizing: border-box;
            margin: 10px 0;
        }
        .flashcards-page .answer-field::placeholder {
            color: #555;
        }
    </style>
</head>
<body class="flashcards-page">
    <div class="container">

        <nav class="nav">
            <span class="hamburger" onclick="toggleSidebar()">&#9776;</span>
            <img src="bell.png" class="bell" alt="Notifications">
        </nav>

        <div class="nav-links" id="sidebar">
            <div class="top-icons">
                <img src="FAQIcon.png" class="help" alt="Help">
                <img src="back.png" class="back" onclick="toggleSidebar()" alt="Close">
            </div>
            <label for="imageInput">
                <img id="preview" src="acc.png" alt="Upload Image">
            </label>
            <input type="file" id="imageInput" accept="image/*" hidden>
            <h3><?php echo $username; ?></h3>
            <p><?php echo $useremail; ?></p>
            <a href="homepage.php">Home</a>
            <a href="notes.php">Notes</a>
            <a href="#">Analytics</a>
            <a href="#">Leaderboard</a>
            <a href="settings.html">Settings</a>
            <a href="index.php">Log out</a>
        </div>

        <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>



        <div class="card-deck-wrapper" id="cardDeckWrapper">
            <div class="cards-track" id="cardsTrack"></div>
        </div>

        <div class="progress-dots" id="progressDots"></div>

        <div class="bottom-file-section">
            <div class="item" onclick="addNewQuestion()">
                <img src="add.png" alt="Add">
                <p>Add</p>
            </div>
            <div class="item" onclick="triggerUpload()">
                <img src="uploaded.png" alt="Uploads">
                <p>Uploads</p>
            </div>
            <div class="item" onclick="goBack()">
                <img src="back.png" alt="Back">
                <p>Back</p>
            </div>
        </div>

        <div class="toast-msg" id="toastMsg"></div>
    </div>

    <script src="script.js"></script>
    <script>
        const NOTE_ID = <?php echo $note_id; ?>;
        const SUBJECT_TITLE = "<?php echo addslashes($subject_title); ?>";
        let cards = [];
        let currentIndex = 0;
        let autoSaveTimer = null;
        let lastSavedHash = '';
        const existingQuestions = <?php echo json_encode($existing_questions); ?>;

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('overlay').classList.toggle('active');
        }

        function showToast(msg) {
            const toast = document.getElementById('toastMsg');
            toast.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2500);
        }

        function createSlideHTML(index, data) {
            data = data || {};
            const qNum = index + 1;
            const question = data.question || '';
            const qType = data.question_type || '';
            const choices = data.choices || [];
            const answer = data.correct_answer || '';
            const showDelete = cards.length > 1 ? '' : 'hidden';
            const hasImage = data.question_image ? 'show' : '';
            const imgSrc = data.question_image || '';

            return `
                <div class="question-slide" data-idx="${index}">
                    <!-- TOP CARD: Question text only -->
                    <div class="q-card">
                        <button class="delete-q-btn ${showDelete}" onclick="event.stopPropagation(); deleteQuestion(${index})" title="Delete question">−</button>
                        <span class="question-counter">Question ${qNum} / ${cards.length}</span>
                        <div class="q-card-header">
                            <input type="text" value="${esc(SUBJECT_TITLE)}" class="subject-field" readonly>
                        </div>
                        <textarea class="q-textarea" placeholder="(insert question here)" id="qText-${index}" rows="3" oninput="scheduleAutoSave()">${esc(question)}</textarea>
                    </div>

                    <!-- BOTTOM MORPHING CARD -->
                    <div class="morph-card" id="morphCard-${index}" data-type="${qType}">

                        <!-- ============================================ -->
                        <!-- FILE ATTACHMENT AREA (always visible, at top) -->
                        <!-- ============================================ -->
                        <div class="file-attachment-section" id="fileAttachSection-${index}">
                            <!-- Image preview (hidden until image attached) -->
                            <div class="q-image-box ${hasImage}" id="qImgWrap-${index}">
                                ${imgSrc ? `<img id="qImgView-${index}" src="${esc(imgSrc)}" alt="Question image">` : `<img id="qImgView-${index}" src="" alt="Question image" style="display:none;">`}
                                <button class="rm-img" onclick="event.stopPropagation(); detachImage(${index})" title="Remove image">×</button>
                            </div>

                            <!-- File icon button (hidden when image attached) -->
                            <div class="file-attachment-area ${hasImage ? 'hidden' : ''}" id="fileIconArea-${index}" onclick="pickImage(${index})">
                                <div class="file-icon-btn">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                    </svg>
                                    <span class="file-plus">+</span>
                                </div>
                                <span class="file-label">File</span>
                            </div>

                            <input type="file" id="qImgFile-${index}" accept="image/*,application/pdf" hidden onchange="onImagePicked(${index}, this)">
                        </div>

                        <!-- ============================================ -->
                        <!-- SELECTOR STATE: Choose answer type -->
                        <!-- ============================================ -->
                        <div class="morph-state selector-state ${qType ? 'hidden' : ''}" id="selectorState-${index}">
                            <p class="type-label">What kind of questionnaire you making?</p>
                            <button class="type-btn" onclick="transitionToChoices(${index})">choice based</button>
                            <button class="type-btn" onclick="transitionToAnswer(${index})">Identification</button>
                        </div>

                        <!-- ============================================ -->
                        <!-- CHOICES STATE -->
                        <!-- ============================================ -->
                        <div class="morph-state choices-state ${qType === 'choice' ? '' : 'hidden'}" id="choicesState-${index}">
                            <div class="morph-header">
                                <span class="morph-back" onclick="transitionBack(${index})">&#8249; Back</span>
                                <span class="morph-title">Multiple Choice</span>
                            </div>
                            <input type="text" class="choice-field ${choices[0]?.correct ? 'correct' : ''}" placeholder="Choice A" value="${esc(choices[0]?.text || '')}" onclick="markCorrect(this)" oninput="scheduleAutoSave()">
                            <input type="text" class="choice-field ${choices[1]?.correct ? 'correct' : ''}" placeholder="Choice B" value="${esc(choices[1]?.text || '')}" onclick="markCorrect(this)" oninput="scheduleAutoSave()">
                            <input type="text" class="choice-field ${choices[2]?.correct ? 'correct' : ''}" placeholder="Choice C" value="${esc(choices[2]?.text || '')}" onclick="markCorrect(this)" oninput="scheduleAutoSave()">
                            <input type="text" class="choice-field ${choices[3]?.correct ? 'correct' : ''}" placeholder="Choice D" value="${esc(choices[3]?.text || '')}" onclick="markCorrect(this)" oninput="scheduleAutoSave()">
                            <p class="choice-hint">Tap the correct answer to highlight it</p>
                        </div>

                        <!-- ============================================ -->
                        <!-- ANSWER STATE -->
                        <!-- ============================================ -->
                        <div class="morph-state answer-state ${qType === 'identification' ? '' : 'hidden'}" id="answerState-${index}">
                            <div class="morph-header">
                                <span class="morph-back" onclick="transitionBack(${index})">&#8249; Back</span>
                                <span class="morph-title">Identification</span>
                            </div>
                            <p class="answer-label">What is the correct answer?</p>
                            <input type="text" class="answer-field" placeholder="(insert the correct answer here)" id="ansField-${index}" value="${esc(answer)}" oninput="scheduleAutoSave()">
                        </div>
                    </div>
                </div>
            `;
        }

        function esc(text) {
            if (!text) return '';
            const d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }

        function renderSlides() {
            const track = document.getElementById('cardsTrack');
            track.innerHTML = '';
            if (cards.length === 0) cards.push({});

            let html = '';
            cards.forEach((c, i) => {
                html += createSlideHTML(i, c);
            });
            track.innerHTML = html;

            requestAnimationFrame(updateUI);
        }

        function updateUI() {
            const track = document.getElementById('cardsTrack');
            track.style.transform = `translateX(-${currentIndex * 100}%)`;
            updateDots();
        }

        function updateDots() {
            const box = document.getElementById('progressDots');
            box.innerHTML = '';
            for (let i = 0; i < cards.length; i++) {
                const d = document.createElement('span');
                d.className = 'dot ' + (i === currentIndex ? 'active' : '');
                d.onclick = () => goTo(i);
                box.appendChild(d);
            }
        }

        function goTo(idx) {
            if (idx >= 0 && idx < cards.length) {
                saveCurrent();
                currentIndex = idx;
                updateUI();
            }
        }

        function scheduleAutoSave() {
            if (autoSaveTimer) clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                saveCurrent();
                if (NOTE_ID) {
                    autoSaveToServer();
                }
            }, 2000);
        }

        function getCardsHash() {
            return JSON.stringify(cards.map(c => ({
                q: c.question || '',
                t: c.question_type || '',
                a: c.correct_answer || '',
                ch: (c.choices || []).map(ch => (ch.text || '') + (ch.correct ? '*' : '')).join('|'),
                img: c.question_image ? 'has_img' : ''
            })));
        }

        async function autoSaveToServer() {
            const currentHash = getCardsHash();
            if (currentHash === lastSavedHash) return;

            const payload = [];
            for (let i = 0; i < cards.length; i++) {
                const c = cards[i];
                if (!c.question || !c.question.trim()) continue;
                if (!c.question_type) continue;

                payload.push({
                    note_id: NOTE_ID,
                    subject: SUBJECT_TITLE,
                    question: c.question,
                    type: c.question_type,
                    question_order: i,
                    choices: c.question_type === 'choice' ? JSON.stringify(c.choices) : null,
                    answer: c.question_type === 'identification' ? c.correct_answer : null,
                    question_image: c.question_image || null
                });
            }

            if (payload.length === 0) return;

            try {
                const r = await fetch('autosavequiz.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ questions: payload })
                });
                const d = await r.json();
                if (d.success) {
                    lastSavedHash = currentHash;
                    showToast('Auto-saved');
                }
            } catch (e) {
                console.error('Auto-save failed:', e);
            }
        }

        function deleteQuestion(index) {
            if (cards.length <= 1) {
                showToast("Can't delete the only question");
                return;
            }

            if (!confirm(`Delete Question ${index + 1}?`)) return;

            cards.splice(index, 1);

            if (currentIndex >= cards.length) {
                currentIndex = cards.length - 1;
            }

            renderSlides();
            showToast('Question deleted');

            setTimeout(() => {
                saveCurrent();
                if (NOTE_ID) autoSaveToServer();
            }, 100);
        }

        function transitionToChoices(index) {
            const morph = document.getElementById(`morphCard-${index}`);
            document.getElementById(`selectorState-${index}`).classList.add('hidden');
            document.getElementById(`choicesState-${index}`).classList.remove('hidden');
            morph.setAttribute('data-type', 'choice');
            scheduleAutoSave();
        }

        function transitionToAnswer(index) {
            const morph = document.getElementById(`morphCard-${index}`);
            document.getElementById(`selectorState-${index}`).classList.add('hidden');
            document.getElementById(`answerState-${index}`).classList.remove('hidden');
            morph.setAttribute('data-type', 'identification');
            scheduleAutoSave();
        }

        function transitionBack(index) {
            const morph = document.getElementById(`morphCard-${index}`);
            document.getElementById(`selectorState-${index}`).classList.remove('hidden');
            document.getElementById(`choicesState-${index}`).classList.add('hidden');
            document.getElementById(`answerState-${index}`).classList.add('hidden');
            morph.setAttribute('data-type', '');

            if (cards[index]) {
                cards[index].question_type = '';
                cards[index].choices = [];
                cards[index].correct_answer = '';
            }
            scheduleAutoSave();
        }

        function addNewQuestion() {
            saveCurrent();
            cards.push({});
            currentIndex = cards.length - 1;
            renderSlides();
            showToast('Question ' + cards.length + ' added!');
        }

        function saveCurrent() {
            if (cards.length === 0) return;
            const slide = document.querySelector(`.question-slide[data-idx="${currentIndex}"]`);
            if (!slide) return;

            const morph = document.getElementById(`morphCard-${currentIndex}`);
            const qType = morph.getAttribute('data-type') || '';

            const choices = [];
            slide.querySelectorAll('.choice-field').forEach(inp => {
                choices.push({
                    text: inp.value,
                    correct: inp.classList.contains('correct')
                });
            });

            const qImg = slide.querySelector(`#qImgView-${currentIndex}`);
            let questionImage = '';
            if (qImg && qImg.src && qImg.src !== '' && qImg.style.display !== 'none') {
                questionImage = qImg.src;
            }

            cards[currentIndex] = {
                question: slide.querySelector(`#qText-${currentIndex}`).value,
                question_type: qType,
                choices: choices,
                correct_answer: slide.querySelector(`#ansField-${currentIndex}`)?.value || '',
                question_image: questionImage
            };
        }

        function markCorrect(inp) {
            const state = inp.closest('.choices-state');
            state.querySelectorAll('.choice-field').forEach(c => c.classList.remove('correct'));
            inp.classList.add('correct');
            scheduleAutoSave();
        }

        function pickImage(index) {
            document.getElementById(`qImgFile-${index}`).click();
        }

        function onImagePicked(index, input) {
            const file = input.files[0];
            if (!file) return;
            if (!file.type.startsWith('image/') && file.type !== 'application/pdf') {
                showToast('Please select an image or PDF file');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const wrap = document.getElementById(`qImgWrap-${index}`);
                let img = document.getElementById(`qImgView-${index}`);
                const fileIconArea = document.getElementById(`fileIconArea-${index}`);

                if (!img) {
                    img = document.createElement('img');
                    img.id = `qImgView-${index}`;
                    img.alt = 'Question image';
                    wrap.appendChild(img);
                }
                img.src = e.target.result;
                img.style.display = 'block';
                wrap.classList.add('show');
                if (fileIconArea) fileIconArea.classList.add('hidden');
                scheduleAutoSave();
            };
            reader.readAsDataURL(file);
        }

        function detachImage(index) {
            const wrap = document.getElementById(`qImgWrap-${index}`);
            const img = document.getElementById(`qImgView-${index}`);
            const fileIconArea = document.getElementById(`fileIconArea-${index}`);
            const input = document.getElementById(`qImgFile-${index}`);

            if (img) {
                img.src = '';
                img.style.display = 'none';
            }
            if (wrap) wrap.classList.remove('show');
            if (fileIconArea) fileIconArea.classList.remove('hidden');
            if (input) input.value = '';
            scheduleAutoSave();
        }

        async function goBack() {
            saveCurrent();

            const flashKey = 'flashcardsData_' + (NOTE_ID || 'new');
            sessionStorage.setItem(flashKey, JSON.stringify({
                subject: SUBJECT_TITLE,
                cards: cards
            }));

            if (NOTE_ID && NOTE_ID > 0) {
                try {
                    await autoSaveToServer();
                    showToast('Saved to database!');
                } catch (err) {
                    console.error('Autosave failed:', err);
                }
            }

            const params = new URLSearchParams();
            if (NOTE_ID) params.append('note_id', NOTE_ID);
            window.location.href = 'addsubject.php?' + params.toString();
        }

        function triggerUpload() {
            document.getElementById(`fileIn-${currentIndex}`).click();
        }

        const wrapper = document.getElementById('cardDeckWrapper');
        let tsX = 0, tsY = 0, isSwiping = false;

        wrapper.addEventListener('touchstart', e => {
            tsX = e.changedTouches[0].screenX;
            tsY = e.changedTouches[0].screenY;
            isSwiping = true;
        }, { passive: true });

        wrapper.addEventListener('touchmove', e => {
            if (!isSwiping) return;
            const diffY = Math.abs(tsY - e.changedTouches[0].screenY);
            const diffX = Math.abs(tsX - e.changedTouches[0].screenX);
            if (diffY > diffX && diffY > 10) isSwiping = false;
        }, { passive: true });

        wrapper.addEventListener('touchend', e => {
            if (!isSwiping) return;
            const diff = tsX - e.changedTouches[0].screenX;
            if (Math.abs(diff) > 50) {
                if (diff > 0 && currentIndex < cards.length - 1) {
                    saveCurrent();
                    goTo(currentIndex + 1);
                } else if (diff < 0 && currentIndex > 0) {
                    saveCurrent();
                    goTo(currentIndex - 1);
                }
            }
            isSwiping = false;
        }, { passive: true });

        window.addEventListener('resize', () => {
            requestAnimationFrame(updateUI);
        });

        window.addEventListener('DOMContentLoaded', () => {
            let sessionCards = null;
            const flashKey = 'flashcardsData_' + (NOTE_ID || 'new');
            const flashRaw = sessionStorage.getItem(flashKey);

            if (flashRaw) {
                try {
                    const flashData = JSON.parse(flashRaw);
                    if (flashData.cards && flashData.cards.length > 0) {
                        sessionCards = flashData.cards;
                    }
                } catch (e) {
                    console.error('Error parsing session flashcards:', e);
                }
            }

            if (existingQuestions && existingQuestions.length > 0) {
                cards = existingQuestions;
            } else if (sessionCards) {
                cards = sessionCards;
            } else {
                cards = [{}];
            }

            saveCurrent();
            lastSavedHash = getCardsHash();
            renderSlides();
        });

        document.getElementById('imageInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const r = new FileReader();
                r.onload = e => {
                    document.getElementById('preview').src = e.target.result;
                    localStorage.setItem('userAvatar', e.target.result);
                };
                r.readAsDataURL(file);
            }
        });
        const av = localStorage.getItem('userAvatar');
        if (av) document.getElementById('preview').src = av;
    </script>
</body>
</html>