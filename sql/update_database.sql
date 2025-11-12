-- Database Update Script for HopeDrops
-- Execute this to add missing columns and tables

USE bloodbank_db;

-- Add missing columns to hospitals table
ALTER TABLE hospitals 
ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) NULL,
ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) NULL,
ADD COLUMN IF NOT EXISTS hospital_type VARCHAR(50) DEFAULT 'General',
ADD COLUMN IF NOT EXISTS phone VARCHAR(15) NULL,
ADD COLUMN IF NOT EXISTS email VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;

-- Update existing hospitals to be active and copy contact info
UPDATE hospitals SET 
    is_active = TRUE,
    phone = contact_phone,
    email = contact_email
WHERE phone IS NULL OR email IS NULL;

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

-- Insert sample badges (only if table is empty)
INSERT INTO badges (name, description, icon, category, requirements, points_awarded) 
SELECT * FROM (
    SELECT 'First Donation' as name, 'Congratulations on your first blood donation!' as description, 'fa-heart' as icon, 'milestone' as category, 'Complete first donation' as requirements, 50 as points_awarded
    UNION ALL SELECT 'Life Saver', 'Your donations have helped save lives', 'fa-life-ring', 'achievement', 'Complete 25 donations', 500
    UNION ALL SELECT 'Blood Hero', 'You are a true blood donation hero!', 'fa-trophy', 'achievement', 'Complete 50 donations', 1000
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM badges LIMIT 1);

-- Insert sample reward items (only if table is empty)
INSERT INTO reward_items (name, description, points_cost, category, stock_quantity) 
SELECT * FROM (
    SELECT 'HopeDrops T-Shirt' as name, 'Official HopeDrops branded t-shirt' as description, 200 as points_cost, 'merchandise' as category, 50 as stock_quantity
    UNION ALL SELECT 'Blood Donor Badge Pin', 'Metal pin badge for blood donors', 100, 'merchandise', 100
    UNION ALL SELECT 'Health Check Voucher', 'Free basic health checkup at partner clinics', 500, 'health', 20
    UNION ALL SELECT 'Coffee Shop Voucher', '$10 voucher for local coffee shops', 150, 'food', 30
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM reward_items LIMIT 1);

-- Initialize user rewards for existing donors
INSERT INTO user_rewards (user_id, total_points, current_points, level, donations_count)
SELECT u.id, 0, 0, 1, 0 
FROM users u 
WHERE u.role = 'donor' 
AND NOT EXISTS (SELECT 1 FROM user_rewards ur WHERE ur.user_id = u.id);

-- Add some sample donations for testing (only for donor1 if no donations exist)
INSERT INTO donations (donor_id, hospital_id, blood_type, donation_date, donation_time, status, units_donated, hemoglobin_level, weight, blood_pressure, notes)
SELECT * FROM (
    SELECT 
        (SELECT id FROM users WHERE username = 'donor1' AND role = 'donor' LIMIT 1) as donor_id,
        1 as hospital_id,
        'O+' as blood_type,
        '2025-10-15' as donation_date,
        '10:30:00' as donation_time,
        'completed' as status,
        1 as units_donated,
        14.5 as hemoglobin_level,
        70.5 as weight,
        '120/80' as blood_pressure,
        'Successful donation, good health' as notes
    UNION ALL SELECT
        (SELECT id FROM users WHERE username = 'donor1' AND role = 'donor' LIMIT 1),
        2,
        'O+',
        '2025-08-10',
        '14:15:00',
        'completed',
        1,
        14.8,
        71.0,
        '118/78',
        'Regular donation, excellent condition'
    UNION ALL SELECT
        (SELECT id FROM users WHERE username = 'donor1' AND role = 'donor' LIMIT 1),
        1,
        'O+',
        '2025-06-05',
        '09:45:00',
        'completed',
        1,
        15.0,
        70.8,
        '122/82',
        'First donation of the year'
) AS sample_donations
WHERE NOT EXISTS (SELECT 1 FROM donations LIMIT 1) 
AND (SELECT id FROM users WHERE username = 'donor1' AND role = 'donor' LIMIT 1) IS NOT NULL;

-- Add some sample hospitals if none exist
INSERT INTO hospitals (user_id, hospital_name, license_number, address, city, state, pincode, contact_person, contact_phone, contact_email, emergency_contact, is_approved, is_active)
SELECT * FROM (
    SELECT 1 as user_id, 'City General Hospital' as hospital_name, 'CGH001' as license_number, '123 Main Street' as address, 'Downtown' as city, 'State' as state, '12345' as pincode, 'Dr. Smith' as contact_person, '555-0101' as contact_phone, 'contact@citygeneral.com' as contact_email, '555-0911' as emergency_contact, TRUE as is_approved, TRUE as is_active
    UNION ALL SELECT 1, 'Regional Medical Center', 'RMC002', '456 Health Ave', 'Midtown', 'State', '12346', 'Dr. Johnson', '555-0102', 'info@regional.com', '555-0912', TRUE, TRUE
    UNION ALL SELECT 1, 'Community Blood Bank', 'CBB003', '789 Care Blvd', 'Uptown', 'State', '12347', 'Ms. Williams', '555-0103', 'blood@community.org', '555-0913', TRUE, TRUE
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM hospitals LIMIT 1);

-- Add some sample blood inventory
INSERT INTO blood_inventory (hospital_id, blood_type, units_available, last_updated)
SELECT h.id, bt.blood_type, FLOOR(RAND() * 20) + 5, NOW()
FROM hospitals h
CROSS JOIN (
    SELECT 'A+' as blood_type UNION ALL SELECT 'A-' UNION ALL SELECT 'B+' UNION ALL SELECT 'B-'
    UNION ALL SELECT 'AB+' UNION ALL SELECT 'AB-' UNION ALL SELECT 'O+' UNION ALL SELECT 'O-'
) bt
WHERE NOT EXISTS (SELECT 1 FROM blood_inventory bi WHERE bi.hospital_id = h.id AND bi.blood_type = bt.blood_type);

-- Update user rewards based on completed donations
UPDATE user_rewards ur
SET 
    donations_count = (
        SELECT COUNT(*) 
        FROM donations d 
        WHERE d.donor_id = ur.user_id AND d.status = 'completed'
    ),
    total_points = (
        SELECT COUNT(*) * 100 
        FROM donations d 
        WHERE d.donor_id = ur.user_id AND d.status = 'completed'
    ),
    current_points = (
        SELECT COUNT(*) * 100 
        FROM donations d 
        WHERE d.donor_id = ur.user_id AND d.status = 'completed'
    )
WHERE EXISTS (
    SELECT 1 FROM donations d WHERE d.donor_id = ur.user_id AND d.status = 'completed'
);

SELECT 'Database update completed successfully!' as Message;