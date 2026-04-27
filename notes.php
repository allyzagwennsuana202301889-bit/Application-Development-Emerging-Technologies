<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
$folder_id = $_GET['folder_id'] ?? null;

/* NOTES QUERY */
if ($folder_id) {
  $sql_notes = "SELECT * FROM notes 
                WHERE student_id=$student_id 
                AND folder_id=$folder_id";
} else {
  $sql_notes = "SELECT * FROM notes 
                WHERE student_id=$student_id 
                AND (folder_id IS NULL OR folder_id = 0)";
}
$notes_result = $conn->query($sql_notes);

/* FOLDERS */
$sql_folders = "SELECT * FROM folders WHERE student_id=$student_id";
$folders_result = $conn->query($sql_folders);
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
 <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

 <nav class="nav">
    <span class="hamburger">&#9776;</span>
   <img src="bell.png" class="bells">
    <button onclick="goBack()" class="back-btn"><img src="back.png"></button>
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

<div class="notesv2-container">

  <div class="notesv2-folders">
    <div class="notesv2-add" onclick="createFolder()">
      <img src="add.png">
      <p>Add</p>
    </div>

    <?php while($f = $folders_result->fetch_assoc()){ ?>
    <div class="notesv2-folder" data-id="<?= $f['folder_id'] ?>">
      <button class="notesv2-delete-btn"
        onclick="deleteFolder(<?= $f['folder_id'] ?>,event)">-</button>

  
      <img src="folder.png">
     <p class="folder-name" onclick="renameFolder(<?= $f['folder_id'] ?>, event)">
  <?= $f['folder_name'] ?>
</p>
    </div>
    <?php } ?>
  </div>

  <div class="notesv2-list">
    <?php while($n = $notes_result->fetch_assoc()){ ?>
    <div class="notesv2-card" data-id="<?= $n['note_id'] ?>">
      <?= nl2br(htmlspecialchars($n['content'])) ?>
    </div>
    <?php } ?>
  </div>

</div>

<!-- BOTTOM BAR -->
<div class="bottom-notes" id="bottomBar">

<div class="item">
   <button onclick="addnote()"><img src="addnote.png"></button>
  <p>Add Subject</p>
</div>

<div class="item">
  <button onclick="upload()"><img src="uploaded.png"></button>
  <p>Uploads</p>
</div>

<div class="item">
 <button onclick="noting()"> <img src="notes.png"></button>
  <p>Add Notes</p>
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

      <div class="folder-option" onclick="moveToFolder(null)">
   Remove from folder
</div>
    <?php } ?>
 <button onclick="closeMove()">Cancel</button>
  </div>
</div>

<script>

/* ================= STATE ================= */
let selected = new Set();
let selecting = false;

/* ================= HOLD SYSTEM ================= */
function addHold(el, callback){
  let timer;
  let held = false;

  el.addEventListener("touchstart", start);
  el.addEventListener("mousedown", start);

  el.addEventListener("touchend", cancel);
  el.addEventListener("mouseup", cancel);
  el.addEventListener("mouseleave", cancel);

  function start(e){
    held = false;

    timer = setTimeout(()=>{
      held = true;
      callback(e);
    }, 600);
  }

  function cancel(){
    clearTimeout(timer);
  }

  return () => held;
}

/* ================= NOTES ================= */
document.querySelectorAll(".notesv2-card").forEach(card=>{
  let id = card.dataset.id;

  addHold(card, ()=>{
    selecting = true;
    switchBar();
    toggle(card,id);
  });

  card.addEventListener("click", ()=>{
    if(selecting){
      toggle(card,id);
    } else {
      openNote(id);
    }
  });
});

/* ================= TOGGLE ================= */
function toggle(card,id){
  if(selected.has(id)){
    selected.delete(id);
    card.classList.remove("selected");
  } else {
    selected.add(id);
    card.classList.add("selected");
  }
}

/* ================= FOLDERS ================= */
document.querySelectorAll(".notesv2-folder").forEach(folder=>{
  let id = folder.dataset.id;

  let isHeld = addHold(folder, ()=>{
    folder.classList.add("show-delete");
  });

  folder.addEventListener("click", (e)=>{
    if(isHeld()){
      e.stopImmediatePropagation();
      return;
    }

    if(selecting) return;

    openFolder(id);
  });
});

/* ================= CLICK OUTSIDE ================= */
document.addEventListener("click", (e)=>{
  if(!e.target.closest(".notesv2-folder")){
    document.querySelectorAll(".notesv2-folder")
      .forEach(f=>f.classList.remove("show-delete"));
  }
});

/* ================= NAVIGATION ================= */
function openFolder(id){
  window.location.href = "notes.php?folder_id=" + id;
}

function openNote(id){
  window.location.href = "edit_note.php?note_id=" + id;
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

/* ================= BOTTOM BAR ================= */
function switchBar(){
  document.getElementById("bottomBar").innerHTML = `
    <button onclick="openMove()">Move</button>
    <button onclick="deleteNotes()">Delete</button>
    <button onclick="cancelSelection()">Cancel</button>
  `;
}

/* ================= CANCEL ================= */
function cancelSelection(){
  selected.clear();
  selecting = false;

  document.querySelectorAll(".notesv2-card")
    .forEach(c=>c.classList.remove("selected"));

  location.reload();
}

/* ================= DELETE NOTES ================= */
function deleteNotes(){
  fetch("delete_multiple_notes.php",{
    method:"POST",
    body:new URLSearchParams({
      ids: JSON.stringify([...selected])
    })
  })
  .then(res=>res.text())
  .then(data=>{
    console.log("delete notes:", data);
    location.reload();
  });
}

/* ================= MOVE ================= */
function openMove(){
  document.getElementById("moveModal").style.display = "flex";
}

function moveToFolder(folderId){
  if(selected.size === 0){
    alert("Select notes first");
    return;
  }

  let data = new URLSearchParams();
  data.append("ids", JSON.stringify([...selected]));

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

/* ================= RENAME FOLDER ================= */
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

</script>
<script src="script.js"></script>
</body>
</html>