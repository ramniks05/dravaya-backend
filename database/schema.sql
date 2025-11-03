-- PayNinja Payout Database Schema
-- Database: dravya
-- Run this SQL in phpMyAdmin or MySQL command line

-- Create database if not exists (optional)
-- CREATE DATABASE IF NOT EXISTS dravya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE dravya;

-- Transactions Table
-- Stores all payout transactions
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_reference_id VARCHAR(100) NOT NULL UNIQUE,
    payninja_transaction_id VARCHAR(100) DEFAULT NULL,
    
    -- Beneficiary Information
    ben_name VARCHAR(255) NOT NULL,
    ben_phone_number VARCHAR(20) NOT NULL,
    ben_vpa_address VARCHAR(255) DEFAULT NULL,
    ben_account_number VARCHAR(50) DEFAULT NULL,
    ben_ifsc VARCHAR(20) DEFAULT NULL,
    ben_bank_name VARCHAR(255) DEFAULT NULL,
    
    -- Transaction Details
    transfer_type ENUM('UPI', 'IMPS', 'NEFT') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    narration VARCHAR(255) DEFAULT NULL,
    status ENUM('PENDING', 'SUCCESS', 'FAILED', 'PROCESSING') DEFAULT 'PENDING',
    
    -- API Response
    api_response TEXT DEFAULT NULL,
    api_error TEXT DEFAULT NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_merchant_ref (merchant_reference_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_transfer_type (transfer_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transaction Logs Table
-- Stores detailed logs of all API interactions
CREATE TABLE IF NOT EXISTS transaction_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT DEFAULT NULL,
    merchant_reference_id VARCHAR(100) NOT NULL,
    log_type ENUM('REQUEST', 'RESPONSE', 'ERROR', 'STATUS_CHECK') NOT NULL,
    log_data TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_merchant_ref (merchant_reference_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Balance History Table
-- Stores balance check history
CREATE TABLE IF NOT EXISTS balance_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    balance DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    api_response TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beneficiaries Table (Optional - for storing frequent beneficiaries)
CREATE TABLE IF NOT EXISTS beneficiaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    vpa_address VARCHAR(255) DEFAULT NULL,
    account_number VARCHAR(50) DEFAULT NULL,
    ifsc VARCHAR(20) DEFAULT NULL,
    bank_name VARCHAR(255) DEFAULT NULL,
    transfer_type ENUM('UPI', 'IMPS', 'NEFT') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_phone (phone_number),
    INDEX idx_transfer_type (transfer_type),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users Table for Authentication
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(36) PRIMARY KEY,  -- UUID
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'vendor') NOT NULL DEFAULT 'vendor',
    status ENUM('active', 'pending', 'suspended') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

