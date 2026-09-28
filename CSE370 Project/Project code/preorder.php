<?php
require_once 'config.php';
require_once 'order_helpers.php';
require_role('Buyer');

$buyerId = (int) $_SESSION['user_id'];
$productId = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$errors = [];
$success = false;

$stmt = $pdo->prepare(
    'SELECT p.Product_ID, p.Crop_Name, p.Available_Quantity, p.Minimum_Order_Quantity, p.Price_Per_KG,
            u.User_ID AS Farmer_ID, u.Name AS Farmer_Name
     FROM product p JOIN users u ON u.User_ID = p.Farmer_ID
     WHERE p.Product_ID = ?'
);
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    $errors[] = t('Product not found.', 'পণ্য খুঁজে পাওয়া যায়নি।');
} elseif ($product['Available_Quantity'] > 0) {
    // Stock is available -- this product doesn't need a preorder.
    header('Location: order.php?product_id=' . $productId);
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
    }

    if (empty($errors)) {
        $dup = $pdo->prepare(
            "SELECT Preorder_ID FROM preorder WHERE Buyer_ID = ? AND Product_ID = ? AND Status = 'Waiting'"
        );
        $dup->execute([$buyerId, $productId]);

        if ($dup->fetch()) {
            $errors[] = t(
                'You already have a preorder waiting for this product.',
                'এই পণ্যের জন্য আপনার ইতিমধ্যে একটি অপেক্ষমাণ প্রি-অর্ডার আছে।'
            );
        } else {
            $pdo->prepare(
                "INSERT INTO preorder (Buyer_ID, Product_ID, Quantity, Status) VALUES (?, ?, ?, 'Waiting')"
            )->execute([$buyerId, $productId, (float) $qty]);

            $buyerName = (string) $_SESSION['name'];
            kd_notify(
                $pdo,
                (int) $product['Farmer_ID'],
                'new_preorder',
                'New preorder: ' . number_format((float) $qty, 2) . ' kg of ' . $product['Crop_Name'] . ' requested by ' . $buyerName . '.',
                'নতুন প্রি-অর্ডার: ' . $buyerName . ' ' . number_format((float) $qty, 2) . ' কেজি ' . $product['Crop_Name'] . ' অনুরোধ করেছেন।',
                'my_products.php',
                (int) $product['Product_ID']
            );

            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $LANG === 'bn' ? 'bn' : 'en' ?>">
<head>
<meta charset="UTF-8">
<title><?= t('Preorder', 'প্রি-অর্ডার') ?> - KrishiDirect</title>
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
  <h1><?= t('Preorder', 'প্রি-অর্ডার') ?></h1>

  <?php foreach ($errors as $e): ?><div class="error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

  <?php if ($success): ?>
    <div class="success">
      <?= t(
        'Your preorder has been recorded. We will let you know when this product is back in stock.',
        'আপনার প্রি-অর্ডার রেকর্ড করা হয়েছে। এই পণ্যটি আবার মজুদ হলে আপনাকে জানানো হবে।'
      ) ?>
      <br><br>
      <a href="my_orders.php"><?= t('View your preorders', 'আপনার প্রি-অর্ডার দেখুন') ?></a>
      &nbsp;|&nbsp;
      <a href="products.php"><?= t('Back to Products', 'পণ্যে ফিরে যান') ?></a>
    </div>
  <?php elseif ($product): ?>
    <p><strong><?= htmlspecialchars($product['Crop_Name']) ?></strong> <?= t('from', 'থেকে') ?> <?= htmlspecialchars($product['Farmer_Name']) ?></p>
    <p class="error" style="display:inline-block;">
      <?= t('Currently out of stock.', 'বর্তমানে মজুদ নেই।') ?>
    </p>
    <p>
      <?= t('Price', 'মূল্য') ?>: ৳<?= number_format($product['Price_Per_KG'], 2) ?> / kg &nbsp;|&nbsp;
      <?= t('Min order', 'সর্বনিম্ন অর্ডার') ?>: <?= number_format($product['Minimum_Order_Quantity'], 2) ?> kg
    </p>
    <p style="color:#666;">
      <?= t(
        'This product is sold out. Register a preorder and it will be recorded against your account for when the farmer restocks.',
        'এই পণ্যটি শেষ হয়ে গেছে। একটি প্রি-অর্ডার নিবন্ধন করুন, কৃষক পুনরায় মজুদ করলে তা আপনার অ্যাকাউন্টের বিপরীতে রেকর্ড করা হবে।'
      ) ?>
    </p>

    <form method="POST" action="preorder.php">
      <input type="hidden" name="product_id" value="<?= (int) $product['Product_ID'] ?>">
      <label><?= t('Quantity (kg)', 'পরিমাণ (কেজি)') ?></label>
      <input type="number" step="0.01" name="quantity"
             min="<?= (float) $product['Minimum_Order_Quantity'] ?>"
             value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>" required>
      <button type="submit"><?= t('Place Preorder', 'প্রি-অর্ডার দিন') ?></button>
    </form>
  <?php endif; ?>
</div>

</body>
</html>
