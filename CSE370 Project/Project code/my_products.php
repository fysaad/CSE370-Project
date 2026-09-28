<?php
require_once 'config.php';
require_once 'order_helpers.php';
require_role('Farmer');

$farmerId = (int) $_SESSION['user_id'];
$errors = [];
$success = false;
$relistNotice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? 'add_product') === 'add_product') {
    $crop      = trim($_POST['crop_name'] ?? '');
    $total     = $_POST['total_quantity'] ?? '';
    $minOrder  = $_POST['min_order'] ?? '';
    $price     = $_POST['price'] ?? '';
    $harvest   = $_POST['harvest_date'] ?? '';
    $expiry    = $_POST['expiry_date'] ?? '';

    if ($crop === '') $errors[] = t('Crop name is required.', 'ফসলের নাম আবশ্যক।');
    if (!is_numeric($total) || $total <= 0) $errors[] = t('Total quantity must be a positive number.', 'মোট পরিমাণ অবশ্যই একটি ধনাত্মক সংখ্যা হতে হবে।');
    if (!is_numeric($minOrder) || $minOrder <= 0) $errors[] = t('Minimum order quantity must be a positive number.', 'সর্বনিম্ন অর্ডারের পরিমাণ অবশ্যই একটি ধনাত্মক সংখ্যা হতে হবে।');
    if (!is_numeric($price) || $price <= 0) $errors[] = t('Price per kg must be a positive number.', 'প্রতি কেজি মূল্য অবশ্যই একটি ধনাত্মক সংখ্যা হতে হবে।');
    if (!strtotime($harvest)) $errors[] = t('Valid harvest date required.', 'সঠিক ফসল কাটার তারিখ আবশ্যক।');
    if (!strtotime($expiry)) $errors[] = t('Valid expiry date required.', 'সঠিক মেয়াদ উত্তীর্ণের তারিখ আবশ্যক।');
    if (empty($errors) && strtotime($expiry) <= strtotime($harvest)) $errors[] = t('Expiry date must be after harvest date.', 'মেয়াদ উত্তীর্ণের তারিখ অবশ্যই ফসল কাটার তারিখের পরে হতে হবে।');
    if (empty($errors) && $minOrder > $total) $errors[] = t('Minimum order quantity cannot exceed total quantity.', 'সর্বনিম্ন অর্ডারের পরিমাণ মোট পরিমাণের চেয়ে বেশি হতে পারবে না।');

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO product
                (Farmer_ID, Crop_Name, Total_Quantity, Available_Quantity, Minimum_Order_Quantity, Price_Per_KG, Harvest_Date, Expiry_Date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $farmerId, $crop,
            (float) $total, (float) $total, // Available_Quantity starts equal to Total_Quantity
            (float) $minOrder, (float) $price,
            $harvest, $expiry
        ]);
        $newProductId = (int) $pdo->lastInsertId();

        // If any buyer had a "Waiting" preorder against an earlier,
        // now-sold-out listing of this same crop from this same farmer,
        // let them know it's back and point them at the new listing.
        kd_notify_preorder_buyers_on_relist($pdo, $farmerId, $crop, $newProductId);

        $success = true;
    }
}

