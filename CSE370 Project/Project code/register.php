<?php
require_once 'config.php';

$errors = [];
$success = false;

// Roles allowed, must match the ENUM in schema.sql / seed data.
// Keys are the real DB values (English) -- only the on-screen label
// is translated, so logic elsewhere in the app is unaffected.
$roles = [
    'Farmer'       => t('Farmer', 'কৃষক'),
    'Buyer'        => t('Buyer', 'ক্রেতা'),
    'Manager'      => t('Manager', 'ব্যবস্থাপক'),
    'Truck Driver' => t('Truck Driver', 'ট্রাক চালক'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $role     = $_POST['role'] ?? '';
    $district = trim($_POST['district'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '') $errors[] = t('Name is required.', 'নাম আবশ্যক।');
    if (!preg_match('/^01[0-9]{9}$/', $phone)) $errors[] = t('Enter a valid 11-digit BD phone number (e.g. 01712345678).', 'সঠিক ১১ সংখ্যার বাংলাদেশি ফোন নম্বর দিন (যেমনঃ 01712345678)।');
    if (!array_key_exists($role, $roles)) $errors[] = t('Please select a valid role.', 'সঠিক ভূমিকা নির্বাচন করুন।');
    if ($district === '') $errors[] = t('District is required.', 'জেলা আবশ্যক।');
    if (strlen($password) < 4) $errors[] = t('Password must be at least 4 characters.', 'পাসওয়ার্ড কমপক্ষে ৪ অক্ষরের হতে হবে।');

    if (empty($errors)) {
        // Check phone number isn't already registered
        $stmt = $pdo->prepare('SELECT User_ID FROM users WHERE Phone_Number = ?');
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $errors[] = t('This phone number is already registered.', 'এই ফোন নম্বরটি ইতিমধ্যে নিবন্ধিত।');
        }
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (Name, Phone_Number, Role, District, Password)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $phone, $role, $district, $hashed]);

        $newUserId = $pdo->lastInsertId();
        $success = true;
    }
}

$pageInstruction = t(
    'Fill in your details below to create an account. Write down your User ID after registering -- you will need it to log in.',
    'অ্যাকাউন্ট তৈরি করতে নিচের তথ্য পূরণ করুন। নিবন্ধনের পর আপনার ইউজার আইডি লিখে রাখুন -- লগইন করতে এটি প্রয়োজন হবে।'
);
?>
<!DOCTYPE html>
<html lang="<?= $LANG === 'bn' ? 'bn' : 'en' ?>">
<head>
<meta charset="UTF-8">
<title><?= t('Register', 'নিবন্ধন') ?> - KrishiDirect</title>
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
  <h1><?= t('Create an account', 'অ্যাকাউন্ট তৈরি করুন') ?></h1>
  <p class="instruction-row" style="color:#5a6b53; font-size:14px; margin-top:-8px;">
    <?= htmlspecialchars($pageInstruction) ?>
  </p>

  <?php if ($success): ?>
    <div class="success">
      <?= t('Registration successful! Your User ID is', 'নিবন্ধন সফল হয়েছে! আপনার ইউজার আইডি হলো') ?>
      <strong><?= htmlspecialchars($newUserId) ?></strong> —
      <?= t('please note it down.', 'দয়া করে এটি লিখে রাখুন।') ?>
      <?= t('You can now', 'আপনি এখন') ?> <a href="login.php"><?= t('log in', 'লগইন করতে পারেন') ?></a>.
    </div>
  <?php else: ?>

    <?php foreach ($errors as $e): ?>
      <div class="error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="register.php">
      <label><?= t('Full Name', 'পুরো নাম') ?></label>
      <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>

      <label><?= t('Phone Number', 'ফোন নম্বর') ?></label>
      <input type="text" name="phone" placeholder="01712345678" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>

      <label><?= t('Role', 'ভূমিকা') ?></label>
      <select name="role" required>
        <option value=""><?= t('-- Select Role --', '-- ভূমিকা নির্বাচন করুন --') ?></option>
        <?php foreach ($roles as $value => $label): ?>
          <option value="<?= htmlspecialchars($value) ?>" <?= (($_POST['role'] ?? '') === $value) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>

      <label><?= t('District', 'জেলা') ?></label>
      <input type="text" name="district" value="<?= htmlspecialchars($_POST['district'] ?? '') ?>" required>

      <label><?= t('Password', 'পাসওয়ার্ড') ?></label>
      <input type="password" name="password" required>

      <button type="submit"><?= t('Register', 'নিবন্ধন করুন') ?></button>
    </form>

    <div class="link-row">
      <?= t('Already have an account?', 'ইতিমধ্যে অ্যাকাউন্ট আছে?') ?>
      <a href="login.php"><?= t('Log in', 'লগইন করুন') ?></a>
    </div>
  <?php endif; ?>
</div>

</body>
</html>
