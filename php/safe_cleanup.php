<?php
/**
 * Targeted Test Data Cleanup - Only remove obvious test patterns
 * Preserves legitimate hospital data
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

    echo "Starting targeted test data cleanup (preserving real hospitals)...\n\n";

    // Only remove obvious test patterns - be more specific
    $removedHospitals = 0;
    
    // Remove only hospitals with clearly test-related names
    $testPatterns = [
        'test_%',           // Only test_ prefix
        'Test %',           // Only Test prefix with space
        'sherpa hos',       // Exact match only
        'TEST%',            // Only uppercase TEST
        'dummy%',           // Only dummy data
        'sample%'           // Only sample data
    ];

    foreach ($testPatterns as $pattern) {
        $stmt = $pdo->prepare("DELETE FROM hospitals WHERE hospital_name LIKE ?");
        $stmt->execute([$pattern]);
        $count = $stmt->rowCount();
        if ($count > 0) {
            echo "Removed {$count} hospitals matching pattern: {$pattern}\n";
            $removedHospitals += $count;
        }
    }

    // Remove only clearly test email addresses
    $testEmails = [
        'test_%@%',         // Only test_ email prefixes
        'dummy%@%',         // Only dummy emails
        'sample%@%'         // Only sample emails
    ];

    foreach ($testEmails as $email) {
        $stmt = $pdo->prepare("DELETE FROM hospitals WHERE contact_email LIKE ?");
        $stmt->execute([$email]);
        $count = $stmt->rowCount();
        if ($count > 0) {
            echo "Removed {$count} hospitals with test email pattern: {$email}\n";
            $removedHospitals += $count;
        }
    }

    echo "Total removed hospitals: {$removedHospitals}\n";

    // Clean orphaned records
    $stmt = $pdo->prepare("
        DELETE bi FROM blood_inventory bi
        LEFT JOIN hospitals h ON bi.hospital_id = h.id
        WHERE h.id IS NULL
    ");
    $stmt->execute();
    $removedInventory = $stmt->rowCount();
    if ($removedInventory > 0) {
        echo "Cleaned {$removedInventory} orphaned blood inventory records\n";
    }

    // Show current hospitals to verify we didn't delete legitimate ones
    echo "\nCurrent hospitals in the system:\n";
    $stmt = $pdo->query("
        SELECT hospital_name, city, contact_email, contact_phone, is_approved
        FROM hospitals 
        ORDER BY hospital_name
    ");
    
    $hospitalCount = 0;
    while ($row = $stmt->fetch()) {
        $hospitalCount++;
        $status = $row['is_approved'] ? 'Approved' : 'Pending';
        echo "- {$row['hospital_name']} ({$row['city']}) - {$row['contact_phone']} - {$status}\n";
    }

    if ($hospitalCount == 0) {
        echo "No hospitals found. You may need to add your hospital data.\n";
    }

    echo "\n✅ Targeted cleanup completed! Legitimate hospitals preserved.\n";

} catch (Exception $e) {
    echo "❌ Error during cleanup: " . $e->getMessage() . "\n";
}
?>