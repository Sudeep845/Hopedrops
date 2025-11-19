-- HopeDrops Blood Bank Management System - Complete Database Setup
-- This file consolidates all necessary tables, data, and configurations
-- Run this single file to set up the complete database system
-- Last updated: November 19, 2025 - Schema verified compatible with current APIs
-- Note: All API endpoints confirmed working with this database structure
-- Recent additions: Appointments system fully integrated with hospital/user dashboards
-- New APIs: find_donor.php for donor lookup by phone/email in hospital appointment creation

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS bloodbank_db;
USE bloodbank_db;

-- Set SQL mode for better compatibility
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ====================================================================
-- CORE TABLES
-- ====================================================================

-- Users table (main authentication and role management)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('donor', 'hospital', 'admin') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100) DEFAULT 'Not specified',
    pincode VARCHAR(10) DEFAULT '000000',
    emergency_contact VARCHAR(15),
    medical_conditions TEXT,
    is_eligible BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Hospitals table (hospital/organization details)
CREATE TABLE IF NOT EXISTS hospitals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    hospital_name VARCHAR(255) NOT NULL,
    license_number VARCHAR(100) NOT NULL UNIQUE,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) DEFAULT 'Not specified',
    pincode VARCHAR(10) DEFAULT '000000',
    contact_person VARCHAR(255) NOT NULL,
    contact_phone VARCHAR(15) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    emergency_contact VARCHAR(15),
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    hospital_type VARCHAR(50) DEFAULT 'General',
    phone VARCHAR(15) NULL,
    email VARCHAR(100) NULL,
    is_approved TINYINT(1) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Blood inventory table (track available blood units by hospital)
CREATE TABLE IF NOT EXISTS blood_inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    units_available INT DEFAULT 0,
    units_required INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    UNIQUE KEY unique_hospital_blood (hospital_id, blood_type)
);

-- Donations table (track donation history)
CREATE TABLE IF NOT EXISTS donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    donation_date DATE NOT NULL,
    donation_time TIME,
    status ENUM('scheduled', 'completed', 'cancelled', 'rejected') DEFAULT 'scheduled',
    units_donated INT DEFAULT 1,
    hemoglobin_level DECIMAL(3,1),
    weight DECIMAL(5,2),
    blood_pressure VARCHAR(20),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

-- Notifications table (system notifications and alerts)
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'emergency') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity logs table (system activity tracking)
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Audit logs table (comprehensive audit trail)
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

-- ====================================================================
-- HOSPITAL MANAGEMENT TABLES
-- ====================================================================

-- Hospital activities table (track hospital operations and events)
CREATE TABLE IF NOT EXISTS hospital_activities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    user_id INT NULL,
    activity_type VARCHAR(100) NOT NULL,
    activity_data JSON,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Emergency blood requests table
CREATE TABLE IF NOT EXISTS emergency_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    units_needed INT NOT NULL,
    urgency_level ENUM('low', 'medium', 'high', 'critical', 'emergency') DEFAULT 'medium',
    status ENUM('pending', 'accepted', 'fulfilled', 'cancelled') DEFAULT 'pending',
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    required_date TIMESTAMP NULL,
    notes TEXT,
    contact_person VARCHAR(255),
    contact_phone VARCHAR(15),
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

-- General requests table (alternative to emergency_requests for compatibility)
CREATE TABLE IF NOT EXISTS requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    units_needed INT NOT NULL,
    urgency_level ENUM('low', 'medium', 'high', 'critical', 'emergency') DEFAULT 'medium',
    status ENUM('pending', 'accepted', 'fulfilled', 'cancelled') DEFAULT 'pending',
    description TEXT,
    contact_person VARCHAR(255),
    contact_phone VARCHAR(15),
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

-- ====================================================================
-- REWARD SYSTEM TABLES
-- ====================================================================

