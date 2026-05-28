<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
$folder_id = $_GET['folder_id'] ?? null;

/* NOTES QUERY */
if ($folder_id) {
  $sql_notes = "SELECT * FROM notes 
                WHERE student_id=$student_id 
                AND folder_id=$folder_id
                AND (type IS NULL OR type NOT IN ('subject_draft', 'subject'))";
} else {
  $sql_notes = "SELECT * FROM notes 
                WHERE student_id=$student_id 
                AND (folder_id IS NULL OR folder_id = 0)
                AND (type IS NULL OR type NOT IN ('subject_draft', 'subject'))";
}
$notes_result = $conn->query($sql_notes);

/* FOLDERS */
$sql_folders = "SELECT * FROM folders WHERE student_id=$student_id";
$folders_result = $conn->query($sql_folders);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
 <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

  <nav class="nav">
    <span class="hamburger">&#9776;</span>
   <div class="bell-wrapper" onclick="notif()">
    <img src="bell.png" class="bell">
    <span class="notif-dot" id="bellDot"></span>
  </nav>

  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" onclick="fax()"  class="help">
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

    <h3><?php echo $_SESSION['name']; ?></h3>
    <p><?php echo $_SESSION['email']; ?></p>

    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="analytics.php">Analytics</a>
    <a href="leaderboard.php">Leaderboard</a>
    <a href="settings.php" onclick="sessionStorage.setItem('settingsFrom', window.location.pathname)">Settings</a>
    <a href="logout.php">Log out</a>
  </div>

  <!-- OVERLAY -->
  <div class="overlay"></div>

  <div class="folders">

    <?php if ($folder_id) { ?>
  <div class="folder back-folder" onclick="exitfolder()">
    <img src="back.png">
  </div>
  <?php } ?>

    <div class="folder add-folder" onclick="createFolder()">
      <img src="addfile.png">
      <p>Add</p>
    </div>

    <?php while($f = $folders_result->fetch_assoc()){ ?>
    <div class="folder" data-id="<?= $f['folder_id'] ?>">
      <img src="folder.png">
      <p class="folder-name">
        <?= $f['folder_name'] ?>
      </p>
    </div>
    <?php } ?>
  </div>

  <!-- NOTES SCROLL AREA -->
  <div class="drafts-container">

<?php if ($folder_id): ?>
  <div style="padding: 8px 12px 0; font-size: 0.78rem; color: #888; font-style: italic;">
  </div>
<?php endif; ?>

