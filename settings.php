<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="style2.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Itim&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #111;
            font-family: 'Itim', cursive;
        }
        
        .container {
            max-width: 412px;
            height: 100vh;
            margin: 0 auto;
            background: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }
        
        /* Blue Header */
        .settings-header {
            height: 80px;
            background: #3B8BFF;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            flex-shrink: 0;
        }
        
        .settings-header .icon-left {
            width: 36px;
            height: 36px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }
        
        .settings-header .title {
            color: #fff;
            font-size: 24px;
            font-family: 'Itim', cursive;
        }
        
        .settings-header .back-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .settings-header .back-btn img {
            width: 28px;
            height: 28px;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }
        
        /* Options List */
        .options-list {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #fff;
        }
        
        .option-row {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 18px 25px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .option-row:hover {
            background: #f5f5f5;
        }
        
        .option-row:last-child {
            border-bottom: none;
        }
        
        .option-icon {
            width: 45px;
            height: 45px;
            object-fit: contain;
            flex-shrink: 0;
            transition: filter 0.3s;
        }
        
        /* Notification logo colors - THE TOGGLE */
        .option-icon.notif-on {
            filter: brightness(0) saturate(100%) invert(55%) sepia(95%) saturate(500%) hue-rotate(-10deg);
        }
        
        .option-icon.notif-off {
            filter: brightness(0);
        }
        
        .option-text {
            font-size: 20px;
            color: #333;
            font-family: 'Itim', cursive;
        }

        /* ========== MODAL OVERLAYS ========== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: flex-end;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        
        .modal-sheet {
            background: #fff;
            width: 100%;
            max-width: 412px;
            border-radius: 20px 20px 0 0;
            padding: 20px 0;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        
        .modal-overlay.active .modal-sheet {
            transform: translateY(0);
        }
        
        .modal-title {
            text-align: center;
            font-size: 18px;
            color: #888;
            margin-bottom: 15px;
            font-family: 'Itim', cursive;
        }
        
        .modal-option {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px 25px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .modal-option:hover {
            background: #f5f5f5;
        }
        
        .modal-option-icon {
            width: 28px;
            height: 28px;
            object-fit: contain;
        }
        
        .modal-option-text {
            font-size: 18px;
            color: #333;
            font-family: 'Itim', cursive;
        }
        
        .modal-option.delete {
            color: #f44336;
        }
        
        .modal-option.delete .modal-option-text {
            color: #f44336;
        }
        
        .modal-divider {
            height: 1px;
            background: #e0e0e0;
            margin: 8px 0;
        }
        
        .modal-cancel {
            text-align: center;
            padding: 16px;
            font-size: 18px;
            color: #888;
            cursor: pointer;
            font-family: 'Itim', cursive;
            margin-top: 10px;
        }
        
        .modal-cancel:hover {
            background: #f5f5f5;
        }

        /* ========== FORM MODALS ========== */
        .form-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1001;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .form-modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        
        .form-modal-card {
            background: #fff;
            width: 90%;
            max-width: 380px;
            border-radius: 20px;
            padding: 25px;
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }
        
        .form-modal-overlay.active .form-modal-card {
            transform: scale(1);
        }
        
        .form-modal-header {
            text-align: center;
            font-size: 22px;
            color: #333;
            margin-bottom: 20px;
            font-family: 'Itim', cursive;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 14px;
            font-size: 16px;
            font-family: 'Itim', cursive;
            outline: none;
            margin-bottom: 15px;
            background: #f5f5f5;
        }
        
        .form-input:focus {
            border-color: #3B8BFF;
            background: #fff;
        }
        
        .form-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 14px;
            background: #3B8BFF;
            color: #fff;
            font-size: 18px;
            font-family: 'Itim', cursive;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .form-btn:hover {
            background: #2a7aee;
        }
        
        .form-btn.danger {
            background: #f44336;
        }
        
        .form-btn.danger:hover {
            background: #d32f2f;
        }
        
        .form-close {
            text-align: center;
            padding-top: 12px;
            font-size: 16px;
            color: #888;
            cursor: pointer;
            font-family: 'Itim', cursive;
        }
        
        .form-message {
            text-align: center;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 14px;
            display: none;
        }
        
        .form-message.success {
            background: #e8f5e9;
            color: #2e7d32;
            display: block;
        }
        
        .form-message.error {
            background: #ffebee;
            color: #c62828;
            display: block;
        }
        
        .current-value {
            text-align: center;
            color: #888;
            font-size: 14px;
            margin-bottom: 15px;
            font-family: 'Itim', cursive;
        }
        
        .warning-text {
            text-align: center;
            color: #f44336;
            font-size: 14px;
            margin-bottom: 15px;
            font-family: 'Itim', cursive;
        }
    </style>
</head>
<body>
<div class="container">

  <!-- HEADER -->
  <div class="settings-header">
    <img src="seticon.png" class="icon-left" alt="Settings">
    <p class="title">Settings</p>
    <button onclick="goBack()" class="back-btn">
      <img src="back.png" alt="Back">
    </button>
  </div>

  <!-- OPTIONS -->
  <div class="options-list">

    <div class="option-row" onclick="openAccountModal()">
      <img src="acc.png" class="option-icon" alt="Account">
      <p class="option-text">Account Settings</p>
    </div>

    <div class="option-row" id="notifRow" onclick="toggleNotification()">
      <img src="notif.png" class="option-icon notif-on" id="notifLogo" alt="Notifications">
      <p class="option-text">Notifications</p>
    </div>


    <div class="option-row" onclick="window.open('https://play.google.com/store', '_blank')">
      <img src="Feedback.png" class="option-icon" alt="Feedback">
      <p class="option-text">Feedback</p>
    </div>

    <div class="option-row" onclick="window.location.href='faq.php'">
      <img src="FAQIcon.png" class="option-icon" alt="FAQ">
      <p class="option-text">FAQ</p>
    </div>

  </div>

  <!-- ACCOUNT MENU MODAL -->
  <div class="modal-overlay" id="accountModal" onclick="closeAccountModal(event)">
    <div class="modal-sheet" onclick="event.stopPropagation()">
      <div class="modal-title">Account</div>
      
      <div class="modal-option" onclick="openEditUsername()">
        <img src="edit.png" class="modal-option-icon" alt="Edit">
        <span class="modal-option-text">Edit username</span>
      </div>
      
      <div class="modal-divider"></div>
      
      <div class="modal-option" onclick="openChangeEmail()">
        <img src="email.png" class="modal-option-icon" alt="Email">
        <span class="modal-option-text">Change Email</span>
      </div>
      
      <div class="modal-divider"></div>
      
      <div class="modal-option" onclick="openChangePassword()">
        <img src="lock.png" class="modal-option-icon" alt="Password">
        <span class="modal-option-text">Change Password</span>
      </div>
      
      <div class="modal-divider"></div>
      
      <div class="modal-option delete" onclick="openDeleteAccount()">
        <img src="delete.png" class="modal-option-icon" alt="Delete">
        <span class="modal-option-text">Delete account</span>
      </div>
      
      <div class="modal-divider"></div>
      
      <div class="modal-cancel" onclick="closeAccountModal()">Cancel</div>
    </div>
  </div>

  <!-- EDIT USERNAME MODAL -->
  <div class="form-modal-overlay" id="usernameModal" onclick="closeFormModal(event, 'usernameModal')">
    <div class="form-modal-card" onclick="event.stopPropagation()">
      <div class="form-modal-header">Edit Username</div>
      <div class="current-value">Current: <span id="currentUsername">Loading...</span></div>
      <div class="form-message" id="usernameMessage"></div>
      <input type="text" class="form-input" id="newUsername" placeholder="Enter new username" maxlength="50">
      <button class="form-btn" onclick="saveUsername()">Save Username</button>
      <div class="form-close" onclick="closeFormModal(null, 'usernameModal')">Cancel</div>
    </div>
  </div>

  <!-- CHANGE EMAIL MODAL -->
  <div class="form-modal-overlay" id="emailModal" onclick="closeFormModal(event, 'emailModal')">
    <div class="form-modal-card" onclick="event.stopPropagation()">
      <div class="form-modal-header">Change Email</div>
      <div class="current-value">Current: <span id="currentEmail">Loading...</span></div>
      <div class="form-message" id="emailMessage"></div>
      <input type="email" class="form-input" id="newEmail" placeholder="Enter new email" maxlength="100">
      <input type="password" class="form-input" id="emailPassword" placeholder="Enter your password to confirm">
      <button class="form-btn" onclick="saveEmail()">Update Email</button>
      <div class="form-close" onclick="closeFormModal(null, 'emailModal')">Cancel</div>
    </div>
  </div>

  <!-- CHANGE PASSWORD MODAL -->
  <div class="form-modal-overlay" id="passwordModal" onclick="closeFormModal(event, 'passwordModal')">
    <div class="form-modal-card" onclick="event.stopPropagation()">
      <div class="form-modal-header">Change Password</div>
      <div class="form-message" id="passwordMessage"></div>
      <input type="password" class="form-input" id="currentPassword" placeholder="Current password">
      <input type="password" class="form-input" id="newPassword" placeholder="New password (min 6 chars)">
      <input type="password" class="form-input" id="confirmPassword" placeholder="Confirm new password">
      <button class="form-btn" onclick="savePassword()">Update Password</button>
      <div class="form-close" onclick="closeFormModal(null, 'passwordModal')">Cancel</div>
    </div>
  </div>

  <!-- DELETE ACCOUNT MODAL -->
  <div class="form-modal-overlay" id="deleteModal" onclick="closeFormModal(event, 'deleteModal')">
    <div class="form-modal-card" onclick="event.stopPropagation()">
      <div class="form-modal-header" style="color: #f44336;">Delete Account</div>
      <div class="warning-text">⚠️ This action cannot be undone!</div>
      <div class="form-message" id="deleteMessage"></div>
      <input type="password" class="form-input" id="deletePassword" placeholder="Enter your password to confirm">
      <button class="form-btn danger" onclick="confirmDelete()">Delete My Account</button>
      <div class="form-close" onclick="closeFormModal(null, 'deleteModal')">Cancel</div>
    </div>
  </div>

</div>

<script>
let notificationsEnabled = true;

// ========== NAVIGATION ==========
function goBack() {
    window.history.back();
}

// ========== NOTIFICATION TOGGLE ==========
function toggleNotification() {
    notificationsEnabled = !notificationsEnabled;
    
    const logo = document.getElementById('notifLogo');
    
    if (notificationsEnabled) {
        logo.classList.remove('notif-off');
        logo.classList.add('notif-on');
    } else {
        logo.classList.remove('notif-on');
        logo.classList.add('notif-off');
    }
    
    localStorage.setItem('notifications_enabled', notificationsEnabled ? '1' : '0');
    
    fetch('api_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_notifications&enabled=${notificationsEnabled ? 1 : 0}`
    })
    .then(r => r.text())  // Get raw text first to catch errors
    .then(text => {
        console.log('Raw response:', text);
        try {
            const data = JSON.parse(text);
            if (data.success) {
                console.log('Notifications:', notificationsEnabled ? 'ON' : 'OFF');
            } else {
                console.error('Server error:', data.error);
                // Revert UI since save failed
                notificationsEnabled = !notificationsEnabled;
                updateNotifUI();
            }
        } catch(e) {
            console.error('Parse error:', text);
        }
    })
    .catch(err => {
        console.error('Network error:', err);
        notificationsEnabled = !notificationsEnabled;
        updateNotifUI();
    });
}

