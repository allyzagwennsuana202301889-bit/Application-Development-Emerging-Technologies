<?php
session_start();
include 'database.php';

$note_id = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;

if ($note_id <= 0) {
    echo "Invalid note.";
    exit;
}

// Get note info
$stmt = $conn->prepare("SELECT title FROM notes WHERE note_id = ?");
$stmt->bind_param("i", $note_id);
$stmt->execute();
$result = $stmt->get_result();
$note = $result->fetch_assoc();

if (!$note) {
    echo "Note not found.";
    exit;
}

$subject_title = $note['title'] ?? 'Untitled';

// Helper function to handle base64 image data
function handleBase64Image($base64Data, $existingImage = '') {
    // If no new base64 data provided, keep existing
    if (empty($base64Data) || $base64Data === $existingImage) {
        return ['path' => $existingImage];
    }
    
    // Validate it's a base64 image
    if (!preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
        return ['error' => 'Invalid image data format.'];
    }
    
    $imageType = strtolower($matches[1]);
    $allowedTypes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
    
    if (!in_array($imageType, $allowedTypes)) {
        return ['error' => 'Invalid image type. Only JPEG, PNG, GIF, WEBP allowed.'];
    }
    
    // Decode base64
    $base64String = preg_replace('/^data:image\/\w+;base64,/', '', $base64Data);
    $imageData = base64_decode($base64String);
    
    if ($imageData === false) {
        return ['error' => 'Failed to decode image data.'];
    }
    
    // Check size (max 5MB decoded)
    if (strlen($imageData) > 5 * 1024 * 1024) {
        return ['error' => 'Image too large. Max 5MB.'];
    }
    
    return ['path' => $base64Data];
}

