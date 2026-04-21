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
    e.preventDefault();

    // Active state
    if (activeLink) {
      activeLink.classList.remove('active');
    }

    link.classList.add('active');
    activeLink = link;

    // Close menu
    closeMenu();

    // Show notification
    notification.textContent = 'Loading page...';
    notification.style.position = 'fixed';
    notification.style.top = '70%';
    notification.style.left = '50%';
    notification.style.transform = 'translateX(-50%)';
    notification.style.background = '#222';
    notification.style.color = 'white';
    notification.style.padding = '10px 20px';
    notification.style.borderRadius = '6px';
    notification.style.opacity = '1';
    notification.style.transition = '0.3s';

    setTimeout(() => {
      notification.textContent = 'Page Loaded!';

      setTimeout(() => {
        notification.style.opacity = '0';
      }, 1500);

    }, 2000);
  });
});