-- User rewards tracking table
CREATE TABLE IF NOT EXISTS user_rewards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    total_points INT DEFAULT 0,
    current_points INT DEFAULT 0,
    level INT DEFAULT 1,
    donations_count INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_reward (user_id)
);

-- Badges system
CREATE TABLE IF NOT EXISTS badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(255),
    category VARCHAR(50),
    requirements TEXT,
    points_awarded INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User badges (earned badges)
CREATE TABLE IF NOT EXISTS user_badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_badge (user_id, badge_id)
);

-- Reward items (shop items that can be redeemed)
CREATE TABLE IF NOT EXISTS reward_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    points_cost INT NOT NULL,
    category VARCHAR(50),
    image_url VARCHAR(255),
    stock_quantity INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reward redemptions (when users redeem items)
CREATE TABLE IF NOT EXISTS reward_redemptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    points_used INT NOT NULL,
    redemption_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES reward_items(id) ON DELETE CASCADE
);

-- ====================================================================
-- APPOINTMENTS SYSTEM
-- ====================================================================

-- Appointments table (blood donation scheduling)
CREATE TABLE IF NOT EXISTS appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    hospital_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'rescheduled', 'no_show') DEFAULT 'scheduled',
    notes TEXT,
    contact_person VARCHAR(255),
    contact_phone VARCHAR(15),
    reminder_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    INDEX idx_appointments_donor_id (donor_id),
    INDEX idx_appointments_hospital_id (hospital_id),
    INDEX idx_appointments_date (appointment_date),
    INDEX idx_appointments_status (status)
);

-- ====================================================================
-- INDEXES FOR PERFORMANCE
-- ====================================================================

-- Users table indexes (only for columns that exist)
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_blood_type ON users(blood_type);

-- Hospitals table indexes
CREATE INDEX IF NOT EXISTS idx_hospitals_user_id ON hospitals(user_id);
CREATE INDEX IF NOT EXISTS idx_hospitals_city ON hospitals(city);
CREATE INDEX IF NOT EXISTS idx_hospitals_approved ON hospitals(is_approved);
CREATE INDEX IF NOT EXISTS idx_hospitals_active ON hospitals(is_active);

-- Blood inventory indexes
CREATE INDEX IF NOT EXISTS idx_blood_inventory_hospital_id ON blood_inventory(hospital_id);
CREATE INDEX IF NOT EXISTS idx_blood_inventory_blood_type ON blood_inventory(blood_type);

-- Donations table indexes
CREATE INDEX IF NOT EXISTS idx_donations_donor_id ON donations(donor_id);
CREATE INDEX IF NOT EXISTS idx_donations_hospital_id ON donations(hospital_id);
CREATE INDEX IF NOT EXISTS idx_donations_date ON donations(donation_date);
CREATE INDEX IF NOT EXISTS idx_donations_status ON donations(status);

-- Notifications table indexes
CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_type ON notifications(type);

-- Activity logs indexes
CREATE INDEX IF NOT EXISTS idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON activity_logs(created_at);

-- Audit logs indexes
CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_timestamp ON audit_logs(timestamp);
CREATE INDEX IF NOT EXISTS idx_audit_logs_category ON audit_logs(category);
CREATE INDEX IF NOT EXISTS idx_audit_logs_status ON audit_logs(status);

-- Hospital activities indexes
CREATE INDEX IF NOT EXISTS idx_hospital_activities_hospital_id ON hospital_activities(hospital_id);
CREATE INDEX IF NOT EXISTS idx_hospital_activities_user_id ON hospital_activities(user_id);
CREATE INDEX IF NOT EXISTS idx_hospital_activities_type ON hospital_activities(activity_type);
CREATE INDEX IF NOT EXISTS idx_hospital_activities_created_at ON hospital_activities(created_at);

