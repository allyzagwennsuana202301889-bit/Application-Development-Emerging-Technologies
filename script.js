

// STATE
let isMenuOpen = false;
let activeLink = null;

// ELEMENTS
const hamburger = document.querySelector('.hamburger');
const navLinks = document.querySelector('.nav-links');
const overlay = document.querySelector('.overlay');
const backBtn = document.querySelector('.back');


// Notification element
const notification = document.createElement('div');
notification.className = 'notification';
document.body.appendChild(notification);

// FUNCTIONS
function openMenu() {
  navLinks.classList.add('active');
  overlay.classList.add('active');

  // make overlay visible but NOT clickable
  overlay.style.pointerEvents = 'none';

  isMenuOpen = true;
}

function closeMenu() {
  navLinks.classList.remove('active');
  overlay.classList.remove('active');
  isMenuOpen = false;
}

// TOGGLE MENU
hamburger.addEventListener('click', () => {
  isMenuOpen ? closeMenu() : openMenu();
});

// BACK BUTTON (closes menu)
backBtn.addEventListener('click', closeMenu);

// HANDLE LINK CLICKS
document.querySelectorAll('.nav-links a').forEach(link => {
  link.addEventListener('click', (e) => {

    if (activeLink) {
      activeLink.classList.remove('active');
    }

    link.classList.add('active');
    activeLink = link;

    closeMenu();
  });
});


function study() {
  window.location.href = "lecture.html";
}

function goBack() {
  window.location.href = "homepage.php";
}

function cardBack() {
  window.history.back();
}

function upload() {
  window.location.href = "Uploaded notes.html";
}

function quiz(){
   window.location.href = "flashcards.html";
}

function addnote(){
   window.location.href = "addsubject.php";
}

function goBack() {
  window.history.back();
}

function goToSubject(id) {
  window.location.href = "subject.php?id=" + id;
}

function openFolder(id) {
  window.location.href = "notes.php?folder_id=" + id;
}

//Picture input//
const input = document.getElementById("imageInput");
const preview = document.getElementById("preview");

input.addEventListener("change", function () {
  const file = this.files[0];

  if (file) {
    const reader = new FileReader();

    reader.onload = function (e) {
      const base64 = e.target.result;

      preview.src = base64;

      // SAVE to localStorage
      localStorage.setItem("profileImage", base64);
    };

    reader.readAsDataURL(file);
  }
});

window.addEventListener("DOMContentLoaded", () => {
  const savedImage = localStorage.getItem("profileImage");

  if (savedImage) {
    document.getElementById("preview").src = savedImage;
  }
});

