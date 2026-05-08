<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;

// Use UNION ALL to keep all rows (UNION removes duplicates)
$sql = "
SELECT 
  subject_id AS id,
  subject_name AS title,
  description,
  'subject' AS source
FROM subjects
WHERE published = 1 OR is_preset = 1

UNION ALL

SELECT 
  note_id AS id,
  title,
  content AS description,
  'note' AS source
FROM notes n
WHERE n.student_id = ?
AND n.type = 'subject'
AND NOT EXISTS (
  SELECT 1 FROM subjects s 
  WHERE s.subject_name = n.title 
  AND s.student_id = n.student_id
)
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo "<p>Database error: " . htmlspecialchars($conn->error) . "</p>";
  exit;
}

$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "<p>No subjects found.</p>";
  exit;
}

while ($row = $result->fetch_assoc()) {
  $id = (int)$row['id'];
  $title = htmlspecialchars($row['title'] ?? 'Untitled');
  $desc = htmlspecialchars($row['description'] ?? '');
  $source = htmlspecialchars($row['source']);
  
  // Truncate long descriptions
  $shortDesc = strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;

  echo "
    <div class='subject-item' data-id='$id' data-source='$source'>
      <h3>$title</h3>
      <p>$shortDesc</p>
    </div>
  ";
}
?>