-- Emergency requests indexes
CREATE INDEX IF NOT EXISTS idx_emergency_requests_hospital_id ON emergency_requests(hospital_id);
CREATE INDEX IF NOT EXISTS idx_emergency_requests_blood_type ON emergency_requests(blood_type);
CREATE INDEX IF NOT EXISTS idx_emergency_requests_urgency ON emergency_requests(urgency_level);
CREATE INDEX IF NOT EXISTS idx_emergency_requests_status ON emergency_requests(status);
CREATE INDEX IF NOT EXISTS idx_emergency_requests_created_at ON emergency_requests(created_at);

-- General requests indexes
CREATE INDEX IF NOT EXISTS idx_requests_hospital_id ON requests(hospital_id);
CREATE INDEX IF NOT EXISTS idx_requests_blood_type ON requests(blood_type);
CREATE INDEX IF NOT EXISTS idx_requests_urgency ON requests(urgency_level);
CREATE INDEX IF NOT EXISTS idx_requests_status ON requests(status);
CREATE INDEX IF NOT EXISTS idx_requests_created_at ON requests(created_at);

-- ====================================================================
-- SAMPLE DATA
-- ====================================================================

-- Insert default admin user
INSERT INTO users (username, email, password, role, full_name, phone, city, state) 
VALUES ('admin', 'admin@hopedrops.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', '555-0000', 'Admin City', 'Admin State')
ON DUPLICATE KEY UPDATE username = username;

-- Insert system user for hospitals
INSERT INTO users (username, email, password, role, full_name, phone, is_eligible, is_active) VALUES ('system', 'system@hopedrops.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Admin', NULL, 1, 1) ON DUPLICATE KEY UPDATE username = username;

-- Insert hospital admin user
INSERT INTO users (username, email, password, role, full_name, phone, is_eligible, is_active) VALUES ('nima_hospital', 'nima@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hospital', 'Nima Hospital Admin', '9800000000', 1, 1) ON DUPLICATE KEY UPDATE username = username;

-- Insert hospitals data
INSERT INTO hospitals (user_id, hospital_name, license_number, address, city, state, pincode, contact_person, contact_phone, contact_email, emergency_contact, latitude, longitude, hospital_type, phone, email, is_approved, is_active) VALUES 
(1, 'Central Medical Center', 'CMC001', 'New Road, Kathmandu', 'Kathmandu', 'Bagmati', '000000', 'System Admin', '01-4241234', 'info@centralmedical.com.np', NULL, NULL, NULL, 'Government', NULL, NULL, 1, 1),
(1, 'Patan Community Hospital', 'PCH002', 'Lagankhel, Lalitpur', 'Lalitpur', 'Bagmati', '000000', 'System Admin', '01-5539595', 'contact@patancommunity.org.np', NULL, NULL, NULL, 'Non-Profit', NULL, NULL, 1, 1),
(3, 'Nima Hospital', 'NH001', 'Kapan, Kathmandu', 'Kathmandu', 'Bagmati', '000000', 'Nima Hospital Admin', '9800000000', 'nima@hospital.com', NULL, NULL, NULL, 'General', NULL, NULL, 1, 1),
(1, 'Sherpa Hospital', 'SH001', 'Kapan, Kathmandu', 'Kathmandu', 'Bagmati', '000000', 'System Admin', '9800000000', 'sherpa@hospital.com', NULL, NULL, NULL, 'General', NULL, NULL, 1, 1)
ON DUPLICATE KEY UPDATE hospital_name = VALUES(hospital_name);

-- Insert blood inventory data for all hospitals
-- Central Medical Center (hospital_id will be 1)
INSERT INTO blood_inventory (hospital_id, blood_type, units_available, units_required) VALUES 
(1, 'A+', 15, 0), (1, 'A-', 8, 0), (1, 'AB+', 12, 0), (1, 'AB-', 5, 0),
(1, 'B+', 18, 0), (1, 'B-', 7, 0), (1, 'O+', 22, 0), (1, 'O-', 10, 0)
ON DUPLICATE KEY UPDATE units_available = VALUES(units_available);

