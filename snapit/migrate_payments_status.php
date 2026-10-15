<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/config.php';
require_admin();

header('Content-Type: text/plain; charset=utf-8');

function col_exists($conn, $table, $column) {
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $res && mysqli_num_rows($res) > 0;
}

echo "=== Snap It: Idempotent payments.status + booth.session_owner migration ===\n";
echo "Date: " . date('c') . "\n\n";

$changes = 0;

if (!col_exists($conn, 'payments', 'status')) {
    $sql = "ALTER TABLE payments
            ADD COLUMN status ENUM('pending','completed','captured','failed','refunded_pending')
                NOT NULL DEFAULT 'completed'
            AFTER payment_date";
    if (mysqli_query($conn, $sql)) {
        echo "[OK] payments.status added (DEFAULT 'completed' -> all existing cash payments marked paid).\n";
        $changes++;
    } else {
        echo "[FAIL] payments.status: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "[SKIP] payments.status already exists.\n";
}

if (!col_exists($conn, 'payments', 'gateway_reference')) {
    $sql = "ALTER TABLE payments
            ADD COLUMN gateway_reference VARCHAR(255) NULL AFTER or_number";
    if (mysqli_query($conn, $sql)) {
        echo "[OK] payments.gateway_reference added for future non-cash methods.\n";
        $changes++;
    } else {
        echo "[FAIL] payments.gateway_reference: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "[SKIP] payments.gateway_reference already exists.\n";
}

if (!col_exists($conn, 'guest_sessions', 'session_owner_user_id')) {
    $sql = "ALTER TABLE guest_sessions
            ADD COLUMN session_owner_user_id INT NULL AFTER guest_name,
            ADD INDEX idx_gs_owner (session_owner_user_id)";
    if (mysqli_query($conn, $sql)) {
        echo "[OK] guest_sessions.session_owner_user_id + index added (cross-user ownership guard).\n";
        $changes++;
    } else {
        echo "[FAIL] guest_sessions.session_owner_user_id: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "[SKIP] guest_sessions.session_owner_user_id already exists.\n";
}

if (!col_exists($conn, 'bookings', 'session_owner_user_id')) {
    // no-op: guest_sessions column enough, but if future query JOIN needed, skip here.
}

echo "\nDone. Applied changes: $changes\n";
echo "You may now navigate to /booth/index.php to test the Admin Booth Module fix.\n";
