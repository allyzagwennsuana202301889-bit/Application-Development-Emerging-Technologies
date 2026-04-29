<?php
session_start();
include 'database.php';

$note_id = isset($_GET['note_id']) ? (int)$_GET['note_id'] : 0;

$stmt = $conn->prepare("
    SELECT notes.*, student.name AS uploader_name
    FROM notes
    JOIN student ON notes.student_id = student.student_id
    WHERE notes.note_id = ?
");
$stmt->bind_param("i", $note_id);
$stmt->execute();
$result = $stmt->get_result();
$note = $result->fetch_assoc();

if (!$note) {
    echo "Note not found.";
    exit;
}

$cards = [];
if (!empty($note['content'])) {
    $decoded = json_decode($note['content'], true);
    if (is_array($decoded)) {
        $cards = $decoded;
    } else {
        $cards = [$note['content']];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Note</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <input type="text" id="searchInput" placeholder="Search Topic">
    <button class="back-btn" style="background:none;border:none;padding:0;">
      <img src="back.png" class="back-btn-img">
    </button>
  </nav>

  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" class="help">
      <img src="back.png" class="back">
    </div>
    <label for="imageInput">
      <img id="preview" src="acc.png">
    </label>
    <input type="file" id="imageInput" hidden>
    <h3><?php echo $_SESSION['name'] ?? 'Guest'; ?></h3>
    <p><?php echo $_SESSION['email'] ?? 'No Email'; ?></p>
    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="#">Analytics</a>
    <a href="#">Leaderboard</a>
    <a href="settings.html">Settings</a>
    <a href="index.php">Log out</a>
  </div>

  <div class="overlay"></div>

<div class="subject-content" id="mainContent">

<div class="subject-top-card">
  <div class="top-left">
    <input type="text" id="subjectName" class="subject-title-input"
      value="<?= htmlspecialchars($note['title']) ?>" placeholder="(Subject)" readonly>
    <p class="uploaded"><strong>Uploaded by:</strong><br><?= htmlspecialchars($note['uploader_name']) ?></p>
  </div>
  <div class="top-right">

    <label>
   <img id="subjectImagePreview"
             src="<?= !empty($note['subject_image']) ? htmlspecialchars($note['subject_image']) : 'file.png' ?>"
             class="subject-image">
        <input type="file" id="subjectImageInput" accept="image/*" hidden>
    </label>
  </div>
</div>

<div id="cardContainer">
<?php foreach ($cards as $card): 
  $title = $card['title'] ?? '';
  $desc  = $card['desc'] ?? '';
  $img   = (!empty($card['img']) && $card['img'] !== 'file.png') ? $card['img'] : 'file.png';
?>

<div class="subject-main-card">
  <button class="delete-card-btn" onclick="deleteCard(this)">-</button>

  <label class="card-image-label">
    <img src="<?= htmlspecialchars($img) ?>" class="card-image-preview" data-img="<?= htmlspecialchars($img) ?>">
    <input type="file" class="card-image-input" hidden disabled>
  </label>

  <input type="text" class="card-title" value="<?= htmlspecialchars($title) ?>" readonly>
  <div class="fake-desc" contenteditable="false"><?= htmlspecialchars($desc) ?></div>
</div>

<?php endforeach; ?>
</div>

</div>

  <div class="bottom-file-section">
    <div class="item">
      <button id="editSaveBtn" style="background:none;border:none;">
        <img src="Edit.png" id="editSaveImg">
      </button>
      <p id="editSaveText">Edit</p>
    </div>
    <div class="item add-btn-wrapper" id="addBtnWrapper">
      <button onclick="addCard()" style="background:none;border:none;">
        <img src="add.png">
      </button>
      <p>Add</p>
    </div>
    <div class="item">
      <button onclick="quiz()" style="background:none;border:none;"><img src="flashcards.png"></button>
      <p>Flash Cards</p>
    </div>
  </div>

<script>
let NOTE_ID = <?= isset($note['note_id']) ? $note['note_id'] : 'null' ?>;
let isEditing = false;
let subjectImageData = "<?= htmlspecialchars($note['subject_image'] ?? '') ?>";

function toggleEditMode() {
  isEditing = !isEditing;
  const mainContent = document.getElementById('mainContent');
  const titleInput = document.getElementById('subjectName');
  const descs = document.querySelectorAll('.fake-desc');
  const titles = document.querySelectorAll('.card-title');
  const imageInputs = document.querySelectorAll('.card-image-input');
  const subjectImageInput = document.getElementById("subjectImageInput");
  const editSaveText = document.getElementById('editSaveText');
  const addBtnWrapper = document.getElementById('addBtnWrapper');

  if (isEditing) {
    mainContent.classList.add('edit-mode');
    titleInput.readOnly = false;
    descs.forEach(el => el.contentEditable = "true");
    titles.forEach(t => t.removeAttribute("readonly"));
    imageInputs.forEach(i => i.disabled = false);
    if (subjectImageInput) subjectImageInput.disabled = false;
    editSaveText.textContent = "Save";
    addBtnWrapper.style.display = "flex";
  } else {
    saveToDatabase().then(() => {
      mainContent.classList.remove('edit-mode');
      titleInput.readOnly = true;
      descs.forEach(el => el.contentEditable = "false");
      titles.forEach(t => t.setAttribute("readonly", true));
      imageInputs.forEach(i => i.disabled = true);
      if (subjectImageInput) subjectImageInput.disabled = true;
      editSaveText.textContent = "Edit";
      addBtnWrapper.style.display = "none";
    });
  }
}

function saveToDatabase() {
  const params = new URLSearchParams();
  params.append("title", document.getElementById("subjectName").value);
  params.append("content", JSON.stringify(getAllContent()));
  params.append("type", "subject_draft");
  if (NOTE_ID) params.append("note_id", NOTE_ID);
  if (subjectImageData) params.append("subject_image", subjectImageData);

  return fetch('savenote.php', {
    method: 'POST',
    body: params
  })
  .then(res => res.json())
  .then(data => {
    console.log("SAVE RESPONSE:", data);
    if (data.note_id) NOTE_ID = data.note_id;
  })
  .catch(err => {
    console.error(err);
    alert("Save failed");
  });
}

/* ========== GET CONTENT - READS data-img INSTEAD OF .src ========== */
function getAllContent() {
  const cards = document.querySelectorAll(".subject-main-card");
  let data = [];

  cards.forEach(card => {
    const preview = card.querySelector(".card-image-preview");
    // Read from data-img attribute, NOT .src (which gives absolute URL)
    let img = preview?.getAttribute("data-img") || "file.png";
    
    // Normalize if it's somehow still an absolute URL to file.png
    if (img.includes('file.png') && !img.startsWith('data:')) {
      img = 'file.png';
    }

    data.push({
      title: card.querySelector(".card-title")?.value || "",
      desc: card.querySelector(".fake-desc")?.innerText || "",
      img: img
    });
  });

  return data;
}

function addCard() {
  if (!isEditing) return;
  const container = document.getElementById("cardContainer");
  const newCard = document.createElement("div");
  newCard.className = "subject-main-card";
  newCard.innerHTML = `
    <button class="delete-card-btn" onclick="deleteCard(this)">-</button>
    <label class="card-image-label">
      <img src="file.png" class="card-image-preview" data-img="file.png">
      <input type="file" class="card-image-input">
    </label>
    <input type="text" class="card-title" placeholder="Title">
    <div class="fake-desc" contenteditable="true">(Insert desc here)</div>
  `;
  container.appendChild(newCard);
  attachImageHandler(newCard);
}

function deleteCard(btn) {
  if (!isEditing) return;
  btn.closest('.subject-main-card')?.remove();
}

function attachImageHandler(card) {
  const input = card.querySelector(".card-image-input");
  const preview = card.querySelector(".card-image-preview");
  if (!input || !preview) return;

  input.addEventListener("change", function() {
    const file = this.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
      preview.src = e.target.result;
      preview.setAttribute("data-img", e.target.result);
    };
    reader.readAsDataURL(file);
  });
}

window.addEventListener("load", () => {
  document.querySelectorAll(".subject-main-card").forEach(card => {
    attachImageHandler(card);
  });
});

const subjectInput = document.getElementById("subjectImageInput");
if (subjectInput) {
  subjectInput.addEventListener("change", function() {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
      subjectImageData = e.target.result;
      document.getElementById("subjectImagePreview").src = subjectImageData;
    };
    reader.readAsDataURL(file);
  });
}

document.getElementById("editSaveBtn").addEventListener("click", toggleEditMode);

document.querySelector(".back-btn").addEventListener("click", async function(e) {
  e.preventDefault();
  if (isEditing) await toggleEditMode();
  window.history.back();
});

function quiz() {}
</script>
<script src="script.js"></script>
</body>
</html>