-- Patan Community Hospital (hospital_id will be 2)
INSERT INTO blood_inventory (hospital_id, blood_type, units_available, units_required) VALUES 
(2, 'A+', 12, 0), (2, 'A-', 6, 0), (2, 'AB+', 9, 0), (2, 'AB-', 4, 0),
(2, 'B+', 14, 0), (2, 'B-', 5, 0), (2, 'O+', 20, 0), (2, 'O-', 8, 0)
ON DUPLICATE KEY UPDATE units_available = VALUES(units_available);

-- Nima Hospital (hospital_id will be 3)
INSERT INTO blood_inventory (hospital_id, blood_type, units_available, units_required) VALUES 
(3, 'A+', 10, 0), (3, 'A-', 5, 0), (3, 'AB+', 7, 0), (3, 'AB-', 3, 0),
(3, 'B+', 12, 0), (3, 'B-', 4, 0), (3, 'O+', 18, 0), (3, 'O-', 6, 0)
ON DUPLICATE KEY UPDATE units_available = VALUES(units_available);

-- Sherpa Hospital (hospital_id will be 4)
INSERT INTO blood_inventory (hospital_id, blood_type, units_available, units_required) VALUES 
(4, 'A+', 8, 0), (4, 'A-', 4, 0), (4, 'AB+', 6, 0), (4, 'AB-', 2, 0),
(4, 'B+', 10, 0), (4, 'B-', 3, 0), (4, 'O+', 15, 0), (4, 'O-', 5, 0)
ON DUPLICATE KEY UPDATE units_available = VALUES(units_available);

-- Insert sample badges
INSERT INTO badges (name, description, icon, category, requirements, points_awarded) VALUES
('First Donation', 'Congratulations on your first blood donation!', 'fa-heart', 'milestone', 'Complete first donation', 50),
('5 Donations', 'You have completed 5 blood donations', 'fa-star', 'milestone', 'Complete 5 donations', 100),
('10 Donations', 'You have completed 10 blood donations', 'fa-medal', 'milestone', 'Complete 10 donations', 200),
('Life Saver', 'Your donations have helped save lives', 'fa-life-ring', 'achievement', 'Complete 25 donations', 500),
('Blood Hero', 'You are a true blood donation hero!', 'fa-trophy', 'achievement', 'Complete 50 donations', 1000),
('Emergency Responder', 'Thank you for responding to emergency requests', 'fa-ambulance', 'special', 'Respond to 3 emergency requests', 300),
('Regular Donor', 'You donate regularly every few months', 'fa-calendar-check', 'consistency', 'Donate at least once every 3 months for a year', 400),
('Community Champion', 'You help organize community blood drives', 'fa-users', 'community', 'Participate in 5 community events', 250)
ON DUPLICATE KEY UPDATE name = name;

-- Insert sample reward items
INSERT INTO reward_items (name, description, points_cost, category, stock_quantity) VALUES
('HopeDrops T-Shirt', 'Official HopeDrops branded t-shirt', 200, 'merchandise', 50),
('Blood Donor Badge Pin', 'Metal pin badge for blood donors', 100, 'merchandise', 100),
('Health Check Voucher', 'Free basic health checkup at partner clinics', 500, 'health', 20),
('Coffee Shop Voucher', '$10 voucher for local coffee shops', 150, 'food', 30),
('Movie Ticket', 'Free movie ticket at partner theaters', 300, 'entertainment', 25),
('Gym Day Pass', 'One day pass to partner fitness centers', 250, 'health', 40),
('Book Store Voucher', '$15 voucher for bookstores', 200, 'education', 35),
('Restaurant Meal Voucher', '$20 voucher for partner restaurants', 400, 'food', 15)
ON DUPLICATE KEY UPDATE name = name;

-- Insert sample hospital activities (for hospitals that exist)
INSERT INTO hospital_activities (hospital_id, user_id, activity_type, activity_data, description)
SELECT h.id, h.user_id, 'system_setup', '{"action": "database_initialization", "timestamp": "2025-11-13"}', 'System initialized and database tables created'
FROM hospitals h
WHERE h.is_approved = 1
LIMIT 5;

