<?php
require_once 'config.php';
require_once 'order_helpers.php';
require_role('Buyer');

$buyerId = (int) $_SESSION['user_id'];
$message = '';
$messageType = '';
$checkoutResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty = $_POST['quantity'] ?? '';

        $stmt = $pdo->prepare('SELECT Available_Quantity, Minimum_Order_Quantity FROM product WHERE Product_ID = ?');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            $message = t('Product not found.', 'পণ্য খুঁজে পাওয়া যায়নি।');
            $messageType = 'error';
        } elseif (!is_numeric($qty) || $qty <= 0) {
            $message = t('Enter a valid quantity.', 'একটি সঠিক পরিমাণ দিন।');
            $messageType = 'error';
        } elseif ($qty < $product['Minimum_Order_Quantity']) {
            $message = t(
                'Quantity is below the minimum order of ' . number_format($product['Minimum_Order_Quantity'], 2) . ' kg.',
                'পরিমাণ সর্বনিম্ন অর্ডারের চেয়ে কম, যা ' . number_format($product['Minimum_Order_Quantity'], 2) . ' কেজি।'
            );
            $messageType = 'error';
        } elseif ($qty > $product['Available_Quantity']) {
            $message = t(
                'Only ' . number_format($product['Available_Quantity'], 2) . ' kg available.',
                'মাত্র ' . number_format($product['Available_Quantity'], 2) . ' কেজি উপলব্ধ।'
            );
            $messageType = 'error';
        } else {
            $existing = $pdo->prepare('SELECT Cart_ID FROM cart WHERE Buyer_ID = ? AND Product_ID = ?');
            $existing->execute([$buyerId, $productId]);
            $row = $existing->fetch();

            if ($row) {
                $pdo->prepare('UPDATE cart SET Quantity = ? WHERE Cart_ID = ?')
                    ->execute([(float) $qty, $row['Cart_ID']]);
            } else {
                $pdo->prepare('INSERT INTO cart (Buyer_ID, Product_ID, Quantity) VALUES (?, ?, ?)')
                    ->execute([$buyerId, $productId, (float) $qty]);
            }
            $message = t('Added to cart.', 'কার্টে যোগ করা হয়েছে।');
            $messageType = 'success';
        }
    }

    if ($action === 'update') {
        $cartId = (int) ($_POST['cart_id'] ?? 0);
        $qty = $_POST['quantity'] ?? '';

        $stmt = $pdo->prepare(
            'SELECT c.Cart_ID, p.Available_Quantity, p.Minimum_Order_Quantity
             FROM cart c JOIN product p ON p.Product_ID = c.Product_ID
             WHERE c.Cart_ID = ? AND c.Buyer_ID = ?'
        );
        $stmt->execute([$cartId, $buyerId]);
        $row = $stmt->fetch();

        if (!$row) {
            $message = t('Cart item not found.', 'কার্ট আইটেম খুঁজে পাওয়া যায়নি।');
            $messageType = 'error';
        } elseif (!is_numeric($qty) || $qty <= 0) {
            $message = t('Enter a valid quantity.', 'একটি সঠিক পরিমাণ দিন।');
            $messageType = 'error';
        } elseif ($qty < $row['Minimum_Order_Quantity']) {
            $message = t('Quantity is below the minimum order.', 'পরিমাণ সর্বনিম্ন অর্ডারের চেয়ে কম।');
            $messageType = 'error';
        } elseif ($qty > $row['Available_Quantity']) {
            $message = t(
                'Only ' . number_format($row['Available_Quantity'], 2) . ' kg available.',
                'মাত্র ' . number_format($row['Available_Quantity'], 2) . ' কেজি উপলব্ধ।'
            );
            $messageType = 'error';
        } else {
            $pdo->prepare('UPDATE cart SET Quantity = ? WHERE Cart_ID = ?')->execute([(float) $qty, $cartId]);
            $message = t('Cart updated.', 'কার্ট আপডেট করা হয়েছে।');
            $messageType = 'success';
        }
    }

    if ($action === 'remove') {
        $cartId = (int) ($_POST['cart_id'] ?? 0);
        $pdo->prepare('DELETE FROM cart WHERE Cart_ID = ? AND Buyer_ID = ?')->execute([$cartId, $buyerId]);
        $message = t('Item removed from cart.', 'কার্ট থেকে আইটেম সরানো হয়েছে।');
        $messageType = 'success';
    }

    if ($action === 'checkout') {
        $stmt = $pdo->prepare(
            'SELECT c.Cart_ID, c.Product_ID, c.Quantity, p.Crop_Name, p.Price_Per_KG, u.District AS Farmer_District
             FROM cart c
             JOIN product p ON p.Product_ID = c.Product_ID
             JOIN users u ON u.User_ID = p.Farmer_ID
             WHERE c.Buyer_ID = ?
             ORDER BY c.Cart_ID ASC'
        );
        $stmt->execute([$buyerId]);
        $items = $stmt->fetchAll();

        if (empty($items)) {
            $message = t('Your cart is empty.', 'আপনার কার্ট খালি।');
            $messageType = 'error';
        } else {
            foreach ($items as $item) {
                $result = kd_checkout_cart_item($pdo, $buyerId, $item);
                $checkoutResults[] = [
                    'crop' => $item['Crop_Name'],
                    'success' => $result['success'],
                    'message' => $result['message'],
                ];
            }
            $anySuccess = count(array_filter($checkoutResults, fn($r) => $r['success'])) > 0;
            $message = $anySuccess
                ? t('Checkout complete. See details below.', 'চেকআউট সম্পন্ন হয়েছে। নিচে বিস্তারিত দেখুন।')
                : t('Checkout failed. See details below.', 'চেকআউট ব্যর্থ হয়েছে। নিচে বিস্তারিত দেখুন।');
            $messageType = $anySuccess ? 'success' : 'error';
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT c.Cart_ID, c.Quantity, p.Product_ID, p.Crop_Name, p.Price_Per_KG,
            p.Available_Quantity, p.Minimum_Order_Quantity, u.Name AS Farmer_Name
     FROM cart c
     JOIN product p ON p.Product_ID = c.Product_ID
     JOIN users u ON u.User_ID = p.Farmer_ID
     WHERE c.Buyer_ID = ?
     ORDER BY c.Cart_ID DESC'
);
$stmt->execute([$buyerId]);
$cartItems = $stmt->fetchAll();

$cartTotal = 0.0;
foreach ($cartItems as $ci) {
    $cartTotal += (float) $ci['Quantity'] * (float) $ci['Price_Per_KG'];
}
?>
<!DOCTYPE html>
<html lang="<?= $LANG === 'bn' ? 'bn' : 'en' ?>">
<head>
<meta charset="UTF-8">
<title><?= t('My Cart', 'আমার কার্ট') ?> - KrishiDirect</title>
<link rel="stylesheet" href="style.css">
<script src="voice.js"></script>
</head>
<body>

<div class="navbar">
  <strong><a href="index.php">KrishiDirect</a></strong>
  <div>
    <a href="index.php"><?= t('Dashboard', 'ড্যাশবোর্ড') ?></a>
    <a href="products.php"><?= t('Products', 'পণ্য') ?></a>
    <a href="cart.php"><?= t('Cart', 'কার্ট') ?> (<?= count($cartItems) ?>)</a>
    <a href="my_orders.php"><?= t('My Orders', 'আমার অর্ডার') ?></a>
    <a href="logout.php"><?= t('Logout', 'লগআউট') ?></a>
    <?= lang_toggle_html() ?>
    <?= nav_speak_button() ?>
    <?= kd_notif_bell_html() ?>
  </div>
</div>

<div class="container wide">
  <h1><?= t('My Cart', 'আমার কার্ট') ?></h1>

  <?php if ($message): ?>
    <div class="<?= $messageType === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if (!empty($checkoutResults)): ?>
    <table style="margin-bottom:20px;">
      <tr>
        <th><?= t('Item', 'আইটেম') ?></th>
        <th><?= t('Result', 'ফলাফল') ?></th>
      </tr>
      <?php foreach ($checkoutResults as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['crop']) ?></td>
          <td style="color:<?= $r['success'] ? 'green' : '#b00020' ?>;"><?= htmlspecialchars($r['message']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <p><a href="my_orders.php"><?= t('Go to My Orders', 'আমার অর্ডারে যান') ?></a></p>
  <?php endif; ?>

  <table>
    <tr>
      <th><?= t('Crop', 'ফসল') ?></th>
      <th><?= t('Farmer', 'কৃষক') ?></th>
      <th><?= t('Price/kg', 'মূল্য/কেজি') ?></th>
      <th><?= t('Quantity (kg)', 'পরিমাণ (কেজি)') ?></th>
      <th><?= t('Subtotal', 'উপমোট') ?></th>
      <th></th>
    </tr>
    <?php foreach ($cartItems as $item): ?>
      <?php $subtotal = (float) $item['Quantity'] * (float) $item['Price_Per_KG']; ?>
      <tr>
        <td><?= htmlspecialchars($item['Crop_Name']) ?></td>
        <td><?= htmlspecialchars($item['Farmer_Name']) ?></td>
        <td>৳<?= number_format($item['Price_Per_KG'], 2) ?></td>
        <td>
          <form method="POST" action="cart.php" style="display:flex;gap:6px;margin:0;">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="cart_id" value="<?= (int) $item['Cart_ID'] ?>">
            <input type="number" step="0.01" name="quantity" value="<?= htmlspecialchars($item['Quantity']) ?>"
                   min="<?= (float) $item['Minimum_Order_Quantity'] ?>"
                   max="<?= (float) $item['Available_Quantity'] ?>" style="width:100px;">
            <button type="submit" style="margin:0;padding:6px 10px;width:auto;background:#6c757d;"><?= t('Update', 'আপডেট') ?></button>
          </form>
        </td>
        <td>৳<?= number_format($subtotal, 2) ?></td>
        <td>
          <form method="POST" action="cart.php" style="margin:0;">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="cart_id" value="<?= (int) $item['Cart_ID'] ?>">
            <button type="submit" style="margin:0;padding:6px 14px;width:auto;background:#b00020;"><?= t('Remove', 'সরান') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($cartItems)): ?>
      <tr><td colspan="6"><?= t('Your cart is empty.', 'আপনার কার্ট খালি।') ?> <a href="products.php"><?= t('Browse products', 'পণ্য দেখুন') ?></a></td></tr>
    <?php endif; ?>
  </table>

  <?php if (!empty($cartItems)): ?>
    <div style="margin-top:16px;display:flex;justify-content:space-between;align-items:center;">
      <h2 style="margin:0;"><?= t('Total', 'মোট') ?>: ৳<?= number_format($cartTotal, 2) ?></h2>
      <form method="POST" action="cart.php" style="margin:0;">
        <input type="hidden" name="action" value="checkout">
        <button type="submit" style="margin:0;padding:10px 24px;width:auto;"
          onclick="return confirm(<?= json_encode(t('Place an order for every item in your cart?', 'কার্টের প্রতিটি আইটেমের জন্য অর্ডার দেবেন?')) ?>);">
          <?= t('Checkout', 'চেকআউট') ?>
        </button>
      </form>
    </div>
    <p style="color:#666;font-size:0.9em;margin-top:8px;">
      <?= t(
        'Checkout places one order per item and reserves the stock immediately. Pay each order from My Orders to confirm it.',
        'চেকআউট প্রতিটি আইটেমের জন্য একটি অর্ডার তৈরি করে এবং সাথে সাথে মজুদ সংরক্ষণ করে। প্রতিটি অর্ডার নিশ্চিত করতে আমার অর্ডার থেকে পেমেন্ট করুন।'
      ) ?>
    </p>
  <?php endif; ?>
</div>

</body>
</html>
