<?php
require_once 'config.php';
require_login();

$role = $_SESSION['role'];
$name = $_SESSION['name'];
$userId = (int) $_SESSION['user_id'];

$roleLabels = [
    'Farmer'       => t('Farmer', 'কৃষক'),
    'Buyer'        => t('Buyer', 'ক্রেতা'),
    'Manager'      => t('Manager', 'ব্যবস্থাপক'),
    'Truck Driver' => t('Truck Driver', 'ট্রাক চালক'),
];

// ---------------------------------------------------------------
// Farmer dashboard data: buyer order notifications, cold storage
// status updates, and total money (from the payment table, so it
// reflects what's actually been paid vs. still held in escrow).
// ---------------------------------------------------------------
$buyerNotifications = [];
$storageUpdates = [];
$totalReleased = 0;
$totalEscrow = 0;
$activeBookingCount = 0;

if ($role === 'Farmer') {
    // Recent orders placed by buyers on this farmer's products
    $stmt = $pdo->prepare(
        "SELECT o.Order_ID, o.Order_Quantity, o.Total_Amount, o.Order_Status, o.Order_Date,
                p.Crop_Name, u.Name AS Buyer_Name
         FROM orders o
         JOIN product p ON p.Product_ID = o.Product_ID
         JOIN users u ON u.User_ID = o.Buyer_ID
         WHERE p.Farmer_ID = ?
         ORDER BY o.Order_Date DESC
         LIMIT 8"
    );
    $stmt->execute([$userId]);
    $buyerNotifications = $stmt->fetchAll();

    // Cold storage booking status for this farmer
    $stmt = $pdo->prepare(
        "SELECT sb.Booking_ID, sb.Sacks_To_Store, sb.Cubic_Meters_To_Occupy, sb.Booking_Date, sb.Status,
                cs.Facility_Name, cs.District
         FROM storage_booking sb
         JOIN cold_storage cs ON cs.Storage_ID = sb.Storage_ID
         WHERE sb.Farmer_ID = ?
         ORDER BY sb.Booking_Date DESC
         LIMIT 8"
    );
    $stmt->execute([$userId]);
    $storageUpdates = $stmt->fetchAll();

    $activeStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM storage_booking WHERE Farmer_ID = ? AND Status = 'Active'"
    );
    $activeStmt->execute([$userId]);
    $activeBookingCount = (int) $activeStmt->fetchColumn();

    // Money actually released to the farmer (escrow paid out).
    // NOTE: this only increases once a buyer confirms delivery on
    // my_orders.php -- see that file for the release step.
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(pay.Amount), 0)
         FROM payment pay
         JOIN orders o ON o.Order_ID = pay.Order_ID
         JOIN product p ON p.Product_ID = o.Product_ID
         WHERE p.Farmer_ID = ? AND pay.Payment_Status = 'Released'"
    );
    $stmt->execute([$userId]);
    $totalReleased = (float) $stmt->fetchColumn();

    // Money paid by buyers but still held in escrow (not yet released)
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(pay.Amount), 0)
         FROM payment pay
         JOIN orders o ON o.Order_ID = pay.Order_ID
         JOIN product p ON p.Product_ID = o.Product_ID
         WHERE p.Farmer_ID = ? AND pay.Payment_Status = 'Held-in-Escrow'"
    );
    $stmt->execute([$userId]);
    $totalEscrow = (float) $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="<?= $LANG === 'bn' ? 'bn' : 'en' ?>">
<head>
<meta charset="UTF-8">
<title><?= t('Dashboard', 'ড্যাশবোর্ড') ?> - KrishiDirect</title>
<link rel="stylesheet" href="style.css">
<script src="voice.js"></script>
</head>
<body>

<div class="navbar">
  <strong><a href="index.php">KrishiDirect</a></strong>
  <div>
    <span><?= t('Hi,', 'হাই,') ?> <?= htmlspecialchars($name) ?> (<?= htmlspecialchars($roleLabels[$role] ?? $role) ?>)</span>
    <a href="logout.php"><?= t('Logout', 'লগআউট') ?></a>
    <?= lang_toggle_html() ?>
    <?= nav_speak_button() ?>
    <?= kd_notif_bell_html() ?>
  </div>