-- Insert sample audit log data
INSERT INTO audit_logs (user_id, user_name, category, action, resource, status, ip_address, location, details, timestamp) VALUES
(1, 'System Administrator', 'system_admin', 'Database Setup', 'Complete Database', 'success', '127.0.0.1', 'Server', '{"action": "database_initialization", "tables_created": 15, "sample_data": "inserted"}', NOW() - INTERVAL 1 HOUR),
(1, 'System Administrator', 'hospital_management', 'Hospital Registration', 'Central Medical Center', 'success', '192.168.1.100', 'Kathmandu, Nepal', '{"hospital_id": 1, "license_number": "CMC001", "approval_status": "approved"}', NOW() - INTERVAL 50 MINUTE),
(3, 'Nima Hospital Admin', 'authentication', 'User Login', 'Admin Portal', 'success', '10.0.1.50', 'Kathmandu, Nepal', '{"user_agent": "Chrome 119.0.0.0", "session_id": "sess_abc123"}', NOW() - INTERVAL 45 MINUTE),
(NULL, 'System', 'blood_operations', 'Inventory Update', 'Blood Inventory Management', 'success', '127.0.0.1', 'Server', '{"hospital_id": 1, "blood_type": "O+", "units_added": 5, "total_units": 22}', NOW() - INTERVAL 30 MINUTE),
(1, 'System Administrator', 'user_management', 'User Account Created', 'Hospital Admin Account', 'success', '192.168.1.100', 'Kathmandu, Nepal', '{"new_user_id": 3, "role": "hospital", "username": "nima_hospital"}', NOW() - INTERVAL 25 MINUTE),
(NULL, 'Unknown', 'security', 'Failed Login Attempt', 'Admin Portal', 'error', '203.154.23.89', 'Unknown', '{"attempted_username": "admin", "failure_reason": "Invalid password", "attempts_count": 3}', NOW() - INTERVAL 20 MINUTE),
(3, 'Nima Hospital Admin', 'notifications', 'Emergency Request Created', 'Emergency Blood Request', 'warning', '10.0.1.50', 'Kathmandu, Nepal', '{"request_id": 1, "blood_type": "A+", "units_needed": 3, "urgency": "high"}', NOW() - INTERVAL 15 MINUTE),
(1, 'System Administrator', 'system_admin', 'System Backup', 'Database Backup', 'success', '127.0.0.1', 'Server', '{"backup_size": "15MB", "duration": "2.1 seconds", "backup_id": "backup_20241117_setup"}', NOW() - INTERVAL 10 MINUTE),
(NULL, 'System', 'blood_operations', 'Donation Recorded', 'Blood Donation Entry', 'success', '192.168.1.101', 'Lalitpur, Nepal', '{"donor_id": 2, "hospital_id": 2, "blood_type": "B+", "units": 1, "donation_id": 1}', NOW() - INTERVAL 5 MINUTE),
(1, 'System Administrator', 'hospital_management', 'Campaign Created', 'Blood Drive Campaign', 'success', '192.168.1.100', 'Kathmandu, Nepal', '{"campaign_title": "Emergency Blood Collection", "target_donors": 100, "hospital_id": 1, "duration_days": 30}', NOW());

-- Insert sample emergency requests (for existing hospitals)
INSERT INTO emergency_requests (hospital_id, blood_type, units_needed, urgency_level, status, notes, contact_person, contact_phone, location)
SELECT 
    h.id, 
    CASE (h.id % 8)
        WHEN 0 THEN 'O+'
        WHEN 1 THEN 'A+'
        WHEN 2 THEN 'B+'
        WHEN 3 THEN 'AB+'
        WHEN 4 THEN 'O-'
        WHEN 5 THEN 'A-'
        WHEN 6 THEN 'B-'
        ELSE 'AB-'
    END,
    FLOOR(1 + RAND() * 5) as units_needed,
    CASE (h.id % 3)
        WHEN 0 THEN 'high'
        WHEN 1 THEN 'critical'
        ELSE 'emergency'
    END,
    'pending',
    'Sample emergency blood request for testing purposes',
    h.contact_person,
    h.contact_phone,
    CONCAT('Emergency Room - ', h.hospital_name)
