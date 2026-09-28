<?php
require_once 'config.php';
require_role('Buyer');

$buyerId = (int) $_SESSION['user_id'];
$orderId = (int) ($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$errors = [];
$success = false;

// Make sure this order belongs to the logged-in buyer and isn't already paid
$stmt = $pdo->prepare(
    "SELECT o.Order_ID, o.Total_Amount, o.Order_Status, p.Crop_Name
     FROM orders o JOIN product p ON p.Product_ID = o.Product_ID
     WHERE o.Order_ID = ? AND o.Buyer_ID = ?"
);
$stmt->execute([$orderId, $buyerId]);
$order = $stmt->fetch();

if (!$order) {
    $errors[] = t('Order not found.', 'অর্ডার খুঁজে পাওয়া যায়নি।');
} else {
    $paidCheck = $pdo->prepare('SELECT Payment_ID FROM payment WHERE Order_ID = ?');
    $paidCheck->execute([$orderId]);
    if ($paidCheck->fetch()) {
        $errors[] = t('This order has already been paid.', 'এই অর্ডারটি ইতিমধ্যে পরিশোধ করা হয়েছে।');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $method = $_POST['method'] ?? '';
    if (!in_array($method, ['BKASH', 'NAGAD'], true)) {
        $errors[] = t('Select a payment method.', 'একটি পেমেন্ট পদ্ধতি নির্বাচন করুন।');
    }

    if (empty($errors)) {
        $txnId = $method . '_TRX_' . strtoupper(bin2hex(random_bytes(4)));

        try {
            $pdo->beginTransaction();

            $ins = $pdo->prepare(
                'INSERT INTO payment (Order_ID, Transaction_MFS_ID, Amount, Payment_Status, Paid_At)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $ins->execute([$orderId, $txnId, $order['Total_Amount'], 'Held-in-Escrow']);

            $upd = $pdo->prepare("UPDATE orders SET Order_Status = 'Paid-Escrow' WHERE Order_ID = ?");
            $upd->execute([$orderId]);

            $pdo->commit();
            $success = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = t('Payment failed: ', 'পেমেন্ট ব্যর্থ হয়েছে: ') . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $LANG === 'bn' ? 'bn' : 'en' ?>">
<head>
<meta charset="UTF-8">
<title><?= t('Pay for Order', 'অর্ডারের জন্য পরিশোধ করুন') ?> - KrishiDirect</title>
<link rel="stylesheet" href="style.css">
<script src="voice.js"></script>
</head>
<body>

<div class="navbar">
  <strong><a href="index.php">KrishiDirect</a></strong>
  <div>
    <a href="index.php"><?= t('Dashboard', 'ড্যাশবোর্ড') ?></a>
    <a href="products.php"><?= t('Products', 'পণ্য') ?></a>
    <a href="my_orders.php"><?= t('My Orders', 'আমার অর্ডার') ?></a>
    <a href="logout.php"><?= t('Logout', 'লগআউট') ?></a>
    <?= lang_toggle_html() ?>
    <?= nav_speak_button() ?>
    <?= kd_notif_bell_html() ?>
  </div>
</div>

<div class="container">
  <h1><?= t('Payment', 'পেমেন্ট') ?></h1>

  <?php foreach ($errors as $e): ?><div class="error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

  <?php if ($success): ?>
    <div class="success">
      <?= sprintf(t('Payment of ৳%s received and held in escrow.', 'পেমেন্ট ৳%s গৃহীত হয়েছে এবং এসক্রোতে সংরক্ষিত আছে।'), number_format($order['Total_Amount'], 2)) ?>
      <?= t('It will be released to the farmer once delivery is confirmed.', 'ডেলিভারি নিশ্চিত হলে এটি কৃষকের কাছে প্রদান করা হবে।') ?>
    </div>
    <a class="btn" href="my_orders.php"><?= t('Back to My Orders', 'আমার অর্ডারে ফিরে যান') ?></a>
  <?php elseif ($order): ?>
    <p><?= sprintf(t('Order #%d', 'অর্ডার #%d'), (int) $order['Order_ID']) ?> — <?= htmlspecialchars($order['Crop_Name']) ?></p>
    <p><?= t('Amount due:', 'পরিশোধযোগ্য পরিমাণ:') ?> <strong>৳<?= number_format($order['Total_Amount'], 2) ?></strong></p>

    <form method="POST" action="payment.php">
      <input type="hidden" name="order_id" value="<?= (int) $order['Order_ID'] ?>">
      <label><?= t('Payment Method', 'পেমেন্ট পদ্ধতি') ?></label>
      <select name="method" required>
        <option value=""><?= t('-- Select --', '-- নির্বাচন করুন --') ?></option>
        <option value="BKASH"><?= t('bKash', 'বিকাশ') ?></option>
        <option value="NAGAD"><?= t('Nagad', 'নগদ') ?></option>
      </select>
      <button type="submit"><?= t('Pay Now', 'এখনই পরিশোধ করুন') ?></button>
    </form>
  <?php endif; ?>
</div>

</body>
</html>
