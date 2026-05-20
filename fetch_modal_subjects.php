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

// Build WHERE clauses to exclude already-added subjects
$exclude_preset = !empty($has_ids) ? "AND subject_id NOT IN ($has_ids_str)" : "";
$exclude_note = !empty($has_ids) ? "AND note_id NOT IN ($has_ids_str)" : "";

// Use two separate queries and merge results, or use UNION without ORDER BY inside
$sql = "
SELECT 
  subject_id AS id,
  subject_name AS title,
  description,
  'subject' AS source
FROM subjects
WHERE is_preset = 1
$exclude_preset

UNION ALL

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
  $title = htmlspecialchars($row['title'] ?? 'Untitled');
  $desc = htmlspecialchars($row['description'] ?? '');
  $source = htmlspecialchars($row['source']);
  
  $shortDesc = strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;

  echo "
    <div class='subject-item' data-id='$id' data-source='$source'>
      <h3>$title</h3>
      <p>$shortDesc</p>
    </div>
  ";
}
?>