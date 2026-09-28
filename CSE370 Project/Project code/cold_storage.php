<?php
require_once 'config.php';
require_login();

$role = $_SESSION['role'];
$userId = (int) $_SESSION['user_id'];
$errors = [];
$success = false;
$successMessage = '';

if ($role === 'Farmer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'book';

    if ($action === 'pickup') {
        // ---- Farmer picks up their product, vacating storage space ----
        $bookingId = (int) ($_POST['booking_id'] ?? 0);

        $stmt = $pdo->prepare(
            'SELECT Booking_ID, Storage_ID, Farmer_ID, Sacks_To_Store, Cubic_Meters_To_Occupy, Status
             FROM storage_booking WHERE Booking_ID = ?'
        );
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        if (!$booking || (int) $booking['Farmer_ID'] !== $userId) {
            $errors[] = t('Booking not found.', 'বুকিং পাওয়া যায়নি।');
        } elseif ($booking['Status'] !== 'Active') {
            $errors[] = t('Only active bookings can be picked up.', 'শুধুমাত্র সক্রিয় বুকিং সংগ্রহ করা যাবে।');
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE storage_booking SET Status = 'Checked-Out' WHERE Booking_ID = ?")
                    ->execute([$bookingId]);
                // GREATEST(0, ...) guards against the count ever going negative
                $pdo->prepare(
                    "UPDATE cold_storage
                     SET Current_Sack_Count = GREATEST(0, Current_Sack_Count - ?),
                         Current_Cubic_Meter_Count = GREATEST(0, Current_Cubic_Meter_Count - ?)
                     WHERE Storage_ID = ?"
                )->execute([$booking['Sacks_To_Store'], $booking['Cubic_Meters_To_Occupy'], $booking['Storage_ID']]);
                $pdo->commit();
                $success = true;
                $successMessage = t(
                    'Product picked up. That space is now vacant for other farmers to book.',
                    'পণ্য সংগ্রহ করা হয়েছে। এই জায়গা এখন অন্য কৃষকদের বুকিংয়ের জন্য খালি।'
                );
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = t('Pickup failed: ', 'সংগ্রহ ব্যর্থ হয়েছে: ') . $e->getMessage();
            }
        }

    } else {
        // ---- Farmer submits a new booking request ----
        $storageId = (int) ($_POST['storage_id'] ?? 0);
        $sacks     = $_POST['sacks'] ?? '';
        $cubicM    = $_POST['cubic_m'] ?? '';
        $date      = $_POST['booking_date'] ?? '';

        if (!is_numeric($sacks) || $sacks <= 0) $errors[] = t('Enter a valid number of sacks.', 'সঠিক সংখ্যক বস্তা লিখুন।');
        if (!is_numeric($cubicM) || $cubicM <= 0) $errors[] = t('Enter a valid cubic meter amount.', 'সঠিক ঘনমিটার পরিমাণ লিখুন।');
        if (!strtotime($date)) $errors[] = t('Valid booking date required.', 'সঠিক বুকিং তারিখ প্রয়োজন।');

        if (empty($errors)) {
            $chk = $pdo->prepare(
                'SELECT Max_Sack_Capacity, Current_Sack_Count, Max_Cubic_Meter_Capacity, Current_Cubic_Meter_Count
                 FROM cold_storage WHERE Storage_ID = ?'
            );
            $chk->execute([$storageId]);
            $facility = $chk->fetch();

            if (!$facility) {
                $errors[] = t('Facility not found.', 'সুবিধা পাওয়া যায়নি।');
            } elseif (($facility['Current_Sack_Count'] + $sacks) > $facility['Max_Sack_Capacity']) {
                $errors[] = t(
                    'This cold storage is full (not enough sack capacity remaining).',
                    'এই কোল্ড স্টোরেজ পূর্ণ (পর্যাপ্ত বস্তার জায়গা অবশিষ্ট নেই)।'
                );
            } elseif (($facility['Current_Cubic_Meter_Count'] + $cubicM) > $facility['Max_Cubic_Meter_Capacity']) {
                $errors[] = t(
                    'This cold storage is full (not enough cubic meter capacity remaining).',
                    'এই কোল্ড স্টোরেজ পূর্ণ (পর্যাপ্ত ঘনমিটার জায়গা অবশিষ্ট নেই)।'
                );
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'INSERT INTO storage_booking (Storage_ID, Farmer_ID, Sacks_To_Store, Cubic_Meters_To_Occupy, Booking_Date, Status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$storageId, $userId, (int) $sacks, (int) $cubicM, $date, 'Pending']);
            $newBookingId = (int) $pdo->lastInsertId();

            // Notify the facility's manager that a new booking needs review
            $mgrStmt = $pdo->prepare('SELECT Manager_ID, Facility_Name FROM cold_storage WHERE Storage_ID = ?');
            $mgrStmt->execute([$storageId]);
            $facilityRow = $mgrStmt->fetch();
            if ($facilityRow) {
                kd_notify(
                    $pdo,
                    (int) $facilityRow['Manager_ID'],
                    'storage_booking',
                    $_SESSION['name'] . ' requested ' . $sacks . ' sacks at ' . $facilityRow['Facility_Name'] . '.',
                    $_SESSION['name'] . ' ' . $facilityRow['Facility_Name'] . '-এ ' . $sacks . ' বস্তার জন্য বুকিং অনুরোধ করেছেন।',
                    'manager_dashboard.php',
                    $newBookingId
                );
            }

            $success = true;
            $successMessage = t(
                'Booking request submitted. Awaiting manager approval.',
                'বুকিং অনুরোধ জমা দেওয়া হয়েছে। ম্যানেজারের অনুমোদনের অপেক্ষায়।'
            );
        }
    }
}