FROM hospitals h
WHERE h.is_approved = 1
LIMIT 3;

-- Insert sample appointments (for testing appointment system)
INSERT INTO appointments (donor_id, hospital_id, appointment_date, appointment_time, blood_type, status, notes, contact_person, contact_phone)
SELECT 
    COALESCE((SELECT id FROM users WHERE role = 'donor' LIMIT 1), 1) as donor_id,
    h.id as hospital_id,
    DATE_ADD(CURDATE(), INTERVAL FLOOR(1 + RAND() * 30) DAY) as appointment_date,
    TIME(CONCAT(FLOOR(9 + RAND() * 8), ':', LPAD(FLOOR(RAND() * 60), 2, '0'), ':00')) as appointment_time,
    CASE (h.id % 8)
        WHEN 0 THEN 'O+'
        WHEN 1 THEN 'A+'  
        WHEN 2 THEN 'B+'
        WHEN 3 THEN 'AB+'
        WHEN 4 THEN 'O-'
        WHEN 5 THEN 'A-'
        WHEN 6 THEN 'B-'
        ELSE 'AB-'
    END as blood_type,
    CASE (h.id % 3)
        WHEN 0 THEN 'scheduled'
        WHEN 1 THEN 'confirmed'
        ELSE 'completed'
    END as status,
    'Sample appointment for testing appointment management system',
    h.contact_person,
    h.contact_phone
FROM hospitals h
WHERE h.is_approved = 1
LIMIT 5;

-- ====================================================================
-- MIGRATION AND COMPATIBILITY FIXES
-- ====================================================================

-- Add user_id column to hospital_activities if it doesn't exist (for existing databases)
ALTER TABLE hospital_activities 
ADD COLUMN IF NOT EXISTS user_id INT NULL AFTER hospital_id,
ADD CONSTRAINT fk_hospital_activities_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Add index for user_id if it doesn't exist
CREATE INDEX IF NOT EXISTS idx_hospital_activities_user_id ON hospital_activities(user_id);

-- ====================================================================
-- POST-SETUP PROCEDURES
-- ====================================================================

-- Initialize user rewards for existing donors (if any)
INSERT INTO user_rewards (user_id, total_points, current_points, level, donations_count)
SELECT u.id, 0, 0, 1, 0 
FROM users u 
WHERE u.role = 'donor' 
AND NOT EXISTS (SELECT 1 FROM user_rewards ur WHERE ur.user_id = u.id);

-- Ensure all hospitals have blood inventory entries for all blood types
INSERT IGNORE INTO blood_inventory (hospital_id, blood_type, units_available, units_required) 
SELECT h.id, bt.blood_type, 0, 0
FROM hospitals h
CROSS JOIN (
    SELECT 'A+' as blood_type UNION ALL
    SELECT 'A-' UNION ALL
    SELECT 'B+' UNION ALL
    SELECT 'B-' UNION ALL
    SELECT 'AB+' UNION ALL
    SELECT 'AB-' UNION ALL
    SELECT 'O+' UNION ALL
    SELECT 'O-'
) bt
WHERE h.is_approved = 1;

-- Update hospital phone/email fields from contact fields (for compatibility)
UPDATE hospitals SET 
    phone = COALESCE(phone, contact_phone),
    email = COALESCE(email, contact_email)
WHERE phone IS NULL OR email IS NULL;

-- ====================================================================
-- CAMPAIGN SYSTEM SETUP
-- ====================================================================

