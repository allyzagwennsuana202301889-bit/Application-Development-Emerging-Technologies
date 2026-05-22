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
        preview.src = e.target.result;
      };
      reader.readAsDataURL(file);
    }
  });
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
  window.location.href = "homepage.php";
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
  window.location.href = "flashcards.php";
}

function addnote() {
  window.location.href = "addsubject.php";
}

function notif() {
  window.location.href = "notification.php";
}


function goToSubject(id) {
  window.location.href = "subject.php?id=" + id;
}

function goToCustomSubject(id, type = "subjects") {
  window.location.href = "subject.php?id=" + id + "&type=" + type;
}



function viewSubject(id, source) {
    window.location.href = "view_subject.php?subject_id=" + id + "&source=subjects";
}



function openFolder(id) {
  window.location.href = "notes.php?folder_id=" + id;
}

function viewNote() {
  window.location.href = "notes.php?"
}


function readNote(id) {
  window.location.href = "viewnote.php?note_id=" + id;
}

function noting() {
  window.location.href = "writenotes.php"
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
  .then(() => {
    // Refresh the modal list without full page reload
    refreshModal();
    // Also refresh the current page cards
    location.reload();
  });
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
      const list = document.querySelector(".subject-list");
      if (list) list.innerHTML = html;
    });
}

// Auto-refresh modal when it opens
document.addEventListener("DOMContentLoaded", () => {
  const modal = document.getElementById("modal");
  if (modal) {
    // Use MutationObserver to detect when modal becomes visible
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === "attributes" && mutation.attributeName === "style") {
          if (modal.style.display === "flex") {
            refreshModal();
          }
        }
      });
    });
    observer.observe(modal, { attributes: true });
  }
});

//back save//

function saveAndBack(){
  let title = document.querySelector("[name='title']").value.trim();
  let content = document.querySelector("[name='content']").value.trim();

  //  don't save empty notes
  if(title === "" && content === ""){
    window.history.back();
    return;
  }

  fetch("savingnote.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: new URLSearchParams({
      title: title,
      content: content
    })
  })
  .then(res => res.text())
  .then(data => {
    console.log("Saved:", data);

    //  AFTER saving → go back
    window.location.href = "notes.php"; 
    // or use history.back() if you prefer
  })
  .catch(err => {
    console.error(err);
    window.history.back(); // fallback
  });
}



/* ========== MODAL FUNCTIONS ========== */
function openModal() {
  const modal = document.getElementById('modal');
  if (modal) {
    modal.style.display = 'flex';
    loadModalSubjects(); // Fetch fresh data when opening
  }
}

function closeModal() {
  const modal = document.getElementById('modal');
  if (modal) modal.style.display = 'none';
}

window.onclick = function(event) {
  const modal = document.getElementById('modal');
  if (event.target === modal) {
    modal.style.display = 'none';
  }
}

function loadModalSubjects() {
  const list = document.getElementById('modalSubjectList');
  if (!list) return;

  list.innerHTML = '<p>Loading...</p>';

  fetch('fetch_modal_subjects.php')
    .then(res => res.text())
    .then(html => {
      list.innerHTML = html;
    })
    .catch(err => {
      console.error('Failed to load subjects:', err);
      list.innerHTML = '<p>Error loading subjects.</p>';
    });
}

/* ========== ATTACH CLICK HANDLERS TO MODAL ITEMS ========== */
document.addEventListener("click", function (e) {
  const item = e.target.closest(".subject-item");
  if (!item) return;

  const id = item.dataset.id;
  const source = item.dataset.source;

  if (source === "subject") {
    window.location.href = "view_subject.php?subject_id=" + id;
  } 
  else if (source === "note") {
    // optional: either ignore or redirect elsewhere
    window.location.href = "view_subject.php?subject_id=" + id;
  }
});


