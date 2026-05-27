<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;

// Get IDs the user already has in their list
$has_ids = [];
if ($student_id > 0) {
    $has_sql = "SELECT subject_id FROM student_subjects WHERE student_id = ?";
    $has_stmt = $conn->prepare($has_sql);
    $has_stmt->bind_param("i", $student_id);
    $has_stmt->execute();
    $has_result = $has_stmt->get_result();
    while ($r = $has_result->fetch_assoc()) {
        $has_ids[] = (int)$r['subject_id'];
    }
}
$has_ids_str = !empty($has_ids) ? implode(',', $has_ids) : '0';
$exclude_note = !empty($has_ids) ? "AND note_id NOT IN ($has_ids_str)" : "";

$sql = "
SELECT 
  note_id AS id,
  title,
  content AS description,
  'note' AS source
FROM notes
WHERE type = 'subject'
$exclude_note
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo "<p>Database error: " . htmlspecialchars($conn->error) . "</p>";
  exit;
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<p>No subjects available to add.</p>";
  exit;
}

while ($row = $result->fetch_assoc()) {
  $id = (int)$row['id'];
  $dbTitle = trim($row['title'] ?? 'Untitled');
  $desc = trim($row['description'] ?? '');
  $source = htmlspecialchars($row['source']);
  
  $displayTitle = $dbTitle;
  $displayDesc = '';
  
  /* ============================================================
     FIX: Decode HTML entities like &nbsp; BEFORE processing
     ============================================================ */
  $desc = html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  
  // Try to parse JSON content
  $parsed = json_decode($desc, true);
  
  if (is_array($parsed) && !empty($parsed)) {
      // Get first item
      $firstItem = $parsed[0] ?? null;
      
      if (is_array($firstItem)) {
          $innerTitle = trim($firstItem['title'] ?? '');
          $innerDesc = trim($firstItem['desc'] ?? '');
          
          if (empty($dbTitle) || $dbTitle === 'Untitled') {
              $displayTitle = $innerTitle;
          }
          
          // STRIP HTML TAGS from description for clean preview
          $displayDesc = strip_tags($innerDesc);
      } else if (is_string($firstItem)) {
          $displayDesc = strip_tags($firstItem);
      }
  } else {
      // Not JSON, strip HTML from raw description
      $displayDesc = strip_tags($desc);
  }
  
  // Fallbacks
  if (empty($displayTitle)) {
      $displayTitle = 'Untitled';
  }
  
  // Strip any remaining JSON artifacts
  $displayTitle = preg_replace('/^[\[\{].*?[\]\}]$/', '', $displayTitle);
  $displayDesc = preg_replace('/^[\[\{].*?[\]\}]$/', '', $displayDesc);
  
  /* ============================================================
     FIX: Also decode entities in the title just in case
     ============================================================ */
  $displayTitle = html_entity_decode($displayTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $displayDesc = html_entity_decode($displayDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  
  $displayTitle = htmlspecialchars($displayTitle);
  $displayDesc = htmlspecialchars($displayDesc);
  
  $shortDesc = strlen($displayDesc) > 100 ? substr($displayDesc, 0, 100) . '...' : $displayDesc;

  echo "
    <div class='subject-item' data-id='$id' data-source='$source'>
      <h3>$displayTitle</h3>
      <p>$shortDesc</p>
    </div>
  ";
}
?>