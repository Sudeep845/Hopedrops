-- HopeDrops Blood Bank Management System Database
-- Created: November 11, 2025
-- Description: MySQL database schema for Blood Bank Management Web Application

CREATE DATABASE IF NOT EXISTS bloodbank_db;
USE bloodbank_db;

-- Users table for authentication (Admin, Hospital, Donor)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'hospital', 'donor') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15),
    address TEXT,
    date_of_birth DATE,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'),
    gender ENUM('Male', 'Female', 'Other'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Hospitals table
CREATE TABLE hospitals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    hospital_name VARCHAR(100) NOT NULL,
    license_number VARCHAR(50) UNIQUE NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(50) NOT NULL,
    state VARCHAR(50) NOT NULL,
    pincode VARCHAR(10) NOT NULL,
    contact_person VARCHAR(100) NOT NULL,
    contact_phone VARCHAR(15) NOT NULL,
    contact_email VARCHAR(100) NOT NULL,
    emergency_contact VARCHAR(15),
    blood_storage JSON, -- Store blood type quantities as JSON
    facilities TEXT,
    accreditation VARCHAR(100),
    established_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_approved BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Blood inventory table
CREATE TABLE blood_inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    units_available INT DEFAULT 0,
    units_required INT DEFAULT 0,
    expiry_date DATE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    UNIQUE KEY unique_hospital_blood (hospital_id, blood_type)
);

-- Donations table
CREATE TABLE donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    donation_date DATE NOT NULL,
    donation_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled', 'approved', 'rejected') DEFAULT 'scheduled',
    units_donated INT DEFAULT 1,
    hemoglobin_level DECIMAL(3,1),
    weight DECIMAL(5,2),
    blood_pressure VARCHAR(20),
    health_status TEXT,
    certificate_generated BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

-- Donation campaigns table
CREATE TABLE campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    organizer_id INT NOT NULL,
    hospital_id INT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    target_units INT DEFAULT 0,
    collected_units INT DEFAULT 0,
    location VARCHAR(200),
    contact_info VARCHAR(100),
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    related_donation_id INT,
    related_campaign_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_donation_id) REFERENCES donations(id) ON DELETE SET NULL,
    FOREIGN KEY (related_campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
);

-- Rewards and points system
CREATE TABLE rewards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    points_earned INT DEFAULT 0,
    points_spent INT DEFAULT 0,
    total_points INT DEFAULT 0,
    donation_id INT,
    reward_type ENUM('donation', 'referral', 'campaign', 'milestone') DEFAULT 'donation',
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE SET NULL
);

-- Reward catalog table
CREATE TABLE reward_catalog (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reward_name VARCHAR(100) NOT NULL,
    description TEXT,
    points_required INT NOT NULL,
    category VARCHAR(50),
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User reward claims
CREATE TABLE reward_claims (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    reward_id INT NOT NULL,
    points_spent INT NOT NULL,
    claim_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'delivered', 'cancelled') DEFAULT 'pending',
    delivery_address TEXT,
    notes TEXT,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES reward_catalog(id) ON DELETE CASCADE
);

-- Blood requests table
CREATE TABLE blood_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    requester_id INT NOT NULL,
    hospital_id INT NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    units_needed INT NOT NULL,
    urgency ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    patient_name VARCHAR(100),
    patient_age INT,
    required_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending', 'fulfilled', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT INTO users (username, password, role, full_name, email, phone) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 'admin@hopedrops.com', '9999999999');

-- Insert sample reward catalog items
INSERT INTO reward_catalog (reward_name, description, points_required, category) VALUES
('Health Checkup Voucher', 'Free basic health checkup at partner clinic', 100, 'Healthcare'),
('Movie Ticket', 'Free movie ticket at partner theaters', 150, 'Entertainment'),
('Blood Donation T-Shirt', 'Official HopeDrops donation t-shirt', 200, 'Merchandise'),
('Gift Voucher ₹500', 'Shopping voucher worth ₹500', 250, 'Shopping'),
('Annual Health Package', 'Complete annual health checkup package', 500, 'Healthcare');

-- User tokens table for remember me and password reset
CREATE TABLE user_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    type ENUM('remember_me', 'password_reset', 'email_verification') NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_token_type (user_id, type),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

