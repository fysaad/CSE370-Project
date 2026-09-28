<?php
/**
 * config.php
 * Shared PDO database connection for the KrishiDirect platform.
 * Include this at the top of every PHP file: require_once 'config.php';
 */

// ---- Edit these to match your local MySQL setup ----
$DB_HOST = 'localhost';
$DB_NAME = 'krishidirect';
$DB_USER = 'root';
$DB_PASS = '';
$DB_CHARSET = 'utf8mb4';
// ------------------------------------------------------

$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // use real prepared statements
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // In production, log this instead of echoing it
    die('Database connection failed: ' . $e->getMessage());
}

// Start session on every page that includes this file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Bilingual (English / Bangla) helper -- gives every page $LANG and t()
require_once __DIR__ . '/lang.php';

/**
 * Helper: require the user to be logged in, otherwise redirect to login.
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Helper: require a specific role (e.g. 'Manager'), otherwise redirect home.
 */
function require_role($role) {
    require_login();
    if ($_SESSION['role'] !== $role) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Turns an Order_Status / Booking_Status / Payment_Status value into a
 * small colored pill. Blank statuses in the seed data are shown as
 * "Pending review" instead of an empty pill. Shared across pages so
 * it's only defined in one place.
 */
function kd_status_pill($status) {
    $status = trim((string) $status);
    if ($status === '') {
        return '<span class="status-pill PendingReview">' . t('Pending review', 'পর্যালোচনাধীন') . '</span>';
    }
    $labels = [
        'Pending'         => t('Pending', 'অপেক্ষমাণ'),
        'Rejected'        => t('Rejected', 'প্রত্যাখ্যাত'),
        'Paid-Escrow'     => t('Paid (Escrow)', 'পরিশোধিত (এসক্রো)'),
        'In-Transit'      => t('In Transit', 'পরিবহনে'),
        'Completed'       => t('Completed', 'সম্পন্ন'),
        'Disputed'        => t('Disputed', 'বিরোধপূর্ণ'),
        'Cancelled'       => t('Cancelled', 'বাতিল'),
        'Active'          => t('Active', 'সক্রিয়'),
        'Checked-Out'     => t('Checked Out', 'চেক-আউট'),
        'Held-in-Escrow'  => t('Held in Escrow', 'এসক্রোতে জমা'),
        'Released'        => t('Released', 'প্রদত্ত'),
        'Refunded'        => t('Refunded', 'ফেরত'),
        'Waiting'         => t('Waiting', 'অপেক্ষমাণ'),
        'Fulfilled'       => t('Fulfilled', 'পূরণ হয়েছে'),
    ];
    $class = str_replace(['-', ' '], '', $status);
    $label = $labels[$status] ?? $status;
    return '<span class="status-pill ' . htmlspecialchars($class) . '">' . htmlspecialchars($label) . '</span>';
}

/**
 * The KrishiDirect one-line pitch, read aloud from the navbar sound
 * button on every page.
 */
function kd_intro_text() {
    return t(
        'KrishiDirect links farmers, buyers, cold storage and truck drivers on one ledger — dynamic pricing, escrow-held payments, and capacity-checked storage bookings.',
        'কৃষিডিরেক্ট কৃষক, ক্রেতা, কোল্ড স্টোরেজ এবং ট্রাক চালকদের একটি অভিন্ন হিসাবের খাতায় সংযুক্ত করে—গতিশীল মূল্য নির্ধারণ, এসক্রোতে সুরক্ষিত পেমেন্ট, এবং ধারণক্ষমতা যাচাই করা স্টোরেজ বুকিং।'
    );
}

/**
 * Ready-made navbar sound button that reads kd_intro_text() aloud.
 */
function nav_speak_button() {
    global $LANG;
    return '<button type="button" class="speak-btn nav-speak-btn" title="' . htmlspecialchars(t('Listen', 'শুনুন')) . '" '
         . 'onclick="kdSpeak(' . htmlspecialchars(json_encode(kd_intro_text()), ENT_QUOTES) . ', \'' . $LANG . '\')">&#128266;</button>';
}

/**
 * ================= Notifications =================
 * Generic inbox used for cross-role events. Right now this powers:
 *   - Farmer notified when a buyer places a preorder.
 *   - Buyer notified when the farmer relists that crop (back in stock).
 * Both English and Bangla text are stored per-row so the bell/inbox
 * can render in whichever language the viewer currently has active,
 * without needing to re-translate anything at read time.
 */

/**
 * Insert a notification for a user. $link is a relative URL (e.g.
 * 'order.php?product_id=5') the notification should open when clicked.
 */
function kd_notify(PDO $pdo, int $userId, string $type, string $msgEn, string $msgBn, ?string $link = null, ?int $relatedId = null): void {
    $pdo->prepare(
        'INSERT INTO notification (User_ID, Type, Message_EN, Message_BN, Link, Related_ID)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$userId, $type, $msgEn, $msgBn, $link, $relatedId]);
}

/** Count of unread notifications for a user (used for the navbar badge). */
function kd_unread_notification_count(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notification WHERE User_ID = ? AND Is_Read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/** Most recent notifications for a user, newest first. */
function kd_recent_notifications(PDO $pdo, int $userId, int $limit = 50): array {
    $stmt = $pdo->prepare(
        'SELECT Notification_ID, Type, Message_EN, Message_BN, Link, Related_ID, Is_Read, Created_At
         FROM notification WHERE User_ID = ? ORDER BY Created_At DESC LIMIT ' . (int) $limit
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/** Marks every notification for a user as read (called from notifications.php). */
function kd_mark_notifications_read(PDO $pdo, int $userId): void {
    $pdo->prepare('UPDATE notification SET Is_Read = 1 WHERE User_ID = ? AND Is_Read = 0')
        ->execute([$userId]);
}

/**
 * Ready-made navbar notification bell with an unread-count badge.
 * Safe to call on any page after login (returns '' if not logged in).
 */
function kd_notif_bell_html() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        return '';
    }
    $count = kd_unread_notification_count($pdo, (int) $_SESSION['user_id']);
    $badge = $count > 0 ? '<span class="notif-badge">' . ($count > 9 ? '9+' : $count) . '</span>' : '';
    return '<a href="notifications.php" class="speak-btn notif-bell" title="' . htmlspecialchars(t('Notifications', 'বিজ্ঞপ্তি')) . '">&#128276;' . $badge . '</a>';
}
