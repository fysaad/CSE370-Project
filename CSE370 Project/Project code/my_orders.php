<?php
require_once 'config.php';
require_once 'order_helpers.php';
require_role('Buyer');

$buyerId = (int) $_SESSION['user_id'];
$confirmError = '';
$confirmSuccess = false;
$cancelError = '';
$cancelSuccess = false;
$preorderCancelled = false;

// -------------------------------------------------------------
// Confirm Delivery & Release Payment
//
// This is the missing link in the escrow flow: payment.php always
// inserts payments as 'Held-in-Escrow' and nothing else in the app
// ever moved that money to 'Released'. Without this step, the
// farmer's "Total Earnings (Released)" on their dashboard stays
// ৳0.00 forever, even for fully paid orders.
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_delivery') {
    $orderId = (int) ($_POST['order_id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT o.Order_ID, o.Order_Status, pay.Payment_ID, pay.Payment_Status
         FROM orders o
         JOIN payment pay ON pay.Order_ID = o.Order_ID
         WHERE o.Order_ID = ? AND o.Buyer_ID = ?
         FOR UPDATE"
    );

    try {
        $pdo->beginTransaction();
        $stmt->execute([$orderId, $buyerId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new Exception(t('Order not found.', 'অর্ডার খুঁজে পাওয়া যায়নি।'));
        }
        if ($row['Payment_Status'] !== 'Held-in-Escrow') {
            throw new Exception(t('This order has no payment waiting to be released.', 'এই অর্ডারের কোনো পেমেন্ট মুক্তির অপেক্ষায় নেই।'));
        }

        $pdo->prepare("UPDATE payment SET Payment_Status = 'Released' WHERE Payment_ID = ?")
            ->execute([$row['Payment_ID']]);
        $pdo->prepare("UPDATE orders SET Order_Status = 'Completed' WHERE Order_ID = ?")
            ->execute([$orderId]);

        $pdo->commit();
        $confirmSuccess = true;
    } catch (Exception $e) {
        $pdo->rollBack();
        $confirmError = $e->getMessage();
    }
}

// -------------------------------------------------------------
// Cancel Order
//
// Allowed only while the order is still Pending or Paid-Escrow and
// hasn't left the farmer yet. Restores stock, releases the pooled
// shipment slot, and refunds escrowed payment automatically.
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_order') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $result = kd_cancel_order($pdo, $orderId, $buyerId);
    $cancelSuccess = $result['success'];
    if (!$result['success']) {
        $cancelError = $result['message'];
    }
}

// -------------------------------------------------------------
// Cancel Preorder
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_preorder') {
    $preorderId = (int) ($_POST['preorder_id'] ?? 0);
    $pdo->prepare("UPDATE preorder SET Status = 'Cancelled' WHERE Preorder_ID = ? AND Buyer_ID = ? AND Status = 'Waiting'")
        ->execute([$preorderId, $buyerId]);
    $preorderCancelled = true;
}

$stmt = $pdo->prepare(
    "SELECT o.Order_ID, o.Order_Quantity, o.Total_Amount, o.Order_Status, o.Order_Date,
            p.Crop_Name, u.Name AS Farmer_Name,
            pay.Payment_ID, pay.Payment_Status,
            s.Shipment_Status
     FROM orders o
     JOIN product p ON p.Product_ID = o.Product_ID
     JOIN users u ON u.User_ID = p.Farmer_ID
     LEFT JOIN payment pay ON pay.Order_ID = o.Order_ID
     LEFT JOIN shipment s ON s.Shipment_ID = o.Shipment_ID
     WHERE o.Buyer_ID = ?
     ORDER BY o.Order_Date DESC"
);
$stmt->execute([$buyerId]);
$orders = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT pr.Preorder_ID, pr.Quantity, pr.Status, pr.Created_At,
            p.Product_ID, p.Crop_Name, p.Available_Quantity, p.Price_Per_KG,
            u.Name AS Farmer_Name
     FROM preorder pr
     JOIN product p ON p.Product_ID = pr.Product_ID
     JOIN users u ON u.User_ID = p.Farmer_ID
     WHERE pr.Buyer_ID = ?
     ORDER BY pr.Created_At DESC"
);
$stmt->execute([$buyerId]);
$preorders = $stmt->fetchAll();