-- Activity logs table for audit trail
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Create indexes for better performance
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_donations_donor_id ON donations(donor_id);
CREATE INDEX idx_donations_hospital_id ON donations(hospital_id);
CREATE INDEX idx_donations_status ON donations(status);
CREATE INDEX idx_donations_date ON donations(donation_date);
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);
CREATE INDEX idx_rewards_donor_id ON rewards(donor_id);
CREATE INDEX idx_blood_inventory_hospital ON blood_inventory(hospital_id);
CREATE INDEX idx_blood_requests_hospital ON blood_requests(hospital_id);
CREATE INDEX idx_campaigns_active ON campaigns(is_active);

-- Create views for better data access
CREATE VIEW donor_stats AS
SELECT 
    u.id,
    u.full_name,
    u.blood_type,
    COUNT(d.id) as total_donations,
    COALESCE(SUM(r.total_points), 0) as total_points,
    MAX(d.donation_date) as last_donation_date
FROM users u
LEFT JOIN donations d ON u.id = d.donor_id AND d.status = 'completed'
LEFT JOIN rewards r ON u.id = r.donor_id
WHERE u.role = 'donor'
GROUP BY u.id, u.full_name, u.blood_type;

CREATE VIEW hospital_inventory AS
SELECT 
    h.id as hospital_id,
    h.hospital_name,
    h.city,
    bi.blood_type,
    bi.units_available,
    bi.units_required,
    bi.last_updated
FROM hospitals h
LEFT JOIN blood_inventory bi ON h.id = bi.hospital_id
WHERE h.is_approved = TRUE;

-- Stored procedures for common operations
DELIMITER //

CREATE PROCEDURE AddDonationPoints(
    IN donor_id INT,
    IN donation_id INT,
    IN points INT
)
BEGIN
    INSERT INTO rewards (donor_id, donation_id, points_earned, total_points, reward_type, description)
    VALUES (donor_id, donation_id, points, points, 'donation', 'Points earned for blood donation');
    
    -- Update total points for the donor
    UPDATE rewards SET total_points = (
        SELECT SUM(points_earned) - SUM(points_spent) 
        FROM rewards 
        WHERE donor_id = donor_id
    ) WHERE donor_id = donor_id;
END //

CREATE PROCEDURE UpdateBloodInventory(
    IN hospital_id INT,
    IN blood_type VARCHAR(3),
    IN units_change INT
)
BEGIN
    INSERT INTO blood_inventory (hospital_id, blood_type, units_available)
    VALUES (hospital_id, blood_type, units_change)
    ON DUPLICATE KEY UPDATE 
    units_available = units_available + units_change,
    last_updated = CURRENT_TIMESTAMP;
END //

DELIMITER ;

-- Sample data for testing
INSERT INTO users (username, password, role, full_name, email, phone, blood_type, gender, date_of_birth) VALUES
('hospital1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hospital', 'City General Hospital', 'contact@citygeneral.com', '9876543210', NULL, NULL, NULL),
('donor1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'donor', 'John Doe', 'john.doe@email.com', '9876543211', 'O+', 'Male', '1990-05-15'),
('donor2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'donor', 'Jane Smith', 'jane.smith@email.com', '9876543212', 'A+', 'Female', '1992-08-20');

INSERT INTO hospitals (user_id, hospital_name, license_number, address, city, state, pincode, contact_person, contact_phone, contact_email, is_approved) VALUES
(2, 'City General Hospital', 'HOSP001', '123 Main Street, Medical District', 'Mumbai', 'Maharashtra', '400001', 'Dr. Rajesh Kumar', '9876543210', 'contact@citygeneral.com', TRUE);

INSERT INTO blood_inventory (hospital_id, blood_type, units_available, units_required) VALUES
(1, 'A+', 25, 10),
(1, 'A-', 15, 5),
(1, 'B+', 20, 8),
(1, 'B-', 10, 3),
(1, 'AB+', 8, 2),
(1, 'AB-', 5, 2),
(1, 'O+', 30, 15),
(1, 'O-', 12, 8);

INSERT INTO campaigns (title, description, organizer_id, hospital_id, start_date, end_date, target_units, location) VALUES
('Emergency Blood Drive', 'Urgent blood donation drive for accident victims', 1, 1, '2025-11-15', '2025-11-20', 100, 'City General Hospital, Mumbai'),
('World Blood Donor Day Campaign', 'Annual blood donation campaign to celebrate World Blood Donor Day', 1, 1, '2025-06-10', '2025-06-16', 200, 'Multiple locations across the city');