<?php
session_start();
include 'database.php';

$student_id = $_SESSION['student_id'] ?? 0;

// BLOCK: If notifications disabled, show "turned off" page
$check = $conn->prepare("SELECT notifications_enabled FROM student WHERE student_id = ?");
$check->bind_param("i", $student_id);
$check->execute();
$enabled = $check->get_result()->fetch_assoc()['notifications_enabled'] ?? 1;

if (!$enabled) {
    // Show simple "off" page and stop
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Notifications</title><link rel="stylesheet" href="style2.css"><style>@import url("https://fonts.googleapis.com/css2?family=Itim&display=swap");body{background:#111;font-family:"Itim",cursive;}.container{max-width:412px;height:100vh;margin:0 auto;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px;}.top-bars{height:55px;background:#3B8BFF;display:flex;align-items:center;justify-content:space-between;padding:0 15px;width:100%;}.icon{width:28px;height:28px;filter:brightness(0)invert(1);cursor:pointer;}.off-icon{width:80px;height:80px;filter:brightness(0);opacity:0.3;}.off-text{font-size:20px;color:#888;}.btn{background:#3B8BFF;color:#fff;border:none;border-radius:20px;padding:12px 30px;font-family:"Itim",cursive;font-size:16px;cursor:pointer;}</style></head><body><div class="container"><div class="top-bars"><img src="mssg.png" class="icon"><img src="back.png" class="icon" onclick="history.back()"></div><img src="notif.png" class="off-icon"><p class="off-text">Notifications are turned off</p><button class="btn" onclick="location.href=\'settings.php\'">Go to Settings</button></div></body></html>';
    exit;
}