// Handle AJAX save requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'update_flashcard') {
        $question_id = (int)$_POST['question_id'];
        $question = $_POST['question'];
        $question_type = $_POST['question_type'];
        $correct_answer = $_POST['correct_answer'] ?? '';
        $choices = $_POST['choices'] ?? '[]';
        $existing_image = $_POST['question_image'] ?? '';
        $base64_image = $_POST['question_image_base64'] ?? '';
        
        // Handle base64 image
        $imageResult = handleBase64Image($base64_image, $existing_image);
        if (isset($imageResult['error'])) {
            echo json_encode(['success' => false, 'error' => $imageResult['error']]);
            exit;
        }
        $question_image = $imageResult['path'];

        $upd = $conn->prepare("UPDATE quiz_questions SET question = ?, question_type = ?, correct_answer = ?, choices = ?, question_image = ? WHERE question_id = ? AND quiz_id = ?");
        $upd->bind_param("sssssii", $question, $question_type, $correct_answer, $choices, $question_image, $question_id, $note_id);

        if ($upd->execute()) {
            echo json_encode(['success' => true, 'question_image' => $question_image]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    }

    if ($_POST['action'] === 'delete_flashcard') {
        $question_id = (int)$_POST['question_id'];

        $del = $conn->prepare("DELETE FROM quiz_questions WHERE question_id = ? AND quiz_id = ?");
        $del->bind_param("ii", $question_id, $note_id);

        if ($del->execute()) {
            $conn->query("SET @row_number = 0");
            $conn->query("UPDATE quiz_questions SET question_order = (@row_number:=@row_number+1) WHERE quiz_id = $note_id ORDER BY question_order ASC");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    }

    if ($_POST['action'] === 'add_flashcard') {
        $question = $_POST['question'];
        $question_type = $_POST['question_type'];
        $correct_answer = $_POST['correct_answer'] ?? '';
        $choices = $_POST['choices'] ?? '[]';
        $existing_image = $_POST['question_image'] ?? '';
        $base64_image = $_POST['question_image_base64'] ?? '';
        
        // Handle base64 image
        $imageResult = handleBase64Image($base64_image, $existing_image);
        if (isset($imageResult['error'])) {
            echo json_encode(['success' => false, 'error' => $imageResult['error']]);
            exit;
        }
        $question_image = $imageResult['path'];

        $ord = $conn->query("SELECT MAX(question_order) as max_ord FROM quiz_questions WHERE quiz_id = $note_id");
        $order = ($ord->fetch_assoc()['max_ord'] ?? 0) + 1;

        $ins = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question, question_type, choices, correct_answer, question_order, question_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("issssis", $note_id, $question, $question_type, $choices, $correct_answer, $order, $question_image);

        if ($ins->execute()) {
            $new_id = $ins->insert_id;
            echo json_encode(['success' => true, 'question_id' => $new_id, 'question_order' => $order, 'question_image' => $question_image]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    }
}

// Get flashcards
$flashcards = [];
$fc_stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order ASC");
$fc_stmt->bind_param("i", $note_id);
$fc_stmt->execute();
$fc_result = $fc_stmt->get_result();

while ($row = $fc_result->fetch_assoc()) {
    $row['choices'] = json_decode($row['choices'], true) ?? [];
    $flashcards[] = $row;
}

$flashcard_count = count($flashcards);

$username = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Insert Username';
$useremail = isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'username@gmail.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Flashcards - <?= htmlspecialchars($subject_title) ?> (<?= $flashcard_count ?>)</title>
    <link href="https://fonts.googleapis.com/css2?family=Inria+Sans:wght@400&family=Itim&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .flashcards-read-page .container {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #3B8BFF;
        }

        .flashcards-read-page .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #3B8BFF;
            flex-shrink: 0;
        }

        .flashcards-read-page .hamburger {
            font-size: 28px;
            cursor: pointer;
            color: #1a1a2e;
            line-height: 1;
        }

        .flashcards-read-page .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .flashcards-read-page .bell {
            width: 28px;
            height: 28px;
            cursor: pointer;
        }

        .flashcards-read-page .back-btn-icon {
            width: 28px;
            height: 28px;
            cursor: pointer;
        }

        .flashcards-read-page .question-counter {
            position: absolute;
            top: 14px;
            right: 16px;
            color: #888;
            font-size: 12px;
            font-family: 'Inria Sans', sans-serif;
            font-style: italic;
            z-index: 5;
        }

        .flashcards-read-page .card-deck-wrapper {
            position: relative;
            flex: 1;
            overflow: hidden;
            width: 100%;
        }

        .flashcards-read-page .cards-track {
            display: flex;
            height: 100%;
            width: 100%;
            transition: transform 0.35s ease-out;
            will-change: transform;
        }

        .flashcards-read-page .question-slide {
            width: 100%;
            height: 100%;
            flex: 0 0 100%;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 0 12px 20px;
            box-sizing: border-box;
        }

        .flashcards-read-page .question-slide::-webkit-scrollbar {
            display: none;
        }

        /* ========== QUESTION CARD (WHITE) ========== */
        .flashcards-read-page .q-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-shrink: 0;
            margin-bottom: 12px;
            position: relative;
        }

        .flashcards-read-page .q-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .flashcards-read-page .subject-field {
            background: transparent;
            border: none;
            font-size: 12px;
            color: #888;
            font-style: italic;
            font-family: 'Inria Sans', sans-serif;
            flex: 1;
            outline: none;
        }

        .flashcards-read-page .q-num-field {
            background: transparent;
            border: none;
            font-size: 12px;
            color: #888;
            font-style: italic;
            font-family: 'Inria Sans', sans-serif;
            width: auto;
            text-align: right;
            outline: none;
        }

        .flashcards-read-page .q-textarea {
            width: 100%;
            border: none;
            background: transparent;
            font-size: 16px;
            font-family: 'Inria Sans', sans-serif;
            color: #1a1a2e;
            resize: none;
            outline: none;
            min-height: 40px;
            line-height: 1.4;
        }

        .flashcards-read-page .q-textarea::placeholder {
            color: #999;
            font-style: italic;
        }

        /* ========== ANSWER CARD (WHITE) ========== */
        .flashcards-read-page .morph-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex-shrink: 0;
            position: relative;
        }

        .flashcards-read-page .delete-q-btn {
            position: absolute;
            top: -12px;
            right: 8px;
            width: 28px;
            height: 28px;
            background: #ff4444;
            color: white;
            border: 2px solid #ffffff;
            border-radius: 50%;
            font-size: 18px;
            font-weight: bold;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 20;
            line-height: 1;
            padding-bottom: 2px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .flashcards-read-page .delete-q-btn:active {
            background: #cc0000;
            transform: scale(0.95);
        }

        .flashcards-read-page .edit-mode .delete-q-btn {
            display: flex;
        }

        /* ========== IMAGE DISPLAY (in morph card) ========== */
        .flashcards-read-page .q-image-box {
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            display: none;
            background: #ddd;
            min-height: 60px;
            margin-bottom: 10px;
        }
        .flashcards-read-page .q-image-box.show {
            display: block;
        }
        .flashcards-read-page .q-image-box img {
            width: 100%;
            height: auto;
            max-height: 200px;
            object-fit: contain;
            border-radius: 10px;
            display: block;
            background: #f5f5f5;
        }
        .flashcards-read-page .q-image-box .rm-img {
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
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            padding-bottom: 2px;
        }
        .flashcards-read-page .q-image-box .rm-img:active {
            transform: scale(0.9);
        }
        /* Show remove button only in edit mode */
        .flashcards-read-page .edit-mode .q-image-box .rm-img {
            display: flex;
        }

        /* File icon (shown when no image) */
        .flashcards-read-page .file-icon-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin-bottom: 10px;
        }
        .flashcards-read-page .file-icon-display.hidden {
            display: none;
        }
        .flashcards-read-page .file-icon-display .file-icon-btn {
            width: 60px;
            height: 70px;
            border: 2px solid #999;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: transparent;
            position: relative;
        }
        .flashcards-read-page .file-icon-display .file-icon-btn svg {
            width: 32px;
            height: 32px;
            stroke: #888;
            fill: none;
            stroke-width: 1.5;
        }
        .flashcards-read-page .file-icon-display .file-plus {
            position: absolute;
            top: 18px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 20px;
            color: #888;
            font-weight: 300;
        }
        .flashcards-read-page .file-icon-display .file-label {
            margin-top: 6px;
            font-size: 13px;
            color: #888;
        }
        /* In edit mode, file icon is clickable */
        .flashcards-read-page .edit-mode .file-icon-display .file-icon-btn {
            cursor: pointer;
            border-color: #3B8BFF;
            background: #e8f4fd;
        }
        .flashcards-read-page .edit-mode .file-icon-display .file-icon-btn:active {
            transform: scale(0.95);
        }
        /* In edit mode, image is clickable to replace */
        .flashcards-read-page .edit-mode .q-image-box {
            cursor: pointer;
            border: 2px dashed #3B8BFF;
        }
        .flashcards-read-page .edit-mode .q-image-box::after {
            content: "Tap to replace image";
            position: absolute;
            bottom: 8px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-family: 'Inria Sans', sans-serif;
            pointer-events: none;
        }

        /* Selector state */
        .flashcards-read-page .selector-state {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }

        .flashcards-read-page .selector-state .type-label {
            font-size: 14px;
            color: #1a1a2e;
            font-family: 'Inria Sans', sans-serif;
            align-self: flex-start;
            margin: 0;
        }

        .flashcards-read-page .type-btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-family: 'Inria Sans', sans-serif;
            cursor: pointer;
            transition: 0.2s;
            background: #67AFEE;
            color: #1a1a2e;
        }

        .flashcards-read-page .type-btn:hover {
            background: #5a9de0;
        }

        /* Choices state */
        .flashcards-read-page .choices-state {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .flashcards-read-page .morph-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .flashcards-read-page .morph-back {
            color: #3B8BFF;
            font-size: 14px;
            cursor: pointer;
            font-family: 'Inria Sans', sans-serif;
        }

        .flashcards-read-page .morph-title {
            font-size: 14px;
            color: #333;
            font-weight: 600;
            font-family: 'Inria Sans', sans-serif;
        }

        .flashcards-read-page .choice-field {
            width: 100%;
            padding: 14px 16px;
            border: none;
            border-radius: 12px;
            background: #67AFEE;
            font-size: 14px;
            font-family: 'Inria Sans', sans-serif;
            color: #1a1a2e;
            outline: none;
            text-align: left;
            line-height: 1.4;
            box-sizing: border-box;
        }

        .flashcards-read-page .choice-field.correct {
            background: #28a745;
            color: white;
        }

        .flashcards-read-page .choice-hint {
            font-size: 12px;
            color: #888;
            text-align: center;
            margin: 4px 0 0;
            font-family: 'Inria Sans', sans-serif;
        }

        /* Answer state */
        .flashcards-read-page .answer-state {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .flashcards-read-page .answer-label {
            font-size: 14px;
            color: #333;
            font-family: 'Inria Sans', sans-serif;
            margin: 0;
        }

        .flashcards-read-page .answer-field {
            width: 100%;
            padding: 14px 16px;
            border: none;
            border-radius: 12px;
            background: #67AFEE;
            font-size: 14px;
            font-family: 'Inria Sans', sans-serif;
            color: #1a1a2e;
            outline: none;
            text-align: center;
            box-sizing: border-box;
        }

        .flashcards-read-page .answer-field::placeholder {
            color: rgba(26, 26, 46, 0.6);
            font-style: italic;
        }

        /* Hidden state */
        .flashcards-read-page .hidden {
            display: none !important;
        }

        /* Identification answer input (read view) */
        .flashcards-read-page .answer-input-wrapper {
            display: flex;
            justify-content: center;
            padding: 20px 0;
        }

        .flashcards-read-page .answer-display {
            width: 80%;
            padding: 12px 16px;
            border: none;
            border-radius: 12px;
            background: #67AFEE;
            font-size: 14px;
            font-family: 'Inria Sans', sans-serif;
            color: #1a1a2e;
            outline: none;
            text-align: center;
        }

        /* Multiple choice buttons (read view) */
        .flashcards-read-page .choice-btn {
            width: 100%;
            padding: 14px 16px;
            border: none;
            border-radius: 12px;
            background: #67AFEE;
            font-size: 14px;
            font-family: 'Inria Sans', sans-serif;
            color: #1a1a2e;
            outline: none;
            text-align: left;
            cursor: default;
            line-height: 1.4;
            margin-bottom: 8px;
            box-sizing: border-box;
        }

        .flashcards-read-page .choice-btn:last-child {
            margin-bottom: 0;
        }

        .flashcards-read-page .choice-btn.correct {
            background: #28a745;
            color: white;
        }

        /* ========== PROGRESS DOTS ========== */
        .flashcards-read-page .progress-dots {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 12px 0;
            flex-shrink: 0;
            background: #3B8BFF;
        }

        .flashcards-read-page .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            transition: 0.3s;
            cursor: pointer;
            flex-shrink: 0;
        }

        .flashcards-read-page .dot.active {
            background: #fff;
            transform: scale(1.2);
        }

        /* ========== BOTTOM NAV ========== */
        .flashcards-read-page .bottom-file-section {
            position: relative;
            width: 100%;
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 10px 20px 16px;
            background: #3B8BFF;
            z-index: 100;
            flex-shrink: 0;
        }

        .flashcards-read-page .item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            cursor: pointer;
        }

        .flashcards-read-page .item img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }

        .flashcards-read-page .item p {
            font-size: 12px;
            color: #1a1a2e;
            font-family: 'Inria Sans', sans-serif;
            margin: 0;
        }

        /* ========== NO FLASHCARDS ========== */
        .flashcards-read-page .no-flashcards {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: rgba(255,255,255,0.8);
            font-size: 18px;
            padding: 40px;
        }

        .flashcards-read-page .no-flashcards p {
            font-size: 14px;
            color: rgba(255,255,255,0.6);
            margin-top: 8px;
        }

        /* ========== EDIT MODE ========== */
        .flashcards-read-page .edit-mode .q-textarea {
            background: #e8f4fd;
            border: 2px dashed #3B8BFF;
            border-radius: 10px;
            padding: 10px;
        }

        .flashcards-read-page .edit-mode .answer-display,
        .flashcards-read-page .edit-mode .choice-btn {
            background: #e8f4fd;
            border: 2px dashed #3B8BFF;
            cursor: text;
        }

        .flashcards-read-page .edit-mode .choice-btn.correct {
            background: #d4edda;
            border: 2px dashed #28a745;
            color: #155724;
        }

        /* Toast */
        .flashcards-read-page .toast-msg {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            background: #333;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 300;
            opacity: 0;
            transition: 0.3s;
            pointer-events: none;
            font-family: 'Inria Sans', sans-serif;
        }

        .flashcards-read-page .toast-msg.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    </style>
