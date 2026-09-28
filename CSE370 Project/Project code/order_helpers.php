<?php
// -------------------------------------------------------------
// Shared helpers for placing, pooling, checking out, and cancelling
// orders. Included by order.php, cart.php, and my_orders.php.
// Assumes config.php (which defines $pdo and t()) is already loaded.
// -------------------------------------------------------------

// Max weight a single truck/shipment can carry before a new one is opened.
if (!defined('TRUCK_CAPACITY_KG')) {
    define('TRUCK_CAPACITY_KG', 5000.00);
}

/**
 * Picks the hub with the fewest currently-pooling shipments, to spread
 * load evenly rather than always stacking new shipments onto Hub #1.
 */
function kd_pick_hub(PDO $pdo): int {
    $stmt = $pdo->query(
        "SELECT h.Hub_ID
         FROM dhaka_hub h
         LEFT JOIN shipment s ON s.Hub_ID = h.Hub_ID AND s.Shipment_Status = 'Pooling'
         GROUP BY h.Hub_ID
         ORDER BY COUNT(s.Shipment_ID) ASC, h.Hub_ID ASC
         LIMIT 1"
    );
    return (int) $stmt->fetchColumn();
}

/**
 * Assigns an order to a shipment, respecting the truck capacity and
 * matching the driver's district to the farmer's district (the schema
 * has no coordinates, so district is used as a proximity proxy).
 *
 * 1. Tries to pool into an existing 'Pooling' shipment run by a driver
 *    in the farmer's district that still has room left.
 * 2. Otherwise opens a new shipment, but only with a driver from the
 *    farmer's district who isn't already busy with another shipment.
 * 3. If neither exists, the order is left unassigned (Shipment_ID stays
 *    NULL) rather than forcing a mismatched or over-capacity truck.
 *
 * Must be called inside an already-open PDO transaction.
 */
function kd_assign_shipment(PDO $pdo, int $orderId, string $farmerDistrict, float $qty): array {
    // 1. Top up an existing nearby shipment that still has room.
    $stmt = $pdo->prepare(
        "SELECT s.Shipment_ID
         FROM shipment s
         JOIN users d ON d.User_ID = s.Driver_ID
         WHERE s.Shipment_Status = 'Pooling'
           AND d.District = ?
           AND (s.Total_Pooled_Weight + ?) <= ?
         ORDER BY s.Total_Pooled_Weight DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$farmerDistrict, $qty, TRUCK_CAPACITY_KG]);
    $shipmentId = $stmt->fetchColumn();

    if ($shipmentId) {
        $pdo->prepare('UPDATE shipment SET Total_Pooled_Weight = Total_Pooled_Weight + ? WHERE Shipment_ID = ?')
            ->execute([$qty, $shipmentId]);
        $pdo->prepare('UPDATE orders SET Shipment_ID = ? WHERE Order_ID = ?')
            ->execute([$shipmentId, $orderId]);
        return [
            'assigned' => true,
            'shipment_id' => (int) $shipmentId,
            'message' => 'Pooled into an existing pickup truck near your farmer.',
        ];
    }

    // 2. No shipment with room nearby -- find a free driver in the same district.
    $stmt = $pdo->prepare(
        "SELECT u.User_ID
         FROM users u
         WHERE u.Role = 'Truck Driver'
           AND u.District = ?
           AND NOT EXISTS (
               SELECT 1 FROM shipment s2
               WHERE s2.Driver_ID = u.User_ID AND s2.Shipment_Status IN ('Pooling', 'Dispatched')
           )
         ORDER BY u.User_ID ASC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$farmerDistrict]);
    $driverId = $stmt->fetchColumn();

    if (!$driverId) {
        return [
            'assigned' => false,
            'shipment_id' => null,
            'message' => 'No truck driver is available near this farmer right now. '
                        . 'Your order will be picked up once one becomes free.',
        ];
    }

    $hubId = kd_pick_hub($pdo);

    // Truck_Plate_Number has no source table to pull a real plate from,
    // so a placeholder is used until the driver/manager records the real one.
    $pdo->prepare(
        "INSERT INTO shipment (Driver_ID, Hub_ID, Total_Pooled_Weight, Truck_Plate_Number, Shipment_Status)
         VALUES (?, ?, ?, 'TBD', 'Pooling')"
    )->execute([$driverId, $hubId, $qty]);
    $newShipmentId = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE orders SET Shipment_ID = ? WHERE Order_ID = ?')
        ->execute([$newShipmentId, $orderId]);

    $note = $qty > TRUCK_CAPACITY_KG
        ? ' Note: this order alone exceeds a single truck\'s normal capacity and was assigned as an oversized load.'
        : '';

    return [
        'assigned' => true,
        'shipment_id' => $newShipmentId,
        'message' => 'A new pickup truck was opened for you nearby.' . $note,
    ];
}

/**
 * Validates and places a single order from a cart line item, mirroring
 * the same stock-lock -> deduct -> insert order -> assign shipment flow
 * used by the direct "Order Now" path in order.php. Runs its own
 * transaction so one bad cart line (e.g. stock changed) doesn't block
 * the rest of the checkout.
 *
 * $item must contain: Product_ID, Quantity, Price_Per_KG, Farmer_District.
 */
