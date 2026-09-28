<?php
require_once 'config.php';
require_login();

$role = $_SESSION['role'];

// Join product with the farmer's name/district for display.
// Sold-out items are now shown too (so buyers can preorder them),
// listed after everything that's still in stock.
$stmt = $pdo->query(
    "SELECT p.Product_ID, p.Crop_Name, p.Available_Quantity, p.Minimum_Order_Quantity,
            p.Price_Per_KG, p.Harvest_Date, p.Expiry_Date,
            u.Name AS Farmer_Name, u.District AS Farmer_District
     FROM product p
     JOIN users u ON u.User_ID = p.Farmer_ID
     ORDER BY (p.Available_Quantity = 0) ASC, p.Expiry_Date ASC"
);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= $LANG === 'bn' ? 'bn' : 'en' ?>">
<head>
<meta charset="UTF-8">
<title><?= t('Browse Products', 'পণ্য দেখুন') ?> - KrishiDirect</title>
<link rel="stylesheet" href="style.css">
<script src="voice.js"></script>
</head>
<body>

<div class="navbar">
  <strong><a href="index.php">KrishiDirect</a></strong>
  <div>
    <a href="index.php"><?= t('Dashboard', 'ড্যাশবোর্ড') ?></a>
    <a href="products.php"><?= t('Products', 'পণ্য') ?></a>
    <?php if ($role === 'Buyer'): ?><a href="cart.php"><?= t('Cart', 'কার্ট') ?></a><?php endif; ?>
    <?php if ($role === 'Buyer'): ?><a href="my_orders.php"><?= t('My Orders', 'আমার অর্ডার') ?></a><?php endif; ?>
    <a href="logout.php"><?= t('Logout', 'লগআউট') ?></a>
    <?= lang_toggle_html() ?>
    <?= nav_speak_button() ?>
    <?= kd_notif_bell_html() ?>
  </div>
</div>

<div class="container wide">
  <h1><?= t('Available Products', 'উপলব্ধ পণ্য') ?></h1>

  <table>
    <tr>
      <th><?= t('Crop', 'ফসল') ?></th>
      <th><?= t('Farmer', 'কৃষক') ?></th>
      <th><?= t('District', 'জেলা') ?></th>
      <th><?= t('Available (kg)', 'উপলব্ধ (কেজি)') ?></th>
      <th><?= t('Min Order (kg)', 'সর্বনিম্ন অর্ডার (কেজি)') ?></th>
      <th><?= t('Price/kg', 'মূল্য/কেজি') ?></th>
      <th><?= t('Expiry', 'মেয়াদ উত্তীর্ণ') ?></th>
      <?php if ($role === 'Buyer'): ?><th></th><?php endif; ?>
    </tr>
    <?php foreach ($products as $p): ?>
      <?php $inStock = $p['Available_Quantity'] > 0; ?>
      <tr<?= $inStock ? '' : ' style="opacity:0.7;"' ?>>
        <td><?= htmlspecialchars($p['Crop_Name']) ?></td>
        <td><?= htmlspecialchars($p['Farmer_Name']) ?></td>
        <td><?= htmlspecialchars($p['Farmer_District']) ?></td>
        <td>
          <?php if ($inStock): ?>
            <?= number_format($p['Available_Quantity'], 2) ?>
          <?php else: ?>
            <span style="color:#b00020;font-weight:bold;"><?= t('Out of stock', 'মজুদ নেই') ?></span>
          <?php endif; ?>
        </td>
        <td><?= number_format($p['Minimum_Order_Quantity'], 2) ?></td>
        <td>৳<?= number_format($p['Price_Per_KG'], 2) ?></td>
        <td><?= htmlspecialchars($p['Expiry_Date']) ?></td>
        <?php if ($role === 'Buyer'): ?>
          <td>
            <?php if ($inStock): ?>
              <div style="display:flex;gap:6px;">
                <a class="btn" style="margin:0;padding:6px 14px;" href="order.php?product_id=<?= (int) $p['Product_ID'] ?>"><?= t('Order', 'অর্ডার') ?></a>
                <form method="POST" action="cart.php" style="margin:0;">
                  <input type="hidden" name="action" value="add">
                  <input type="hidden" name="product_id" value="<?= (int) $p['Product_ID'] ?>">
                  <input type="hidden" name="quantity" value="<?= (float) $p['Minimum_Order_Quantity'] ?>">
                  <button type="submit" style="margin:0;padding:6px 14px;width:auto;background:#6c757d;"><?= t('Add to Cart', 'কার্টে যোগ করুন') ?></button>
                </form>
              </div>
            <?php else: ?>
              <a class="btn" style="margin:0;padding:6px 14px;background:#b00020;" href="preorder.php?product_id=<?= (int) $p['Product_ID'] ?>"><?= t('Preorder', 'প্রি-অর্ডার') ?></a>
            <?php endif; ?>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($products)): ?>
      <tr><td colspan="8"><?= t('No products available right now.', 'এখন কোনো পণ্য উপলব্ধ নেই।') ?></td></tr>
    <?php endif; ?>
  </table>
</div>

</body>
</html>
