<?php
session_start();
include 'database.php';

// Generate missing notifications on page load
include 'check_missing.php';

$student_id = $_SESSION['student_id'] ?? 0;

$sql_updates = "
    SELECT n.*, 
        COALESCE(s.subject_image, ns.subject_image) as subject_image,
        COALESCE(s.subject_name, ns.title) as subject_name,
        st.name as updater_name
    FROM notification n
    LEFT JOIN subjects s ON n.subject_id = s.subject_id
    LEFT JOIN notes ns ON n.subject_id = ns.note_id AND ns.type = 'subject'
    LEFT JOIN student st ON n.triggered_by = st.student_id
    WHERE n.student_id = ?
    AND n.section = 'updates'
    ORDER BY n.date_sent DESC
    LIMIT 50
";
$stmt = $conn->prepare($sql_updates);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$updates = $stmt->get_result();

$sql_missing = "
    SELECT n.*, 
        COALESCE(s.subject_image, ns.subject_image) as subject_image,
        COALESCE(s.subject_name, ns.title) as subject_name
    FROM notification n
    LEFT JOIN subjects s ON n.subject_id = s.subject_id
    LEFT JOIN notes ns ON n.subject_id = ns.note_id AND ns.type = 'subject'
    WHERE n.student_id = ?
    AND n.section = 'missing'
    ORDER BY n.date_sent DESC
    LIMIT 50
";
$stmt2 = $conn->prepare($sql_missing);
$stmt2->bind_param("i", $student_id);
$stmt2->execute();
$missing = $stmt2->get_result();

$count_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM notification 
    WHERE student_id = ? AND status = 'unread'
");
$count_stmt->bind_param("i", $student_id);
$count_stmt->execute();
$unread_count = $count_stmt->get_result()->fetch_assoc()['count'] ?? 0;

function getActualSourceType($conn, $subject_id) {
    $check = $conn->prepare("SELECT 1 FROM notes WHERE note_id = ? AND type = 'subject' LIMIT 1");
    $check->bind_param("i", $subject_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) return 'notes';
    $check = $conn->prepare("SELECT 1 FROM subjects WHERE subject_id = ? LIMIT 1");
    $check->bind_param("i", $subject_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) return 'subjects';
    return 'subjects';
}

function isSubjectAdded($conn, $student_id, $subject_id) {
    $source_type = getActualSourceType($conn, $subject_id);
    $check = $conn->prepare("SELECT 1 FROM student_subjects WHERE student_id = ? AND subject_id = ? AND source_type = ? LIMIT 1");
    $check->bind_param("iis", $student_id, $subject_id, $source_type);
    $check->execute();
    return $check->get_result()->num_rows > 0;
}

function buildActionUrl($row) {
    global $conn;
    $subject_id = (int)($row['subject_id'] ?? 0);
    $type = $row['type'] ?? '';
    if ($subject_id === 0) return 'homepage.php';
    $is_preset = false;
    $is_published_note = false;
    $check_preset = $conn->prepare("SELECT is_preset FROM subjects WHERE subject_id = ? LIMIT 1");
    $check_preset->bind_param("i", $subject_id);
    $check_preset->execute();
    $preset_result = $check_preset->get_result()->fetch_assoc();
    if ($preset_result && (int)$preset_result['is_preset'] === 1) $is_preset = true;
    $check_note = $conn->prepare("SELECT 1 FROM notes WHERE note_id = ? AND type = 'subject' LIMIT 1");
    $check_note->bind_param("i", $subject_id);
    $check_note->execute();
    if ($check_note->get_result()->num_rows > 0) $is_published_note = true;
    $source_type = $is_published_note ? 'notes' : 'subjects';
    if ($is_preset) return 'subject.php?id=' . $subject_id . '&type=subjects';
    if (strpos($type, 'subject_published') !== false || strpos($type, 'subject_unpublished') !== false) {
        $added = isSubjectAdded($conn, $_SESSION['student_id'] ?? 0, $subject_id);
        return $added ? 'subject.php?id=' . $subject_id . '&type=' . $source_type : 'view_subject.php?subject_id=' . $subject_id;
    }
    if (!empty($row['action_url']) && strpos($row['action_url'], '.php') !== false) return $row['action_url'];
    if (strpos($type, 'quiz') !== false) return 'quiz.php?id=' . $subject_id . '&type=' . $source_type;
    return 'subject.php?id=' . $subject_id . '&type=' . $source_type;
}

