<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Note</title>
<link rel="stylesheet" href="style2.css">
</head>

<body>

<div class="container">

<form id="noteForm" method="POST" action="savingnote.php">

<input type="hidden" name="note_id" value="">
<input type="hidden" name="text_alignment" id="textAlignment" value="center">
<input type="hidden" name="content" id="hiddenContent">

<!-- TOP -->
<div class="top-bar">
  <button type="button" class="back-btn" onclick="window.location.href='notes.php'" style="background:none;border:none;font-size:30px;cursor:pointer;color:#000;">←</button>
  <input type="text" name="title" class="title-input" placeholder="(Insert title here)">
</div>

<!-- TOOLBAR -->
<div class="text-toolbar">
  <button type="button" class="toolbar-btn" data-align="left" title="Align Left">
    <svg viewBox="0 0 24 24"><path d="M4 19h6v-2H4v2zm0-4h10v-2H4v2zm0-4h16v-2H4v2zm0-8v2h16V3H4z"/></svg>
  </button>
  <button type="button" class="toolbar-btn active" data-align="center" title="Align Center">
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
<div class="note-content-area">
  <div class="rich-editor" id="noteEditor" contenteditable="true" placeholder="(insert text here)"></div>
</div>

<!-- BOTTOM -->
<div class="bottom-bar">
  <button type="submit" onclick="prepareSubmit()"><img src="addnote.png"></button>
  <p>Add Subject</p>
</div>

</form>

</div>

<script>
const editor = document.getElementById('noteEditor');
const alignmentInput = document.getElementById('textAlignment');
const hiddenContent = document.getElementById('hiddenContent');
const toolbarBtns = document.querySelectorAll('.toolbar-btn');

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
  editor.style.textAlign = align;
  alignmentInput.value = align;
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
editor.addEventListener('click', updateFormatButtons);

if (window.getSelection) {
  document.addEventListener('selectionchange', () => {
    const sel = window.getSelection();
    if (sel.rangeCount > 0 && editor.contains(sel.anchorNode)) {
      updateFormatButtons();
    }
  });
}

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
</script>
</body>
</html>