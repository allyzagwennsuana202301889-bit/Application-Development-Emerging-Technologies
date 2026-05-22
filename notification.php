<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;

// UPDATES: Personal notifications only
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

// MISSING: Personal only
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

// Count unread
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM notification 
    WHERE student_id = ? AND status = 'unread'
");
$count_stmt->bind_param("i", $student_id);
$count_stmt->execute();
$unread_count = $count_stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Helper: Determine actual source type by checking which table the subject exists in
function getActualSourceType($conn, $subject_id) {
    // Check notes first
    $check = $conn->prepare("SELECT 1 FROM notes WHERE note_id = ? AND type = 'subject' LIMIT 1");
    $check->bind_param("i", $subject_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        return 'notes';
    }
    
    // Then check subjects
    $check = $conn->prepare("SELECT 1 FROM subjects WHERE subject_id = ? LIMIT 1");
    $check->bind_param("i", $subject_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        return 'subjects';
    }
    
    return 'subjects'; // fallback
}

function isSubjectAdded($conn, $student_id, $subject_id) {
    $source_type = getActualSourceType($conn, $subject_id);
    
    $check = $conn->prepare("
        SELECT 1 FROM student_subjects 
        WHERE student_id = ? AND subject_id = ? AND source_type = ?
        LIMIT 1
    ");
    $check->bind_param("iis", $student_id, $subject_id, $source_type);
    $check->execute();
    $result = $check->get_result();
    
    return $result->num_rows > 0;
}

function buildActionUrl($row) {
    global $conn;
    
    $subject_id = (int)($row['subject_id'] ?? 0);
    $type = $row['type'] ?? '';
    
    if ($subject_id === 0) {
        return 'homepage.php';
    }
    
    // Determine what kind of subject this is
    $is_preset = false;
    $is_published_note = false;
    
    // Check if it's a preset in subjects table
    $check_preset = $conn->prepare("SELECT is_preset FROM subjects WHERE subject_id = ? LIMIT 1");
    $check_preset->bind_param("i", $subject_id);
    $check_preset->execute();
    $preset_result = $check_preset->get_result()->fetch_assoc();
    if ($preset_result && (int)$preset_result['is_preset'] === 1) {
        $is_preset = true;
    }
    
    // Check if it's a published note
    $check_note = $conn->prepare("SELECT 1 FROM notes WHERE note_id = ? AND type = 'subject' LIMIT 1");
    $check_note->bind_param("i", $subject_id);
    $check_note->execute();
    if ($check_note->get_result()->num_rows > 0) {
        $is_published_note = true;
    }
    
    $source_type = $is_published_note ? 'notes' : 'subjects';
    
    // PRESETS: Always go directly to subject.php (no need to add first)
    if ($is_preset) {
        return 'subject.php?id=' . $subject_id . '&type=subjects';
    }
    
    // PUBLISHED/UNPUBLISHED SUBJECTS (notes): Check if added first
    if (strpos($type, 'subject_published') !== false || 
        strpos($type, 'subject_unpublished') !== false) {
        
        $added = isSubjectAdded($conn, $_SESSION['student_id'] ?? 0, $subject_id);
        
        if ($added) {
            return 'subject.php?id=' . $subject_id . '&type=' . $source_type;
        } else {
            return 'view_subject.php?subject_id=' . $subject_id;
        }
    }
    
    // For other types, use stored action_url if available
    if (!empty($row['action_url']) && strpos($row['action_url'], '.php') !== false) {
        return $row['action_url'];
    }
    
    if (strpos($type, 'quiz') !== false) {
        return 'quiz.php?id=' . $subject_id . '&type=' . $source_type;
    }
    
    return 'subject.php?id=' . $subject_id . '&type=' . $source_type;
}

function getNotifImage($row) {
    if (!empty($row['subject_image'])) {
        return htmlspecialchars($row['subject_image']);
    }
    $icons = [
        'preset_uploaded' => 'upload.png',
        'preset_edited' => 'globe.png',
        'preset_deleted' => 'del.png',
        'subject_published' => 'globe.png',
        'subject_unpublished' => 'folder.png',
        'subject_edited' => 'globe.png',
        'subject_uploaded' => 'upload.png',
        'subject_added' => 'globe.png',
        'quiz_published' => 'dna.png',
        'quiz_unpublished' => 'dna.png',
        'quiz_edited' => 'dna.png',
        'no_progress' => 'folder.png',
        'no_quiz' => 'dna.png',
        'welcome' => 'globe.png'
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
                                <img src="check.png">
                            </div>
                            <div class="notif-icon">
                                <img src="<?= getNotifImage($row) ?>" 
                                     alt="icon"
                                     onerror="this.src='file.png'">
                            </div>
                            <div class="notif-text">
                                <p class="notif-title"><?= htmlspecialchars($row['title']) ?></p>
                                <p class="notif-desc"><?= htmlspecialchars($row['message']) ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No updates yet</p>
                    </div>
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
                                <img src="check.png">
                            </div>
                            <div class="notif-icon">
                                <img src="<?= getNotifImage($row) ?>" 
                                     alt="icon"
                                     onerror="this.src='file.png'">
                            </div>
                            <div class="notif-text">
                                <p class="notif-title"><?= htmlspecialchars($row['title']) ?></p>
                                <p class="notif-desc"><?= htmlspecialchars($row['message']) ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>You're all caught up!</p>
                    </div>
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
            <div class="action" onclick="markAllRead()">
                <img src="markmssg.png">
                <p>Mark all as read</p>
            </div>
        </div>

    </div>

    <script>
    let currentTab = 'updates';
    let deleteMode = false;
    let selectedItems = new Set();
    let holdTimer = null;
    let isHolding = false;
    const HOLD_DURATION = 800;

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
            item.classList.add('delete-mode-active');
            const check = item.querySelector('.check-icon');
            const icon = item.querySelector('.notif-icon');
            if (check) check.classList.remove('hidden');
            if (icon) icon.classList.add('hidden');
        });
    }

    function cancelDeleteMode() {
        deleteMode = false;
        selectedItems.clear();
        document.getElementById('bottomActions').classList.remove('hidden');
        document.getElementById('deleteModeBar').classList.add('hidden');

        document.querySelectorAll('.notif-item').forEach(item => {
            item.classList.remove('delete-mode-active', 'selected');
            const check = item.querySelector('.check-icon');
            const icon = item.querySelector('.notif-icon');
            if (check) {
                check.classList.add('hidden');
                check.querySelector('img').src = 'check.png';
            }
            if (icon) icon.classList.remove('hidden');
        });
    }

    function toggleSelect(notifId, element) {
        if (!deleteMode) return;

        if (selectedItems.has(notifId)) {
            selectedItems.delete(notifId);
            element.classList.remove('selected');
            const check = element.querySelector('.check-icon img');
            if (check) check.src = 'bluecheck.png';
        } else {
            selectedItems.add(notifId);
            element.classList.add('selected');
            const check = element.querySelector('.check-icon img');
            if (check) check.src = 'bluecheck.png';
        }

        if (selectedItems.size === 0) {
            cancelDeleteMode();
        }
    }

    function deleteSelected() {
        if (selectedItems.size === 0) {
            cancelDeleteMode();
            return;
        }
        if (!confirm('Delete ' + selectedItems.size + ' notification(s)?')) return;

        const ids = Array.from(selectedItems).join(',');
        fetch('api_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_selected&ids=' + ids
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                selectedItems.forEach(id => {
                    const item = document.querySelector('[data-id="' + id + '"]');
                    if (item) item.remove();
                });
                cancelDeleteMode();
                updateBadge();
            }
        });
    }

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
        } else {
            console.log('No valid URL for notification', notifId, 'URL:', url);
        }
    }

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

    function updateBadge() {
        fetch('api_notification.php?action=unread_count')
        .then(r => r.json())
        .then(data => {
            document.getElementById('notifBadge').textContent = data.count > 0 ? data.count : '';
        });
    }


    function deleteAll() {
    if (!confirm('Delete all notifications?')) return;

    fetch('api_notification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_all'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notif-item').forEach(item => item.remove());
            // Show empty state
            const section = document.getElementById(currentTab + 'Section');
            if (section.querySelectorAll('.notif-item').length === 0) {
                section.innerHTML = '<div class="empty-state"><p>No ' + (currentTab === 'updates' ? 'updates' : 'missing items') + ' yet</p></div>';
            }
            updateBadge();
        }
    });
}

    function goBack() { window.history.back(); }
    </script>
</body>
</html>