</div>

<div class="container <?= $role === 'Farmer' ? 'wide' : '' ?>">
  <h1><?= t('Welcome,', 'স্বাগতম,') ?> <?= htmlspecialchars($name) ?></h1>
  <p><?= t('Role:', 'ভূমিকা:') ?> <span class="badge <?= str_replace(' ', '', $role) ?>"><?= htmlspecialchars($roleLabels[$role] ?? $role) ?></span></p>

  <?php if ($role === 'Buyer'): ?>
    <a class="btn" href="products.php"><?= t('Browse Products', 'পণ্য দেখুন') ?></a>
    <a class="btn" href="my_orders.php"><?= t('My Orders', 'আমার অর্ডার') ?></a>
  <?php endif; ?>

  <?php if ($role === 'Manager'): ?>
    <a class="btn" href="manager_dashboard.php"><?= t('Manager Dashboard', 'ব্যবস্থাপক ড্যাশবোর্ড') ?></a>
  <?php endif; ?>

  <?php if ($role === 'Farmer'): ?>
    <a class="btn" href="products.php"><?= t('Browse Products', 'পণ্য দেখুন') ?></a>
    <a class="btn" href="my_products.php"><?= t('My Products', 'আমার পণ্য') ?></a>
    <a class="btn" href="cold_storage.php"><?= t('Cold Storage', 'কোল্ড স্টোরেজ') ?></a>

    <!-- ================= Farmer earnings summary ================= -->
    <div class="stats-grid">
      <div class="stat-box">
        <div class="stat-label"><strong><?= t('Total Earnings (Released)', 'মোট আয় (প্রদত্ত)') ?></strong></div>
        <div class="stat-value">৳<?= number_format($totalReleased, 2) ?></div>
      </div>
      <div class="stat-box escrow">
        <div class="stat-label"><strong><?= t('Held in Escrow', 'এসক্রোতে জমা') ?></strong></div>
        <div class="stat-value">৳<?= number_format($totalEscrow, 2) ?></div>
      </div>
      <div class="stat-box bookings">
        <div class="stat-label"><strong><?= t('Active Cold Storage Bookings', 'সক্রিয় কোল্ড স্টোরেজ বুকিং') ?></strong></div>
        <div class="stat-value"><?= $activeBookingCount ?></div>
      </div>
    </div>

    <div class="dash-columns">
      <!-- ================= Buying notifications ================= -->
      <div>
        <h2><?= t('Buyer Notifications', 'ক্রেতাদের বিজ্ঞপ্তি') ?></h2>
        <?php if (empty($buyerNotifications)): ?>
          <p class="empty-note"><?= t('No orders yet. Once a buyer orders your crops, it will show up here.', 'এখনো কোনো অর্ডার নেই। কোনো ক্রেতা আপনার ফসল অর্ডার করলে তা এখানে দেখা যাবে।') ?></p>
        <?php else: ?>
          <ul class="notif-list">
            <?php foreach ($buyerNotifications as $n): ?>
              <li class="notif-item">
                <div class="notif-top">
                  <span><?= htmlspecialchars($n['Buyer_Name']) ?> &mdash; <?= htmlspecialchars($n['Crop_Name']) ?></span>
                  <?= kd_status_pill($n['Order_Status']) ?>
                </div>
                <div class="notif-sub">
                  <?= number_format($n['Order_Quantity'], 2) ?> kg &nbsp;|&nbsp;
                  ৳<?= number_format($n['Total_Amount'], 2) ?> &nbsp;|&nbsp;
                  <?= htmlspecialchars(date('d M Y, h:i A', strtotime($n['Order_Date']))) ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

   
  <?php endif; ?>

  <?php if ($role === 'Truck Driver'): ?>
    <a class="btn" href="shipments.php"><?= t('Manage Shipment', 'শিপমেন্ট পরিচালনা') ?></a>
  <?php endif; ?>
</div>

</body>
</html>