// ---------------------------------------------------------------
// Restock an existing listing. This is how a sold-out product goes
// back to being "available" -- bumping Available_Quantity above 0
// is all products.php needs to move it out of the out-of-stock
// section and show Order/Add to Cart again instead of Preorder.
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restock') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $addQty    = $_POST['add_quantity'] ?? '';

    if (!is_numeric($addQty) || $addQty <= 0) {
        $errors[] = t('Restock quantity must be a positive number.', 'পুনরায় মজুদের পরিমাণ অবশ্যই একটি ধনাত্মক সংখ্যা হতে হবে।');
    } else {
        // Make sure this product actually belongs to the logged-in farmer
        $check = $pdo->prepare(
            'SELECT Product_ID, Crop_Name, Available_Quantity FROM product WHERE Product_ID = ? AND Farmer_ID = ?'
        );
        $check->execute([$productId, $farmerId]);
        $product = $check->fetch();

        if (!$product) {
            $errors[] = t('Product not found.', 'পণ্য পাওয়া যায়নি।');
        } else {
            $wasOutOfStock = ((float) $product['Available_Quantity']) <= 0;

            $update = $pdo->prepare(
                'UPDATE product
                 SET Available_Quantity = Available_Quantity + ?, Total_Quantity = Total_Quantity + ?
                 WHERE Product_ID = ?'
            );
            $update->execute([(float) $addQty, (float) $addQty, $productId]);

            if ($wasOutOfStock) {
                // Just came back in stock -- let buyers who preordered know
                kd_notify_preorder_buyers_on_relist($pdo, $farmerId, $product['Crop_Name'], $productId);
                $relistNotice = t(
                    'Product restocked and is available again. Buyers who preordered it have been notified.',
                    'পণ্য পুনরায় মজুদ করা হয়েছে এবং আবার উপলব্ধ। যেসব ক্রেতা প্রি-অর্ডার করেছিলেন তাদের জানানো হয়েছে।'
                );
            }

            $success = true;
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT Product_ID, Crop_Name, Total_Quantity, Available_Quantity, Minimum_Order_Quantity, Price_Per_KG, Harvest_Date, Expiry_Date
     FROM product WHERE Farmer_ID = ? ORDER BY Harvest_Date DESC'
);
$stmt->execute([$farmerId]);
$myProducts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= $LANG === 'bn' ? 'bn' : 'en' ?>">
<head>
<meta charset="UTF-8">
<title><?= t('My Products', 'আমার পণ্য') ?> - KrishiDirect</title>
<link rel="stylesheet" href="style.css">
<script src="voice.js"></script>
</head>
<body>

<div class="navbar">
  <strong><a href="index.php">KrishiDirect</a></strong>
  <div>
    <a href="index.php"><?= t('Dashboard', 'ড্যাশবোর্ড') ?></a>
    <a href="my_products.php"><?= t('My Products', 'আমার পণ্য') ?></a>
    <a href="logout.php"><?= t('Logout', 'লগআউট') ?></a>
    <?= lang_toggle_html() ?>
    <?= nav_speak_button() ?>
    <?= kd_notif_bell_html() ?>
  </div>
</div>

<div class="container wide">
  <h1><?= t('My Products', 'আমার পণ্য') ?></h1>

  <?php if ($success && $relistNotice === ''): ?>
    <div class="success"><?= t('Product listed successfully.', 'পণ্য সফলভাবে তালিকাভুক্ত হয়েছে।') ?></div>
  <?php elseif ($success && $relistNotice !== ''): ?>
    <div class="success"><?= htmlspecialchars($relistNotice) ?></div>
  <?php endif; ?>
  <?php foreach ($errors as $e): ?><div class="error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

  <h2><?= t('Add New Product', 'নতুন পণ্য যোগ করুন') ?></h2>
  <form method="POST" action="my_products.php">
    <input type="hidden" name="action" value="add_product">
    <label><?= t('Crop Name', 'ফসলের নাম') ?></label>
    <input type="text" name="crop_name" value="<?= htmlspecialchars($_POST['crop_name'] ?? '') ?>" required>

    <label><?= t('Total Quantity (kg)', 'মোট পরিমাণ (কেজি)') ?></label>
    <input type="number" step="0.01" name="total_quantity" value="<?= htmlspecialchars($_POST['total_quantity'] ?? '') ?>" required>

    <label><?= t('Minimum Order Quantity (kg)', 'সর্বনিম্ন অর্ডারের পরিমাণ (কেজি)') ?></label>
    <input type="number" step="0.01" name="min_order" value="<?= htmlspecialchars($_POST['min_order'] ?? '') ?>" required>

    <label><?= t('Price per kg (৳)', 'প্রতি কেজি মূল্য (৳)') ?></label>
    <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>

    <label><?= t('Harvest Date', 'ফসল কাটার তারিখ') ?></label>
    <input type="date" name="harvest_date" value="<?= htmlspecialchars($_POST['harvest_date'] ?? '') ?>" required>

    <label><?= t('Expiry Date', 'মেয়াদ উত্তীর্ণের তারিখ') ?></label>
    <input type="date" name="expiry_date" value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>" required>

    <button type="submit"><?= t('Add Product', 'পণ্য যোগ করুন') ?></button>
  </form>

  <h2><?= t('My Listings', 'আমার তালিকা') ?></h2>
  <table>
    <tr>
      <th><?= t('Crop', 'ফসল') ?></th>
      <th><?= t('Total (kg)', 'মোট (কেজি)') ?></th>
      <th><?= t('Available (kg)', 'উপলব্ধ (কেজি)') ?></th>
      <th><?= t('Min Order', 'সর্বনিম্ন অর্ডার') ?></th>
      <th><?= t('Price/kg', 'মূল্য/কেজি') ?></th>
      <th><?= t('Harvest', 'ফসল কাটার তারিখ') ?></th>
      <th><?= t('Expiry', 'মেয়াদ উত্তীর্ণ') ?></th>
      <th><?= t('Restock', 'পুনরায় মজুদ') ?></th>
    </tr>
    <?php foreach ($myProducts as $p): ?>
      <?php $isOutOfStock = ((float) $p['Available_Quantity']) <= 0; ?>
      <tr<?= $isOutOfStock ? ' style="background:#fdf0f0;"' : '' ?>>
        <td><?= htmlspecialchars($p['Crop_Name']) ?></td>
        <td><?= number_format($p['Total_Quantity'], 2) ?></td>
        <td>
          <?php if ($isOutOfStock): ?>
            <span style="color:#b00020;font-weight:bold;"><?= t('Out of stock', 'মজুদ নেই') ?></span>
          <?php else: ?>
            <?= number_format($p['Available_Quantity'], 2) ?>
          <?php endif; ?>
        </td>
        <td><?= number_format($p['Minimum_Order_Quantity'], 2) ?></td>
        <td>৳<?= number_format($p['Price_Per_KG'], 2) ?></td>
        <td><?= htmlspecialchars($p['Harvest_Date']) ?></td>
        <td><?= htmlspecialchars($p['Expiry_Date']) ?></td>
        <td>
          <form method="POST" action="my_products.php" style="display:flex;gap:6px;margin:0;">
            <input type="hidden" name="action" value="restock">
            <input type="hidden" name="product_id" value="<?= (int) $p['Product_ID'] ?>">
            <input type="number" step="0.01" min="0.01" name="add_quantity" placeholder="<?= htmlspecialchars(t('kg', 'কেজি')) ?>" required style="margin:0;width:90px;">
            <button type="submit" style="margin:0;padding:8px 12px;width:auto;<?= $isOutOfStock ? 'background:#b00020;' : 'background:#6c757d;' ?>">
              <?= $isOutOfStock ? t('Relist', 'পুনঃতালিকা') : t('Add Stock', 'মজুদ যোগ') ?>
            </button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($myProducts)): ?>
      <tr><td colspan="8"><?= t("You haven't listed any products yet.", 'আপনি এখনো কোনো পণ্য তালিকাভুক্ত করেননি।') ?></td></tr>
    <?php endif; ?>
  </table>
</div>

</body>
</html>