-- Fix Hospital Registration Tables
-- Run this if hospitals table doesn't exist or has issues

-- Create hospitals table if it doesn't exist
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
    is_approved TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create blood_inventory table if it doesn't exist
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

-- Add indexes for better performance
CREATE INDEX idx_hospitals_user_id ON hospitals(user_id);
CREATE INDEX idx_hospitals_city ON hospitals(city);
CREATE INDEX idx_hospitals_approved ON hospitals(is_approved);
CREATE INDEX idx_blood_inventory_hospital_id ON blood_inventory(hospital_id);
CREATE INDEX idx_blood_inventory_blood_type ON blood_inventory(blood_type);

-- Insert sample blood types for testing if none exist
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
WHERE NOT EXISTS (
    SELECT 1 FROM blood_inventory bi 
    WHERE bi.hospital_id = h.id AND bi.blood_type = bt.blood_type
);

SELECT 'Hospital registration tables created/updated successfully!' as message;