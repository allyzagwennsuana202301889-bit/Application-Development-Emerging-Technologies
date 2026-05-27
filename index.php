<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LogIn</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .password-wrap {
            position: relative;
            width: 100%;
            max-width: 300px;
            margin-top: 20px;
        }
        .password-wrap input {
            width: 100%;
            height: 55px;
            padding: 12px 45px 12px 12px;
            border: none;
            border-radius: 10px;
            background: #ddd;
            font-size: 18px;
            outline: none;
        }
        .eye-toggle {

    position: absolute;
    right: -40px;
    top: 10%;                     /* ← fixed from 40% */
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    cursor: pointer;
    opacity: 0.5;
    transition: opacity 0.2s;
    background: transparent !important;  /* ← removes orange */
    border: none;
    box-shadow: none;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
        }
        .eye-toggle:hover {
            opacity: 0.8;
        }
        .eye-toggle svg {
            width: 22px;
            height: 22px;
            fill: #555;
        }
    </style>
</head>
<body>
<div class="container">
  <div class="login-box">
    <img src="logo.png">

    <h1>The Ins</h1>
    <p class="sub">"Knowledge that connects"</p>

<form action="login.php" method="POST">
  <input type="email" name="email" placeholder="email" required>
  <div class="password-wrap">
    <input type="password" name="password" id="loginPassword" placeholder="password" required>
    <button type="button" class="eye-toggle" onclick="toggleEye()">
      <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
    </button>
  </div>
  <button type="submit">Login</button>
</form>
  </div>
</div>

<script src="script.js"></script>
<script>
  function toggleEye() {
    const input = document.getElementById('loginPassword');
    const btn = document.querySelector('.eye-toggle');
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';

    btn.innerHTML = isHidden 
      ? '<svg viewBox="0 0 24 24"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.1 2.1C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>'
      : '<svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>';
  }
</script>
</body>
</html>
