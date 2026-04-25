// WAIT until DOM is fully loaded
document.addEventListener("DOMContentLoaded", () => {

  // STATE
  let isMenuOpen = false;
  let activeLink = null;

  // ELEMENTS
  const hamburger = document.querySelector('.hamburger');
  const navLinks = document.querySelector('.nav-links');
  const overlay = document.querySelector('.overlay');
  const backBtn = document.querySelector('.back');
  const input = document.getElementById("imageInput");
  const preview = document.getElementById("preview");
  const search = document.getElementById("search");

  // Notification (safe)
  const notification = document.createElement('div');
  notification.className = 'notification';
  document.body.appendChild(notification);

  // MENU FUNCTIONS
  function openMenu() {
    if (navLinks) navLinks.classList.add('active');
    if (overlay) {
      overlay.classList.add('active');
      overlay.style.pointerEvents = 'none';
    }
    isMenuOpen = true;
  }

  function closeMenu() {
    if (navLinks) navLinks.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
    isMenuOpen = false;
  }

  // TOGGLE MENU
  if (hamburger) {
    hamburger.addEventListener('click', () => {
      isMenuOpen ? closeMenu() : openMenu();
    });
  }

  // BACK BUTTON
  if (backBtn) {
    backBtn.addEventListener('click', closeMenu);
  }

  // NAV LINKS
  document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', () => {
      if (activeLink) activeLink.classList.remove('active');
      link.classList.add('active');
      activeLink = link;
      closeMenu();
    });
  });

  // IMAGE UPLOAD
  if (input && preview) {
    input.addEventListener("change", function () {
      const file = this.files[0];

      if (file) {
        const reader = new FileReader();

        reader.onload = function (e) {
          const base64 = e.target.result;
          preview.src = base64;
          localStorage.setItem("profileImage", base64);
        };

        reader.readAsDataURL(file);
      }
    });
  }

  // LOAD SAVED IMAGE
  const savedImage = localStorage.getItem("profileImage");
  if (savedImage && preview) {
    preview.src = savedImage;
  }

  // SEARCH FILTER (MODAL)
  if (search) {
    search.addEventListener("keyup", function () {
      let value = this.value.toLowerCase();
      let items = document.querySelectorAll(".subject-item");

      items.forEach(item => {
        item.style.display = item.innerText.toLowerCase().includes(value)
          ? "block"
          : "none";
      });
    });
  }

}); // END DOMContentLoaded


// GLOBAL FUNCTIONS (for HTML onclick)

function study() {
  window.location.href = "lecture.html";
}

function goBack() {
  window.history.back();
}

function cardBack() {
  window.history.back();
}

function upload() {
  window.location.href = "Uploaded notes.php";
}

function quiz() {
  window.location.href = "flashcards.html";
}

function addnote() {
  window.location.href = "addsubject.php";
}

function goToSubject(id) {
  window.location.href = "subject.php?id=" + id;
}

function openFolder(id) {
  window.location.href = "notes.php?folder_id=" + id;
}

// MODAL CONTROLS (must be global)
function openModal() {
  const modal = document.getElementById("modal");
  if (modal) modal.style.display = "flex";
}

function closeModal() {
  const modal = document.getElementById("modal");
  if (modal) modal.style.display = "none";
}


function readNote(id) {
  window.location.href = "viewnote.php?note_id=" + id;
}

function deleteNote(id) {
  if (!confirm("Delete this note?")) return;

  fetch("deletenote.php", {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body: "note_id=" + id
  })
  .then(res => res.text())
  .then(data => {
    console.log("Server:", data);

    if (data === "deleted") {
      location.reload();
    } else {
      alert("Delete failed: " + data);
    }
  })
  .catch(err => console.error(err));
}

function togglePublish(id, currentType) {
  const newType = currentType === "subject" ? "subject_draft" : "subject";

  fetch("togglepublish.php", {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body: `note_id=${id}&type=${newType}`
  })
  .then(() => location.reload());
}

function addNoteAsSubject(noteId, el) {
  fetch("add_note_to_subject.php", {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body: "note_id=" + noteId
  })
  .then(res => res.text())
  .then(() => {
    el.remove();
  });
}

function refreshModal() {
  fetch("fetch_modal_subjects.php")
    .then(res => res.text())
    .then(html => {
      document.querySelector(".subject-list").innerHTML = html;
    });
}