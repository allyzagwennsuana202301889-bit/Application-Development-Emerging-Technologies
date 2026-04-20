let isMenuOpen = false;
  let activeLink = null;

  const hamburger = document.querySelector('.hamburger');
  const navLinks = document.querySelector('.nav-links');
  const overlay = document.querySelector('.overlay');
  const notification = document.createElement('div');
  notification.className = 'notification';
  document.body.appendChild(notification);

  // Toggle menu
  hamburger.addEventListener('click', () => {
    isMenuOpen = !isMenuOpen;
    navLinks.classList.toggle('active');
    overlay.classList.toggle('active');
  });

  // Close menu when overlay clicked
  overlay.addEventListener('click', () => {
    navLinks.classList.remove('active');
    overlay.classList.remove('active');
    isMenuOpen = false;
  });

  // Handle link clicks
  document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();

      // Active state
      if (activeLink) {
        activeLink.classList.remove('active');
      }

      link.classList.add('active');
      activeLink = link;

      // Close menu on click (mobile UX)
      navLinks.classList.remove('active');
      overlay.classList.remove('active');

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