-- Wallet and Topup Request Management Schema (Simple Version - No Foreign Keys)
-- Use this if foreign key constraints keep failing
-- Database: dravya

USE dravya;

-- Vendor Wallets Table (without foreign key - you can add it manually later)
CREATE TABLE IF NOT EXISTS vendor_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id VARCHAR(36) NOT NULL,
    balance DECIMAL(15, 2) DEFAULT 0.00,
    currency VARCHAR(10) DEFAULT 'INR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_vendor (vendor_id),
    INDEX idx_vendor_id (vendor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topup Requests Table
CREATE TABLE IF NOT EXISTS topup_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id VARCHAR(36) NOT NULL,
    request_id VARCHAR(100) NOT NULL UNIQUE,
    amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_id VARCHAR(36) DEFAULT NULL,
    admin_notes TEXT DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_status (status),
    INDEX idx_request_id (request_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wallet Transactions Table
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id VARCHAR(36) NOT NULL,
    transaction_type ENUM('topup', 'deduction', 'refund', 'adjustment') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    balance_before DECIMAL(15, 2) NOT NULL,
    balance_after DECIMAL(15, 2) NOT NULL,
    reference_id VARCHAR(100) DEFAULT NULL,
    topup_request_id INT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_vendor_id (vendor_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_reference_id (reference_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign keys manually after tables are created (optional)
-- ALTER TABLE vendor_wallets ADD CONSTRAINT fk_vendor_wallet_user 
--     FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE;

-- ALTER TABLE topup_requests ADD CONSTRAINT fk_topup_vendor 
--     FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE;

-- ALTER TABLE wallet_transactions ADD CONSTRAINT fk_wallet_txn_vendor 
--     FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE;

-- ALTER TABLE wallet_transactions ADD CONSTRAINT fk_wallet_txn_topup 
--     FOREIGN KEY (topup_request_id) REFERENCES topup_requests(id) ON DELETE SET NULL;