function kd_cancellable(array $o): bool {
    if (!in_array($o['Order_Status'], ['Pending', 'Paid-Escrow'], true)) {
        return false;
    }
    if ($o['Shipment_Status'] && !in_array($o['Shipment_Status'], ['Pooling'], true)) {
        return false;
    }
    return true;
}
?>
<!DOCTYPE html>
<html lang="<?= $LANG === 'bn' ? 'bn' : 'en' ?>">
<head>
<meta charset="UTF-8">
<title><?= t('My Orders', 'আমার অর্ডার') ?> - KrishiDirect</title>
<link rel="stylesheet" href="style.css">
<script src="voice.js"></script>
</head>
<body>

<div class="navbar">
  <strong><a href="index.php">KrishiDirect</a></strong>
  <div>
    <a href="index.php"><?= t('Dashboard', 'ড্যাশবোর্ড') ?></a>
    <a href="products.php"><?= t('Products', 'পণ্য') ?></a>
    <a href="cart.php"><?= t('Cart', 'কার্ট') ?></a>
    <a href="my_orders.php"><?= t('My Orders', 'আমার অর্ডার') ?></a>
    <a href="logout.php"><?= t('Logout', 'লগআউট') ?></a>
    <?= lang_toggle_html() ?>
    <?= nav_speak_button() ?>
    <?= kd_notif_bell_html() ?>
  </div>
</div>

