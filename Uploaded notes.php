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
  <style>
     .folder {
  position: relative;
}

.delete-btn {
  position: absolute;
  top: 0;
  right: 5px;
  background: red;
  color: white;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  display: none;
}

.folder.show-delete .delete-btn {
  display: block;
}
</style>
  <link rel="stylesheet" href="style.css">

</head>

<body>

<div class="container">

  <nav class="nav">
    <span class="hamburger">&#9776;</span>
   <img src="bell.png" class="bell">
  </nav>

  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" class="help">
      <img src="back.png" class="back">
    </div>

    <label for="imageInput">
      <img id="preview" src="acc.png" alt="Upload Image">
    </label>

    <input type="file" id="imageInput" accept="image/*" hidden>

    <h3><?php echo $_SESSION['name']; ?></h3>
    <p><?php echo $_SESSION['email']; ?></p>

    <a href="homepage.php">Home</a>
    <a href="notes.php">Notes</a>
    <a href="#">Analytics</a>
    <a href="#">Leaderboard</a>
    <a href="settings.html">Settings</a>
    <a href="index.php">Log out</a>
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

  <button class='delete-btn'
    onclick='deleteFolder(".$row['folder_id'].", event)'>−</button>

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
  <img src='offlinemode.png' class='draft-download'>

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
      <img src='subject-icon.png' class='draft-icon'>

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
      <div class="bottom-add-section">

     <div class="item">
    <button onclick="addnote()"><img src="addnote.png"></button>
    <p>Add Subject</p>
  </div>

    <div class="item">
      <button onclick="goBack()"><img src="back.png"></button>
      <p>Back</p>
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

/* ================= OPEN FOLDER ================= */
function openFolder(id){
  window.location.href = "notes.php?folder_id=" + id;
}

/* ================= DELETE FOLDER ================= */
function deleteFolder(id,e){
  e.stopPropagation();

  fetch("delete_folder.php",{
    method:"POST",
    body:new URLSearchParams({folder_id:id})
  })
  .then(res=>res.text())
  .then(data=>{
    console.log("delete folder:", data);
    location.reload();
  });
}

/* ================= RENAME ================= */
function renameFolder(id, e){
  e.stopPropagation();

  let newName = prompt("New folder name:");
  if(!newName) return;

  fetch("rename_folder.php",{
    method:"POST",
    headers:{
      "Content-Type":"application/x-www-form-urlencoded"
    },
    body: new URLSearchParams({
      folder_id: id,
      folder_name: newName
    })
  })
  .then(res=>res.text())
  .then(data=>{
    console.log("rename:", data);
    location.reload();
  });
}

/* ================= HOLD SYSTEM ================= */
document.querySelectorAll(".folder").forEach(folder=>{
  let id = folder.dataset.id;

  // skip ADD button
  if(!id) return;

  let holdTimer;
  let isHolding = false;

  folder.addEventListener("mousedown", startHold);
  folder.addEventListener("touchstart", startHold);

  folder.addEventListener("mouseup", cancelHold);
  folder.addEventListener("mouseleave", cancelHold);
  folder.addEventListener("touchend", cancelHold);

  function startHold(){
    isHolding = false;

    holdTimer = setTimeout(()=>{
      isHolding = true;

      document.querySelectorAll(".folder")
        .forEach(f => f.classList.remove("show-delete"));

      folder.classList.add("show-delete");
    }, 600);
  }

  function cancelHold(){
    clearTimeout(holdTimer);
  }

  folder.addEventListener("click", (e)=>{
    if(isHolding){
      e.stopImmediatePropagation();
      return;
    }

    openFolder(id);
  });
});

/* ================= CLICK OUTSIDE ================= */
document.addEventListener("click", (e)=>{
  if(!e.target.closest(".folder")){
    document.querySelectorAll(".folder")
      .forEach(f => f.classList.remove("show-delete"));
  }
});

function createFolder(){
  let name = prompt("Folder name");
  if(!name) return;

  fetch("create_folder.php",{
    method:"POST",
    body:new URLSearchParams({folder_name:name})
  })
  .then(res=>res.text())
  .then(data=>{
    console.log("create folder:", data);
    location.reload();
  });
}
</script>
<script src="script.js"></script>
</body>
</html>