</head>
<body class="flashcards-read-page">
    <div class="container">

        <nav class="nav">
            <span class="hamburger" onclick="toggleSidebar()">&#9776;</span>
            <div class="nav-right">    
                <img src="bell.png" onclick="notif()" >
                <img src="back.png" class="back-btn-icon" onclick="goBack()" alt="Back">
            </div>
        </nav>

        <div class="nav-links" id="sidebar">
            <div class="top-icons">
                <img src="FAQIcon.png" class="help" alt="Help">
                <img src="back.png" class="back" onclick="toggleSidebar()" alt="Close">
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

            <h3><?php echo $username; ?></h3>
            <p><?php echo $useremail; ?></p>
            <a href="homepage.php">Home</a>
            <a href="notes.php">Notes</a>
            <a href="#">Analytics</a>
            <a href="#">Leaderboard</a>
            <a href="settings.html">Settings</a>
            <a href="logout.php">Log out</a>
        </div>

        <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

        <div class="card-deck-wrapper" id="cardDeckWrapper">
            <div class="cards-track" id="cardsTrack"></div>
        </div>

        <div class="progress-dots" id="progressDots"></div>

        <div class="bottom-file-section">
            <div class="item" id="editItem" onclick="toggleEditMode()">
                <img src="edit.png" alt="Edit" id="editIcon">
                <p id="editLabel">Edit</p>
            </div>
            <div class="item" onclick="addNewQuestion()">
                <img src="add.png" alt="Add">
                <p>Add</p>
            </div>
            <div class="item" onclick="upload()">
                <img src="uploaded.png" alt="Back">
                <p>Uploads</p>
            </div>
        </div>

        <div class="toast-msg" id="toastMsg"></div>
    </div>

    <script src="script.js"></script>
    <script>
        const NOTE_ID = <?php echo $note_id; ?>;
        const SUBJECT_TITLE = "<?php echo addslashes($subject_title); ?>";
        let cards = <?php echo json_encode($flashcards, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        let currentIndex = 0;
        let isEditMode = false;
        let isAdding = false;

        console.log('Flashcards loaded:', cards);
        console.log('Count:', cards.length);

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

        function esc(text) {
            if (!text) return '';
            const d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }

        // ===== HELPER: Build image section for morph card =====
        function buildImageSection(data, index) {
            const hasImage = data && data.question_image ? true : false;
            const imgSrc = data && data.question_image ? data.question_image : '';

            return `
                <!-- Image preview (if attached) -->
                <div class="q-image-box ${hasImage ? 'show' : ''}" id="qImgWrap-${index}" onclick="if(isEditMode) pickImage(${index})">
                    ${hasImage ? `<img id="qImgView-${index}" src="${esc(imgSrc)}" alt="Question image">` : `<img id="qImgView-${index}" src="" alt="Question image" style="display:none;">`}
                    <button class="rm-img" onclick="event.stopPropagation(); detachImage(${index})" title="Remove image">×</button>
                </div>
                <!-- File icon (shown when no image) -->
                <div class="file-icon-display ${hasImage ? 'hidden' : ''}" id="fileIconArea-${index}" onclick="if(isEditMode) pickImage(${index})">
                    <div class="file-icon-btn">
                        <svg viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <span class="file-plus">+</span>
                    </div>
                    <span class="file-label">File</span>
                </div>
                <input type="file" id="qImgFile-${index}" accept="image/*" hidden onchange="onImagePicked(${index}, this)">
            `;
        }

        // ===== CREATE SLIDE HTML =====
        function createSlideHTML(index, data) {
            const qNum = index + 1;
            const question = data ? (data.question || '') : '';
            const qType = data ? (data.question_type || '') : '';
            const choices = data ? (data.choices || []) : [];
            const answer = data ? (data.correct_answer || '') : '';
            const qId = data ? (data.question_id || 0) : 0;
            const isNew = data ? (data.isNew || false) : false;

            if (isNew) {
                return createNewSlideHTML(index);
            }

            const imageSection = buildImageSection(data, index);

            if (qType === 'choice') {
                let choicesHtml = '';
                choices.forEach((ch, i) => {
                    const correctClass = ch.correct ? 'correct' : '';
                    const label = ['A', 'B', 'C', 'D'][i] || String.fromCharCode(65 + i);
                    choicesHtml += `<button type="button" class="choice-btn ${correctClass}" data-choice-idx="${i}" onclick="if(isEditMode) toggleCorrect(this)">${esc(label + '. ' + (ch.text || ''))}</button>`;
                });

                return `
                    <div class="question-slide" data-idx="${index}" data-qid="${qId}">
                        <div class="q-card">
                            <button class="delete-q-btn" onclick="deleteCard(${index})" title="Delete">−</button>
                            <span class="question-counter">Question ${qNum} / ${cards.length}</span>
                            <div class="q-card-header">
                                <input type="text" value="${esc(SUBJECT_TITLE)}" class="subject-field" readonly>
                            </div>
                            <textarea class="q-textarea" readonly data-field="question">${esc(question)}</textarea>
                        </div>
                        <div class="morph-card">
                            ${imageSection}
                            ${choicesHtml}
                        </div>
                    </div>
                `;
            } else {
                return `
                    <div class="question-slide" data-idx="${index}" data-qid="${qId}">
                        <div class="q-card">
                            <button class="delete-q-btn" onclick="deleteCard(${index})" title="Delete">−</button>
                            <span class="question-counter">Question ${qNum} / ${cards.length}</span>
                            <div class="q-card-header">
                                <input type="text" value="${esc(SUBJECT_TITLE)}" class="subject-field" readonly>
                            </div>
                            <textarea class="q-textarea" readonly data-field="question">${esc(question)}</textarea>
                        </div>
                        <div class="morph-card">
                            ${imageSection}
                            <div class="answer-input-wrapper">
                                <input type="text" class="answer-display" value="${esc(answer)}" readonly data-field="answer" placeholder="(Your answer)">
                            </div>
                        </div>
                    </div>
                `;
            }
        }

        // New question slide
        function createNewSlideHTML(index) {
            return `
                <div class="question-slide" data-idx="${index}" data-qid="0" data-is-new="true">
                    <div class="q-card">
                        <button class="delete-q-btn" onclick="cancelAdd()" title="Cancel">×</button>
                        <span class="question-counter">Question ${index + 1} / ${cards.length}</span>
                        <div class="q-card-header">
                            <input type="text" value="${esc(SUBJECT_TITLE)}" class="subject-field" readonly>
                        </div>
                        <textarea class="q-textarea" placeholder="(insert question here)" data-field="question" id="newQText"></textarea>
                    </div>
                    <div class="morph-card" id="newMorphCard" data-type="">
                        <div class="file-icon-display" id="fileIconArea-${index}" onclick="pickImage(${index})">
                            <div class="file-icon-btn">
                                <svg viewBox="0 0 24 24">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                </svg>
                                <span class="file-plus">+</span>
                            </div>
                            <span class="file-label">File</span>
                        </div>
                        <div class="q-image-box" id="qImgWrap-${index}">
                            <img id="qImgView-${index}" src="" alt="Question image" style="display:none;">
                            <button class="rm-img" onclick="event.stopPropagation(); detachImage(${index})" title="Remove image">×</button>
                        </div>
                        <input type="file" id="qImgFile-${index}" accept="image/*" hidden onchange="onImagePicked(${index}, this)">

                        <div class="selector-state" id="newSelector">
                            <p class="type-label">What kind of questionnaire you making?</p>
                            <button class="type-btn" onclick="newToChoices()">choice based</button>
                            <button class="type-btn" onclick="newToIdentification()">Identification</button>
                        </div>

                        <div class="choices-state hidden" id="newChoicesState">
                            <div class="morph-header">
                                <span class="morph-back" onclick="newGoBack()">&#8249; Back</span>
                                <span class="morph-title">Multiple Choice</span>
                            </div>
                            <input type="text" class="choice-field" placeholder="Choice A" data-choice-idx="0" id="newCh0">
                            <input type="text" class="choice-field" placeholder="Choice B" data-choice-idx="1" id="newCh1">
                            <input type="text" class="choice-field" placeholder="Choice C" data-choice-idx="2" id="newCh2">
                            <input type="text" class="choice-field" placeholder="Choice D" data-choice-idx="3" id="newCh3">
                            <p class="choice-hint">Tap the correct answer to highlight it</p>
                        </div>

                        <div class="answer-state hidden" id="newAnswerState">
                            <div class="morph-header">
                                <span class="morph-back" onclick="newGoBack()">&#8249; Back</span>
                                <span class="morph-title">Identification</span>
                            </div>
                            <p class="answer-label">What is the correct answer?</p>
                            <input type="text" class="answer-field" placeholder="(Type the correct answer here)" id="newAnsField">
                        </div>
                    </div>
                </div>
            `;
        }

        function renderSlides() {
            const track = document.getElementById('cardsTrack');
            track.innerHTML = '';

            if (!cards || cards.length === 0) {
                track.innerHTML = `
                    <div class="question-slide no-flashcards">
                        <div>No flashcards made</div>
                        <p>This subject has no flashcards yet.</p>
                    </div>
                `;
                document.getElementById('progressDots').innerHTML = '';
                return;
            }

            let html = '';
            cards.forEach((c, i) => {
                html += createSlideHTML(i, c);
            });
            track.innerHTML = html;

            if (isAdding) {
                document.querySelectorAll('#newChoicesState .choice-field').forEach(field => {
                    field.addEventListener('click', function() {
                        document.querySelectorAll('#newChoicesState .choice-field').forEach(f => f.classList.remove('correct'));
                        this.classList.add('correct');
                    });
                });
            }

            requestAnimationFrame(updateUI);
        }

        function updateUI() {
            if (!cards || cards.length === 0) return;
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
            if (!cards || cards.length === 0) return;
            if (idx >= 0 && idx < cards.length) {
                currentIndex = idx;
                updateUI();
            }
        }

        function goBack() {
            window.location.href = 'viewnote.php?note_id=' + NOTE_ID;
        }

        // ===== IMAGE HANDLING (BASE64) =====
        function pickImage(index) {
            if (!isEditMode && !isAdding) return;
            document.getElementById(`qImgFile-${index}`).click();
        }

        function onImagePicked(index, input) {
            const file = input.files[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                showToast('Please select an image file');
                return;
            }
            // Check file size before reading (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                showToast('Image too large. Max 5MB.');
                input.value = '';
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
                showToast('Image attached');
            };
            reader.readAsDataURL(file);
        }

        function detachImage(index) {
            if (!isEditMode && !isAdding) return;
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
            showToast('Image removed');
        }

        function getImageData(index) {
            const img = document.getElementById(`qImgView-${index}`);
            if (img && img.src && img.src !== '' && img.style.display !== 'none') {
                return img.src;
            }
            return '';
        }

        // ===== ADD NEW QUESTION =====
        function addNewQuestion() {
            if (!isEditMode) {
                showToast('Enter edit mode first');
                return;
            }
            
            if (isAdding) {
                showToast('Finish adding current question first');
                return;
            }

            isAdding = true;
            cards.push({ isNew: true });
            currentIndex = cards.length - 1;
            renderSlides();
            showToast('Fill in the new question');
        }

        function cancelAdd() {
            cards.pop();
            isAdding = false;
            if (currentIndex >= cards.length) currentIndex = Math.max(0, cards.length - 1);
            renderSlides();
            showToast('Cancelled');
        }

        function newToChoices() {
            document.getElementById('newSelector').classList.add('hidden');
            document.getElementById('newChoicesState').classList.remove('hidden');
            document.getElementById('newMorphCard').setAttribute('data-type', 'choice');
        }

        function newToIdentification() {
            document.getElementById('newSelector').classList.add('hidden');
            document.getElementById('newAnswerState').classList.remove('hidden');
            document.getElementById('newMorphCard').setAttribute('data-type', 'identification');
        }

        function newGoBack() {
            document.getElementById('newSelector').classList.remove('hidden');
            document.getElementById('newChoicesState').classList.add('hidden');
            document.getElementById('newAnswerState').classList.add('hidden');
            document.getElementById('newMorphCard').setAttribute('data-type', '');
        }

        function submitNewCard() {
            const question = document.getElementById('newQText').value.trim();
            if (!question) {
                showToast('Please enter a question');
                return;
            }

            const morphType = document.getElementById('newMorphCard').getAttribute('data-type');
            if (!morphType) {
                showToast('Please select a question type');
                return;
            }

            let correctAnswer = '';
            let choices = [];
            const questionImage = getImageData(currentIndex);

            if (morphType === 'identification') {
                correctAnswer = document.getElementById('newAnsField').value.trim();
                if (!correctAnswer) {
                    showToast('Please enter the correct answer');
                    return;
                }
            } else {
                const choiceFields = document.querySelectorAll('#newChoicesState .choice-field');
                let hasCorrect = false;
                let hasEmpty = false;

                choiceFields.forEach((field, i) => {
                    const val = field.value.trim();
                    if (!val) hasEmpty = true;
                    const isCorrect = field.classList.contains('correct');
                    if (isCorrect) hasCorrect = true;
                    choices.push({ text: val, correct: isCorrect });
                    if (isCorrect) correctAnswer = val;
                });

                if (hasEmpty) {
                    showToast('Please fill all choices');
                    return;
                }
                if (!hasCorrect) {
                    showToast('Please select the correct answer');
                    return;
                }
            }

            const formData = new FormData();
            formData.append('action', 'add_flashcard');
            formData.append('question', question);
            formData.append('question_type', morphType);
            formData.append('correct_answer', correctAnswer);
            formData.append('choices', JSON.stringify(choices));
            formData.append('question_image', ''); // No file path needed
            formData.append('question_image_base64', questionImage); // Send base64 data

            fetch('readflashcards.php?note_id=' + NOTE_ID, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    cards[currentIndex] = {
                        question_id: data.question_id,
                        question: question,
                        question_type: morphType,
                        correct_answer: correctAnswer,
                        choices: choices,
                        question_order: data.question_order,
                        question_image: data.question_image || questionImage,
                        isNew: false
                    };
                    isAdding = false;
                    renderSlides();
                    showToast('Question added!');
                } else {
                    showToast('Error: ' + (data.error || 'Failed to add'));
                }
            })
            .catch(err => {
                showToast('Error adding question');
                console.error(err);
            });
        }

        // ===== EDIT MODE =====
        function toggleEditMode() {
            const editItem = document.getElementById('editItem');
            const editIcon = document.getElementById('editIcon');
            const editLabel = document.getElementById('editLabel');

            if (!isEditMode) {
                isEditMode = true;
                editIcon.src = 'save.png';
                editLabel.textContent = 'Save';

                const slides = document.querySelectorAll('.question-slide');
                slides.forEach((slide) => {
                    slide.classList.add('edit-mode');

                    const qText = slide.querySelector('[data-field="question"]');
                    if (qText) qText.removeAttribute('readonly');

                    const ansField = slide.querySelector('[data-field="answer"]');
                    if (ansField) ansField.removeAttribute('readonly');
                });
                showToast('Edit mode ON - tap image to replace, tap choice to set correct');

            } else {
                if (isAdding) {
                    submitNewCard();
                    return;
                }
                saveCurrentCard();
            }
        }

        function toggleCorrect(btn) {
            if (!isEditMode) return;
            const slide = btn.closest('.question-slide');
            slide.querySelectorAll('.choice-btn').forEach(b => b.classList.remove('correct'));
            btn.classList.add('correct');
            showToast('Correct answer updated');
        }

        function saveCurrentCard() {
            const slide = document.querySelector(`.question-slide[data-idx="${currentIndex}"]`);
            if (!slide) return;

            const qId = slide.getAttribute('data-qid');
            const qText = slide.querySelector('[data-field="question"]')?.value || '';
            const choiceBtns = slide.querySelectorAll('.choice-btn');
            const ansField = slide.querySelector('[data-field="answer"]');
            const questionImage = getImageData(currentIndex);

            let qType = 'identification';
            let correctAnswer = '';
            let choices = [];

            if (choiceBtns.length > 0) {
                qType = 'choice';
                choiceBtns.forEach((btn, i) => {
                    const text = btn.textContent.replace(/^[A-D]\.\s*/, '');
                    const isCorrect = btn.classList.contains('correct');
                    choices.push({ text: text, correct: isCorrect });
                    if (isCorrect) correctAnswer = text;
                });
            } else {
                correctAnswer = ansField?.value || '';
            }

            const formData = new FormData();
            formData.append('action', 'update_flashcard');
            formData.append('question_id', qId);
            formData.append('question', qText);
            formData.append('question_type', qType);
            formData.append('correct_answer', correctAnswer);
            formData.append('choices', JSON.stringify(choices));
            formData.append('question_image', ''); // No file path needed
            formData.append('question_image_base64', questionImage); // Send base64 data

            fetch('readflashcards.php?note_id=' + NOTE_ID, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    cards[currentIndex].question = qText;
                    cards[currentIndex].correct_answer = correctAnswer;
                    cards[currentIndex].question_image = data.question_image || questionImage;
                    if (qType === 'choice') cards[currentIndex].choices = choices;

                    isEditMode = false;
                    document.getElementById('editIcon').src = 'edit.png';
                    document.getElementById('editLabel').textContent = 'Edit';

                    renderSlides();
                    showToast('Saved!');
                } else {
                    showToast('Error: ' + (data.error || 'Failed to save'));
                }
            })
            .catch(err => {
                showToast('Error saving');
                console.error(err);
            });
        }

        // ===== DELETE =====
        function deleteCard(index) {
            if (cards.length <= 1) {
                showToast("Can't delete the only question");
                return;
            }

            if (!confirm('Delete Question ' + (index + 1) + '?')) return;

            const slide = document.querySelector(`.question-slide[data-idx="${index}"]`);
            const qId = slide?.getAttribute('data-qid');

            const formData = new FormData();
            formData.append('action', 'delete_flashcard');
            formData.append('question_id', qId);

            fetch('readflashcards.php?note_id=' + NOTE_ID, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    cards.splice(index, 1);
                    if (currentIndex >= cards.length) currentIndex = Math.max(0, cards.length - 1);

                    if (isEditMode) {
                        isEditMode = false;
                        document.getElementById('editIcon').src = 'edit.png';
                        document.getElementById('editLabel').textContent = 'Edit';
                    }

                    renderSlides();
                    showToast('Deleted!');
                } else {
                    showToast('Error: ' + (data.error || 'Failed to delete'));
                }
            })
            .catch(err => {
                showToast('Error deleting');
                console.error(err);
            });
        }

        // SWIPE
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
                    goTo(currentIndex + 1);
                } else if (diff < 0 && currentIndex > 0) {
                    goTo(currentIndex - 1);
                }
            }
            isSwiping = false;
        }, { passive: true });

        window.addEventListener('resize', () => {
            requestAnimationFrame(updateUI);
        });

        window.addEventListener('DOMContentLoaded', () => {
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