<div class="container wide">
  <h1><?= t('My Orders', 'আমার অর্ডার') ?></h1>

  <?php if ($confirmSuccess): ?>
    <div class="success"><?= t('Delivery confirmed. Payment has been released to the farmer.', 'ডেলিভারি নিশ্চিত হয়েছে। পেমেন্ট কৃষকের কাছে প্রদান করা হয়েছে।') ?></div>
  <?php endif; ?>
  <?php if ($confirmError): ?>
    <div class="error"><?= htmlspecialchars($confirmError) ?></div>
  <?php endif; ?>
  <?php if ($cancelSuccess): ?>
    <div class="success"><?= t('Order cancelled.', 'অর্ডার বাতিল করা হয়েছে।') ?></div>
  <?php endif; ?>
  <?php if ($cancelError): ?>
    <div class="error"><?= htmlspecialchars($cancelError) ?></div>
  <?php endif; ?>
  <?php if ($preorderCancelled): ?>
    <div class="success"><?= t('Preorder cancelled.', 'প্রি-অর্ডার বাতিল করা হয়েছে।') ?></div>
  <?php endif; ?>

  <table>
    <tr>
      <th><?= t('Crop', 'ফসল') ?></th>
      <th><?= t('Farmer', 'কৃষক') ?></th>
      <th><?= t('Quantity', 'পরিমাণ') ?></th>
      <th><?= t('Amount', 'পরিমাণ (টাকা)') ?></th>
      <th><?= t('Order Status', 'অর্ডার অবস্থা') ?></th>
      <th><?= t('Payment', 'পেমেন্ট') ?></th>
      <th></th>
    </tr>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td><?= htmlspecialchars($o['Crop_Name']) ?></td>
        <td><?= htmlspecialchars($o['Farmer_Name']) ?></td>
        <td><?= number_format($o['Order_Quantity'], 2) ?> kg</td>
        <td>৳<?= number_format($o['Total_Amount'], 2) ?></td>
        <td><?= kd_status_pill($o['Order_Status']) ?></td>
        <td>
          <?php if (!$o['Payment_ID']): ?>
            <?= kd_status_pill('Pending') ?>
          <?php else: ?>
            <?= kd_status_pill($o['Payment_Status']) ?>
          <?php endif; ?>
        </td>
        <td>
          <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-start;">
            <?php if (!$o['Payment_ID'] && $o['Order_Status'] === 'Pending'): ?>
              <a class="btn" style="margin:0;padding:6px 14px;" href="payment.php?order_id=<?= (int) $o['Order_ID'] ?>"><?= t('Pay Now', 'এখনই পরিশোধ করুন') ?></a>
            <?php elseif ($o['Payment_Status'] === 'Held-in-Escrow'): ?>
              <form method="POST" action="my_orders.php" style="margin:0;">
                <input type="hidden" name="action" value="confirm_delivery">
                <input type="hidden" name="order_id" value="<?= (int) $o['Order_ID'] ?>">
                <button type="submit" style="margin:0;padding:6px 14px;width:auto;"
                  onclick="return confirm(<?= json_encode(t('Confirm you have received this delivery? This will release payment to the farmer.', 'আপনি কি এই ডেলিভারি পেয়েছেন তা নিশ্চিত করছেন? এটি কৃষকের কাছে পেমেন্ট প্রদান করবে।')) ?>);">
                  <?= t('Confirm Delivery', 'ডেলিভারি নিশ্চিত করুন') ?>
                </button>
              </form>
            <?php endif; ?>

            <?php if (kd_cancellable($o)): ?>
              <form method="POST" action="my_orders.php" style="margin:0;">
                <input type="hidden" name="action" value="cancel_order">
                <input type="hidden" name="order_id" value="<?= (int) $o['Order_ID'] ?>">
                <button type="submit" style="margin:0;padding:6px 14px;width:auto;background:#b00020;"
                  onclick="return confirm(<?= json_encode(
                    $o['Payment_Status'] === 'Held-in-Escrow'
                      ? t('Cancel this order? Your payment will be refunded.', 'এই অর্ডারটি বাতিল করবেন? আপনার পেমেন্ট ফেরত দেওয়া হবে।')
                      : t('Cancel this order?', 'এই অর্ডারটি বাতিল করবেন?')
                  ) ?>);">
                  <?= t('Cancel Order', 'অর্ডার বাতিল করুন') ?>
                </button>
              </form>
            <?php endif; ?>

            <?php if (!kd_cancellable($o) && ($o['Payment_ID'] ? $o['Payment_Status'] !== 'Held-in-Escrow' : $o['Order_Status'] !== 'Pending')): ?>
              &mdash;
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($orders)): ?>
      <tr><td colspan="7"><?= t("You haven't placed any orders yet.", 'আপনি এখনো কোনো অর্ডার দেননি।') ?></td></tr>
    <?php endif; ?>
  </table>

  <h1 style="margin-top:40px;"><?= t('My Preorders', 'আমার প্রি-অর্ডার') ?></h1>
  <table>
    <tr>
      <th><?= t('Crop', 'ফসল') ?></th>
      <th><?= t('Farmer', 'কৃষক') ?></th>
      <th><?= t('Quantity', 'পরিমাণ') ?></th>
      <th><?= t('Status', 'অবস্থা') ?></th>
      <th><?= t('Requested', 'অনুরোধ করা হয়েছে') ?></th>
      <th></th>
    </tr>
    <?php foreach ($preorders as $pr): ?>
      <tr>
        <td><?= htmlspecialchars($pr['Crop_Name']) ?></td>
        <td><?= htmlspecialchars($pr['Farmer_Name']) ?></td>
        <td><?= number_format($pr['Quantity'], 2) ?> kg</td>
        <td><?= kd_status_pill($pr['Status']) ?></td>
        <td><?= htmlspecialchars($pr['Created_At']) ?></td>
        <td>
          <?php if ($pr['Status'] === 'Waiting'): ?>
            <form method="POST" action="my_orders.php" style="margin:0;">
              <input type="hidden" name="action" value="cancel_preorder">
              <input type="hidden" name="preorder_id" value="<?= (int) $pr['Preorder_ID'] ?>">
              <button type="submit" style="margin:0;padding:6px 14px;width:auto;background:#6c757d;"
                onclick="return confirm(<?= json_encode(t('Cancel this preorder?', 'এই প্রি-অর্ডারটি বাতিল করবেন?')) ?>);">
                <?= t('Cancel', 'বাতিল করুন') ?>
              </button>
            </form>
          <?php elseif ($pr['Status'] === 'Fulfilled'): ?>
            <a class="btn" style="margin:0;padding:6px 14px;" href="order.php?product_id=<?= (int) $pr['Product_ID'] ?>"><?= t('Order Now', 'এখনই অর্ডার করুন') ?></a>
          <?php else: ?>
            &mdash;
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($preorders)): ?>
      <tr><td colspan="6"><?= t("You haven't placed any preorders.", 'আপনি এখনো কোনো প্রি-অর্ডার দেননি।') ?></td></tr>
    <?php endif; ?>
  </table>
</div>

</body>
</html>
