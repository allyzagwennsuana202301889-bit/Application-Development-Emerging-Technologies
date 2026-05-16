<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
if (!$student_id) {
    header('Location: index.php');
    exit;
}

// Fetch ONLY user-added subjects (NOT presets)
$stmt = $conn->prepare("
    SELECT sp.*, 
           COALESCE(s.subject_name, n.title) as subject_name
    FROM subject_progress sp
    JOIN student_subjects ss ON sp.subject_id = ss.subject_id 
                            AND sp.source_type = ss.source_type
    LEFT JOIN subjects s ON sp.subject_id = s.subject_id AND sp.source_type = 'subjects'
    LEFT JOIN notes n ON sp.subject_id = n.note_id AND sp.source_type = 'notes'
    WHERE sp.student_id = ? 
      AND ss.student_id = ?
    ORDER BY sp.overall_percent ASC
");
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$subjects = $stmt->get_result();

$subject_data = [];
$total_progress = 0;
$count = 0;
$lowest_subject = null;
$lowest_percent = 100;

while ($row = $subjects->fetch_assoc()) {
    $subject_data[] = $row;
    $total_progress += $row['overall_percent'];
    $count++;
    
    if ($row['overall_percent'] < $lowest_percent) {
        $lowest_percent = $row['overall_percent'];
        $lowest_subject = $row['subject_name'];
    }
}

$average = $count > 0 ? round($total_progress / $count) : 0;

function generateInsight($subject_data, $average, $lowest_subject, $lowest_percent) {
    $insight = "";
    $focus = null;
    
    if (count($subject_data) === 0) {
        return ["No subjects added yet. Start by adding subjects from Lectures!", null];
    }
    
    $highest = max(array_column($subject_data, 'overall_percent'));
    $lowest = min(array_column($subject_data, 'overall_percent'));
    $gap = $highest - $lowest;
    
    if ($gap > 30) {
        $insight = "The Analysis finds that it is harder to balance out the given subjects.\n\nit is suggested to focus one at a time for better management";
        $focus = $lowest_subject;
    } elseif ($average < 40) {
        $insight = "Your overall progress is low. Focus on completing reading materials and retaking quizzes to build a stronger foundation.";
        $focus = $lowest_subject;
    } elseif ($average > 80 && $gap < 15) {
        $insight = "Excellent balance! You're performing consistently across all subjects. Keep maintaining this steady pace.";
        $focus = null;
    } elseif ($lowest_percent < 30) {
        $insight = $lowest_subject . " needs immediate attention. Prioritize reading its lessons and practicing the quiz to catch up.";
        $focus = $lowest_subject;
    } else {
        $insight = "Good progress overall. To improve further, focus on the subjects with the lowest scores and review corrections from past quizzes.";
        $focus = $lowest_subject;
    }
    
    return [$insight, $focus];
}

list($insight_text, $focus_subject) = generateInsight($subject_data, $average, $lowest_subject, $lowest_percent);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics</title>
  <link href="https://fonts.googleapis.com/css2?family=Itim&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    /* Analytics specific styles */
    .analytics-page .container {
      background: #4A90FF;
    }
    
    .analytics-content {
      position: absolute;
      top: 90px;
      bottom: 0;
      left: 0;
      right: 0;
      overflow-y: auto;
      padding: 20px 25px;
    }
    
    .analytics-content::-webkit-scrollbar {
      display: none;
    }
    
    /* Subject progress bars */
    .subject-bar {
      margin-bottom: 20px;
    }
    
    .subject-name {
      font-style: italic;
      font-size: 18px;
      color: white;
      margin-bottom: 6px;
      display: block;
      font-family: 'Itim', cursive;
    }
    
    .bar-container {
      display: flex;
      align-items: center;
      gap: 12px;
      background: white;
      border-radius: 4px;
      padding: 10px 14px;
    }
    
    .bar-visual {
      display: flex;
      gap: 6px;
      flex: 1;
    }
    
    .bar-segment {
      flex: 1;
      height: 40px;
      background: #F4A261;
      border-radius: 3px;
      transition: all 0.3s;
    }
    
    .bar-segment.empty {
      background: transparent;
    }
    
    .bar-percent {
      color: #F4A261;
      font-size: 32px;
      font-style: italic;
      font-weight: bold;
      min-width: 60px;
      text-align: right;
      font-family: 'Itim', cursive;
    }
    
    /* Insight text */
    .insight-box {
      margin-top: 35px;
      line-height: 1.5;
      color: white;
      font-family: 'Itim', cursive;
    }
    
    .insight-box p {
      margin-bottom: 18px;
      font-size: 20px;
    }
    
    /* Confirm button */
    .confirm-btn {
      display: block;
      width: 140px;
      margin: 30px auto 40px;
      padding: 12px 24px;
      background: #000;
      color: white;
      border: none;
      border-radius: 10px;
      font-family: 'Itim', cursive;
      font-size: 16px;
      cursor: pointer;
    }
    
    .confirm-btn:hover {
      background: #333;
    }
    
    /* Empty state */
    .empty-state {
      text-align: center;
      padding: 80px 20px;
      color: white;
      font-family: 'Itim', cursive;
    }
    
    .empty-state p {
      font-size: 20px;
      margin-bottom: 10px;
    }
    
    .empty-state .sub {
      font-size: 16px;
      opacity: 0.8;
    }
  </style>
</head>
<body class="analytics-page">

<div class="container">

  <!-- NAV -->
  <nav class="nav">
    <span class="hamburger" onclick="toggleSidebar()">&#9776;</span>
    <img src="bell.png" class="bell" onclick="window.location.href='notifications.php'">
  </nav>

  <!-- SIDEBAR -->
  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" class="help">
      <img src="back.png" class="back">
    </div>
     <!-- Profile Image Upload -->
    <form id="pfpForm" enctype="multipart/form-data" style="display: contents;">
      <label for="imageInput" style="cursor: pointer; position: relative;">
        <img id="preview" src="<?= !empty($_SESSION['profile_image']) ? $_SESSION['profile_image'] : 'acc.png' ?>" 
             style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover;">
      </label>
      <input type="file" id="imageInput" name="profile_image" accept="image/*" hidden onchange="uploadPFP()">
    </form>
    <h3><?php echo $_SESSION['name'] ?? 'Guest'; ?></h3>
    <p><?php echo $_SESSION['email'] ?? ''; ?></p>
    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="#">Analytics</a>
    <a href="#">Leaderboard</a>
    <a href="settings.html">Settings</a>
    <a href="index.php">Log out</a>
  </div>

  <div class="overlay"></div>

  <!-- CONTENT -->
  <div class="analytics-content">

    <?php if (count($subject_data) === 0): ?>
      <div class="empty-state">
        <p>No subjects added yet.</p>
        <p class="sub">Add subjects from Lectures to see your analytics!</p>
        <button class="confirm-btn" onclick="window.location.href='lectures.php'">Go to Lectures</button>
      </div>
    <?php else: ?>

      <?php foreach ($subject_data as $subj): 
          $percent = $subj['overall_percent'];
          $filled = round($percent / 20);
      ?>
        <div class="subject-bar">
          <span class="subject-name"><?= htmlspecialchars($subj['subject_name']) ?></span>
          <div class="bar-container">
            <div class="bar-visual">
              <?php for ($i = 0; $i < 5; $i++): ?>
                <div class="bar-segment <?= $i < $filled ? '' : 'empty' ?>"></div>
              <?php endfor; ?>
            </div>
            <span class="bar-percent"><?= $percent ?>%</span>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="insight-box">
        <?php 
        $paragraphs = explode("\n\n", $insight_text);
        foreach ($paragraphs as $p): 
        ?>
          <p><?= nl2br(htmlspecialchars($p)) ?></p>
        <?php endforeach; ?>
      </div>

      <button class="confirm-btn" onclick="window.location.href='homepage.php'">Confirm</button>

    <?php endif; ?>

  </div>

</div>

<script src="script.js"></script>
</body>
</html>