<?php while($n = $notes_result->fetch_assoc()){ 
  $title = !empty($n['title']) ? $n['title'] : 'Untitled';
  $content = $n['content'] ?? '';

  // Check if content is JSON (old format)
  $decoded = json_decode($content, true);
  if ($decoded !== null && is_array($decoded)) {
    // Extract text from JSON structure
    $parts = [];
    foreach ($decoded as $item) {
      if (!empty($item['title'])) $parts[] = $item['title'];
      if (!empty($item['desc'])) $parts[] = $item['desc'];
    }
    $plainText = html_entity_decode(implode("\n", $parts), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  } else {
   // Normal HTML content — strip tags
$plainText = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }

  // Get first few lines for preview (max 4 lines)
  $lines = explode("\n", $plainText);
  $previewLines = array_slice($lines, 0, 4);
  $preview = implode("\n", $previewLines);
?>
<div class="notesv2-card" data-id="<?= $n['note_id'] ?>">
  <div class="note-card-title"><?= htmlspecialchars($title) ?></div>
  <div class="note-card-content"><?= nl2br(htmlspecialchars($preview)) ?></div>
</div>
<?php } ?>

  </div>

  <!-- BOTTOM -->
  <div class="bottom-add-section" id="bottomBar">

  <div class="item">
      <button onclick="noting()"><img src="addingnote.png"></button>
      <p>Add Notes</p>
    </div>

    <div class="item">
      <button onclick="upload()"><img src="uploaded.png"></button>
      <p>Uploads</p>
    </div>

  </div>

</div>

<!-- FOLDER NAME MODAL -->
<div class="folder-modal-overlay" id="folderNameModal">
  <div class="folder-modal-box">
    <h3 id="folderModalTitle">Folder name</h3>
    <input type="text" id="folderNameInput" placeholder="Enter name...">
    <div class="folder-modal-btns">
      <button class="folder-modal-cancel" onclick="closeFolderModal()">Cancel</button>
      <button class="folder-modal-confirm" onclick="confirmFolderModal()">OK</button>
    </div>
  </div>
</div>

<!-- MOVE MODAL -->
<div class="modales" id="moveModal">
  <div class="modal-contentss">
    <h3>Select Folder</h3>

    <?php 
    $folders_result->data_seek(0);
    while($f = $folders_result->fetch_assoc()){ ?>
      <div class="folder-option" onclick="event.stopPropagation(); moveToFolder(<?= $f['folder_id'] ?>)">
        <?= $f['folder_name'] ?>
      </div>
    <?php } ?>
    <div class="folder-option" onclick="moveToFolder(null)">
      Remove from folder
    </div>
    <button onclick="closeMove()">Cancel</button>
  </div>
</div>

<script>

const CURRENT_FOLDER_ID = <?= json_encode($folder_id) ?>;

function exitfolder() {
  window.location.href = "notes.php";
}

/* ================= STATE ================= */
let selectedFolders = new Set();
let folderSelecting = false;
let selectedNotes = new Set();
let noteSelecting = false;
let activeFolderId = null; // track which folder was held

/* ================= FOLDER INTERACTIONS ================= */
document.querySelectorAll(".folder").forEach(folder => {
  const id = folder.dataset.id;
  if (!id) return; // skip Add / Back buttons

  let holdTimer = null;
  let didHold = false;
  let startX = 0;
  let startY = 0;
  let endX = 0;
  const SCROLL_THRESHOLD = 8;

  /* ---- TOUCH (mobile) ---- */
  folder.addEventListener("touchstart", e => {
    didHold = false;
    const t = e.touches[0];
    startX = t.clientX;
    startY = t.clientY;
    endX = t.clientX;
    holdTimer = setTimeout(() => {
      didHold = true;
      showFolderActionBar(folder, id);
    }, 600);
  }, { passive: true });

  folder.addEventListener("touchmove", e => {
    const t = e.touches[0];
    endX = t.clientX;
    if (!holdTimer) return;
    const dx = Math.abs(t.clientX - startX);
    const dy = Math.abs(t.clientY - startY);
    if (dx > SCROLL_THRESHOLD || dy > SCROLL_THRESHOLD) {
      clearTimeout(holdTimer);
      holdTimer = null;
    }
  }, { passive: true });

  folder.addEventListener("touchend", e => {
    clearTimeout(holdTimer);
    holdTimer = null;
    if (didHold) { didHold = false; return; }
    if (Math.abs(endX - startX) > 10) return;
    openFolder(id);
  });

  /* ---- MOUSE (desktop) ---- */
  folder.addEventListener("mousedown", e => {
    didHold = false;
    holdTimer = setTimeout(() => {
      didHold = true;
      showFolderActionBar(folder, id);
    }, 600);
  });

  folder.addEventListener("mouseup", () => { clearTimeout(holdTimer); holdTimer = null; });
  folder.addEventListener("mouseleave", () => { clearTimeout(holdTimer); holdTimer = null; });

  folder.addEventListener("click", e => {
    if (didHold) { didHold = false; return; }
    openFolder(id);
  });
});

/* Show Delete / Rename / Cancel in the bottom bar for a specific folder */
function showFolderActionBar(folder, id) {
  activeFolderId = id;
  folder.classList.add("selected");
  document.getElementById("bottomBar").innerHTML = `
    <div class="item">
      <button onclick="deleteActiveFolder()"><img src="bin.png"></button>
      <p>Delete</p>
    </div>
    <div class="item">
      <button onclick="renameActiveFolder()"><img src="Edit.png"></button>
      <p>Rename</p>
    </div>
    <div class="item">
      <button onclick="cancelFolderAction()"><img src="back.png"></button>
      <p>Cancel</p>
    </div>
  `;
}

function cancelFolderAction() {
  if (activeFolderId) {
    const el = document.querySelector(`.folder[data-id="${activeFolderId}"]`);
    if (el) el.classList.remove("selected");
  }
  activeFolderId = null;
  restoreBottomBar();
}

function deleteActiveFolder() {
  if (!activeFolderId) return;
  fetch("delete_multiple_folders.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ ids: JSON.stringify([activeFolderId]) })
  })
  .then(res => res.text())
  .then(() => location.reload());
}

