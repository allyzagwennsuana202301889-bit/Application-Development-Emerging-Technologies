<?php
session_start();
include 'database.php';

$data = json_decode(file_get_contents('php://input'), true);
$questions = $data['questions'] ?? [];
$saved = 0;

foreach ($questions as $q) {
    $note_id = (int)$q['note_id'];
    
    if ($note_id <= 0) {
        continue;
    }
    
    $question = $conn->real_escape_string($q['question'] ?? '');
    $type = $conn->real_escape_string($q['type'] ?? 'choice');
    $order = (int)($q['question_order'] ?? 0);
    
    // FIX: Don't escape JSON strings - they need to stay valid JSON
    $choices = !empty($q['choices']) ? $q['choices'] : null;
    $answer = !empty($q['answer']) ? $conn->real_escape_string($q['answer']) : null;
    $question_image = !empty($q['question_image']) ? $q['question_image'] : null;

    $check = $conn->prepare("SELECT question_id FROM quiz_questions WHERE quiz_id = ? AND question_order = ?");
    $check->bind_param("ii", $note_id, $order);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $question_id = $row['question_id'];
        $stmt = $conn->prepare("UPDATE quiz_questions SET question = ?, question_type = ?, choices = ?, correct_answer = ?, question_image = ? WHERE question_id = ?");
        $stmt->bind_param("sssssi", $question, $type, $choices, $answer, $question_image, $question_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question, question_type, choices, correct_answer, question_order, question_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssis", $note_id, $question, $type, $choices, $answer, $order, $question_image);
    }
    
    if ($stmt->execute()) {
        $saved++;
    }
}

echo json_encode(['success' => true, 'saved' => $saved, 'total' => count($questions)]);
?>