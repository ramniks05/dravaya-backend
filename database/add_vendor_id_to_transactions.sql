-- Add vendor_id to transactions table
-- This links transactions to vendors for wallet management
-- Database: dravya

USE dravya;

-- Add vendor_id column if it doesn't exist
ALTER TABLE transactions 
ADD COLUMN IF NOT EXISTS vendor_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER id,
ADD COLUMN IF NOT EXISTS beneficiary_id INT DEFAULT NULL AFTER vendor_id,
ADD INDEX IF NOT EXISTS idx_vendor_id (vendor_id),
ADD INDEX IF NOT EXISTS idx_beneficiary_id (beneficiary_id);

-- Optional: Add foreign key constraints (uncomment if needed)
-- ALTER TABLE transactions 
-- ADD CONSTRAINT fk_transaction_vendor 
-- FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE SET NULL;

-- ALTER TABLE transactions 
-- ADD CONSTRAINT fk_transaction_beneficiary 
-- FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id) ON DELETE SET NULL;

