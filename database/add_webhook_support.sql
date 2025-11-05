-- Migration: Add Webhook Support
-- Add UTR field to transactions table and WEBHOOK log type to transaction_logs

-- Step 1: Add UTR column to transactions table (if not exists)
ALTER TABLE transactions 
ADD COLUMN IF NOT EXISTS utr VARCHAR(100) DEFAULT NULL COMMENT 'Unique Transaction Reference from bank';

-- Add index for UTR lookups
ALTER TABLE transactions 
ADD INDEX IF NOT EXISTS idx_utr (utr);

-- Step 2: Update transaction_logs log_type enum to include WEBHOOK
-- Note: MySQL doesn't support IF EXISTS for ENUM modification, so we need to check first
-- If column already has WEBHOOK, this will fail - that's okay, just ignore the error

-- Check current enum values and modify if needed
ALTER TABLE transaction_logs 
MODIFY COLUMN log_type ENUM('REQUEST', 'RESPONSE', 'ERROR', 'STATUS_CHECK', 'WEBHOOK') NOT NULL;

