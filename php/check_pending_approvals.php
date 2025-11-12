<?php
/**
 * Check Pending Hospital Approvals
 * Shows hospitals waiting for admin approval
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

header('Content-Type: text/plain');

echo "=== PENDING HOSPITAL APPROVALS ===\n\n";

try {
    $db = getDBConnection();
    
    // Get all hospitals with their approval status
    $stmt = $db->query("
        SELECT 
            h.id,
            h.hospital_name,
            h.license_number,
            h.city,
            h.contact_person,
            h.contact_phone,
            h.contact_email,
            h.is_approved,
            h.created_at,
            u.username,
            u.full_name as user_full_name
        FROM hospitals h
        JOIN users u ON h.user_id = u.id
        ORDER BY h.created_at DESC
    ");
    
    $hospitals = $stmt->fetchAll();
    
    if (empty($hospitals)) {
        echo "❌ No hospitals found in database\n";
        echo "\nTo test the approval system:\n";
        echo "1. Register a new hospital at: http://localhost/HopeDrops/register.html\n";
        echo "2. Select 'Hospital/Organization' role\n";
        echo "3. Complete the registration\n";
        echo "4. Return here to see pending approvals\n";
    } else {
        echo "Total Hospitals: " . count($hospitals) . "\n\n";
        
        $pending = array_filter($hospitals, function($h) { return $h['is_approved'] == 0; });
        $approved = array_filter($hospitals, function($h) { return $h['is_approved'] == 1; });
        
        echo "📊 SUMMARY:\n";
        echo "Pending Approval: " . count($pending) . "\n";
        echo "Approved: " . count($approved) . "\n\n";
        
        if (!empty($pending)) {
            echo "🔍 PENDING HOSPITALS (Need Admin Approval):\n";
            echo str_repeat("=", 60) . "\n";
            
            foreach ($pending as $hospital) {
                echo "Hospital ID: {$hospital['id']}\n";
                echo "Name: {$hospital['hospital_name']}\n";
                echo "License: {$hospital['license_number']}\n";
                echo "City: {$hospital['city']}\n";
                echo "Contact Person: {$hospital['contact_person']}\n";
                echo "Phone: {$hospital['contact_phone']}\n";
                echo "Email: {$hospital['contact_email']}\n";
                echo "Registered By: {$hospital['user_full_name']} ({$hospital['username']})\n";
                echo "Registration Date: {$hospital['created_at']}\n";
                echo "Status: ❌ PENDING APPROVAL\n";
                echo str_repeat("-", 40) . "\n";
            }
            
            echo "\n🎯 TO APPROVE HOSPITALS:\n";
            echo "1. Go to: http://localhost/HopeDrops/admin/manage_hospitals.html\n";
            echo "2. Login as admin if prompted\n";
            echo "3. Find pending hospitals (marked with warning icons)\n";
            echo "4. Click 'Approve' button for each hospital\n";
        }
        
        if (!empty($approved)) {
            echo "\n✅ APPROVED HOSPITALS:\n";
            echo str_repeat("=", 60) . "\n";
            
            foreach ($approved as $hospital) {
                echo "✅ {$hospital['hospital_name']} - {$hospital['city']}\n";
                echo "   Contact: {$hospital['contact_person']} ({$hospital['contact_phone']})\n";
                echo "   Registered: {$hospital['created_at']}\n\n";
            }
        }
    }
    
    // Check admin users
    echo "\n👤 ADMIN USERS:\n";
    $adminStmt = $db->query("SELECT id, username, full_name FROM users WHERE role = 'admin'");
    $admins = $adminStmt->fetchAll();
    
    if ($admins) {
        foreach ($admins as $admin) {
            echo "Admin: {$admin['full_name']} (username: {$admin['username']})\n";
        }
    } else {
        echo "❌ No admin users found!\n";
        echo "Create admin user to manage approvals\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📋 QUICK LINKS:\n";
echo "• Hospital Registration: http://localhost/HopeDrops/register.html\n";
echo "• Admin Dashboard: http://localhost/HopeDrops/admin/dashboard.html\n";
echo "• Manage Hospitals: http://localhost/HopeDrops/admin/manage_hospitals.html\n";
echo "• Login Page: http://localhost/HopeDrops/login.html\n";
?>