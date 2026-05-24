<?php
session_start();
include 'database.php';

$note_id = $_GET['note_id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM notes WHERE note_id=?");
$stmt->bind_param("i", $note_id);
$stmt->execute();
$result = $stmt->get_result();
$note = $result->fetch_assoc();

$savedAlign = $note['text_alignment'] ?? 'center';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Note</title>
<link rel="stylesheet" href="style2.css">
</head>
<body>

<div class="container">

<form id="noteForm" method="POST" action="savingnote.php">

<input type="hidden" name="note_id" value="<?= $note_id ?>">
<input type="hidden" name="text_alignment" id="textAlignment" value="<?= htmlspecialchars($savedAlign) ?>">
<input type="hidden" name="content" id="hiddenContent">

<!-- TOP -->
<div class="top-bar">
  <button type="button" class="back-btn" onclick="window.location.href='notes.php'" style="background:none;border:none;font-size:30px;cursor:pointer;color:#000;">←</button>
  <input type="text" name="title" class="title-input" value="<?= htmlspecialchars($note['title'] ?? '') ?>" placeholder="(Insert title here)">
</div>

<!-- TOOLBAR -->
<div class="text-toolbar">
  <button type="button" class="toolbar-btn" data-align="left" title="Align Left">
    <svg viewBox="0 0 24 24"><path d="M4 19h6v-2H4v2zm0-4h10v-2H4v2zm0-4h16v-2H4v2zm0-8v2h16V3H4z"/></svg>
  </button>
  <button type="button" class="toolbar-btn" data-align="center" title="Align Center">
    <svg viewBox="0 0 24 24"><path d="M7 15v2h10v-2H7zm-4-4h18v-2H3v2zm4-6v2h10V5H7z"/></svg>
  </button>
  <button type="button" class="toolbar-btn" data-align="right" title="Align Right">
    <svg viewBox="0 0 24 24"><path d="M14 19h6v-2h-6v2zm-4-4h10v-2H10v2zm-6-4h16v-2H4v2zm0-8v2h16V3H4z"/></svg>
  </button>
  <button type="button" class="toolbar-btn" data-align="justify" title="Justify">
    <svg viewBox="0 0 24 24"><path d="M4 19h16v-2H4v2zm0-4h16v-2H4v2zm0-4h16v-2H4v2zm0-8v2h16V3H4z"/></svg>
  </button>

  <div class="toolbar-divider"></div>

  <button type="button" class="toolbar-btn" data-list="bullet" title="Bullet List">
    <svg viewBox="0 0 24 24"><path d="M4 6h2v2H4zm0 5h2v2H4zm0 5h2v2H4zm4-10h12v2H8zm0 5h12v2H8zm0 5h12v2H8z"/></svg>
  </button>
  <button type="button" class="toolbar-btn" data-list="number" title="Numbered List">
    <svg viewBox="0 0 24 24"><path d="M2 17h2v.5H3v1h1v.5H2v1h3v-4H2v1zm1-9h1V4H2v1h1v3zm-1 3h1.8L2 13.1v.9h3v-1H3.2L5 10.9V10H2v1zm5-6v2h14V5H7zm0 14h14v-2H7v2zm0-6h14v-2H7v2z"/></svg>
  </button>

  <div class="toolbar-divider"></div>

  <button type="button" class="toolbar-btn" data-cmd="bold" title="Bold">
    <b>B</b>
  </button>
  <button type="button" class="toolbar-btn" data-cmd="italic" title="Italic">
    <i>I</i>
  </button>
  <button type="button" class="toolbar-btn" data-cmd="underline" title="Underline">
    <u>U</u>
  </button>
  <button type="button" class="toolbar-btn" data-cmd="strikeThrough" title="Strikethrough">
    <s>abc</s>
  </button>
  <button type="button" class="toolbar-btn" data-cmd="subscript" title="Subscript">
    x<sub>2</sub>
  </button>
  <button type="button" class="toolbar-btn" data-cmd="superscript" title="Superscript">
    x<sup>2</sup>
  </button>
</div>

<!-- CONTENT -->
<div class="editor">
  <div class="rich-editor" id="noteEditor" contenteditable="true" placeholder="(insert text here)"></div>
</div>

<!-- BOTTOM -->
<div class="bottom-bar">
  <button type="submit" class="bottom-btn" onclick="prepareSubmit()">
    <svg viewBox="0 0 24 24" width="28" height="28"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
    <p>Save changes</p>
  </button>
  <button type="button" class="bottom-btn" onclick="document.getElementById('imageInput').click()" title="Add Image">
    <svg viewBox="0 0 24 24" width="28" height="28"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
    <p>Add image</p>
  </button>
</div>

<input type="file" id="imageInput" accept="image/*" style="display:none" onchange="handleImageUpload(this)">

</form>
</div>

<script>
const editor = document.getElementById('noteEditor');
const alignmentInput = document.getElementById('textAlignment');
const hiddenContent = document.getElementById('hiddenContent');
const toolbarBtns = document.querySelectorAll('.toolbar-btn');

// ========== LOAD SAVED CONTENT ==========
const savedContent = <?= json_encode($note['content'] ?? '') ?>;
if (savedContent) {
  editor.innerHTML = savedContent;
}

// ========== LOAD SAVED ALIGNMENT ==========
const savedAlign = alignmentInput.value || 'center';
editor.style.textAlign = savedAlign;

toolbarBtns.forEach(btn => {
  if (btn.dataset.align) {
    btn.classList.remove('active');
    if (btn.dataset.align === savedAlign) {
      btn.classList.add('active');
    }
  }
});

// ========== IMAGE SELECTION ==========
let selectedImage = null;

function selectImage(img) {
  if (selectedImage) {
    selectedImage.classList.remove('selected-image');
  }
  selectedImage = img;
  if (img) {
    img.classList.add('selected-image');
  }
}

function deselectImage() {
  if (selectedImage) {
    selectedImage.classList.remove('selected-image');
    selectedImage = null;
  }
}

function alignImage(img, align) {
  img.setAttribute('data-align', align);
  // Force re-apply styles by removing and re-adding to trigger CSS
  img.style.cssText = '';
}

// ========== SIMPLE FORMATTING WITH EXECCOMMAND ==========
function toggleFormat(cmd) {
  editor.focus();
  document.execCommand(cmd, false, null);
  updateFormatButtons();
}

// ========== LIST HANDLING ==========
function toggleList(listType) {
  editor.focus();
  const cmd = listType === 'bullet' ? 'insertUnorderedList' : 'insertOrderedList';
  document.execCommand(cmd, false, null);
}

// ========== ALIGNMENT ==========
function setAlignment(align, btn) {
  if (selectedImage && editor.contains(selectedImage)) {
    // Image is selected — only align the image
    alignImage(selectedImage, align);
  } else {
    // No image selected — align the text/editor
    editor.style.textAlign = align;
    alignmentInput.value = align;
  }
  // Update toolbar buttons
  toolbarBtns.forEach(b => {
    if (b.dataset.align) b.classList.remove('active');
  });
  btn.classList.add('active');
}

// ========== TOOLBAR HANDLERS ==========
toolbarBtns.forEach(btn => {
  btn.addEventListener('mousedown', (e) => {
    e.preventDefault();
  });

  btn.addEventListener('click', (e) => {
    e.preventDefault();

    const align = btn.dataset.align;
    const list = btn.dataset.list;
    const cmd = btn.dataset.cmd;

    if (align) setAlignment(align, btn);
    if (list) toggleList(list);
    if (cmd) toggleFormat(cmd);
  });
});

// ========== UPDATE BUTTON STATES ==========
function updateFormatButtons() {
  toolbarBtns.forEach(btn => {
    const cmd = btn.dataset.cmd;
    if (cmd) {
      const state = document.queryCommandState(cmd);
      if (state) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    }
  });
}

editor.addEventListener('keyup', updateFormatButtons);
editor.addEventListener('mouseup', updateFormatButtons);
editor.addEventListener('click', (e) => {
  updateFormatButtons();
  if (e.target.tagName === 'IMG') {
    e.preventDefault();
    e.stopPropagation();
    selectImage(e.target);
    // Update toolbar to show image's alignment
    const imgAlign = e.target.getAttribute('data-align') || 'center';
    toolbarBtns.forEach(btn => {
      if (btn.dataset.align) {
        btn.classList.remove('active');
        if (btn.dataset.align === imgAlign) {
          btn.classList.add('active');
        }
      }
    });
  } else {
    deselectImage();
    // Restore toolbar to show editor's text alignment
    const editorAlign = editor.style.textAlign || 'center';
    toolbarBtns.forEach(btn => {
      if (btn.dataset.align) {
        btn.classList.remove('active');
        if (btn.dataset.align === editorAlign) {
          btn.classList.add('active');
        }
      }
    });
  }
});

if (window.getSelection) {
  document.addEventListener('selectionchange', () => {
    const sel = window.getSelection();
    if (sel.rangeCount > 0 && editor.contains(sel.anchorNode)) {
      updateFormatButtons();
    }
  });
}

// Click outside editor deselects image
document.addEventListener('click', (e) => {
  if (!editor.contains(e.target)) {
    deselectImage();
  }
});

// ========== SAVE ==========
function prepareSubmit() {
  hiddenContent.value = editor.innerHTML;
}

// ========== PLACEHOLDER ==========
function checkPlaceholder() {
  if (editor.textContent.trim() === '' && editor.innerHTML.replace(/<br\s*\/?>/gi, '').trim() === '') {
    editor.classList.add('empty');
  } else {
    editor.classList.remove('empty');
  }
}

editor.addEventListener('input', checkPlaceholder);
editor.addEventListener('blur', checkPlaceholder);
editor.addEventListener('focus', checkPlaceholder);
checkPlaceholder();

// ========== IMAGE UPLOAD ==========
function handleImageUpload(input) {
  const file = input.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = function(e) {
    const img = document.createElement('img');
    img.src = e.target.result;
    img.style.maxWidth = '100%';
    img.style.borderRadius = '8px';
    img.style.margin = '10px 0';

    editor.focus();

    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      if (editor.contains(range.commonAncestorContainer)) {
        range.deleteContents();
        range.insertNode(img);

        range.setStartAfter(img);
        range.setEndAfter(img);
        selection.removeAllRanges();
        selection.addRange(range);

        const br = document.createElement('br');
        range.insertNode(br);
        range.setStartAfter(br);
        range.setEndAfter(br);
        selection.removeAllRanges();
        selection.addRange(range);
      } else {
        editor.appendChild(img);
        editor.appendChild(document.createElement('br'));
      }
    } else {
      editor.appendChild(img);
      editor.appendChild(document.createElement('br'));
    }

    checkPlaceholder();
  };
  reader.readAsDataURL(file);
  input.value = '';
}
</script>

</body>
</html>