-- Campaign data is stored in hospital_activities table with activity_type = 'campaign_created'
-- The activity_data column contains JSON with campaign details:
-- {
--   "title": "Campaign Title",
--   "description": "Campaign Description", 
--   "start_date": "YYYY-MM-DD",
--   "end_date": "YYYY-MM-DD",
--   "start_time": "HH:MM",
--   "end_time": "HH:MM",
--   "target_donors": number,
--   "max_capacity": number,
--   "organizer": "Organizer Name",
--   "location": "Campaign Location",
--   "image_path": "uploads/campaigns/filename.jpg",
--   "status": "active|completed|cancelled",
--   "current_donors": number,
--   "campaign_type": "blood_drive"
-- }

-- Sample campaign data (remove after first setup if not needed)
INSERT IGNORE INTO hospital_activities (hospital_id, user_id, activity_type, activity_data, description)
SELECT 
    h.id,
    NULL,
    'campaign_created',
    JSON_OBJECT(
        'title', CONCAT(h.hospital_name, ' Blood Drive Campaign'),
        'description', CONCAT('Emergency blood collection campaign at ', h.hospital_name),
        'start_date', DATE(NOW()),
        'end_date', DATE(DATE_ADD(NOW(), INTERVAL 30 DAY)),
        'start_time', '09:00',
        'end_time', '17:00',
        'target_donors', 100,
        'max_capacity', 150,
        'organizer', h.contact_person,
        'location', CONCAT(h.hospital_name, ', ', h.city),
        'image_path', NULL,
        'status', 'active',
        'current_donors', 0,
        'campaign_type', 'blood_drive'
    ),
    CONCAT('Sample campaign for ', h.hospital_name)
FROM hospitals h 
WHERE h.is_approved = 1 
LIMIT 1;

-- ====================================================================
-- FILE SYSTEM REQUIREMENTS
-- ====================================================================

-- IMPORTANT: Create these directories in your web server:
-- 
-- uploads/                          (Main uploads directory)
-- └── campaigns/                    (Campaign images directory)
--     └── .htaccess                 (Security file - see below)
--
-- Create uploads/campaigns/.htaccess with this content to secure uploaded files:
-- <Files "*">
--     Order Allow,Deny
--     Allow from all
-- </Files>
-- <FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">
--     Order Allow,Deny
--     Deny from all
-- </FilesMatch>
--
-- Set proper permissions:
-- - uploads/ directory: 755 (rwxr-xr-x)
-- - campaigns/ directory: 755 (rwxr-xr-x)  
-- - uploaded files: 644 (rw-r--r--)

-- ====================================================================
-- API ENDPOINTS ADDED
-- ====================================================================

-- The following PHP API files support the campaign system:
-- 
-- php/create_campaign.php          - Create new campaigns (POST)
-- php/get_campaigns.php            - List all campaigns (GET)  
-- php/get_campaign_details.php     - Get campaign details (GET ?id=<campaign_id>)
-- php/get_campaign_stats.php       - Get campaign statistics (GET)
--
-- The following PHP API files support the audit logs system:
--
-- php/get_audit_logs.php           - Fetch audit logs with filtering and pagination (GET)
-- php/log_audit.php                - Log new audit events (POST)
-- php/get_audit_stats.php          - Get audit statistics and chart data (GET)
--
-- All APIs use self-contained PDO connections and return JSON responses
-- All APIs include proper error handling and fallback data

-- ====================================================================
-- COMPLETION MESSAGE
-- ====================================================================

SELECT 'HopeDrops Blood Bank Database setup completed successfully!' as Status,
       (SELECT COUNT(*) FROM users) as Total_Users,
       (SELECT COUNT(*) FROM hospitals) as Total_Hospitals,
       (SELECT COUNT(*) FROM appointments) as Sample_Appointments,
       (SELECT COUNT(*) FROM badges) as Available_Badges,
       (SELECT COUNT(*) FROM reward_items) as Available_Rewards,
       (SELECT COUNT(*) FROM audit_logs) as Sample_Audit_Logs;