function renameActiveFolder() {
  if (!activeFolderId) return;
  const id = activeFolderId;
  openFolderModal("Rename folder", "", newName => {
    fetch("rename_folder.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({ folder_id: id, folder_name: newName })
    })
    .then(res => res.text())
    .then(() => location.reload());
  });
}

function restoreBottomBar(){
  if (CURRENT_FOLDER_ID) {
    document.getElementById("bottomBar").innerHTML = `
      <div class="item">
        <button onclick="noting()"><img src="addingnote.png"></button>
        <p>Add Notes</p>
      </div>
      <div class="item">
        <button onclick="upload()"><img src="uploaded.png"></button>
        <p>Uploads</p>
      </div>
    `;
  } else {
    document.getElementById("bottomBar").innerHTML = `
      <div class="item">
        <button onclick="noting()"><img src="addingnote.png"></button>
        <p>Add Notes</p>
      </div>
      <div class="item">
        <button onclick="upload()"><img src="uploaded.png"></button>
        <p>Uploads</p>
      </div>
    `;
  }
}

/* ================= NOTES ================= */
document.querySelectorAll(".notesv2-card").forEach(card => {
  const id = card.dataset.id;

  let holdTimer = null;
  let didHold = false;

  function startHold() {
    didHold = false;
    holdTimer = setTimeout(() => {
      didHold = true;
      noteSelecting = true;
      switchNoteBar();
      toggleNote(card, id);
    }, 600);
  }

  function endHold() { clearTimeout(holdTimer); }

  card.addEventListener("mousedown",  startHold);
  card.addEventListener("touchstart", startHold, { passive: true });
  card.addEventListener("mouseup",    endHold);
  card.addEventListener("mouseleave", endHold);
  card.addEventListener("touchend",   endHold);

  card.addEventListener("click", () => {
    if (didHold) { didHold = false; return; }
    if (noteSelecting) {
      toggleNote(card, id);
    } else {
      openNote(id);
    }
  });
});

/* ================= NOTE TOGGLE ================= */
function toggleNote(card,id){
  if(selectedNotes.has(id)){
    selectedNotes.delete(id);
    card.classList.remove("selected");
  } else {
    selectedNotes.add(id);
    card.classList.add("selected");
  }
}

function switchNoteBar(){
  document.getElementById("bottomBar").innerHTML = `
    <div class="item">
      <button onclick="openMove()"><img src="moveit.png"></button>
      <p>Move</p>
    </div>
    <div class="item">
      <button onclick="deleteNotes()"><img src="bin.png"></button>
      <p>Delete</p>
    </div>
    <div class="item">
      <button onclick="cancelNoteSelection()"><img src="back.png"></button>
      <p>Cancel</p>
    </div>
  `;
}

/* ================= CANCEL NOTE SELECTION ================= */
function cancelNoteSelection(){
  selectedNotes.clear();
  noteSelecting = false;
  document.querySelectorAll(".notesv2-card")
    .forEach(c=>c.classList.remove("selected"));
  restoreBottomBar();
}

/* ================= DELETE NOTES ================= */
function deleteNotes(){
  fetch("delete_multiple_notes.php",{
    method:"POST",
    body:new URLSearchParams({
      ids: JSON.stringify([...selectedNotes])
    })
  })
  .then(res=>res.text())
  .then(data=>{
    console.log("delete notes:", data);
    location.reload();
  });
}

/* ================= NAVIGATION ================= */
function openFolder(id){
  window.location.href = "notes.php?folder_id=" + id;
}

function openNote(id){
  window.location.href = "edit_note.php?note_id=" + id;
}

/* ================= MOVE ================= */
function openMove(){
  document.getElementById("moveModal").style.display = "flex";
}

