-- Wallet and Topup Request Management Schema (Fixed Version)
-- Database: dravya
-- Run this SQL in phpMyAdmin or MySQL command line
-- IMPORTANT: Make sure the 'users' table exists first!

-- Select database
USE dravya;

-- First, let's verify and fix the users.id column charset if needed
-- This ensures foreign key compatibility
ALTER TABLE users MODIFY id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;

-- Vendor Wallets Table
-- Stores wallet balance for each vendor
CREATE TABLE IF NOT EXISTS vendor_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    balance DECIMAL(15, 2) DEFAULT 0.00,
    currency VARCHAR(10) DEFAULT 'INR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_vendor (vendor_id),
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_vendor_id (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topup Requests Table
-- Stores topup/recharge requests from vendors
CREATE TABLE IF NOT EXISTS topup_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    request_id VARCHAR(100) NOT NULL UNIQUE,
    amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    admin_notes TEXT DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_status (status),
    INDEX idx_request_id (request_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wallet Transactions Table
-- Stores all wallet transactions (topup, payout deductions, etc.)
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    transaction_type ENUM('topup', 'deduction', 'refund', 'adjustment') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    balance_before DECIMAL(15, 2) NOT NULL,
    balance_after DECIMAL(15, 2) NOT NULL,
    reference_id VARCHAR(100) DEFAULT NULL,
    topup_request_id INT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (topup_request_id) REFERENCES topup_requests(id) ON DELETE SET NULL,
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_reference_id (reference_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialize wallets for existing vendors (optional)
-- INSERT INTO vendor_wallets (vendor_id, balance) 
-- SELECT id, 0.00 FROM users WHERE role = 'vendor' 
-- ON DUPLICATE KEY UPDATE balance = balance;