function updateNotifUI() {
    const logo = document.getElementById('notifLogo');
    if (notificationsEnabled) {
        logo.classList.remove('notif-off');
        logo.classList.add('notif-on');
    } else {
        logo.classList.remove('notif-on');
        logo.classList.add('notif-off');
    }
}

// Load notification state on init
function loadNotificationState() {
    fetch('api_settings.php?action=get_notifications_state')
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notificationsEnabled = data.enabled;
            const logo = document.getElementById('notifLogo');
            
            if (notificationsEnabled) {
                logo.classList.remove('notif-off');
                logo.classList.add('notif-on');
            } else {
                logo.classList.remove('notif-on');
                logo.classList.add('notif-off');
            }
        }
    })
    .catch(() => {});
}

// ========== ACCOUNT MENU ==========
function openAccountModal() {
    document.getElementById('accountModal').classList.add('active');
}

function closeAccountModal(event) {
    if (!event || event.target.id === 'accountModal') {
        document.getElementById('accountModal').classList.remove('active');
    }
}

// ========== FORM MODALS ==========
function openEditUsername() {
    closeAccountModal();
    document.getElementById('usernameModal').classList.add('active');
    fetch('api_settings.php?action=get_user')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('currentUsername').textContent = data.name || 'Unknown';
            }
        })
        .catch(() => {
            document.getElementById('currentUsername').textContent = '<?php echo $_SESSION['name'] ?? 'Guest'; ?>';
        });
}

