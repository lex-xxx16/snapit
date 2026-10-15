<?php
$passwords = ['admin123', 'staff123', 'cust123', 'password'];
$seed_hashes = [
    'admin@snapit.ph'    => '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy',
    'staff@snapit.ph'    => '$2y$10$3xGJ9mzG.wfLq6sBf3g3IuW5Z7X6P0o0Q4wQe3R2T1Y0U9I8O7M6N5',
    'customer@snapit.ph' => '$2y$10$e8e7Y3kf5d3c7b9a1z0x8y7w6v5u4t3s2r1q0p9o8n7m6l5k4j3i2',
];

header("Content-Type: text/plain; charset=utf-8");

echo "=== H1: Do seed hashes match the advertised plaintexts? ===\n\n";
foreach ($passwords as $plain) {
    echo "Testing plaintext '$plain':\n";
    foreach ($seed_hashes as $email => $hash) {
        $ok = @password_verify($plain, $hash);
        echo "  $email => " . ($ok ? 'MATCH!' : 'no match') . "\n";
    }
    echo "\n";
}

echo "=== H1b: Generate NEW correct hashes for admin123/staff123/cust123 ===\n\n";
foreach (['admin123', 'staff123', 'cust123'] as $p) {
    $h = password_hash($p, PASSWORD_BCRYPT);
    echo "password_hash('$p', PASSWORD_BCRYPT) => $h\n";
    $v = password_verify($p, $h) ? 'valid' : 'INVALID';
    echo "  verification: $v\n\n";
}