/* ========== SEARCH SUBJECT FILTER (homepage) ========== */
(function() {
  function initSubjectSearch() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
      const filter = this.value.toLowerCase().trim();
      const cards = document.querySelectorAll('.subjects-container .subject-card');
      let visibleCount = 0;

      cards.forEach(function(card) {
        const subjectName = card.querySelector('.card-left h2');
        const uploaderName = card.querySelector('.uploaded');
        
        const text = (
          (subjectName ? subjectName.textContent : '') + ' ' +
          (uploaderName ? uploaderName.textContent : '')
        ).toLowerCase();

        if (text.includes(filter)) {
          card.style.display = '';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });

      // No results message
      let noResults = document.querySelector('.no-results-msg');
      if (visibleCount === 0 && filter !== '') {
        if (!noResults) {
          noResults = document.createElement('div');
          noResults.className = 'no-results-msg';
          noResults.textContent = 'No subjects found';
          noResults.style.cssText = 'text-align:center; color:white; font-family:Itim,cursive; font-size:18px; padding:40px 20px;';
          const container = document.querySelector('.subjects-container');
          if (container) container.appendChild(noResults);
        }
        noResults.style.display = 'block';
      } else if (noResults) {
        noResults.style.display = 'none';
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSubjectSearch);
  } else {
    initSubjectSearch();
  }
})();



function uploadPFP() {
    const input = document.getElementById('imageInput');
    const preview = document.getElementById('preview');
    
    if (!input.files || !input.files[0]) return;
    
    const formData = new FormData();
    formData.append('profile_image', input.files[0]);
    
    fetch('upload_pfp.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            preview.src = data.image;
        } else {
            alert(data.error || 'Upload failed');
        }
    })
    .catch(err => {
        console.error('Upload error:', err);
        alert('Upload failed');
    });
}



/* ============================================================
   QUICK NOTE
   ============================================================ */