function kd_checkout_cart_item(PDO $pdo, int $buyerId, array $item): array {
    try {
        $pdo->beginTransaction();

        $lockStmt = $pdo->prepare(
            'SELECT Available_Quantity, Minimum_Order_Quantity FROM product WHERE Product_ID = ? FOR UPDATE'
        );
        $lockStmt->execute([$item['Product_ID']]);
        $current = $lockStmt->fetch();

        if (!$current) {
            throw new Exception(t('This product no longer exists.', 'এই পণ্যটি আর নেই।'));
        }
        if ((float) $item['Quantity'] < (float) $current['Minimum_Order_Quantity']) {
            throw new Exception(t('Quantity is below the minimum order.', 'পরিমাণ সর্বনিম্ন অর্ডারের চেয়ে কম।'));
        }
        if ((float) $item['Quantity'] > (float) $current['Available_Quantity']) {
            throw new Exception(t(
                'Only ' . number_format($current['Available_Quantity'], 2) . ' kg available now.',
                'এখন মাত্র ' . number_format($current['Available_Quantity'], 2) . ' কেজি উপলব্ধ।'
            ));
        }

        $qty = (float) $item['Quantity'];
        $totalAmount = round($qty * (float) $item['Price_Per_KG'], 2);

        $pdo->prepare('UPDATE product SET Available_Quantity = Available_Quantity - ? WHERE Product_ID = ?')
            ->execute([$qty, $item['Product_ID']]);

        $ins = $pdo->prepare(
            'INSERT INTO orders (Buyer_ID, Product_ID, Shipment_ID, Order_Quantity, Total_Amount, Order_Status, Order_Date)
             VALUES (?, ?, NULL, ?, ?, ?, NOW())'
        );
        $ins->execute([$buyerId, $item['Product_ID'], $qty, $totalAmount, 'Pending']);
        $orderId = (int) $pdo->lastInsertId();

        kd_assign_shipment($pdo, $orderId, $item['Farmer_District'], $qty);

        $pdo->prepare('DELETE FROM cart WHERE Buyer_ID = ? AND Product_ID = ?')
            ->execute([$buyerId, $item['Product_ID']]);

        $pdo->commit();

        return [
            'success' => true,
            'order_id' => $orderId,
            'message' => sprintf(t('Order #%d placed.', 'অর্ডার #%d তৈরি হয়েছে।'), $orderId),
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'order_id' => null, 'message' => $e->getMessage()];
    }
}

/**
 * Cancels a buyer's own order, only while it's still Pending or
 * Paid-Escrow and hasn't actually left the farmer yet:
 *   - Restores the reserved quantity back onto the product listing.
 *   - Detaches it from any 'Pooling' shipment and shrinks that
 *     shipment's pooled weight back down (shipment row itself is
 *     left in place -- other orders may still be pooled onto it).
 *   - If payment was already collected into escrow, marks it Refunded.
 * Refuses to cancel once the shipment has left (Dispatched / Arrived)
 * even if Order_Status hasn't been flipped to In-Transit yet.
 */
