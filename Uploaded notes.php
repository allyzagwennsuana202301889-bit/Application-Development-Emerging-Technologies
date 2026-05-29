<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;

/* GET ALL SUBJECT DRAFTS */
$sql = "SELECT * FROM notes 
        WHERE student_id = ? 
        AND type IN ('subject_draft', 'subject')
        ORDER BY note_id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

/* GET SUBJECT FOLDERS */
$sf_sql = "SELECT * FROM subject_folders WHERE student_id=? ORDER BY folder_id ASC";
$sf_stmt = $conn->prepare($sf_sql);
$sf_stmt->bind_param("i", $student_id);
$sf_stmt->execute();
$sf_result = $sf_stmt->get_result();
$subject_folders = [];
while ($r = $sf_result->fetch_assoc()) $subject_folders[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Subject</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* ── use the same .folders / .folder classes from style.css ── */
    /* folder selected state — exact match to notes.php */
    .folder.selected img {
      filter: brightness(0) saturate(100%) invert(75%) sepia(60%) saturate(500%) hue-rotate(340deg) brightness(1.05);
    }
    .folder.selected p { color: #FFAE71; }

    /* pressing state during hold — same yellow highlight */
    .folder.sf-pressing img {
      filter: brightness(0) saturate(100%) invert(75%) sepia(60%) saturate(500%) hue-rotate(340deg) brightness(1.05);
      transition: filter 0.15s;
    }
    .folder.sf-pressing p { color: #FFAE71; }

    /* back folder item in the row */
    .back-folder {
      cursor: pointer;
      min-width: 20px;
      text-align: center;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 0;
    }
    .back-folder.visible { display: flex; }
    .back-folder img {
      width: 40px;
      height: 40px;
      display: block;
      margin: 0 auto;
    }

    /* ── draft card long-press highlight ── */
    .draft-card.pressing { opacity: 0.6; transform: scale(0.97); transition: 0.15s; }
    .draft-card.card-selected { outline: 3px solid #F4A261; border-radius: 10px; }
  </style>
</head>

<body>

<div class="container">

  <nav class="nav">
    <span class="hamburger">&#9776;</span>
    <input type="text" id="searchInput" placeholder="Search subject">
    <div class="bell-wrapper" onclick="notif()">
      <img src="bell.png" class="bell">
      <span class="notif-dot" id="bellDot"></span>
    </div>
  </nav>

  <div class="nav-links">
    <div class="top-icons">
      <img src="FAQIcon.png" onclick="fax()" class="help">
      <img src="back.png" class="back">
    </div>

    <?php
    $pfp_stmt = $conn->prepare("SELECT profile_image FROM student WHERE student_id = ?");
    $pfp_stmt->bind_param("i", $student_id);
    $pfp_stmt->execute();
    $pfp_result = $pfp_stmt->get_result()->fetch_assoc();
    $profile_image = !empty($pfp_result['profile_image']) ? $pfp_result['profile_image'] : 'acc.png';
    $image_src = $profile_image;
    if (strpos($image_src, 'data:') === 0) {
        $image_src = $profile_image;
    } else {
        $image_src .= '?t=' . time();
    }
    ?>

    <form id="pfpForm" enctype="multipart/form-data" style="display:contents;">
      <label for="imageInput" style="cursor:pointer;position:relative;">
        <img id="preview" src="<?= htmlspecialchars($image_src) ?>"
             style="width:90px;height:90px;border-radius:50%;object-fit:cover;"
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
    <a href="settings.php" onclick="sessionStorage.setItem('settingsFrom', window.location.pathname)">Settings</a>
    <a href="logout.php">Log out</a>
  </div>

  <div class="overlay"></div>

  <!-- ══════════ SUBJECT FOLDERS ROW ══════════ -->
  <div class="folders" id="subjectFolderRow">

    <!-- back button: visible only inside a folder -->
    <div class="back-folder" id="sfBackItem" onclick="closeSubjectFolder()">
      <img src="back.png">
    </div>

    <div class="folder add-folder" onclick="createSubjectFolder()">
      <img src="addfile.png">
      <p>Add</p>
    </div>

    <?php foreach ($subject_folders as $sf): ?>
    <div class="folder"
         data-sfid="<?= $sf['folder_id'] ?>"
         data-sfname="<?= htmlspecialchars($sf['folder_name']) ?>"
         onclick="onSFClick(event, <?= $sf['folder_id'] ?>, '<?= addslashes($sf['folder_name']) ?>')"
         onmousedown="startSFHold(event, <?= $sf['folder_id'] ?>)"
         onmouseup="clearSFHold()"
         onmouseleave="clearSFHold()"
         ontouchstart="startSFHold(event, <?= $sf['folder_id'] ?>)"
         ontouchend="clearSFHold()"
    >
      <img src="folder.png">
      <p class="folder-name"><?= htmlspecialchars($sf['folder_name']) ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ══════════ NOTES SCROLL AREA ══════════ -->
  <div class="drafts-container" id="draftsContainer">

  <?php
  if ($result->num_rows === 0) {
    echo "<p style='padding:15px;'>No drafts found.</p>";
  } else {
    while ($draft = $result->fetch_assoc()) {
      $title  = $draft['title']   ?? 'Untitled';
      $author = $_SESSION['name'] ?? 'User';
      $sfid   = $draft['subject_folder_id'] ?? null;

      echo "
<div class='draft-card'
     data-noteid='" . $draft['note_id'] . "'
     data-sfid='" . ($sfid ?? '') . "'
     data-type='" . htmlspecialchars($draft['type']) . "'
     data-title='" . htmlspecialchars(strtolower($title)) . "'
     onmousedown='startCardHold(event, this)'
     onmouseup='clearCardHold()'
     onmouseleave='clearCardHold()'
     ontouchstart='startCardHold(event, this)'
     ontouchend='clearCardHold()'
     onclick='onCardClick(event, this)'
>
  <img src='offlinemode.png' class='draft-download' onclick='toggleDownload(this)'>
  <div class='draft-content'>
    <div class='draft-left'>
      <h3>" . htmlspecialchars($title) . "</h3>
      <p class='draft-author'>Uploaded by:<br>" . htmlspecialchars($author) . "</p>
      <div class='draft-left-btns'>
        <button onclick='readNote(" . $draft['note_id'] . ")' class='btn-read'>Read</button>
        <button onclick='deleteNote(" . $draft['note_id'] . ")' class='btn-delete'>Delete</button>
      </div>
    </div>
    <div class='draft-right'>
      <img src='" . (!empty($draft['subject_image']) ? htmlspecialchars($draft['subject_image']) : 'file.png') . "' class='draft-icon'>
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

  </div><!-- /drafts-container -->

  <!-- BOTTOM BAR -->
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

</div><!-- /container -->

<!-- ══════════ MODALS ══════════ -->

<!-- Folder name modal (reused for create/rename) -->
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

<!-- Delete note modal -->
<div class="folder-modal-overlay" id="deleteNoteModal">
  <div class="folder-modal-box">
    <h3>Delete this note?</h3>
    <p style="text-align:center;color:#666;font-size:14px;margin-top:-6px;">This will also delete its quiz.</p>
    <div class="folder-modal-btns">
      <button class="folder-modal-cancel" onclick="closeDeleteNoteModal()">Cancel</button>
      <button class="folder-modal-confirm" style="background:#e74c3c;" onclick="confirmDeleteNote()">Delete</button>
    </div>
  </div>
</div>

<!-- Move modal — same .modales/.modal-contentss/.folder-option as notes.php -->
<div class="modales" id="folderPickerModal">
  <div class="modal-contentss">
    <h3>Select Folder</h3>
    <div id="fpList"></div>
    <button onclick="closeFolderPicker()">Cancel</button>
  </div>
</div>

<script>
/* ══════════════════════════════════════
   DATA FROM PHP
══════════════════════════════════════ */
const subjectFolders = <?php echo json_encode($subject_folders); ?>;

/* ══════════════════════════════════════
   FOLDER VIEW STATE
══════════════════════════════════════ */
let activeSFId   = null;
let activeSFName = '';

function openSubjectFolder(id, name) {
  activeSFId   = id;
  activeSFName = name;
  document.getElementById('sfBackItem').classList.add('visible');
  filterCards();
}

function closeSubjectFolder() {
  activeSFId   = null;
  activeSFName = '';
  document.getElementById('sfBackItem').classList.remove('visible');
  filterCards();
  document.querySelectorAll('.folder[data-sfid]').forEach(el => el.classList.remove('selected'));
}

function filterCards() {
  const query = (document.getElementById('searchInput').value || '').trim().toLowerCase();

  document.querySelectorAll('.draft-card').forEach(card => {
    const cardSFId  = card.dataset.sfid ? parseInt(card.dataset.sfid) : null;
    const cardTitle = (card.dataset.title || '').toLowerCase();

    /* ── folder filter ── */
    let folderMatch;
    if (activeSFId === null) {
      folderMatch = !cardSFId || isNaN(cardSFId);
    } else {
      folderMatch = cardSFId === activeSFId;
    }

    /* ── search filter ── */
    const searchMatch = !query || cardTitle.includes(query);

    card.style.display = (folderMatch && searchMatch) ? '' : 'none';
  });

  /* show/hide empty-state message */
  const anyVisible = [...document.querySelectorAll('.draft-card')]
    .some(c => c.style.display !== 'none');
  let emptyMsg = document.getElementById('searchEmptyMsg');
  if (!anyVisible && query) {
    if (!emptyMsg) {
      emptyMsg = document.createElement('p');
      emptyMsg.id = 'searchEmptyMsg';
      emptyMsg.style.cssText = 'padding:15px;color:#888;font-size:14px;text-align:center;';
      document.getElementById('draftsContainer').appendChild(emptyMsg);
    }
    emptyMsg.textContent = 'No subjects match "' + query + '".';
    emptyMsg.style.display = '';
  } else if (emptyMsg) {
    emptyMsg.style.display = 'none';
  }
}

filterCards();

/* ══════════════════════════════════════
   LONG-PRESS ON DRAFT CARD
   → shows Move / Delete / Cancel in bottom bar
══════════════════════════════════════ */
let cardHoldTimer   = null;
let cardHoldTarget  = null;
let cardHoldFired   = false;
let cardTouchStartX = 0;
let cardTouchStartY = 0;
let cardSelecting   = false;
let selectedCards   = new Set();
let activeCardEl    = null;

function startCardHold(e, el) {
  cardHoldFired  = false;
  cardHoldTarget = el;
  if (e.touches) {
    cardTouchStartX = e.touches[0].clientX;
    cardTouchStartY = e.touches[0].clientY;
    el.addEventListener('touchmove', onCardTouchMove, { passive: true });
  }
  el.classList.add('pressing');
  cardHoldTimer = setTimeout(() => {
    cardHoldFired = true;
    el.classList.remove('pressing');
    if (!cardSelecting) {
      cardSelecting = true;
      selectedCards.clear();
      showCardActionBar(el);
    }
    toggleCardSelect(el);
  }, 600);
}

function onCardTouchMove(e) {
  const dx = Math.abs(e.touches[0].clientX - cardTouchStartX);
  const dy = Math.abs(e.touches[0].clientY - cardTouchStartY);
  if (dx > 8 || dy > 8) clearCardHold();
}

function clearCardHold() {
  clearTimeout(cardHoldTimer);
  cardHoldTimer = null;
  if (cardHoldTarget) {
    cardHoldTarget.classList.remove('pressing');
    cardHoldTarget.removeEventListener('touchmove', onCardTouchMove);
    cardHoldTarget = null;
  }
}

function onCardClick(e, el) {
  if (cardHoldFired) { cardHoldFired = false; return; }
  if (cardSelecting) {
    e.stopPropagation();
    toggleCardSelect(el);
  }
}

function toggleCardSelect(el) {
  const id = el.dataset.noteid;
  if (selectedCards.has(id)) {
    selectedCards.delete(id);
    el.classList.remove('card-selected');
  } else {
    selectedCards.add(id);
    el.classList.add('card-selected');
  }
  if (selectedCards.size === 0) cancelCardAction();
}

function showCardActionBar(el) {
  activeCardEl = el;
  document.getElementById('bottomBar').innerHTML = `
    <div class="item">
      <button onclick="openFolderPicker()"><img src="folder.png"></button>
      <p>Move</p>
    </div>
    <div class="item">
      <button onclick="cancelCardAction()"><img src="back.png"></button>
      <p>Cancel</p>
    </div>
  `;
}

function cancelCardAction() {
  cardSelecting = false;
  selectedCards.clear();
  activeCardEl = null;
  document.querySelectorAll('.draft-card').forEach(c => c.classList.remove('card-selected', 'pressing'));
  restoreBottomBar();
}

/* ══════════════════════════════════════
   FOLDER PICKER (shown after tapping Move)
══════════════════════════════════════ */
function openFolderPicker() {
  if (!activeCardEl) return;
  const currentSFId = activeCardEl.dataset.sfid ? parseInt(activeCardEl.dataset.sfid) : null;

  const list = document.getElementById('fpList');
  list.innerHTML = '';

  if (currentSFId) {
    const rem = document.createElement('div');
    rem.className = 'folder-option';
    rem.style.color = '#c0392b';
    rem.textContent = 'Remove from folder';
    rem.onclick = () => moveToFolder(null);
    list.appendChild(rem);
  }

  if (subjectFolders.length === 0) {
    const empty = document.createElement('p');
    empty.style.cssText = 'font-size:13px;color:#888;text-align:center;padding:10px 0;';
    empty.textContent = 'No folders yet. Create one with the Add button above.';
    list.appendChild(empty);
  }

  subjectFolders.forEach(sf => {
    if (sf.folder_id === currentSFId) return;
    const opt = document.createElement('div');
    opt.className = 'folder-option';
    opt.textContent = sf.folder_name;
    opt.onclick = () => moveToFolder(sf.folder_id);
    list.appendChild(opt);
  });

  document.getElementById('folderPickerModal').style.display = 'flex';
}

function closeFolderPicker() {
  document.getElementById('folderPickerModal').style.display = 'none';
}

function moveToFolder(folderId) {
  const ids = selectedCards.size > 0
    ? [...selectedCards].map(Number)
    : (activeCardEl ? [parseInt(activeCardEl.dataset.noteid)] : []);

  if (ids.length === 0) return;
  closeFolderPicker();
  cancelCardAction();

  Promise.all(ids.map(nid =>
    fetch('move_subject_to_folder.php', {
      method: 'POST',
      body: new URLSearchParams({ note_id: nid, folder_id: folderId === null ? 'null' : folderId })
    }).then(r => r.text())
  )).then(() => {
    ids.forEach(nid => {
      const el = document.querySelector(`.draft-card[data-noteid="${nid}"]`);
      if (el) el.dataset.sfid = folderId === null ? '' : folderId;
    });
    filterCards();
    showToast(folderId === null ? 'Removed from folder' : 'Moved to folder');
  });
}

/* ══════════════════════════════════════
   LONG-PRESS ON SUBJECT FOLDER
   → shows Rename / Delete / Cancel in bottom bar
══════════════════════════════════════ */
let sfHoldTimer = null;
let sfHoldFired = false;
let sfHoldId    = null;

function startSFHold(e, id) {
  sfHoldFired = false;
  sfHoldId    = id;
  sfHoldTimer = setTimeout(() => {
    sfHoldFired = true;
    const el = document.querySelector(`.folder[data-sfid="${id}"]`);
    if (el) el.classList.add('sf-pressing');
    showSFActionBar(id);
  }, 600);
  e.stopPropagation();
}

function clearSFHold() {
  clearTimeout(sfHoldTimer);
  sfHoldTimer = null;
  if (sfHoldId) {
    const el = document.querySelector(`.folder[data-sfid="${sfHoldId}"]`);
    if (el) el.classList.remove('sf-pressing');
    sfHoldId = null;
  }
}

/* tap vs hold: only open folder if hold didn't fire */
function onSFClick(e, id, name) {
  if (sfHoldFired) { sfHoldFired = false; return; }
  /* if cards are in hold/select state, cancel it first */
  if (typeof cardSelecting !== 'undefined' && cardSelecting) cancelCardAction();
  /* if folder action bar is open, cancel it first then navigate */
  if (activeSFActionId) cancelSFAction();
  openSubjectFolder(id, name);
}

let activeSFActionId = null;

function showSFActionBar(id) {
  activeSFActionId = id;
  document.querySelectorAll('.folder[data-sfid]').forEach(el => {
    el.classList.remove('sf-pressing');
    el.classList.toggle('selected', parseInt(el.dataset.sfid) === id);
  });

  document.getElementById('bottomBar').innerHTML = `
    <div class="item">
      <button onclick="renameSFFolder()"><img src="Edit.png"></button>
      <p>Rename</p>
    </div>
    <div class="item">
      <button onclick="deleteSFFolder()"><img src="bin.png"></button>
      <p>Delete</p>
    </div>
    <div class="item">
      <button onclick="cancelSFAction()"><img src="back.png"></button>
      <p>Cancel</p>
    </div>
  `;
}

function cancelSFAction() {
  document.querySelectorAll('.folder[data-sfid]').forEach(el => el.classList.remove('selected'));
  activeSFActionId = null;
  restoreBottomBar();
}

function renameSFFolder() {
  const id = activeSFActionId;
  cancelSFAction();
  const sf = subjectFolders.find(f => f.folder_id === id);
  openFolderModal('Rename folder', sf ? sf.folder_name : '', newName => {
    fetch('rename_subject_folder.php', {
      method: 'POST',
      body: new URLSearchParams({ folder_id: id, folder_name: newName })
    }).then(() => location.reload());
  });
}

function deleteSFFolder() {
  const id = activeSFActionId;
  cancelSFAction();
  fetch('delete_subject_folder.php', {
    method: 'POST',
    body: new URLSearchParams({ folder_id: id })
  }).then(() => location.reload());
}

/* ══════════════════════════════════════
   SUBJECT FOLDER CREATION
══════════════════════════════════════ */
function createSubjectFolder() {
  openFolderModal('Folder name', '', name => {
    fetch('create_subject_folder.php', {
      method: 'POST',
      body: new URLSearchParams({ folder_name: name })
    })
    .then(r => r.json())
    .then(data => { if (data.folder_id) location.reload(); });
  });
}

/* ══════════════════════════════════════
   BOTTOM BAR RESTORE
══════════════════════════════════════ */
function restoreBottomBar() {
  document.getElementById('bottomBar').innerHTML = `
    <div class="item">
      <button onclick="addnote()"><img src="addnote.png"></button>
      <p>Add Subject</p>
    </div>
    <div class="item">
      <button onclick="viewNote()"><img src="notes.png"></button>
      <p>Notes</p>
    </div>
  `;
}

/* ══════════════════════════════════════
   GENERIC FOLDER NAME MODAL
══════════════════════════════════════ */
let folderModalCallback = null;

function openFolderModal(title, defaultVal, callback) {
  document.getElementById('folderModalTitle').textContent = title;
  const input = document.getElementById('folderNameInput');
  input.value = defaultVal || '';
  folderModalCallback = callback;
  document.getElementById('folderNameModal').classList.add('active');
  setTimeout(() => input.focus(), 100);
}

function closeFolderModal() {
  document.getElementById('folderNameModal').classList.remove('active');
  document.getElementById('folderNameInput').value = '';
  folderModalCallback = null;
}

function confirmFolderModal() {
  const val = document.getElementById('folderNameInput').value.trim();
  if (!val) return;
  const cb = folderModalCallback;
  folderModalCallback = null;
  closeFolderModal();
  if (cb) cb(val);
}

/* ══════════════════════════════════════
   SEARCH INPUT — filter by subject title
══════════════════════════════════════ */
document.getElementById('searchInput').addEventListener('input', filterCards);

document.getElementById('searchInput').addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    e.target.value = '';
    filterCards();
    e.target.blur();
  }
});

document.getElementById('folderNameInput').addEventListener('keydown', e => {
  if (e.key === 'Enter')  confirmFolderModal();
  if (e.key === 'Escape') closeFolderModal();
});

/* ══════════════════════════════════════
   READ / DELETE / PUBLISH NOTE
══════════════════════════════════════ */
function readNote(id) { window.location.href = 'viewnote.php?note_id=' + id; }

let pendingDeleteId = null;

function deleteNote(id) {
  pendingDeleteId = id;
  if (activeCardEl) activeCardEl.classList.remove('card-selected');
  restoreBottomBar();
  activeCardEl = null;
  document.getElementById('deleteNoteModal').classList.add('active');
}

function confirmDeleteNote() {
  if (!pendingDeleteId) return;
  fetch('deletenote.php', {
    method: 'POST',
    body: new URLSearchParams({ note_id: pendingDeleteId })
  }).then(() => location.reload());
}

function closeDeleteNoteModal() {
  pendingDeleteId = null;
  document.getElementById('deleteNoteModal').classList.remove('active');
}

function togglePublish(id, currentType) {
  const newType = currentType === 'subject' ? 'subject_draft' : 'subject';
  fetch('togglepublish.php', {
    method: 'POST',
    body: new URLSearchParams({ note_id: id, type: newType })
  }).then(() => location.reload());
}

/* ══════════════════════════════════════
   OFFLINE DOWNLOAD
══════════════════════════════════════ */
function toggleDownload(img) {
  const isDownloaded = img.getAttribute('data-downloaded') === 'true';
  if (!isDownloaded) {
    img.src = 'bluecheck.png';
    img.setAttribute('data-downloaded', 'true');
    showToast('Downloaded for offline reading');
  } else {
    img.src = 'offlinemode.png';
    img.setAttribute('data-downloaded', 'false');
    showToast('Removed from offline');
  }
}

function showToast(msg) {
  let toast = document.getElementById('toastMsg');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'toastMsg';
    toast.style.cssText = `
      position:fixed;top:80px;left:50%;
      transform:translateX(-50%) translateY(-20px);
      background:#333;color:white;padding:12px 24px;
      border-radius:8px;font-size:14px;z-index:999;
      opacity:0;transition:0.3s;pointer-events:none;
      font-family:'Inria Sans',sans-serif;
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

/* ══════════════════════════════════════
   UTILS
══════════════════════════════════════ */
function escHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
<script src="script.js"></script>
</body>
</html>