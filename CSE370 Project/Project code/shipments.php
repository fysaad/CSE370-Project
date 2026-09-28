<?php
require_once 'config.php';
require_login();

$role = $_SESSION['role'];
$userId = (int) $_SESSION['user_id'];

if ($role !== 'Truck Driver' && $role !== 'Manager') {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = false;

// Valid forward-only status transitions
$nextStatus = [
    'Pooling'         => 'Dispatched',
    'Dispatched'      => 'Arrived_At_Hub',
    'Arrived_At_Hub'  => null, // terminal state in this workflow
];

// Order of steps for the visual status bar
$statusSteps = ['Pooling', 'Dispatched', 'Arrived_At_Hub'];

/** Bilingual label for a shipment status code. */
function kd_status_step_label(string $status): string {
    switch ($status) {
        case 'Pooling':        return t('Pooling', 'সংগ্রহ চলছে');
        case 'Dispatched':      return t('Dispatched', 'পাঠানো হয়েছে');
        case 'Arrived_At_Hub':  return t('Arrived At Hub', 'হাবে পৌঁছেছে');
        default:                return str_replace('_', ' ', $status);
    }
}

/**
 * A driver is "Busy" if they currently have any shipment that is
 * still pooling or already dispatched (i.e. not yet finished).
 * "Available" means they have no active shipment right now.
 */
function kd_driver_busy(PDO $pdo, int $driverId): bool {
    $stmt = $pdo->prepare(
        "SELECT EXISTS (
            SELECT 1 FROM shipment
            WHERE Driver_ID = ? AND Shipment_Status IN ('Pooling', 'Dispatched')
        )"
    );
    $stmt->execute([$driverId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Farmers whose orders are pooled into a given shipment.
 * orders has no direct Farmer_ID — the farmer is reached via
 * orders.Product_ID -> product.Farmer_ID -> users, and the
 * quantity column is Order_Quantity.
 */
function kd_pooled_farmers(PDO $pdo, int $shipmentId): array {
    $stmt = $pdo->prepare(
        "SELECT o.Order_ID, o.Order_Quantity, o.Order_Status, u.Name AS Farmer_Name
         FROM orders o
         JOIN product p ON p.Product_ID = o.Product_ID
         JOIN users u ON u.User_ID = p.Farmer_ID
         WHERE o.Shipment_ID = ?
         ORDER BY u.Name"
    );
    $stmt->execute([$shipmentId]);
    return $stmt->fetchAll();
}

/** Renders a compact Pooling -> Dispatched -> Arrived_At_Hub progress bar. */
function kd_render_status_stepper(string $status, array $steps): string {
    $currentIndex = array_search($status, $steps, true);
    $html = '<div class="stepper">';
    foreach ($steps as $i => $step) {
        $stateClass = 'pending';
        if ($currentIndex !== false) {
            if ($i < $currentIndex)       $stateClass = 'completed';
            elseif ($i === $currentIndex) $stateClass = 'current';
        }
        $label = kd_status_step_label($step);
        $html .= '<div class="step ' . $stateClass . '">'
               . '<span class="dot"></span>'
               . '<span class="label">' . htmlspecialchars($label) . '</span>'
               . '</div>';
        if ($i < count($steps) - 1) {
            $connectorClass = ($currentIndex !== false && $i < $currentIndex) ? 'completed' : '';
            $html .= '<div class="connector ' . $connectorClass . '"></div>';
        }
    }
    $html .= '</div>';
    return $html;
}

if ($role === 'Truck Driver' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipmentId = (int) ($_POST['shipment_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT Shipment_ID, Driver_ID, Shipment_Status FROM shipment WHERE Shipment_ID = ?');
    $stmt->execute([$shipmentId]);
    $shipment = $stmt->fetch();

    if (!$shipment || (int) $shipment['Driver_ID'] !== $userId) {
        $errors[] = t('Shipment not found.', 'শিপমেন্ট পাওয়া যায়নি।');
    } else {
        $current = $shipment['Shipment_Status'];
        $advance = $nextStatus[$current] ?? null;

        if ($advance === null) {
            $errors[] = t('This shipment has no further status to advance to.', 'এই শিপমেন্টের আর কোনো পরবর্তী অবস্থা নেই।');
        } else {
            if ($advance === 'Dispatched') {
                $upd = $pdo->prepare("UPDATE shipment SET Shipment_Status = ?, Dispatched_At = NOW() WHERE Shipment_ID = ?");
            } else { // Arrived_At_Hub
                $upd = $pdo->prepare("UPDATE shipment SET Shipment_Status = ?, Arrived_At_Hub_At = NOW() WHERE Shipment_ID = ?");
            }
            $upd->execute([$advance, $shipmentId]);
            $success = true;
        }
    }
}

if ($role === 'Truck Driver') {
    $stmt = $pdo->prepare(
        "SELECT s.Shipment_ID, s.Truck_Plate_Number, s.Shipment_Status,
                s.Dispatched_At, s.Arrived_At_Hub_At, h.Hub_Name,
                COALESCE(SUM(o.Order_Quantity), 0) AS Total_Pooled_Weight
         FROM shipment s
         JOIN dhaka_hub h ON h.Hub_ID = s.Hub_ID
         LEFT JOIN orders o ON o.Shipment_ID = s.Shipment_ID
         WHERE s.Driver_ID = ?
         GROUP BY s.Shipment_ID, s.Truck_Plate_Number, s.Shipment_Status,
                  s.Dispatched_At, s.Arrived_At_Hub_At, h.Hub_Name
         ORDER BY s.Shipment_ID DESC"
    );
    $stmt->execute([$userId]);
    $myAvailability = kd_driver_busy($pdo, $userId) ? 'Busy' : 'Available';
} else {
    $stmt = $pdo->query(
        "SELECT s.Shipment_ID, s.Truck_Plate_Number, s.Shipment_Status,
                s.Dispatched_At, s.Arrived_At_Hub_At, h.Hub_Name, u.Name AS Driver_Name,
                COALESCE(SUM(o.Order_Quantity), 0) AS Total_Pooled_Weight
         FROM shipment s
         JOIN dhaka_hub h ON h.Hub_ID = s.Hub_ID
         JOIN users u ON u.User_ID = s.Driver_ID
         LEFT JOIN orders o ON o.Shipment_ID = s.Shipment_ID
         GROUP BY s.Shipment_ID, s.Truck_Plate_Number, s.Shipment_Status,
                  s.Dispatched_At, s.Arrived_At_Hub_At, h.Hub_Name, u.Name
         ORDER BY s.Shipment_ID DESC"
    );

    // Availability for every driver, so the manager can see who's free
    $driverStmt = $pdo->query(
        "SELECT u.User_ID, u.Name,
                EXISTS (
                    SELECT 1 FROM shipment s2
                    WHERE s2.Driver_ID = u.User_ID AND s2.Shipment_Status IN ('Pooling', 'Dispatched')
                ) AS Is_Busy
         FROM users u
         WHERE u.Role = 'Truck Driver'
         ORDER BY u.Name"
    );
    $drivers = $driverStmt->fetchAll();
}
$shipments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($LANG) ?>">
<head>
<meta charset="UTF-8">
<title><?= t('Shipments - KrishiDirect', 'শিপমেন্ট - কৃষিডিরেক্ট') ?></title>
<link rel="stylesheet" href="style.css">
<script src="voice.js"></script>
<style>
  /* Status stepper */
  .stepper { display: flex; align-items: center; }
  .stepper .step { display: flex; flex-direction: column; align-items: center; font-size: 11px; min-width: 60px; }
  .stepper .dot { width: 14px; height: 14px; border-radius: 50%; background: #d0d0d0; display: block; margin-bottom: 4px; border: 2px solid #d0d0d0; }
  .stepper .step.completed .dot { background: #2e7d32; border-color: #2e7d32; }
  .stepper .step.current .dot { background: #fff; border-color: #1565c0; box-shadow: 0 0 0 3px rgba(21,101,192,0.2); }
  .stepper .step.completed .label { color: #2e7d32; font-weight: 600; }
  .stepper .step.current .label { color: #1565c0; font-weight: 600; }
  .stepper .step.pending .label { color: #999; }
  .stepper .connector { flex: 1; height: 2px; background: #d0d0d0; margin: 0 2px 16px 2px; min-width: 16px; }
  .stepper .connector.completed { background: #2e7d32; }

  /* Availability badges */
  .avail-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
  .avail-badge.available { background: #e6f4ea; color: #2e7d32; }
  .avail-badge.busy { background: #fdecea; color: #c62828; }

  .driver-list { list-style: none; margin: 0 0 20px 0; padding: 0; display: flex; flex-wrap: wrap; gap: 10px; }
  .driver-list li { border: 1px solid #eee; border-radius: 8px; padding: 8px 12px; display: flex; align-items: center; gap: 8px; }

  details.pooled-farmers { margin-top: 6px; font-size: 12px; }
  details.pooled-farmers summary { cursor: pointer; color: #1565c0; }
  details.pooled-farmers ul { margin: 6px 0 0 0; padding-left: 18px; }
  .order-status { color: #888; font-size: 11px; }
</style>
</head>
<body>

<div class="navbar">
  <strong><a href="index.php">KrishiDirect</a></strong>
  <div>
    <a href="index.php"><?= t('Dashboard', 'ড্যাশবোর্ড') ?></a>
    <a href="shipments.php"><?= t('Shipments', 'শিপমেন্ট') ?></a>
    <?php if ($role === 'Manager'): ?><a href="manager_dashboard.php"><?= t('Manager Dashboard', 'ম্যানেজার ড্যাশবোর্ড') ?></a><?php endif; ?>
    <a href="logout.php"><?= t('Logout', 'লগআউট') ?></a>
    <?= lang_toggle_html() ?>
    <?= nav_speak_button() ?>
    <?= kd_notif_bell_html() ?>
  </div>
</div>

<div class="container wide">
  <h1><?= $role === 'Truck Driver' ? t('My Shipments', 'আমার শিপমেন্ট') : t('All Shipments', 'সকল শিপমেন্ট') ?></h1>

  <?php if ($role === 'Truck Driver'): ?>
    <p>
      <?= t('Your status:', 'আপনার অবস্থা:') ?>
      <span class="avail-badge <?= $myAvailability === 'Busy' ? 'busy' : 'available' ?>">
        <?= $myAvailability === 'Busy' ? t('Busy', 'ব্যস্ত') : t('Available', 'উপলব্ধ') ?>
      </span>
    </p>
  <?php else: ?>
    <h3><?= t('Driver Availability', 'ড্রাইভারের প্রাপ্যতা') ?></h3>
    <ul class="driver-list">
      <?php foreach ($drivers as $d): ?>
        <li>
          <?= htmlspecialchars($d['Name']) ?>
          <span class="avail-badge <?= $d['Is_Busy'] ? 'busy' : 'available' ?>">
            <?= $d['Is_Busy'] ? t('Busy', 'ব্যস্ত') : t('Available', 'উপলব্ধ') ?>
          </span>
        </li>
      <?php endforeach; ?>
      <?php if (empty($drivers)): ?>
        <li><?= t('No truck drivers found.', 'কোনো ট্রাক ড্রাইভার পাওয়া যায়নি।') ?></li>
      <?php endif; ?>
    </ul>
  <?php endif; ?>

  <?php if ($success): ?><div class="success"><?= t('Shipment status updated.', 'শিপমেন্টের অবস্থা আপডেট হয়েছে।') ?></div><?php endif; ?>
  <?php foreach ($errors as $e): ?><div class="error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

  <table>
    <tr>
      <th><?= t('ID', 'আইডি') ?></th><th><?= t('Hub', 'হাব') ?></th>
      <?php if ($role === 'Manager'): ?><th><?= t('Driver', 'ড্রাইভার') ?></th><?php endif; ?>
      <th><?= t('Truck Plate', 'ট্রাকের নম্বর প্লেট') ?></th>
      <th><?= t('Weight (kg)', 'ওজন (কেজি)') ?></th>
      <th><?= t('Status', 'অবস্থা') ?></th>
      <th><?= t('Dispatched At', 'পাঠানোর সময়') ?></th>
      <th><?= t('Arrived At Hub', 'হাবে পৌঁছানোর সময়') ?></th>
      <?php if ($role === 'Truck Driver'): ?><th></th><?php endif; ?>
    </tr>
    <?php foreach ($shipments as $s): ?>
      <tr>
        <td>#<?= (int) $s['Shipment_ID'] ?></td>
        <td><?= htmlspecialchars($s['Hub_Name']) ?></td>
        <?php if ($role === 'Manager'): ?><td><?= htmlspecialchars($s['Driver_Name']) ?></td><?php endif; ?>
        <td><?= htmlspecialchars($s['Truck_Plate_Number']) ?></td>
        <td><?= number_format($s['Total_Pooled_Weight'], 2) ?></td>
        <td>
          <?= kd_render_status_stepper($s['Shipment_Status'], $statusSteps) ?>
          <?php if ($role === 'Truck Driver'): ?>
            <?php $farmers = kd_pooled_farmers($pdo, (int) $s['Shipment_ID']); ?>
            <details class="pooled-farmers">
              <summary>
                <?= count($farmers) ?>
                <?= count($farmers) === 1 ? t('farmer pooled', 'জন কৃষক একত্রিত') : t('farmers pooled', 'জন কৃষক একত্রিত') ?>
              </summary>
              <ul>
                <?php foreach ($farmers as $f): ?>
                  <li><?= htmlspecialchars($f['Farmer_Name']) ?> — <?= number_format($f['Order_Quantity'], 2) ?> kg
                    <span class="order-status">(<?= htmlspecialchars($f['Order_Status']) ?>)</span></li>
                <?php endforeach; ?>
                <?php if (empty($farmers)): ?>
                  <li><?= t('No pooled orders yet.', 'এখনো কোনো একত্রিত অর্ডার নেই।') ?></li>
                <?php endif; ?>
              </ul>
            </details>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($s['Dispatched_At'] ?? '—') ?></td>
        <td><?= htmlspecialchars($s['Arrived_At_Hub_At'] ?? '—') ?></td>
        <?php if ($role === 'Truck Driver'): ?>
          <td>
            <?php if (($nextStatus[$s['Shipment_Status']] ?? null) !== null): ?>
              <form method="POST" action="shipments.php" style="margin:0;">
                <input type="hidden" name="shipment_id" value="<?= (int) $s['Shipment_ID'] ?>">
                <button type="submit" style="margin:0;padding:6px 14px;width:auto;">
                  <?= t('Mark as', 'চিহ্নিত করুন') ?> <?= htmlspecialchars(kd_status_step_label($nextStatus[$s['Shipment_Status']])) ?>
                </button>
              </form>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($shipments)): ?>
      <tr><td colspan="8"><?= t('No shipments found.', 'কোনো শিপমেন্ট পাওয়া যায়নি।') ?></td></tr>
    <?php endif; ?>
  </table>
</div>

</body>
</html>