(function() {
  'use strict';

  let qnOverlay = null;
  let qnTextarea = null;
  let qnTitleInput = null;
  let qnSaveBtn = null;
  let qnCloseBtn = null;
  let qnCard = null;
  let qnOpen = false;
  let qnInitialized = false;

  function initQuickNote() {
    if (qnInitialized) return;
    qnInitialized = true;

    if (!document.getElementById('qnOverlay')) {
      const html = `
        <div class="qn-overlay" id="qnOverlay">
          <div class="qn-card" id="qnCard">
            <div class="qn-header">
              <div class="qn-header-left">
                <button class="qn-save-btn" id="qnSaveBtn" title="Save to notes">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/>
                  </svg>
                </button>
                <input type="text" class="qn-title-input" id="qnTitleInput" placeholder="(Title here)" value="Untitled">
              </div>
              <button class="qn-close-btn" id="qnCloseBtn" title="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                  <path d="M6 9l6 6 6-6"/>
                </svg>
              </button>
            </div>
            <div class="qn-body">
              <textarea class="qn-textarea" id="qnTextarea" placeholder="Add quick note here"></textarea>
            </div>
          </div>
        </div>
      `;
      document.body.insertAdjacentHTML('beforeend', html);
    }

    qnOverlay = document.getElementById('qnOverlay');
    qnTextarea = document.getElementById('qnTextarea');
    qnTitleInput = document.getElementById('qnTitleInput');
    qnSaveBtn = document.getElementById('qnSaveBtn');
    qnCloseBtn = document.getElementById('qnCloseBtn');
    qnCard = document.getElementById('qnCard');

    if (!qnOverlay) return;

    if (qnSaveBtn) {
      qnSaveBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        saveQN();
      });
    }

    if (qnCloseBtn) {
      qnCloseBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        closeQN();
      });
    }

    qnOverlay.addEventListener('click', function(e) {
      if (e.target === qnOverlay) closeQN();
    });

    if (qnTextarea) {
      qnTextarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 300) + 'px';
      });
    }

    document.addEventListener('keydown', function(e) {
      if (!qnOpen) return;
      if (e.key === 'Escape') {
        e.preventDefault();
        closeQN();
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        saveQN();
      }
    });
  }

  function bindTriggers() {
    const items = document.querySelectorAll('.bottom-file-section .item, .bottom-add-section .item');
    items.forEach(function(item) {
      const txt = (item.textContent || '').toLowerCase();
      if (txt.includes('quick note') || txt.includes('quicknote') || txt.includes('add notes')) {
        item.style.cursor = 'pointer';
        item.addEventListener('click', openQN);
      }
    });
  }

  function openQN(e) {
    if (e) e.stopPropagation();
    if (qnOpen) return;
    if (!qnInitialized) initQuickNote();
    if (!qnOverlay) return;

    qnOpen = true;
    if (qnTextarea) {
      qnTextarea.value = '';
      qnTextarea.style.height = 'auto';
    }
    if (qnTitleInput) {
      qnTitleInput.value = 'Untitled';
    }
    if (qnSaveBtn) qnSaveBtn.classList.remove('saved');

    qnOverlay.style.display = 'flex';
    requestAnimationFrame(function() {
      qnOverlay.classList.add('active');
    });
    setTimeout(function() {
      if (qnTitleInput) qnTitleInput.focus();
    }, 300);
  }

  function closeQN() {
    if (!qnOpen) return;
    qnOpen = false;
    if (qnOverlay) qnOverlay.classList.remove('active');
    setTimeout(function() {
      if (qnOverlay) qnOverlay.style.display = 'none';
      if (qnTextarea) {
        qnTextarea.value = '';
        qnTextarea.style.height = 'auto';
      }
      if (qnTitleInput) qnTitleInput.value = 'Untitled';
      if (qnSaveBtn) qnSaveBtn.classList.remove('saved');
    }, 250);
  }

  function saveQN() {
    if (!qnTextarea || !qnTitleInput) return;
    
    const content = qnTextarea.value.trim();
    const title = qnTitleInput.value.trim() || 'Untitled';
    
    if (!content) return;

    if (qnSaveBtn) qnSaveBtn.classList.add('saved');

    fetch('api_quicknote.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        title: title,
        content: content 
      })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.success) {
        setTimeout(function() { closeQN(); }, 600);
      } else {
        alert('Save failed: ' + (data.error || 'Unknown error'));
        if (qnSaveBtn) qnSaveBtn.classList.remove('saved');
      }
    })
    .catch(function(err) {
      alert('Network error: ' + err.message);
      if (qnSaveBtn) qnSaveBtn.classList.remove('saved');
    });
  }

  window.QuickNote = {
    open: openQN,
    close: closeQN,
    init: initQuickNote
  };

  function onReady() {
    initQuickNote();
    bindTriggers();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onReady);
  } else {
    onReady();
  }

  setTimeout(bindTriggers, 500);
  setTimeout(bindTriggers, 1500);

})();



/* ============================================================
   SEARCH TOPIC FILTER (subject page)
   ============================================================ */

(function() {
  'use strict';

  function initTopicSearch() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
      const filter = this.value.toLowerCase().trim();
      const cards = document.querySelectorAll('.subject-content .subject-main-card');
      let visibleCount = 0;

      cards.forEach(function(card) {
        // Get title from the card-title input or any h3/h4 inside
        const titleInput = card.querySelector('.card-title');
        const title = titleInput ? titleInput.value.toLowerCase() : '';

        // Also search description text
        const desc = card.querySelector('.fake-desc');
        const descText = desc ? desc.textContent.toLowerCase() : '';

        const combined = title + ' ' + descText;

        if (combined.includes(filter)) {
          card.style.display = '';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });

      // No results message
      let noResults = document.querySelector('.no-topic-results');
      const container = document.querySelector('.subject-content');

      if (visibleCount === 0 && filter !== '') {
        if (!noResults && container) {
          noResults = document.createElement('div');
          noResults.className = 'no-topic-results';
          noResults.textContent = 'No topics found';
          container.appendChild(noResults);
        }
        if (noResults) noResults.style.display = 'block';
      } else if (noResults) {
        noResults.style.display = 'none';
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTopicSearch);
  } else {
    initTopicSearch();
  }

})();