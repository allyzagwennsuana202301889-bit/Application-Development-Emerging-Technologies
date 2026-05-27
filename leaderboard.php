<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
$current_name = $_SESSION['name'] ?? 'Guest';
$current_image = $_SESSION['profile_image'] ?? '';

// Fetch from DB if session empty
if (empty($current_image) && $student_id) {
    $stmt = $conn->prepare("SELECT profile_image FROM student WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $current_image = $result['profile_image'] ?? '';
    $_SESSION['profile_image'] = $current_image;
}

// Get top 3
$stmt = $conn->prepare("
    SELECT 
        sp.student_id,
        st.name,
        st.profile_image,
        AVG(sp.overall_percent) as avg_progress,
        SUM(sp.reading_percent + sp.quiz_percent) as total_points
    FROM subject_progress sp
    JOIN student st ON sp.student_id = st.student_id
    GROUP BY sp.student_id
    ORDER BY avg_progress DESC, total_points DESC
    LIMIT 3
");
$stmt->execute();
$top3 = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Pad to 3
while (count($top3) < 3) {
    $top3[] = ['student_id' => 0, 'name' => '---', 'profile_image' => '', 'avg_progress' => 0, 'total_points' => 0];
}

$ordered = [$top3[1], $top3[0], $top3[2]];
$is_top = ($top3[0]['student_id'] == $student_id && $student_id != 0);

// Helper function for image
function getImage($profile_image) {
    if (!empty($profile_image) && strlen($profile_image) > 100) {
        return htmlspecialchars($profile_image);
    }
    return 'acc.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Leaderboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Itim&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .leaderboard-page .container {
      background: #4A90FF;
    }
    
    .leaderboard-content {
      position: absolute;
      top: 90px;
      bottom: 0;
      left: 0;
      right: 0;
      overflow-y: auto;
      padding: 0 25px 30px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    
    .leaderboard-content::-webkit-scrollbar { display: none; }
    
    /* Podium area */
    .podium {
      display: flex;
      align-items: flex-end;
      justify-content: center;
      gap: 20px;
      margin-top: 20px;
      width: 100%;
    }
    
    .podium-slot {
      display: flex;
      flex-direction: column;
      align-items: center;
      flex: 1;
    }
    
    /* Profile images */
 .lb-avatar {
  width: 70px;
  height: 70px;
  border-radius: 50%;
  object-fit: cover;
  background: transparent;  
}
    
.podium-slot.first .lb-avatar {
  width: 90px;
  height: 90px;
  background: transparent;  
}

.podium-slot.second .lb-avatar,
.podium-slot.third .lb-avatar {
  width: 65px;
  height: 65px;
  background: transparent;  
}
    
    /* Trophy */
    .trophy-img {
      width: 70px;
      height: 70px;
      margin: 10px 0;
      object-fit: contain;
    }
    
    .podium-slot.first .trophy-img {
      width: 90px;
      height: 90px;
    }
    
    /* Name */
    .lb-name {
      color: white;
      font-size: 18px;
      font-style: italic;
      font-family: 'Itim', cursive;
      margin-top: 8px;
      text-align: center;
    }
    
    /* Message */
    .lb-message {
      color: white;
      font-size: 20px;
      font-family: 'Itim', cursive;
      line-height: 1.5;
      text-align: left;
      width: 100%;
      margin-top: 30px;
    }
    
    /* Empty state */
    .lb-empty {
      color: white;
      font-family: 'Itim', cursive;
      font-size: 18px;
      text-align: center;
      padding: 60px 20px;
    }
  </style>
</head>
<body class="leaderboard-page">

<div class="container">

  <!-- NAV -->
  <nav class="nav">
    <span class="hamburger" onclick="toggleSidebar()">&#9776;</span>
    <span style="margin: 0 auto; color: white; font-family: 'Itim', cursive; font-size: 24px;">Leaderboard</span>
   <div class="bell-wrapper" onclick="notif()">
    <img src="bell.png" class="bell">
    <span class="notif-dot" id="bellDot"></span>
  </nav>

  <!-- SIDEBAR -->
  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" onclick="fax()" class="help">
      <img src="back.png" class="back" onclick="closeSidebar()">
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
    <p><?php echo $_SESSION['email'] ?? 'No Email'; ?></p>
    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="analytics.php">Analytics</a>
    <a href="leaderboard.php">Leaderboard</a>
    <a href="settings.php" onclick="sessionStorage.setItem('settingsFrom', window.location.pathname)">Settings</a>
    <a href="logout.php">Log out</a>
  </div>

  <div class="overlay"></div>

  <div class="leaderboard-content">

    <?php if (count($top3) > 0 && $top3[0]['student_id'] != 0): ?>

      <!-- Podium -->
      <div class="podium">
        
        <!-- 2nd Place (Left) -->
        <div class="podium-slot second">
          <img src="<?= getImage($ordered[0]['profile_image']) ?>" 
               class="lb-avatar" 
               onerror="this.src='acc.png'">
          <img src="silver-trophy.png" class="trophy-img" onerror="this.style.display='none'">
          <svg class="trophy-img" viewBox="0 0 100 100" style="display: none;" onload="this.style.display='block'; this.previousElementSibling.style.display='none'">
            <path fill="#C0C0C0" d="M15 20 L15 50 Q15 65 30 65 L70 65 Q85 65 85 50 L85 20 L75 20 L75 50 Q75 55 70 55 L30 55 Q25 55 25 50 L25 20 Z"/>
            <rect x="30" y="65" width="40" height="10" fill="#444"/>
            <rect x="25" y="75" width="50" height="8" fill="#333"/>
          </svg>
          <span class="lb-name"><?= htmlspecialchars($ordered[0]['name']) ?></span>
        </div>

        <!-- 1st Place (Center) -->
        <div class="podium-slot first">
          <img src="<?= getImage($ordered[1]['profile_image']) ?>" 
               class="lb-avatar" 
               onerror="this.src='acc.png'">
          <img src="gold-trophy.png" class="trophy-img" onerror="this.style.display='none'">
          <svg class="trophy-img" viewBox="0 0 100 100" style="display: none;" onload="this.style.display='block'; this.previousElementSibling.style.display='none'">
            <path fill="#FFD700" d="M15 20 L15 50 Q15 65 30 65 L70 65 Q85 65 85 50 L85 20 L75 20 L75 50 Q75 55 70 55 L30 55 Q25 55 25 50 L25 20 Z"/>
            <rect x="30" y="65" width="40" height="10" fill="#444"/>
            <rect x="25" y="75" width="50" height="8" fill="#333"/>
            <path d="M50 10 L53 17 L60 17 L55 22 L57 29 L50 25 L43 29 L45 22 L40 17 L47 17 Z" fill="#FFD700"/>
          </svg>
          <span class="lb-name"><?= htmlspecialchars($ordered[1]['name']) ?></span>
        </div>

        <!-- 3rd Place (Right) -->
        <div class="podium-slot third">
          <img src="<?= getImage($ordered[2]['profile_image']) ?>" 
               class="lb-avatar" 
               onerror="this.src='acc.png'">
          <img src="bronze-trophy.png" class="trophy-img" onerror="this.style.display='none'">
          <svg class="trophy-img" viewBox="0 0 100 100" style="display: none;" onload="this.style.display='block'; this.previousElementSibling.style.display='none'">
            <path fill="#CD7F32" d="M15 20 L15 50 Q15 65 30 65 L70 65 Q85 65 85 50 L85 20 L75 20 L75 50 Q75 55 70 55 L30 55 Q25 55 25 50 L25 20 Z"/>
            <rect x="30" y="65" width="40" height="10" fill="#444"/>
            <rect x="25" y="75" width="50" height="8" fill="#333"/>
          </svg>
          <span class="lb-name"><?= htmlspecialchars($ordered[2]['name']) ?></span>
        </div>

      </div>

      <!-- Message -->
      <div class="lb-message">
        <?php if ($is_top): ?>
          <p>Congratulations <?= htmlspecialchars($current_name) ?>!<br>You are top of the leader board. Keep going and learning</p>
        <?php else: ?>
          <p>Keep studying to climb the leaderboard!<br>Complete more lessons and quizzes to earn points.</p>
        <?php endif; ?>
      </div>

    <?php else: ?>

      <div class="lb-empty">
        <p>No leaderboard data yet.</p>
        <p style="font-size: 14px; opacity: 0.8; margin-top: 10px;">Be the first to add subjects and start learning!</p>
      </div>

    <?php endif; ?>

  </div>

</div>

<script src="script.js"></script>
</body>
</html>