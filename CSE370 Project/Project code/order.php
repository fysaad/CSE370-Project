<?php
require_once 'config.php';
require_once 'order_helpers.php';
require_role('Buyer');

$buyerId = (int) $_SESSION['user_id'];
$productId = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$errors = [];
$success = false;
$newOrderId = null;
$shipmentResult = null;

$stmt = $pdo->prepare(
    'SELECT p.Product_ID, p.Crop_Name, p.Available_Quantity, p.Minimum_Order_Quantity, p.Price_Per_KG,
            u.Name AS Farmer_Name, u.District AS Farmer_District
     FROM product p JOIN users u ON u.User_ID = p.Farmer_ID
     WHERE p.Product_ID = ?'
);
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    $errors[] = t('Product not found.', 'পণ্য খুঁজে পাওয়া যায়নি।');
} elseif ($product['Available_Quantity'] <= 0) {
    // Nothing left to order directly -- send the buyer to the preorder flow instead.
    header('Location: preorder.php?product_id=' . $productId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product) {
    $qty = $_POST['quantity'] ?? '';

    if (!is_numeric($qty) || $qty <= 0) {
        $errors[] = t('Enter a valid quantity.', 'একটি সঠিক পরিমাণ দিন।');
    } elseif ($qty < $product['Minimum_Order_Quantity']) {
        $errors[] = t(
            'Quantity is below the minimum order of ' . number_format($product['Minimum_Order_Quantity'], 2) . ' kg.',
            'পরিমাণ সর্বনিম্ন অর্ডারের চেয়ে কম, যা ' . number_format($product['Minimum_Order_Quantity'], 2) . ' কেজি।'
        );
    } elseif ($qty > $product['Available_Quantity']) {
        $errors[] = t(
            'Only ' . number_format($product['Available_Quantity'], 2) . ' kg available.',
            'মাত্র ' . number_format($product['Available_Quantity'], 2) . ' কেজি উপলব্ধ।'
        );
    }

    if (empty($errors)) {
        $qty = (float) $qty;
        $totalAmount = round($qty * (float) $product['Price_Per_KG'], 2);

        try {
            $pdo->beginTransaction();

            // Re-check availability inside the transaction to avoid race conditions
            $lockStmt = $pdo->prepare('SELECT Available_Quantity FROM product WHERE Product_ID = ? FOR UPDATE');
            $lockStmt->execute([$productId]);
            $current = $lockStmt->fetchColumn();

            if ($current === false || $qty > $current) {
                throw new Exception(t('Product availability changed. Please try again.', 'পণ্যের উপলব্ধতা পরিবর্তিত হয়েছে। আবার চেষ্টা করুন।'));
            }

            $upd = $pdo->prepare('UPDATE product SET Available_Quantity = Available_Quantity - ? WHERE Product_ID = ?');
            $upd->execute([$qty, $productId]);

            $ins = $pdo->prepare(
                'INSERT INTO orders (Buyer_ID, Product_ID, Shipment_ID, Order_Quantity, Total_Amount, Order_Status, Order_Date)
                 VALUES (?, ?, NULL, ?, ?, ?, NOW())'
            );
            $ins->execute([$buyerId, $productId, $qty, $totalAmount, 'Pending']);
            $newOrderId = (int) $pdo->lastInsertId();

            $shipmentResult = kd_assign_shipment($pdo, $newOrderId, $product['Farmer_District'], $qty);

            // Any cart entry for this product is now redundant -- clean it up.
            $pdo->prepare('DELETE FROM cart WHERE Buyer_ID = ? AND Product_ID = ?')
                ->execute([$buyerId, $productId]);

            $pdo->commit();
            $success = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $LANG === 'bn' ? 'bn' : 'en' ?>">
<head>
<meta charset="UTF-8">
<title><?= t('Place Order', 'অর্ডার করুন') ?> - KrishiDirect</title>
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

<div class="container">
  <h1><?= t('Place Order', 'অর্ডার করুন') ?></h1>

  <?php foreach ($errors as $e): ?><div class="error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

  <?php if ($success): ?>
    <div class="success">
      <?= sprintf(t('Order #%d placed successfully.', 'অর্ডার #%d সফলভাবে তৈরি হয়েছে।'), $newOrderId) ?>
      <a href="payment.php?order_id=<?= $newOrderId ?>"><?= t('Pay now', 'এখনই পরিশোধ করুন') ?></a>
      <?= t('to confirm it, or view it later in', 'নিশ্চিত করতে, অথবা পরে দেখুন') ?>
      <a href="my_orders.php"><?= t('My Orders', 'আমার অর্ডার') ?></a>.
      <br><br>
      <?= htmlspecialchars($shipmentResult['message']) ?>
    </div>
  <?php elseif ($product): ?>
    <p><strong><?= htmlspecialchars($product['Crop_Name']) ?></strong> <?= t('from', 'থেকে') ?> <?= htmlspecialchars($product['Farmer_Name']) ?></p>
    <p><?= t('Price', 'মূল্য') ?>: ৳<?= number_format($product['Price_Per_KG'], 2) ?> / kg &nbsp;|&nbsp;
       <?= t('Available', 'উপলব্ধ') ?>: <?= number_format($product['Available_Quantity'], 2) ?> kg &nbsp;|&nbsp;
       <?= t('Min order', 'সর্বনিম্ন অর্ডার') ?>: <?= number_format($product['Minimum_Order_Quantity'], 2) ?> kg</p>

    <form method="POST" action="order.php">
      <input type="hidden" name="product_id" value="<?= (int) $product['Product_ID'] ?>">
      <label><?= t('Quantity (kg)', 'পরিমাণ (কেজি)') ?></label>
      <input type="number" step="0.01" name="quantity"
             min="<?= (float) $product['Minimum_Order_Quantity'] ?>"
             max="<?= (float) $product['Available_Quantity'] ?>"
             value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>" required>
      <button type="submit"><?= t('Confirm Order', 'অর্ডার নিশ্চিত করুন') ?></button>
    </form>

    <form method="POST" action="cart.php" style="margin-top:10px;">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="product_id" value="<?= (int) $product['Product_ID'] ?>">
      <input type="hidden" name="quantity" value="<?= (float) $product['Minimum_Order_Quantity'] ?>">
      <button type="submit" style="background:#6c757d;"><?= t('Add to Cart Instead', 'পরিবর্তে কার্টে যোগ করুন') ?></button>
    </form>
  <?php endif; ?>
</div>

</body>
</html>
