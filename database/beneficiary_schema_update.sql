-- Beneficiary Table Schema Update
-- Add vendor_id column to link beneficiaries to vendors
-- Database: dravya

USE dravya;

-- Add vendor_id column if it doesn't exist
ALTER TABLE beneficiaries 
ADD COLUMN IF NOT EXISTS vendor_id VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER id,
ADD INDEX IF NOT EXISTS idx_vendor_id (vendor_id);

-- If you want to add foreign key constraint (optional, uncomment if needed):
-- ALTER TABLE beneficiaries 
-- ADD CONSTRAINT fk_beneficiary_vendor 
-- FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE;

-- Update existing beneficiaries if you want to assign them to a vendor (optional):
-- UPDATE beneficiaries SET vendor_id = 'your-vendor-uuid' WHERE vendor_id IS NULL;

