<?php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId   = trim($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT User_ID, Name, Role, Password FROM users WHERE User_ID = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $ok = false;
    if ($user) {
        // Handles passwords created via password_hash() by register.php
        if (password_verify($password, $user['Password'])) {
            $ok = true;
        }
        // Fallback for the plaintext seed data (e.g. '1234') so existing
        // seeded accounts still work. Remove this branch once all
        // passwords have been migrated to hashed values.
        elseif (hash_equals($user['Password'], $password)) {
            $ok = true;
        }
    }

    if ($ok) {
        $_SESSION['user_id'] = (int) $user['User_ID'];
        $_SESSION['name']    = $user['Name'];
        $_SESSION['role']    = $user['Role'];

        header('Location: index.php');
        exit;
    } else {
        $error = t('Invalid user ID or password.', 'ভুল ইউজার আইডি বা পাসওয়ার্ড।');
    }
}

$pageInstruction = t(
    'Enter your User ID and password to log in.',
    'লগইন করতে আপনার ইউজার আইডি এবং পাসওয়ার্ড দিন।'
);
?>
<!DOCTYPE html>
<html lang="<?= $LANG === 'bn' ? 'bn' : 'en' ?>">
<head>
<meta charset="UTF-8">
<title><?= t('Login', 'লগইন') ?> - KrishiDirect</title>
<link rel="stylesheet" href="style.css">
<script src="voice.js"></script>
</head>
<body>

<div class="navbar">
  <strong><a href="index.php">KrishiDirect</a></strong>
  <div>
    <a href="login.php"><?= t('Login', 'লগইন') ?></a>
    <a href="register.php"><?= t('Register', 'নিবন্ধন') ?></a>
    <?= lang_toggle_html() ?>
    <?= nav_speak_button() ?>
  </div>
</div>

<div class="container">
  <div style="text-align:center; margin-bottom:14px;">
    <img src="img/logo.png" alt="KrishiDirect logo" style="max-width:220px; width:100%; height:auto;">
  </div>
  <h1><?= t('Log in', 'লগইন করুন') ?></h1>
  <p class="instruction-row" style="color:#5a6b53; font-size:14px; margin-top:-8px;">
    <?= htmlspecialchars($pageInstruction) ?>
  </p>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="login.php">
    <label><?= t('User ID', 'ইউজার আইডি') ?></label>
    <input type="text" name="user_id" placeholder="<?= t('e.g. 1024', 'যেমনঃ ১০২৪') ?>" value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>" required>

    <label><?= t('Password', 'পাসওয়ার্ড') ?></label>
    <input type="password" name="password" required>

    <button type="submit"><?= t('Log in', 'লগইন করুন') ?></button>
  </form>

  <div class="link-row">
    <?= t("Don't have an account?", 'অ্যাকাউন্ট নেই?') ?>
    <a href="register.php"><?= t('Register', 'নিবন্ধন করুন') ?></a>
  </div>
</div>

</body>
</html>