function openChangeEmail() {
    closeAccountModal();
    document.getElementById('emailModal').classList.add('active');
    fetch('api_settings.php?action=get_user')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('currentEmail').textContent = data.email || 'Unknown';
            }
        })
        .catch(() => {
            document.getElementById('currentEmail').textContent = '<?php echo $_SESSION['email'] ?? ''; ?>';
        });
}

function openChangePassword() {
    closeAccountModal();
    document.getElementById('passwordModal').classList.add('active');
}

function openDeleteAccount() {
    closeAccountModal();
    document.getElementById('deleteModal').classList.add('active');
}

function closeFormModal(event, modalId) {
    if (!event || event.target.id === modalId) {
        document.getElementById(modalId).classList.remove('active');
        const modal = document.getElementById(modalId);
        modal.querySelectorAll('input').forEach(input => input.value = '');
        const msg = modal.querySelector('.form-message');
        if (msg) {
            msg.className = 'form-message';
            msg.textContent = '';
        }
    }
}

// ========== SAVE FUNCTIONS ==========
function showMessage(elementId, message, isSuccess) {
    const msgEl = document.getElementById(elementId);
    msgEl.textContent = message;
    msgEl.className = 'form-message ' + (isSuccess ? 'success' : 'error');
}

function saveUsername() {
    const newName = document.getElementById('newUsername').value.trim();
    
    if (!newName) {
        showMessage('usernameMessage', 'Please enter a username', false);
        return;
    }
    
    if (newName.length < 3) {
        showMessage('usernameMessage', 'Username must be at least 3 characters', false);
        return;
    }
    
    fetch('api_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_username&name=${encodeURIComponent(newName)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showMessage('usernameMessage', 'Username updated successfully!', true);
            document.getElementById('currentUsername').textContent = newName;
            setTimeout(() => closeFormModal(null, 'usernameModal'), 1500);
        } else {
            showMessage('usernameMessage', data.error || 'Failed to update username', false);
        }
    })
    .catch(err => {
        showMessage('usernameMessage', 'Error: ' + err.message, false);
    });
}

