<?php
require_once 'config.php';
require_role('Manager');

$managerId = (int) $_SESSION['user_id'];
$errors = [];
$success = false;
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_facility') {
        // ---- Manager adds a brand-new cold storage facility ----
        $facilityName = trim($_POST['facility_name'] ?? '');
        $district     = trim($_POST['district'] ?? '');
        $upazila      = trim($_POST['upazila'] ?? '');
        $maxSacks     = $_POST['max_sacks'] ?? '';
        $maxCubic     = $_POST['max_cubic'] ?? '';

        if ($facilityName === '') $errors[] = t('Facility name is required.', 'সুবিধার নাম আবশ্যক।');
        if ($district === '') $errors[] = t('District is required.', 'জেলা আবশ্যক।');
        if ($upazila === '') $errors[] = t('Upazila is required.', 'উপজেলা আবশ্যক।');
        if (!is_numeric($maxSacks) || $maxSacks <= 0) $errors[] = t('Enter a valid sack capacity.', 'সঠিক বস্তার ধারণক্ষমতা লিখুন।');
        if (!is_numeric($maxCubic) || $maxCubic <= 0) $errors[] = t('Enter a valid cubic meter capacity.', 'সঠিক ঘনমিটার ধারণক্ষমতা লিখুন।');

        if (empty($errors)) {
            $ins = $pdo->prepare(
                'INSERT INTO cold_storage
                    (Manager_ID, Facility_Name, District, Upazila, Max_Sack_Capacity, Max_Cubic_Meter_Capacity, Current_Sack_Count, Current_Cubic_Meter_Count)
                 VALUES (?, ?, ?, ?, ?, ?, 0, 0)'
            );
            $ins->execute([$managerId, $facilityName, $district, $upazila, (int) $maxSacks, (int) $maxCubic]);
            $success = true;
            $successMessage = t('New facility added.', 'নতুন সুবিধা যোগ করা হয়েছে।');
        }

    } elseif ($action === 'approve' || $action === 'reject') {
        // ---- Manager approves/rejects a pending storage booking ----
        $bookingId = (int) ($_POST['booking_id'] ?? 0);

        $stmt = $pdo->prepare(
            "SELECT sb.Booking_ID, sb.Storage_ID, sb.Farmer_ID, sb.Sacks_To_Store, sb.Cubic_Meters_To_Occupy, sb.Status,
                    cs.Manager_ID, cs.Facility_Name, cs.Max_Sack_Capacity, cs.Current_Sack_Count,
                    cs.Max_Cubic_Meter_Capacity, cs.Current_Cubic_Meter_Count
             FROM storage_booking sb JOIN cold_storage cs ON cs.Storage_ID = sb.Storage_ID
             WHERE sb.Booking_ID = ?"
        );
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        if (!$booking || (int) $booking['Manager_ID'] !== $managerId) {
            $errors[] = t('Booking not found.', 'বুকিং পাওয়া যায়নি।');
        } elseif ($booking['Status'] !== 'Pending') {
            $errors[] = t('This booking has already been processed.', 'এই বুকিং ইতিমধ্যে প্রক্রিয়াকৃত হয়েছে।');
        } elseif ($action === 'approve') {
            $fitsSacks = ($booking['Current_Sack_Count'] + $booking['Sacks_To_Store']) <= $booking['Max_Sack_Capacity'];
            $fitsCubic = ($booking['Current_Cubic_Meter_Count'] + $booking['Cubic_Meters_To_Occupy']) <= $booking['Max_Cubic_Meter_Capacity'];

            if (!$fitsSacks || !$fitsCubic) {
                $errors[] = t(
                    'This cold storage is full — there is not enough remaining capacity to approve this booking.',
                    'এই কোল্ড স্টোরেজ পূর্ণ — এই বুকিং অনুমোদনের জন্য পর্যাপ্ত জায়গা অবশিষ্ট নেই।'
                );
            } else {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE storage_booking SET Status = 'Active' WHERE Booking_ID = ?")->execute([$bookingId]);
                    $pdo->prepare(
                        "UPDATE cold_storage
                         SET Current_Sack_Count = Current_Sack_Count + ?, Current_Cubic_Meter_Count = Current_Cubic_Meter_Count + ?
                         WHERE Storage_ID = ?"
                    )->execute([$booking['Sacks_To_Store'], $booking['Cubic_Meters_To_Occupy'], $booking['Storage_ID']]);
                    $pdo->commit();

                    kd_notify(
                        $pdo,
                        (int) $booking['Farmer_ID'],
                        'storage_booking',
                        'Your cold storage booking at ' . $booking['Facility_Name'] . ' was approved.',
                        $booking['Facility_Name'] . '-এ আপনার কোল্ড স্টোরেজ বুকিং অনুমোদিত হয়েছে।',
                        'cold_storage.php',
                        $bookingId
                    );

                    $success = true;
                    $successMessage = t(
                        'Booking approved. It is now active on the farmer\'s account.',
                        'বুকিং অনুমোদিত হয়েছে। এটি এখন কৃষকের অ্যাকাউন্টে সক্রিয়।'
                    );
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $errors[] = t('Approval failed: ', 'অনুমোদন ব্যর্থ হয়েছে: ') . $e->getMessage();
                }
            }
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE storage_booking SET Status = 'Rejected' WHERE Booking_ID = ?")->execute([$bookingId]);

            kd_notify(
                $pdo,
                (int) $booking['Farmer_ID'],
                'storage_booking',
                'Your cold storage booking at ' . $booking['Facility_Name'] . ' was rejected.',
                $booking['Facility_Name'] . '-এ আপনার কোল্ড স্টোরেজ বুকিং প্রত্যাখ্যাত হয়েছে।',
                'cold_storage.php',
                $bookingId
            );

            $success = true;
            $successMessage = t('Booking rejected.', 'বুকিং প্রত্যাখ্যাত হয়েছে।');
        }
    } else {
        $errors[] = t('Unknown action.', 'অজানা কার্যক্রম।');
    }
}

