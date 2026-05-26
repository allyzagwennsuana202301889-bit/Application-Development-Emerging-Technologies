<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;

/*  GET ALL SUBJECT DRAFTS  */
$sql = "SELECT * FROM notes 
        WHERE student_id = ? 
        AND type IN ('subject_draft', 'subject')
        ORDER BY note_id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Subject</title>
  <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="container">

  <nav class="nav">
    <span class="hamburger">&#9776;</span>
      <img src="bell.png" class="bell" onclick="notif()">
  </nav>

  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" onclick="fax()" class="help">
      <img src="back.png" class="back">
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

    <h3><?php echo $_SESSION['name']; ?></h3>
    <p><?php echo $_SESSION['email']; ?></p>

    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="analytics.php">Analytics</a>
    <a href="leaderboard.php">Leaderboard</a>
    <a href="settings.php">Settings</a>
    <a href="logout.php">Log out</a>
  </div>

  <!-- OVERLAY -->
  <div class="overlay"></div>

 <div class="folders">

   <div class="folder add-folder" onclick="createFolder()">
    <img src="add.png">
    <p>Add</p>
  </div>

  <?php
  $student_id = $_SESSION['student_id'];

  $folder_sql = "SELECT * FROM folders WHERE student_id=?";
  $stmt = $conn->prepare($folder_sql);
  $stmt->bind_param("i", $student_id);
  $stmt->execute();
  $folder_result = $stmt->get_result();

  while ($row = $folder_result->fetch_assoc()) {
    echo "
<div class='folder' data-id='".$row['folder_id']."'>

  <img src='folder.png'>

  <p class='folder-name'
     onclick='renameFolder(".$row['folder_id'].", event)'>
     {$row['folder_name']}
  </p>

</div>
    ";
  }
  ?>
  </div>

  <!--  NOTES SCROLL AREA -->
  <div class="drafts-container">

<?php
if ($result->num_rows === 0) {
  echo "<p style='padding:15px;'>No drafts found.</p>";
} else {
  while ($draft = $result->fetch_assoc()) {

    $title = $draft['title'] ?? 'Untitled';
    $desc  = $draft['content'] ?? '';
    $author = $_SESSION['name'] ?? 'User';

    echo "
<div class='draft-card'>

  <!-- TOP RIGHT DOWNLOAD -->
  <img src='offlinemode.png' class='draft-download' onclick='toggleDownload(this)'>

  <div class='draft-content'>

    <!-- LEFT SIDE -->
    <div class='draft-left'>
      <h3>" . htmlspecialchars($title) . "</h3>

      <p class='draft-author'>
        Uploaded by:<br>" . htmlspecialchars($author) . "
      </p>

      <div class='draft-left-btns'>
  <button onclick='readNote(" . $draft['note_id'] . ")' class='btn-read'>Read</button>
  <button onclick='deleteNote(" . $draft['note_id'] . ")' class='btn-delete'>Delete</button>
</div>
    </div>

    <!-- RIGHT SIDE -->
    <div class='draft-right'>
      <img src='" . (!empty($draft['subject_image']) 
    ? htmlspecialchars($draft['subject_image']) 
    : "file.png") . "' class='draft-icon'>

     <button onclick='togglePublish(" . $draft['note_id'] . ", \"" . $draft['type'] . "\")' class='btn-unpublish'>
  " . ($draft['type'] === 'subject' ? 'Unpublish' : 'Publish') . "
</button>
    </div>

  </div>

</div>
";
  }
}
?>

</div>

    <!-- BOTTOM -->
      <div class="bottom-add-section" id="bottomBar">

     <div class="item">
    <button onclick="addnote()"><img src="addnote.png"></button>
    <p>Add Subject</p>
  </div>

    <div class="item">
      <button onclick="viewNote()"><img src="notes.png"></button>
      <p>Notes</p>
    </div>
</div>

  </div>

<script>
const descBox = document.getElementById("descBox");
const descInput = document.getElementById("descInput");

if (descBox && descInput) {
  descBox.addEventListener("input", () => {
    descInput.value = descBox.innerText;
  });
}

/* ================= STATE ================= */
let selectedFolders = new Set();
let folderSelecting = false;

/* ================= FOLDER INTERACTIONS ================= */
document.querySelectorAll(".folder").forEach(folder => {
  const id = folder.dataset.id;
  if (!id) return;

  let holdTimer = null;
  let didHold = false;
  let startX = 0;
  let startY = 0;
  const SCROLL_THRESHOLD = 8;

  /* ---- TOUCH (mobile) ---- */
  folder.addEventListener("touchstart", e => {
    didHold = false;
    const t = e.touches[0];
    startX = t.clientX;
    startY = t.clientY;
    holdTimer = setTimeout(() => {
      didHold = true;
      enterSelectionMode(folder, id);
    }, 600);
  }, { passive: true });

  folder.addEventListener("touchmove", e => {
    if (!holdTimer) return;
    const t = e.touches[0];
    if (Math.abs(t.clientX - startX) > SCROLL_THRESHOLD ||
        Math.abs(t.clientY - startY) > SCROLL_THRESHOLD) {
      clearTimeout(holdTimer);
      holdTimer = null;
    }
  }, { passive: true });

  folder.addEventListener("touchend", e => {
    clearTimeout(holdTimer);
    holdTimer = null;

    if (didHold) {
      didHold = false;
      return;
    }

    if (folderSelecting) {
      toggleFolder(folder, id);
      return;
    }

    openFolder(id);
  });

  /* ---- MOUSE (desktop) ---- */
  folder.addEventListener("mousedown", e => {
    didHold = false;
    holdTimer = setTimeout(() => {
      didHold = true;
      enterSelectionMode(folder, id);
    }, 600);
  });

  folder.addEventListener("mouseup", () => {
    clearTimeout(holdTimer);
    holdTimer = null;
  });

  folder.addEventListener("mouseleave", () => {
    clearTimeout(holdTimer);
    holdTimer = null;
  });

  folder.addEventListener("click", e => {
    if (didHold) { didHold = false; return; }
    if (folderSelecting) { toggleFolder(folder, id); return; }
    openFolder(id);
  });
});

