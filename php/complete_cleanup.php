<?php
/**
 * Complete Test Data Cleanup - Remove all sherpa hos test data
 */

header('Content-Type: application/json');

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bloodbank_db;charset=utf8",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    echo "Starting comprehensive test data cleanup...\n\n";

    // 1. Find and remove hospitals with test data
    $testPatterns = [
        'sherpa hos',
        'Sherpa hospital', 
        'sherpa%',
        'test%'
    ];

    $testEmails = [
        'ni@ma.com',
        'nimahospital@gmail.com',
        'test_%@%'
    ];

    $testPhones = [
        '9800000000',
        '9811111111',
        '1212121212'
    ];

    $removedHospitals = 0;
    
    // Remove by hospital name patterns
    foreach ($testPatterns as $pattern) {
        $stmt = $pdo->prepare("DELETE FROM hospitals WHERE hospital_name LIKE ?");
        $stmt->execute([$pattern]);
        $removedHospitals += $stmt->rowCount();
    }

    // Remove by email patterns
    foreach ($testEmails as $email) {
        if (strpos($email, '%') !== false) {
            $stmt = $pdo->prepare("DELETE FROM hospitals WHERE contact_email LIKE ?");
        } else {
            $stmt = $pdo->prepare("DELETE FROM hospitals WHERE contact_email = ?");
        }
        $stmt->execute([$email]);
        $removedHospitals += $stmt->rowCount();
    }

    // Remove by phone patterns
    foreach ($testPhones as $phone) {
        $stmt = $pdo->prepare("DELETE FROM hospitals WHERE contact_phone = ?");
        $stmt->execute([$phone]);
        $removedHospitals += $stmt->rowCount();
    }

    echo "Removed {$removedHospitals} test hospital records\n";

    // 2. Remove associated users
    $removedUsers = 0;
    $userPatterns = ['test_%', 'nima', 'sherpa%'];
    
    foreach ($userPatterns as $pattern) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE username LIKE ?");
        $stmt->execute([$pattern]);
        $removedUsers += $stmt->rowCount();
    }

    foreach ($testEmails as $email) {
        if (strpos($email, '%') !== false) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE email LIKE ?");
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
        }
        $stmt->execute([$email]);
        $removedUsers += $stmt->rowCount();
    }

    echo "Removed {$removedUsers} test user records\n";

    // 3. Clean orphaned blood inventory
    $stmt = $pdo->prepare("
        DELETE bi FROM blood_inventory bi
        LEFT JOIN hospitals h ON bi.hospital_id = h.id
        WHERE h.id IS NULL
    ");
    $stmt->execute();
    $removedInventory = $stmt->rowCount();
    echo "Cleaned {$removedInventory} orphaned blood inventory records\n";

    // 4. Clean orphaned hospital activities
    $stmt = $pdo->prepare("
        DELETE ha FROM hospital_activities ha
        LEFT JOIN hospitals h ON ha.hospital_id = h.id
        WHERE h.id IS NULL
    ");
    $stmt->execute();
    $removedActivities = $stmt->rowCount();
    echo "Cleaned {$removedActivities} orphaned hospital activity records\n";

    // 5. Verify current state
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM hospitals WHERE is_approved = 1");
    $approvedCount = $stmt->fetch()['count'];
    echo "\nAfter cleanup: {$approvedCount} approved hospitals remain\n";

    // 6. Show remaining hospitals
    if ($approvedCount > 0) {
        echo "\nRemaining hospitals:\n";
        $stmt = $pdo->query("
            SELECT hospital_name, city, contact_email, contact_phone
            FROM hospitals 
            WHERE is_approved = 1
            ORDER BY hospital_name
        ");
        while ($row = $stmt->fetch()) {
            echo "- {$row['hospital_name']} ({$row['city']}) - {$row['contact_email']} - {$row['contact_phone']}\n";
        }
    } else {
        echo "\nNo approved hospitals remaining. You may need to run the hospital setup script.\n";
    }

    echo "\n✅ Comprehensive cleanup completed!\n";

} catch (Exception $e) {
    echo "❌ Error during cleanup: " . $e->getMessage() . "\n";
}
?>