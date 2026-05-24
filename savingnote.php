<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
$title          = $_POST['title'] ?? '';
$content        = $_POST['content'] ?? '';
$text_alignment = $_POST['text_alignment'] ?? 'center';
$note_id        = $_POST['note_id'] ?? null;

if (!$student_id) {
    die("No session / not logged in");
}

if ($title === '' && $content === '') {
    die("Empty note not saved");
}

/* ========== SANITIZE CONTENT (keep images, remove scripts) ========== */
// Allow basic formatting tags + images, but strip dangerous ones
$allowed_tags = '<p><br><b><strong><i><em><u><s><strike><sub><sup><ul><ol><li><img><div><span>';
$content = strip_tags($content, $allowed_tags);

/* ========== SANITIZE IMAGE SRC (only allow data URIs) ========== */
$content = preg_replace_callback(
    '/<<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i',
    function ($matches) {
        $src = $matches[1];
        // Only allow base64 data URIs, block external URLs
        if (!preg_match('/^data:image\/(jpeg|png|gif|webp);base64,/i', $src)) {
            return ''; // Remove dangerous image tags
        }
        return $matches[0];
    },
    $content
);

/* ================= SAVE ================= */
if ($note_id) {
    $stmt = $conn->prepare("UPDATE notes SET title=?, content=?, text_alignment=? WHERE note_id=? AND student_id=?");
    $stmt->bind_param("sssii", $title, $content, $text_alignment, $note_id, $student_id);
} else {
    $stmt = $conn->prepare("INSERT INTO notes (student_id, title, content, text_alignment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $student_id, $title, $content, $text_alignment);
}

if (!$stmt->execute()) {
    die("SQL ERROR: " . $stmt->error);
}

header("Location: notes.php");
exit;
?>