function kd_cancel_order(PDO $pdo, int $orderId, int $buyerId): array {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT o.Order_ID, o.Order_Status, o.Product_ID, o.Order_Quantity, o.Shipment_ID,
                    pay.Payment_ID, pay.Payment_Status
             FROM orders o
             LEFT JOIN payment pay ON pay.Order_ID = o.Order_ID
             WHERE o.Order_ID = ? AND o.Buyer_ID = ?
             FOR UPDATE"
        );
        $stmt->execute([$orderId, $buyerId]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new Exception(t('Order not found.', 'অর্ডার খুঁজে পাওয়া যায়নি।'));
        }
        if (!in_array($order['Order_Status'], ['Pending', 'Paid-Escrow'], true)) {
            throw new Exception(t('This order can no longer be cancelled.', 'এই অর্ডারটি আর বাতিল করা যাবে না।'));
        }

        if ($order['Shipment_ID']) {
            $shipStmt = $pdo->prepare('SELECT Shipment_Status FROM shipment WHERE Shipment_ID = ? FOR UPDATE');
            $shipStmt->execute([$order['Shipment_ID']]);
            $shipStatus = $shipStmt->fetchColumn();

            if ($shipStatus !== 'Pooling') {
                throw new Exception(t(
                    'This order is already on its way and cannot be cancelled.',
                    'এই অর্ডারটি ইতিমধ্যে পথে আছে, বাতিল করা যাবে না।'
                ));
            }

            $pdo->prepare('UPDATE shipment SET Total_Pooled_Weight = GREATEST(0, Total_Pooled_Weight - ?) WHERE Shipment_ID = ?')
                ->execute([$order['Order_Quantity'], $order['Shipment_ID']]);
            $pdo->prepare('UPDATE orders SET Shipment_ID = NULL WHERE Order_ID = ?')
                ->execute([$orderId]);
        }

        // Give the reserved stock back to the listing.
        $pdo->prepare('UPDATE product SET Available_Quantity = Available_Quantity + ? WHERE Product_ID = ?')
            ->execute([$order['Order_Quantity'], $order['Product_ID']]);

        // Refund escrowed payment, if any.
        if ($order['Payment_ID'] && $order['Payment_Status'] === 'Held-in-Escrow') {
            $pdo->prepare("UPDATE payment SET Payment_Status = 'Refunded' WHERE Payment_ID = ?")
                ->execute([$order['Payment_ID']]);
        }

        $pdo->prepare("UPDATE orders SET Order_Status = 'Cancelled' WHERE Order_ID = ?")
            ->execute([$orderId]);

        $pdo->commit();

        $wasPaid = $order['Payment_ID'] && $order['Payment_Status'] === 'Held-in-Escrow';
        return [
            'success' => true,
            'message' => $wasPaid
                ? t('Order cancelled and your payment has been refunded.', 'অর্ডার বাতিল হয়েছে এবং আপনার পেমেন্ট ফেরত দেওয়া হয়েছে।')
                : t('Order cancelled.', 'অর্ডার বাতিল করা হয়েছে।'),
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Called right after a farmer adds a NEW product listing (my_products.php).
 * Because listing a new batch creates a fresh Product_ID rather than
 * restocking the old (sold-out) one, this matches on Farmer_ID + Crop_Name
 * instead of Product_ID: any buyer with a 'Waiting' preorder against an
 * earlier listing of the same crop from the same farmer is notified that
 * it's back, their preorder is flipped to 'Fulfilled', and they're pointed
 * straight at the new listing to order from.
 */
function kd_notify_preorder_buyers_on_relist(PDO $pdo, int $farmerId, string $cropName, int $newProductId): void {
    $stmt = $pdo->prepare(
        "SELECT pr.Preorder_ID, pr.Buyer_ID
         FROM preorder pr
         JOIN product p ON p.Product_ID = pr.Product_ID
         WHERE p.Farmer_ID = ? AND p.Crop_Name = ? AND pr.Status = 'Waiting'
         FOR UPDATE"
    );
    $stmt->execute([$farmerId, $cropName]);
    $waiting = $stmt->fetchAll();

    foreach ($waiting as $pr) {
        $pdo->prepare("UPDATE preorder SET Status = 'Fulfilled' WHERE Preorder_ID = ?")
            ->execute([$pr['Preorder_ID']]);

        kd_notify(
            $pdo,
            (int) $pr['Buyer_ID'],
            'preorder_fulfilled',
            'Good news! "' . $cropName . '" is back in stock. You can now place your order.',
            'সুখবর! "' . $cropName . '" আবার মজুদ হয়েছে। এখন আপনি অর্ডার করতে পারেন।',
            'order.php?product_id=' . $newProductId,
            $newProductId
        );
    }
}

/**
 * Best-effort matching of waiting preorders against newly-available stock,
 * oldest first. Not called from anywhere in the buyer-facing pages in this
 * repo -- wire it into whatever farmer/manager code increases
 * product.Available_Quantity (e.g. a restock endpoint) so preorders get
 * marked 'Fulfilled' automatically instead of sitting there forever.
 * Fulfilling a preorder here does NOT create an order or move stock by
 * itself -- it just flags that the buyer should now be able to order it;
 * hook up a notification or auto-order step if you want that automated too.
 */
function kd_fulfill_preorders(PDO $pdo, int $productId): void {
    $stmt = $pdo->prepare(
        "SELECT Preorder_ID, Buyer_ID, Quantity FROM preorder
         WHERE Product_ID = ? AND Status = 'Waiting'
         ORDER BY Created_At ASC
         FOR UPDATE"
    );
    $stmt->execute([$productId]);
    $preorders = $stmt->fetchAll();

    if (!$preorders) {
        return;
    }

    $availStmt = $pdo->prepare('SELECT Available_Quantity FROM product WHERE Product_ID = ? FOR UPDATE');
    $availStmt->execute([$productId]);
    $available = (float) $availStmt->fetchColumn();

    $cropStmt = $pdo->prepare('SELECT Crop_Name FROM product WHERE Product_ID = ?');
    $cropStmt->execute([$productId]);
    $cropName = (string) $cropStmt->fetchColumn();

    foreach ($preorders as $p) {
        if ((float) $p['Quantity'] > $available) {
            continue; // Not enough stock yet for this one -- leave it waiting.
        }
        $pdo->prepare("UPDATE preorder SET Status = 'Fulfilled' WHERE Preorder_ID = ?")
            ->execute([$p['Preorder_ID']]);
        $available -= (float) $p['Quantity'];

        kd_notify(
            $pdo,
            (int) $p['Buyer_ID'],
            'preorder_fulfilled',
            'Good news! "' . $cropName . '" is back in stock. You can now place your order.',
            'সুখবর! "' . $cropName . '" আবার মজুদ হয়েছে। এখন আপনি অর্ডার করতে পারেন।',
            'order.php?product_id=' . $productId,
            $productId
        );
    }
}