function enterSelectionMode(folder, id) {
  folder.classList.add("show-delete");
  if (!folderSelecting) {
    folderSelecting = true;
    switchFolderBar();
  }
  toggleFolder(folder, id);
}

function toggleFolder(folder, id) {
  if (selectedFolders.has(id)) {
    selectedFolders.delete(id);
    folder.classList.remove("selected");
    folder.classList.remove("show-delete");
  } else {
    selectedFolders.add(id);
    folder.classList.add("selected");
    folder.classList.add("show-delete");
  }
  if (selectedFolders.size === 0) cancelFolderSelection();
}

function switchFolderBar() {
  document.getElementById("bottomBar").innerHTML = `
    <div class="item">
      <button onclick="deleteSelectedFolders()"><img src="bin.png"></button>
      <p>Delete</p>
    </div>
    <div class="item">
      <button onclick="cancelFolderSelection()"><img src="back.png"></button>
      <p>Cancel</p>
    </div>
  `;
}

function cancelFolderSelection() {
  selectedFolders.clear();
  folderSelecting = false;
  document.querySelectorAll(".folder").forEach(f => {
    f.classList.remove("selected");
    f.classList.remove("show-delete");
  });
  restoreBottomBar();
}

function restoreBottomBar() {
  document.getElementById("bottomBar").innerHTML = `
    <div class="item">
      <button onclick="addnote()"><img src="addnote.png"></button>
      <p>Add Subject</p>
    </div>
    <div class="item">
      <button onclick="viewNote()"><img src="back.png"></button>
      <p>Back</p>
    </div>
  `;
}

function deleteSelectedFolders() {
  if (selectedFolders.size === 0) return;
  fetch("delete_multiple_folders.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ ids: JSON.stringify([...selectedFolders]) })
  })
  .then(res => res.text())
  .then(() => location.reload());
}

/* ================= OPEN FOLDER ================= */
function openFolder(id) {
  window.location.href = "notes.php?folder_id=" + id;
}

/* ================= RENAME ================= */
function renameFolder(id, e) {
  e.stopPropagation();
  let newName = prompt("New folder name:");
  if (!newName) return;
  fetch("rename_folder.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({ folder_id: id, folder_name: newName })
  })
  .then(res => res.text())
  .then(() => location.reload());
}

/* ================= FOLDER CREATE ================= */
function createFolder() {
  let name = prompt("Folder name");
  if (!name) return;
  fetch("create_folder.php", {
    method: "POST",
    body: new URLSearchParams({ folder_name: name })
  })
  .then(res => res.text())
  .then(() => location.reload());
}

/* ================= READ / DELETE / PUBLISH NOTE ================= */
function readNote(id) {
  window.location.href = "viewnote.php?note_id=" + id;
}

function deleteNote(id) {
  if (!confirm("Delete this note?")) return;
  fetch("delete_note.php", {
    method: "POST",
    body: new URLSearchParams({ note_id: id })
  })
  .then(res => res.text())
  .then(() => location.reload());
}

function togglePublish(id, currentType) {
  const newType = currentType === "subject" ? "subject_draft" : "subject";
  fetch("togglepublish.php", {
    method: "POST",
    body: new URLSearchParams({ note_id: id, type: newType })
  })
  .then(res => res.text())
  .then(() => location.reload());
}

function hasRealContent() {
  const title = document.getElementById("subjectName")?.value.trim();
  return title !== "";
}

/* ================= OFFLINE DOWNLOAD SIMULATION ================= */
function toggleDownload(img) {
  const isDownloaded = img.getAttribute('data-downloaded') === 'true';
  
  if (!isDownloaded) {
    img.src = 'bluecheck.png';
    img.setAttribute('data-downloaded', 'true');
    img.title = 'Downloaded for offline';
    showToast('Downloaded for offline reading');
  } else {
    img.src = 'offlinemode.png';
    img.setAttribute('data-downloaded', 'false');
    img.title = 'Download for offline';
    showToast('Removed from offline');
  }
}

function showToast(msg) {
  let toast = document.getElementById('toastMsg');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'toastMsg';
    toast.style.cssText = `
      position: fixed;
      top: 80px;
      left: 50%;
      transform: translateX(-50%) translateY(-20px);
      background: #333;
      color: white;
      padding: 12px 24px;
      border-radius: 8px;
      font-size: 14px;
      z-index: 300;
      opacity: 0;
      transition: 0.3s;
      pointer-events: none;
      font-family: 'Inria Sans', sans-serif;
    `;
    document.body.appendChild(toast);
  }
  toast.textContent = msg;
  toast.style.opacity = '1';
  toast.style.transform = 'translateX(-50%) translateY(0)';
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(-50%) translateY(-20px)';
  }, 2500);
}
</script>
<script src="script.js"></script>
</body>
</html>