function getNotifImage($row) {
    if (!empty($row['subject_image'])) return htmlspecialchars($row['subject_image']);
    $icons = [
        'preset_uploaded' => 'upload.png', 'preset_edited' => 'file.png',
        'preset_deleted' => 'del.png', 'subject_published' => 'file.png',
        'subject_unpublished' => 'folder.png', 'subject_edited' => 'file.png',
        'subject_uploaded' => 'upload.png', 'subject_added' => 'file.png',
        'quiz_published' => 'dna.png', 'quiz_unpublished' => 'file.png',
        'quiz_edited' => 'dna.png', 'no_progress' => 'file.png',
        'no_quiz' => 'dna.png', 'welcome' => 'file.png'
    ];
    return $icons[$row['type'] ?? ''] ?? 'file.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link href="https://fonts.googleapis.com/css2?family=Itim&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style2.css">
    <style>
        /* ========== READ MORE BUTTON STYLES ========== */
        .notif-item {
            position: relative;
        }
        
        .notif-text {
            flex: 1;
            min-width: 0;
            padding-right: 30px;
        }
        
        .notif-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .notif-desc {
            font-size: 14px;
            color: #555;
            font-style: italic;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: all 0.3s ease;
        }
        
        .notif-desc.expanded {
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
        }
        
        .read-more-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 8px;
            font-size: 26px;
            color: #888;
            transition: color 0.2s, transform 0.2s;
            z-index: 2;
            pointer-events: auto;
        }
        
        .read-more-btn:hover {
            color: #3B8BFF;
        }
        
        .read-more-btn.expanded {
            transform: translateY(-50%) rotate(180deg);
        }

        /* ========== MOBILE CONFIRM MODAL ========== */
        .confirm-overlay {
            display: none;
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 1000;
            align-items: flex-end;
            justify-content: center;
        }
        .confirm-overlay.show {
            display: flex;
        }
        .confirm-sheet {
            background: #fff;
            width: 100%;
            border-radius: 24px 24px 0 0;
            padding: 28px 24px 40px;
            font-family: 'Itim', cursive;
            animation: slideUp 0.25s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(100%); }
            to   { transform: translateY(0); }
        }
        .confirm-sheet h3 {
            font-size: 18px;
            color: #333;
            margin: 0 0 8px;
            text-align: center;
        }
        .confirm-sheet p {
            font-size: 14px;
            color: #888;
            text-align: center;
            margin: 0 0 24px;
        }
        .confirm-btns {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .confirm-btn-delete {
            background: #ff4d4d;
            color: #fff;
            border: none;
            border-radius: 14px;
            padding: 14px;
            font-size: 16px;
            font-family: 'Itim', cursive;
            cursor: pointer;
        }
        .confirm-btn-cancel {
            background: #f0f0f0;
            color: #333;
            border: none;
            border-radius: 14px;
            padding: 14px;
            font-size: 16px;
            font-family: 'Itim', cursive;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">

        <!-- TOP BAR -->
        <div class="top-bars">
            <div class="top-left">
                <img src="mssg.png" class="icon">
                <span class="badge" id="notifBadge"><?= $unread_count > 0 ? $unread_count : '' ?></span>
            </div>
            <img src="back.png" class="icon right" onclick="goBack()">
        </div>

        <!-- TABS -->
        <div class="tabs">
            <span class="tab-btn active" onclick="switchTab('updates')" id="tab-updates">Updates</span>
            <span class="tab-btn" onclick="switchTab('missing')" id="tab-missing">Missing</span>
        </div>

        <div class="tab-line">
            <div class="tab-indicator" id="tabIndicator"></div>
        </div>

        <!-- CONTENT -->
        <div class="content" id="contentArea">

            <!-- UPDATES SECTION -->
            <div class="notif-section" id="updatesSection">
                <?php if ($updates && $updates->num_rows > 0): ?>
                    <?php while ($row = $updates->fetch_assoc()): 
                        $url = buildActionUrl($row);
                    ?>
                        <div class="notif-item <?= $row['status'] === 'unread' ? 'unread' : 'read' ?>" 
                             data-id="<?= $row['notification_id'] ?>"
                             data-url="<?= htmlspecialchars($url) ?>"
                             onmousedown="startHold(<?= $row['notification_id'] ?>, this)"
                             onmouseup="endHold(this)"
                             onmouseleave="endHold(this)"
                             ontouchstart="startHold(<?= $row['notification_id'] ?>, this)"
                             ontouchend="endHold(this)"
                             onclick="handleClick(<?= $row['notification_id'] ?>, this)">
                            <div class="check-icon hidden">
                                <img src="bluecheck.png">
                            </div>
                            <div class="notif-icon">
                                <img src="<?= getNotifImage($row) ?>" alt="icon" onerror="this.src='file.png'">
                            </div>
                            <div class="notif-text">
                                <p class="notif-title"><?= htmlspecialchars($row['title']) ?></p>
                                <p class="notif-desc" id="desc-<?= $row['notification_id'] ?>"><?= htmlspecialchars($row['message']) ?></p>
                            </div>
                            <button class="read-more-btn" onclick="event.stopPropagation(); toggleReadMore(<?= $row['notification_id'] ?>, this)">⌄</button>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state"><p>No updates yet</p></div>
                <?php endif; ?>
            </div>

            <!-- MISSING SECTION -->
            <div class="notif-section hidden" id="missingSection">
                <?php if ($missing && $missing->num_rows > 0): ?>
                    <?php while ($row = $missing->fetch_assoc()): 
                        $url = buildActionUrl($row);
                    ?>
                        <div class="notif-item <?= $row['status'] === 'unread' ? 'unread' : 'read' ?>" 
                             data-id="<?= $row['notification_id'] ?>"
                             data-url="<?= htmlspecialchars($url) ?>"
                             onmousedown="startHold(<?= $row['notification_id'] ?>, this)"
                             onmouseup="endHold(this)"
                             onmouseleave="endHold(this)"
                             ontouchstart="startHold(<?= $row['notification_id'] ?>, this)"
                             ontouchend="endHold(this)"
                             onclick="handleClick(<?= $row['notification_id'] ?>, this)">
                            <div class="check-icon hidden">
                                <img src="bluecheck.png">
                            </div>
                            <div class="notif-icon">
                                <img src="<?= getNotifImage($row) ?>" alt="icon" onerror="this.src='file.png'">
                            </div>
                            <div class="notif-text">
                                <p class="notif-title"><?= htmlspecialchars($row['title']) ?></p>
                                <p class="notif-desc" id="desc-<?= $row['notification_id'] ?>"><?= htmlspecialchars($row['message']) ?></p>
                            </div>
                            <button class="read-more-btn" onclick="event.stopPropagation(); toggleReadMore(<?= $row['notification_id'] ?>, this)">⌄</button>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state"><p>You're all caught up!</p></div>
                <?php endif; ?>
            </div>

        </div>

        <!-- NORMAL BOTTOM BAR -->
        <div class="bottom-actions" id="bottomActions">
            <div class="action" onclick="deleteAll()">
                <img src="del.png">
                <p>Delete All</p>
            </div>
            <div class="action" onclick="markAllRead()">
                <img src="markmssg.png">
                <p>Mark all as read</p>
            </div>
        </div>

        <!-- DELETE MODE BAR -->
        <div class="bottom-actions delete-mode hidden" id="deleteModeBar">
            <div class="action" onclick="deleteSelected()">
                <img src="del.png">
                <p>Delete Selected</p>
            </div>
            <div class="action" onclick="cancelDeleteMode()">
                <img src="back.png">
                <p>Cancel</p>
            </div>
        </div>

        <!-- MOBILE CONFIRM MODAL -->
        <div class="confirm-overlay" id="confirmOverlay">
            <div class="confirm-sheet">
                <h3 id="confirmTitle">Delete?</h3>
                <p id="confirmMessage">This action cannot be undone.</p>
                <div class="confirm-btns">
                    <button class="confirm-btn-delete" id="confirmYes">Delete</button>
                    <button class="confirm-btn-cancel" onclick="closeConfirm()">Cancel</button>
                </div>
            </div>
        </div>

    </div>

    <script src="script.js"></script>
    <script>
    let currentTab = 'updates';
    let deleteMode = false;
    let selectedItems = new Set();
    let holdTimer = null;
    let isHolding = false;
    const HOLD_DURATION = 800;

    /* ========== MOBILE CONFIRM MODAL ========== */
    function showConfirm(title, message, onConfirm) {
        document.getElementById('confirmTitle').textContent = title;
        document.getElementById('confirmMessage').textContent = message;
        document.getElementById('confirmYes').onclick = () => { closeConfirm(); onConfirm(); };
        document.getElementById('confirmOverlay').classList.add('show');
    }

    function closeConfirm() {
        document.getElementById('confirmOverlay').classList.remove('show');
    }

    document.getElementById('confirmOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeConfirm();
    });

    /* ========== TABS ========== */
    function switchTab(tab) {
        if (deleteMode) cancelDeleteMode();
        currentTab = tab;
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        const indicator = document.getElementById('tabIndicator');
        indicator.style.transform = tab === 'updates' ? 'translateX(0)' : 'translateX(100%)';
        document.getElementById('updatesSection').classList.toggle('hidden', tab !== 'updates');
        document.getElementById('missingSection').classList.toggle('hidden', tab !== 'missing');
    }

    /* ========== READ MORE TOGGLE ========== */
    function toggleReadMore(notifId, btn) {
        const desc = document.getElementById('desc-' + notifId);
        if (!desc) return;
        
        const isExpanded = desc.classList.contains('expanded');
        
        document.querySelectorAll('.notif-desc.expanded').forEach(el => {
            el.classList.remove('expanded');
        });
        document.querySelectorAll('.read-more-btn.expanded').forEach(el => {
            el.classList.remove('expanded');
        });
        
        if (!isExpanded) {
            desc.classList.add('expanded');
            btn.classList.add('expanded');
        }
    }

    /* ========== HOLD TO SELECT ========== */
    function startHold(notifId, element) {
        if (deleteMode) return;
        isHolding = false;
        holdTimer = setTimeout(() => {
            isHolding = true;
            enterDeleteMode();
            toggleSelect(notifId, element);
        }, HOLD_DURATION);
    }

    function endHold(element) {
        clearTimeout(holdTimer);
    }

    function enterDeleteMode() {
        deleteMode = true;
        selectedItems.clear();
        document.getElementById('bottomActions').classList.add('hidden');
        document.getElementById('deleteModeBar').classList.remove('hidden');
        document.querySelectorAll('.notif-item').forEach(item => {
            item.classList.remove('delete-mode-active', 'selected');
            const icon = item.querySelector('.notif-icon img');
            if (icon && item.dataset.originalImg) icon.src = item.dataset.originalImg;
            const check = item.querySelector('.check-icon');
            if (check) check.classList.add('hidden');
            const notifIcon = item.querySelector('.notif-icon');
            if (notifIcon) notifIcon.classList.remove('hidden');
        });
    }

    function cancelDeleteMode() {
        deleteMode = false;
        selectedItems.clear();
        document.getElementById('bottomActions').classList.remove('hidden');
        document.getElementById('deleteModeBar').classList.add('hidden');
        document.querySelectorAll('.notif-item').forEach(item => {
            item.classList.remove('delete-mode-active', 'selected');
            const icon = item.querySelector('.notif-icon img');
            if (icon && item.dataset.originalImg) icon.src = item.dataset.originalImg;
            const check = item.querySelector('.check-icon');
            if (check) check.classList.add('hidden');
            const notifIcon = item.querySelector('.notif-icon');
            if (notifIcon) notifIcon.classList.remove('hidden');
        });
    }

    function toggleSelect(notifId, element) {
        if (!deleteMode) return;

        const icon = element.querySelector('.notif-icon img');

        if (selectedItems.has(notifId)) {
            selectedItems.delete(notifId);
            element.classList.remove('selected');
            if (icon) icon.src = element.dataset.originalImg;
        } else {
            selectedItems.add(notifId);
            element.classList.add('selected');
            if (icon && !element.dataset.originalImg) {
                element.dataset.originalImg = icon.src;
            }
            if (icon) icon.src = 'bluecheck.png';
        }

        if (selectedItems.size === 0) {
            cancelDeleteMode();
        }
    }

    /* ========== DELETE SELECTED ========== */
    function deleteSelected() {
        if (selectedItems.size === 0) { cancelDeleteMode(); return; }
        const count = selectedItems.size;
        showConfirm(
            'Delete ' + count + ' notification' + (count > 1 ? 's' : '') + '?',
            'This cannot be undone.',
            () => {
                const ids = Array.from(selectedItems).join(',');
                cancelDeleteMode();
                fetch('api_notification.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=delete_selected&ids=' + ids
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        ids.split(',').forEach(id => {
                            const item = document.querySelector('[data-id="' + id + '"]');
                            if (item) item.remove();
                        });
                        updateBadge();
                    }
                });
            }
        );
    }

    /* ========== CLICK HANDLER ========== */
    function handleClick(notifId, element) {
        if (isHolding) {
            isHolding = false;
            return;
        }

        if (deleteMode) {
            toggleSelect(notifId, element);
            return;
        }

        const url = element.getAttribute('data-url');

        fetch('api_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_read&notification_id=' + notifId
        })
        .then(r => r.json())
        .then(data => {
            element.classList.remove('unread');
            element.classList.add('read');
            updateBadge();
        })
        .catch(err => console.log('Mark read error:', err));

        if (url && url !== '' && url !== 'homepage.php') {
            window.location.href = url;
        }
    }

    /* ========== MARK ALL READ ========== */
    function markAllRead() {
        fetch('api_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_all_read'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notif-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    item.classList.add('read');
                });
                updateBadge();
            }
        });
    }

    /* ========== BADGE ========== */
    function updateBadge() {
        fetch('api_notification.php?action=unread_count')
        .then(r => r.json())
        .then(data => {
            document.getElementById('notifBadge').textContent = data.count > 0 ? data.count : '';
        });
    }

    /* ========== DELETE ALL ========== */
    function deleteAll() {
        showConfirm(
            'Delete all notifications?',
            'This cannot be undone.',
            () => {
                fetch('api_notification.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=delete_all'
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll('.notif-item').forEach(item => item.remove());
                        const section = document.getElementById(currentTab + 'Section');
                        if (section.querySelectorAll('.notif-item').length === 0) {
                            section.innerHTML = '<div class="empty-state"><p>No ' + (currentTab === 'updates' ? 'updates' : 'missing items') + ' yet</p></div>';
                        }
                        updateBadge();
                    }
                });
            }
        );
    }

    function goBack() { window.history.back(); }
    </script>
</body>
</html>