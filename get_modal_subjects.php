<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'];

$sql = "
  SELECT 
    subject_id AS id,
    subject_name AS title,
    description,
    'subject' AS source
  FROM subjects

  UNION

  SELECT 
    note_id AS id,
    title,
    content AS description,
    'note' AS source
  FROM notes
  WHERE student_id = $student_id
  AND type = 'subject'
";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
  $id = $row['id'];
  $title = htmlspecialchars($row['title']);
  $desc = htmlspecialchars($row['description']);
  $source = $row['source'];

  if ($source === 'subject') {
    $click = "addSubject($id)";
  } else {
    $click = "addNoteAsSubject($id)";
  }

  echo "
    <div class='subject-item' onclick='$click'>
      <h3>$title</h3>
      <p>$desc</p>
    </div>
  ";
}
?>