$facilities = $pdo->query(
    "SELECT cs.Storage_ID, cs.Facility_Name, cs.District, cs.Upazila,
            cs.Max_Sack_Capacity, cs.Current_Sack_Count,
            cs.Max_Cubic_Meter_Capacity, cs.Current_Cubic_Meter_Count,
            u.Name AS Manager_Name
     FROM cold_storage cs JOIN users u ON u.User_ID = cs.Manager_ID
     ORDER BY cs.District"
)->fetchAll();

$myBookings = [];
if ($role === 'Farmer') {
    $stmt = $pdo->prepare(
        "SELECT sb.Booking_ID, sb.Sacks_To_Store, sb.Cubic_Meters_To_Occupy, sb.Booking_Date, sb.Status,
                cs.Facility_Name
         FROM storage_booking sb JOIN cold_storage cs ON cs.Storage_ID = sb.Storage_ID
         WHERE sb.Farmer_ID = ? ORDER BY sb.Booking_Date DESC"
    );
    $stmt->execute([$userId]);
    $myBookings = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($LANG) ?>">
<head>
<meta charset="UTF-8">
<title><?= t('Cold Storage - KrishiDirect', 'কোল্ড স্টোরেজ - কৃষিডিরেক্ট') ?></title>
<link rel="stylesheet" href="style.css">
<script src="voice.js"></script>
</head>
<body>

<div class="navbar">
  <strong><a href="index.php">KrishiDirect</a></strong>
  <div>
    <a href="index.php"><?= t('Dashboard', 'ড্যাশবোর্ড') ?></a>
    <a href="cold_storage.php"><?= t('Cold Storage', 'কোল্ড স্টোরেজ') ?></a>
    <a href="logout.php"><?= t('Logout', 'লগআউট') ?></a>
    <?= lang_toggle_html() ?>
    <?= nav_speak_button() ?>
    <?= kd_notif_bell_html() ?>
  </div>
</div>

<div class="container wide">
  <h1><?= t('Cold Storage Facilities', 'কোল্ড স্টোরেজ সুবিধা') ?></h1>

  <?php if ($success): ?><div class="success"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
  <?php foreach ($errors as $e): ?><div class="error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

  <table>
    <tr>
      <th><?= t('Facility', 'সুবিধা') ?></th>
      <th><?= t('District', 'জেলা') ?></th>
      <th><?= t('Upazila', 'উপজেলা') ?></th>
      <th><?= t('Manager', 'ম্যানেজার') ?></th>
      <th><?= t('Sack Capacity', 'বস্তার ধারণক্ষমতা') ?></th>
      <th><?= t('Cubic Capacity', 'ঘনমিটার ধারণক্ষমতা') ?></th>
      <?php if ($role === 'Farmer'): ?><th></th><?php endif; ?>
    </tr>
    <?php foreach ($facilities as $f): ?>
      <?php
        $sackFull  = (int) $f['Current_Sack_Count']  >= (int) $f['Max_Sack_Capacity'];
        $cubicFull = (int) $f['Current_Cubic_Meter_Count'] >= (int) $f['Max_Cubic_Meter_Capacity'];
        $isFull    = $sackFull || $cubicFull;
      ?>
      <tr>
        <td><?= htmlspecialchars($f['Facility_Name']) ?></td>
        <td><?= htmlspecialchars($f['District']) ?></td>
        <td><?= htmlspecialchars($f['Upazila']) ?></td>
        <td><?= htmlspecialchars($f['Manager_Name']) ?></td>
        <td><?= (int) $f['Current_Sack_Count'] ?> / <?= (int) $f['Max_Sack_Capacity'] ?></td>
        <td><?= (int) $f['Current_Cubic_Meter_Count'] ?> / <?= (int) $f['Max_Cubic_Meter_Capacity'] ?> m³</td>
        <?php if ($role === 'Farmer'): ?>
          <td>
            <?php if ($isFull): ?>
              <span class="error" style="display:inline-block;padding:6px 10px;"><?= t('Full', 'পূর্ণ') ?></span>
            <?php else: ?>
              <button type="button" class="btn" style="margin:0;padding:6px 14px;"
                onclick="document.getElementById('bookForm').style.display='block';
                         document.getElementById('storageSelect').value='<?= (int) $f['Storage_ID'] ?>';">
                <?= t('Book', 'বুক করুন') ?>
              </button>
            <?php endif; ?>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </table>

  <?php if ($role === 'Farmer'): ?>
    <div id="bookForm" style="display:none; margin-top:24px;">
      <h2><?= t('Book Storage Space', 'স্টোরেজ স্পেস বুক করুন') ?></h2>
      <form method="POST" action="cold_storage.php">
        <input type="hidden" name="action" value="book">

        <label><?= t('Facility', 'সুবিধা') ?></label>
        <select name="storage_id" id="storageSelect" required>
          <?php foreach ($facilities as $f): ?>
            <option value="<?= (int) $f['Storage_ID'] ?>"><?= htmlspecialchars($f['Facility_Name']) ?> (<?= htmlspecialchars($f['District']) ?>)</option>
          <?php endforeach; ?>
        </select>

        <label><?= t('Sacks to Store', 'সংরক্ষণের বস্তা') ?></label>
        <input type="number" name="sacks" min="1" required>

        <label><?= t('Cubic Meters to Occupy', 'দখলকৃত ঘনমিটার') ?></label>
        <input type="number" name="cubic_m" min="1" required>

        <label><?= t('Booking Date', 'বুকিং তারিখ') ?></label>
        <input type="date" name="booking_date" value="<?= date('Y-m-d') ?>" required>

        <button type="submit"><?= t('Submit Booking', 'বুকিং জমা দিন') ?></button>
      </form>
    </div>

    <h2><?= t('My Bookings', 'আমার বুকিং') ?></h2>
    <table>
      <tr>
        <th><?= t('Facility', 'সুবিধা') ?></th>
        <th><?= t('Sacks', 'বস্তা') ?></th>
        <th><?= t('Cubic m', 'ঘনমিটার') ?></th>
        <th><?= t('Date', 'তারিখ') ?></th>
        <th><?= t('Status', 'অবস্থা') ?></th>
        <th></th>
      </tr>
      <?php foreach ($myBookings as $b): ?>
        <tr>
          <td><?= htmlspecialchars($b['Facility_Name']) ?></td>
          <td><?= (int) $b['Sacks_To_Store'] ?></td>
          <td><?= (int) $b['Cubic_Meters_To_Occupy'] ?></td>
          <td><?= htmlspecialchars($b['Booking_Date']) ?></td>
          <td><?= kd_status_pill($b['Status']) ?></td>
          <td>
            <?php if ($b['Status'] === 'Active'): ?>
              <form method="POST" action="cold_storage.php" style="margin:0;"
                onsubmit="return confirm('<?= t('Confirm pickup? This will vacate your storage space.', 'সংগ্রহ নিশ্চিত করবেন? এটি আপনার স্টোরেজ স্পেস খালি করে দেবে।') ?>');">
                <input type="hidden" name="action" value="pickup">
                <input type="hidden" name="booking_id" value="<?= (int) $b['Booking_ID'] ?>">
                <button type="submit" style="margin:0;padding:6px 14px;width:auto;"><?= t('Pickup', 'সংগ্রহ করুন') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($myBookings)): ?>
        <tr><td colspan="6"><?= t('No bookings yet.', 'এখনো কোনো বুকিং নেই।') ?></td></tr>
      <?php endif; ?>
    </table>
  <?php endif; ?>
</div>

</body>
</html>