function moveToFolder(folderId){
  if(selectedNotes.size === 0){
    alert("Select notes first");
    return;
  }

  let data = new URLSearchParams();
  data.append("ids", JSON.stringify([...selectedNotes]));

  if(folderId === null){
    data.append("folder_id", "NULL");
  } else {
    data.append("folder_id", folderId);
  }

  fetch("move_notes.php",{
    method:"POST",
    headers:{
      "Content-Type":"application/x-www-form-urlencoded"
    },
    body: data
  })
  .then(res => res.text())
  .then(data => {
    console.log("MOVE RESPONSE:", data);
    location.reload();
  });
}

/* ================= CREATE FOLDER ================= */
let folderModalCallback = null;

function openFolderModal(title, defaultVal, callback) {
  document.getElementById("folderModalTitle").textContent = title;
  const input = document.getElementById("folderNameInput");
  input.value = defaultVal || "";
  folderModalCallback = callback;
  document.getElementById("folderNameModal").classList.add("active");
  setTimeout(() => input.focus(), 100);
}

function closeFolderModal() {
  document.getElementById("folderNameModal").classList.remove("active");
  document.getElementById("folderNameInput").value = "";
  folderModalCallback = null;
}

function confirmFolderModal() {
  const val = document.getElementById("folderNameInput").value.trim();
  if (!val) return;
  const cb = folderModalCallback;
  folderModalCallback = null;
  closeFolderModal();
  if (cb) cb(val);
}

document.getElementById("folderNameInput").addEventListener("keydown", e => {
  if (e.key === "Enter") confirmFolderModal();
  if (e.key === "Escape") closeFolderModal();
});

function createFolder(){
  openFolderModal("Folder name", "", name => {
    fetch("create_folder.php",{
      method:"POST",
      body:new URLSearchParams({folder_name:name})
    })
    .then(res=>res.text())
    .then(() => location.reload());
  });
}

/* ================= MODAL ================= */
document.querySelector(".modal-contentss").addEventListener("click", function(e){
  e.stopPropagation();
});

function closeMove(){
  document.getElementById("moveModal").style.display = "none";
}

/* ================= EDIT NOTE (DOUBLE TAP) ================= */
document.querySelectorAll(".notesv2-card").forEach(card=>{
  let id = card.dataset.id;

  card.addEventListener("dblclick", ()=>{
    editNote(id, card);
  });

  let lastTap = 0;
  card.addEventListener("touchend", ()=>{
    let now = new Date().getTime();
    if(now - lastTap < 300){
      editNote(id, card);
    }
    lastTap = now;
  });
});

function editNote(id, card){
  let currentText = card.innerText;
  let updated = prompt("Edit note:", currentText);

  if(updated === null) return;

  fetch("update_note.php",{
    method:"POST",
    headers:{
      "Content-Type":"application/x-www-form-urlencoded"
    },
    body: new URLSearchParams({
      note_id: id,
      content: updated
    })
  })
  .then(res=>res.text())
  .then(data=>{
    console.log("update note:", data);
    card.innerHTML = updated.replace(/\n/g, "<br>");
  });
}

/* ================= NOTING OVERRIDE ================= */
// Defined here AND re-applied after script.js to ensure folder_id is always passed
function _notingWithFolder() {
  if (CURRENT_FOLDER_ID) {
    window.location.href = "edit_note.php?folder_id=" + CURRENT_FOLDER_ID;
  } else {
    window.location.href = "edit_note.php";
  }
}
window.noting = _notingWithFolder;


function renameFolder(id, e){
  e.stopPropagation();
  openFolderModal("Rename folder", "", newName => {
    fetch("rename_folder.php",{
      method:"POST",
      headers:{"Content-Type":"application/x-www-form-urlencoded"},
      body: new URLSearchParams({ folder_id: id, folder_name: newName })
    })
    .then(res=>res.text())
    .then(() => location.reload());
  });
}

</script>
<script src="script.js"></script>
<script>
  // Re-apply after script.js in case it redefined noting()
  window.noting = _notingWithFolder;
</script>
</body>
</html>