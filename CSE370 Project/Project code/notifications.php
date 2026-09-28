<?php
require_once 'config.php';
require_login();

$userId = (int) $_SESSION['user_id'];

// Fetch first, then mark as read, so the "new" styling is visible on
// this exact page load and the badge clears once they've seen them.
$notifications = kd_recent_notifications($pdo, $userId, 100);
kd_mark_notifications_read($pdo, $userId);
?>
<!DOCTYPE html>
<html lang="<?= $LANG === 'bn' ? 'bn' : 'en' ?>">
<head>
<meta charset="UTF-8">
<title><?= t('Notifications', 'বিজ্ঞপ্তি') ?> - KrishiDirect</title>
<link rel="stylesheet" href="style.css">
<script src="voice.js"></script>
</head>
<body>

<div class="navbar">
  <strong><a href="index.php">KrishiDirect</a></strong>
  <div>
    <a href="index.php"><?= t('Dashboard', 'ড্যাশবোর্ড') ?></a>
    <?php if ($_SESSION['role'] === 'Buyer'): ?>
      <a href="products.php"><?= t('Products', 'পণ্য') ?></a>
      <a href="cart.php"><?= t('Cart', 'কার্ট') ?></a>
      <a href="my_orders.php"><?= t('My Orders', 'আমার অর্ডার') ?></a>
    <?php elseif ($_SESSION['role'] === 'Farmer'): ?>
      <a href="products.php"><?= t('Products', 'পণ্য') ?></a>
      <a href="my_products.php"><?= t('My Products', 'আমার পণ্য') ?></a>
      <a href="cold_storage.php"><?= t('Cold Storage', 'কোল্ড স্টোরেজ') ?></a>
    <?php elseif ($_SESSION['role'] === 'Manager'): ?>
      <a href="manager_dashboard.php"><?= t('Manager Dashboard', 'ম্যানেজার ড্যাশবোর্ড') ?></a>
    <?php elseif ($_SESSION['role'] === 'Truck Driver'): ?>
      <a href="shipments.php"><?= t('Shipments', 'শিপমেন্ট') ?></a>
    <?php endif; ?>
    <a href="logout.php"><?= t('Logout', 'লগআউট') ?></a>
    <?= lang_toggle_html() ?>
    <?= nav_speak_button() ?>
  </div>
</div>

<div class="container wide">
  <h1><?= t('Notifications', 'বিজ্ঞপ্তি') ?></h1>

  <ul class="notif-list">
    <?php foreach ($notifications as $n): ?>
      <li class="notif-item<?= $n['Is_Read'] ? '' : ' unread' ?>">
        <div class="notif-top">
          <span><?= htmlspecialchars(t($n['Message_EN'], $n['Message_BN'])) ?></span>
          <?php if (!$n['Is_Read']): ?><span class="status-pill Pending"><?= t('New', 'নতুন') ?></span><?php endif; ?>
        </div>
        <div class="notif-sub">
          <?= htmlspecialchars(date('d M Y, h:i A', strtotime($n['Created_At']))) ?>
          <?php if ($n['Link']): ?>
            &nbsp;|&nbsp;
            <a href="<?= htmlspecialchars($n['Link']) ?>"><?= t('View', 'দেখুন') ?></a>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
    <?php if (empty($notifications)): ?>
      <p class="empty-note"><?= t('No notifications yet.', 'এখনো কোনো বিজ্ঞপ্তি নেই।') ?></p>
    <?php endif; ?>
  </ul>
</div>

</body>
</html>