function saveEmail() {
    const newEmail = document.getElementById('newEmail').value.trim();
    const password = document.getElementById('emailPassword').value;
    
    if (!newEmail || !password) {
        showMessage('emailMessage', 'Please fill in all fields', false);
        return;
    }
    
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
        showMessage('emailMessage', 'Please enter a valid email', false);
        return;
    }
    
    fetch('api_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_email&email=${encodeURIComponent(newEmail)}&password=${encodeURIComponent(password)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showMessage('emailMessage', 'Email updated successfully!', true);
            document.getElementById('currentEmail').textContent = newEmail;
            setTimeout(() => closeFormModal(null, 'emailModal'), 1500);
        } else {
            showMessage('emailMessage', data.error || 'Failed to update email', false);
        }
    })
    .catch(err => {
        showMessage('emailMessage', 'Error: ' + err.message, false);
    });
}

function savePassword() {
    const currentPass = document.getElementById('currentPassword').value;
    const newPass = document.getElementById('newPassword').value;
    const confirmPass = document.getElementById('confirmPassword').value;
    
    if (!currentPass || !newPass || !confirmPass) {
        showMessage('passwordMessage', 'Please fill in all fields', false);
        return;
    }
    
    if (newPass.length < 6) {
        showMessage('passwordMessage', 'New password must be at least 6 characters', false);
        return;
    }
    
    if (newPass !== confirmPass) {
        showMessage('passwordMessage', 'Passwords do not match', false);
        return;
    }
    
    fetch('api_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_password&current_password=${encodeURIComponent(currentPass)}&new_password=${encodeURIComponent(newPass)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showMessage('passwordMessage', 'Password updated successfully!', true);
            setTimeout(() => closeFormModal(null, 'passwordModal'), 1500);
        } else {
            showMessage('passwordMessage', data.error || 'Failed to update password', false);
        }
    })
    .catch(err => {
        showMessage('passwordMessage', 'Error: ' + err.message, false);
    });
}

function confirmDelete() {
    const password = document.getElementById('deletePassword').value;
    
    if (!password) {
        showMessage('deleteMessage', 'Please enter your password', false);
        return;
    }
    
    if (!confirm('Are you absolutely sure? This will permanently delete your account and all data!')) {
        return;
    }
    
    fetch('api_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_account&password=${encodeURIComponent(password)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showMessage('deleteMessage', 'Account deleted. Redirecting...', true);
            setTimeout(() => {
                window.location.href = 'logout.php';
            }, 2000);
        } else {
            showMessage('deleteMessage', data.error || 'Failed to delete account', false);
        }
    })
    .catch(err => {
        showMessage('deleteMessage', 'Error: ' + err.message, false);
    });
}

// Init
loadNotificationState();
</script>

</body>
</html>