// Facilities managed by this manager
$facStmt = $pdo->prepare('SELECT Storage_ID FROM cold_storage WHERE Manager_ID = ?');
$facStmt->execute([$managerId]);
$facilityIds = array_column($facStmt->fetchAll(), 'Storage_ID');

$pendingBookings = [];
if (!empty($facilityIds)) {
    $placeholders = implode(',', array_fill(0, count($facilityIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT sb.Booking_ID, sb.Sacks_To_Store, sb.Cubic_Meters_To_Occupy, sb.Booking_Date,
                cs.Facility_Name, u.Name AS Farmer_Name
         FROM storage_booking sb
         JOIN cold_storage cs ON cs.Storage_ID = sb.Storage_ID
         JOIN users u ON u.User_ID = sb.Farmer_ID
         WHERE sb.Status = 'Pending' AND sb.Storage_ID IN ($placeholders)
         ORDER BY sb.Booking_Date"
    );
    $stmt->execute($facilityIds);
    $pendingBookings = $stmt->fetchAll();
}

$myFacilities = $pdo->prepare(
    'SELECT Storage_ID, Facility_Name, District, Upazila,
            Max_Sack_Capacity, Current_Sack_Count, Max_Cubic_Meter_Capacity, Current_Cubic_Meter_Count
     FROM cold_storage WHERE Manager_ID = ?'
);
$myFacilities->execute([$managerId]);
$myFacilities = $myFacilities->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($LANG) ?>">
<head>
<meta charset="UTF-8">
<title><?= t('Manager Dashboard - KrishiDirect', 'ম্যানেজার ড্যাশবোর্ড - কৃষিডিরেক্ট') ?></title>
<link rel="stylesheet" href="style.css">
<script src="voice.js"></script>
</head>
<body>

<div class="navbar">
  <strong><a href="index.php">KrishiDirect</a></strong>
  <div>
    <a href="index.php"><?= t('Dashboard', 'ড্যাশবোর্ড') ?></a>
    <a href="manager_dashboard.php"><?= t('Manager Dashboard', 'ম্যানেজার ড্যাশবোর্ড') ?></a>
    
    <a href="logout.php"><?= t('Logout', 'লগআউট') ?></a>
    <?= lang_toggle_html() ?>
    <?= nav_speak_button() ?>
    <?= kd_notif_bell_html() ?>
  </div>
</div>

<div class="container wide">
  <h1><?= t('Manager Dashboard', 'ম্যানেজার ড্যাশবোর্ড') ?></h1>

  <?php if ($success): ?><div class="success"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
  <?php foreach ($errors as $e): ?><div class="error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

  <h2><?= t('My Facilities', 'আমার সুবিধাসমূহ') ?></h2>
  <table>
    <tr>
      <th><?= t('Facility', 'সুবিধা') ?></th>
      <th><?= t('District', 'জেলা') ?></th>
      <th><?= t('Upazila', 'উপজেলা') ?></th>
      <th><?= t('Sacks', 'বস্তা') ?></th>
      <th><?= t('Cubic m', 'ঘনমিটার') ?></th>
    </tr>
    <?php foreach ($myFacilities as $f): ?>
      <?php
        $sackFull  = (int) $f['Current_Sack_Count']  >= (int) $f['Max_Sack_Capacity'];
        $cubicFull = (int) $f['Current_Cubic_Meter_Count'] >= (int) $f['Max_Cubic_Meter_Capacity'];
      ?>
      <tr>
        <td><?= htmlspecialchars($f['Facility_Name']) ?></td>
        <td><?= htmlspecialchars($f['District']) ?></td>
        <td><?= htmlspecialchars($f['Upazila']) ?></td>
        <td><?= (int) $f['Current_Sack_Count'] ?> / <?= (int) $f['Max_Sack_Capacity'] ?><?= $sackFull ? ' <span class="error" style="display:inline;padding:2px 6px;">' . t('Full', 'পূর্ণ') . '</span>' : '' ?></td>
        <td><?= (int) $f['Current_Cubic_Meter_Count'] ?> / <?= (int) $f['Max_Cubic_Meter_Capacity'] ?><?= $cubicFull ? ' <span class="error" style="display:inline;padding:2px 6px;">' . t('Full', 'পূর্ণ') . '</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($myFacilities)): ?>
      <tr><td colspan="5"><?= t('No facilities assigned to you.', 'আপনার জন্য কোনো সুবিধা নির্ধারিত নেই।') ?></td></tr>
    <?php endif; ?>
  </table>

  <h2><?= t('Add New Cold Storage Facility', 'নতুন কোল্ড স্টোরেজ সুবিধা যোগ করুন') ?></h2>
  <form method="POST" action="manager_dashboard.php" style="max-width:480px;">
    <input type="hidden" name="action" value="add_facility">

    <label><?= t('Facility Name', 'সুবিধার নাম') ?></label>
    <input type="text" name="facility_name" required>

    <label><?= t('District', 'জেলা') ?></label>
    <input type="text" name="district" required>

    <label><?= t('Upazila', 'উপজেলা') ?></label>
    <input type="text" name="upazila" required>

    <label><?= t('Max Sack Capacity', 'সর্বোচ্চ বস্তার ধারণক্ষমতা') ?></label>
    <input type="number" name="max_sacks" min="1" required>

    <label><?= t('Max Cubic Meter Capacity', 'সর্বোচ্চ ঘনমিটার ধারণক্ষমতা') ?></label>
    <input type="number" name="max_cubic" min="1" required>

    <button type="submit"><?= t('Add Facility', 'সুবিধা যোগ করুন') ?></button>
  </form>

  <h2><?= t('Pending Storage Bookings', 'মুলতুবি স্টোরেজ বুকিং') ?></h2>
  <table>
    <tr>
      <th><?= t('Farmer', 'কৃষক') ?></th>
      <th><?= t('Facility', 'সুবিধা') ?></th>
      <th><?= t('Sacks', 'বস্তা') ?></th>
      <th><?= t('Cubic m', 'ঘনমিটার') ?></th>
      <th><?= t('Date', 'তারিখ') ?></th>
      <th></th>
    </tr>
    <?php foreach ($pendingBookings as $b): ?>
      <tr>
        <td><?= htmlspecialchars($b['Farmer_Name']) ?></td>
        <td><?= htmlspecialchars($b['Facility_Name']) ?></td>
        <td><?= (int) $b['Sacks_To_Store'] ?></td>
        <td><?= (int) $b['Cubic_Meters_To_Occupy'] ?></td>
        <td><?= htmlspecialchars($b['Booking_Date']) ?></td>
        <td>
          <form method="POST" action="manager_dashboard.php" style="display:inline; margin:0;">
            <input type="hidden" name="booking_id" value="<?= (int) $b['Booking_ID'] ?>">
            <input type="hidden" name="action" value="approve">
            <button type="submit" style="margin:0;padding:6px 14px;width:auto;"><?= t('Approve', 'অনুমোদন') ?></button>
          </form>
          <form method="POST" action="manager_dashboard.php" style="display:inline; margin:0;">
            <input type="hidden" name="booking_id" value="<?= (int) $b['Booking_ID'] ?>">
            <input type="hidden" name="action" value="reject">
            <button type="submit" style="margin:0;padding:6px 14px;width:auto;background:#9b1c1c;"><?= t('Reject', 'প্রত্যাখ্যান') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($pendingBookings)): ?>
      <tr><td colspan="6"><?= t('No pending bookings.', 'কোনো মুলতুবি বুকিং নেই।') ?></td></tr>
    <?php endif; ?>
  </table>
</div>

</body>
</html>
