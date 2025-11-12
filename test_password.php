<?php
// Test what password matches the hash in database
$storedHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

$commonPasswords = [
    'password',
    'admin',
    'admin123',
    'hopedrops',
    'HopeDrops',
    'HopeDrops123',
    '123456',
    'default'
];

echo "<h2>Password Hash Testing</h2>\n";
echo "<p>Stored hash: " . $storedHash . "</p>\n";

foreach ($commonPasswords as $testPassword) {
    if (password_verify($testPassword, $storedHash)) {
        echo "<p style='color: green;'>✅ MATCH FOUND: Password is '$testPassword'</p>\n";
        break;
    } else {
        echo "<p>❌ '$testPassword' - No match</p>\n";
    }
}

// Also test bcrypt with specific salt that might match Laravel's default
$laravelTestHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
if (password_verify('password', $laravelTestHash)) {
    echo "<p style='color: green;'>✅ This is Laravel's default 'password' hash</p>\n";
}
?>