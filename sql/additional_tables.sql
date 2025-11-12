-- Additional tables for HopeDrops reward and badge system
-- Execute this after running the main bloodbank_db.sql

USE bloodbank_db;

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

-- Add missing columns to hospitals table if they don't exist
ALTER TABLE hospitals 
ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) NULL,
ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) NULL,
ADD COLUMN IF NOT EXISTS hospital_type VARCHAR(50) DEFAULT 'General',
ADD COLUMN IF NOT EXISTS phone VARCHAR(15) NULL,
ADD COLUMN IF NOT EXISTS email VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;

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

-- Insert sample data for testing (only if tables are empty)
INSERT IGNORE INTO user_rewards (user_id, total_points, current_points, level, donations_count)
SELECT id, 0, 0, 1, 0 FROM users WHERE role = 'donor';