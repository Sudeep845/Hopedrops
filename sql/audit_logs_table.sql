-- Create audit_logs table for HopeDrops
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_name VARCHAR(255) NOT NULL,
    category ENUM('authentication', 'user_management', 'hospital_management', 'blood_operations', 'system_admin', 'security', 'notifications') NOT NULL,
    action VARCHAR(255) NOT NULL,
    resource VARCHAR(255) NOT NULL,
    status ENUM('success', 'warning', 'error') NOT NULL DEFAULT 'success',
    ip_address VARCHAR(45) NULL,
    location VARCHAR(255) NULL,
    details TEXT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timestamp (timestamp),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_user_id (user_id)
);

-- Insert sample audit log data
INSERT INTO audit_logs (user_id, user_name, category, action, resource, status, ip_address, location, details, timestamp) VALUES
(145, 'Dr. Sarah Johnson', 'hospital_management', 'Blood Inventory Updated', 'O+ Blood Units (City Hospital)', 'success', '192.168.1.100', 'New York, NY', '{"previous_count": 25, "new_count": 22, "units_used": 3, "reason": "Emergency transfusion"}', NOW() - INTERVAL 5 MINUTE),

(234, 'John Smith', 'authentication', 'User Login', 'Admin Portal', 'success', '10.0.1.50', 'Los Angeles, CA', '{"user_agent": "Chrome 119.0.0.0", "session_id": "sess_abc123"}', NOW() - INTERVAL 10 MINUTE),

(NULL, 'System', 'system_admin', 'Database Backup', 'Main Database', 'success', '127.0.0.1', 'Server', '{"backup_size": "245MB", "duration": "3.2 seconds", "backup_id": "backup_20241117_142008"}', NOW() - INTERVAL 15 MINUTE),

(NULL, 'Unknown', 'security', 'Failed Login Attempt', 'Admin Portal', 'error', '203.154.23.89', 'Unknown', '{"attempted_username": "admin", "failure_reason": "Invalid password", "attempts_count": 3}', NOW() - INTERVAL 20 MINUTE),

(1, 'Admin', 'user_management', 'Hospital Registration Approved', 'Metro General Hospital', 'success', '192.168.1.10', 'Chicago, IL', '{"hospital_id": 89, "verification_docs": "approved", "capacity": 200}', NOW() - INTERVAL 25 MINUTE),

(NULL, 'Emergency System', 'blood_operations', 'Critical Blood Request', 'AB- Blood Type', 'warning', '10.0.2.15', 'Emergency Medical Center', '{"urgency": "critical", "patient_id": "P-4521", "units_needed": 4, "estimated_time": "30 minutes"}', NOW() - INTERVAL 30 MINUTE),

(156, 'Dr. Michael Brown', 'blood_operations', 'Blood Donation Recorded', 'Donation Center A', 'success', '192.168.2.45', 'Boston, MA', '{"donor_id": "D-8934", "blood_type": "O+", "units_donated": 1, "next_eligible_date": "2024-12-15"}', NOW() - INTERVAL 35 MINUTE),

(NULL, 'Notification System', 'notifications', 'Email Notification Sent', 'Blood Shortage Alert', 'success', '127.0.0.1', 'Server', '{"recipients": 145, "notification_type": "shortage_alert", "blood_type": "B-", "delivery_status": "delivered"}', NOW() - INTERVAL 40 MINUTE),

(178, 'Dr. Emily Davis', 'hospital_management', 'Hospital Status Updated', 'Central Medical Center', 'success', '192.168.1.75', 'Miami, FL', '{"previous_status": "pending", "new_status": "active", "verification_completed": true}', NOW() - INTERVAL 45 MINUTE),

(NULL, 'System', 'system_admin', 'Cache Cleared', 'System Cache', 'success', '127.0.0.1', 'Server', '{"cache_type": "application", "size_cleared": "128MB", "performance_impact": "positive"}', NOW() - INTERVAL 50 MINUTE),

(67, 'Mark Wilson', 'authentication', 'Password Changed', 'User Account', 'success', '10.0.3.22', 'Seattle, WA', '{"user_type": "donor", "security_level": "enhanced", "last_change": "2024-10-15"}', NOW() - INTERVAL 55 MINUTE),

(NULL, 'Security System', 'security', 'Suspicious Activity Detected', 'Multiple Login Attempts', 'warning', '198.51.100.42', 'Unknown Location', '{"attempts": 5, "time_window": "2 minutes", "blocked": true, "threat_level": "medium"}', NOW() - INTERVAL 60 MINUTE),

(89, 'Lisa Chen', 'blood_operations', 'Blood Request Fulfilled', 'A+ Blood Units', 'success', '192.168.3.10', 'San Francisco, CA', '{"request_id": "REQ-2024-001", "units_provided": 2, "hospital": "SF General", "patient_condition": "stable"}', NOW() - INTERVAL 65 MINUTE),

(1, 'Admin', 'user_management', 'User Account Activated', 'Donor Registration', 'success', '192.168.1.10', 'Chicago, IL', '{"user_id": 445, "account_type": "donor", "activation_method": "email_verification", "approval_time": "immediate"}', NOW() - INTERVAL 70 MINUTE),

(NULL, 'System', 'notifications', 'Emergency Alert Sent', 'Blood Shortage Critical', 'warning', '127.0.0.1', 'Server', '{"alert_level": "critical", "blood_types": ["O-", "AB-"], "hospitals_notified": 25, "response_time": "immediate"}', NOW() - INTERVAL 75 MINUTE),

(203, 'Dr. Robert Taylor', 'hospital_management', 'Blood Inventory Alert', 'Low Stock Warning', 'warning', '192.168.4.15', 'Houston, TX', '{"blood_type": "B-", "current_stock": 3, "minimum_required": 10, "reorder_triggered": true}', NOW() - INTERVAL 80 MINUTE),

(NULL, 'System', 'system_admin', 'System Restart', 'Application Server', 'success', '127.0.0.1', 'Server', '{"restart_reason": "scheduled_maintenance", "downtime": "30 seconds", "services_affected": "web_interface", "data_integrity": "maintained"}', NOW() - INTERVAL 85 MINUTE),

(156, 'Dr. Michael Brown', 'authentication', 'Two-Factor Authentication Enabled', 'Security Settings', 'success', '192.168.2.45', 'Boston, MA', '{"method": "SMS", "backup_codes_generated": 8, "security_level": "high"}', NOW() - INTERVAL 90 MINUTE),

(NULL, 'Automated System', 'blood_operations', 'Expired Blood Units Removed', 'Inventory Management', 'success', '127.0.0.1', 'Server', '{"units_removed": 5, "blood_types": ["A+", "O+"], "expiry_date": "2024-11-16", "disposal_method": "medical_waste"}', NOW() - INTERVAL 95 MINUTE),

(1, 'Admin', 'user_management', 'Hospital Account Suspended', 'Compliance Violation', 'warning', '192.168.1.10', 'Chicago, IL', '{"hospital_id": 67, "violation_type": "documentation", "suspension_duration": "7 days", "review_scheduled": "2024-11-24"}', NOW() - INTERVAL 100 MINUTE);

-- Create indexes for better performance
CREATE INDEX idx_audit_category_status ON audit_logs(category, status);
CREATE INDEX idx_audit_timestamp_desc ON audit_logs(timestamp DESC);
CREATE INDEX idx_audit_user_timestamp ON audit_logs(user_id, timestamp DESC);