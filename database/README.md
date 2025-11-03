# Database Setup Guide

## Quick Setup

1. **Create Database**
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Create a new database named `dravya`
   - Or run this SQL:
     ```sql
     CREATE DATABASE dravya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     ```

2. **Import Schema**
   - Open phpMyAdmin and select the `dravya` database
   - Click on "Import" tab
   - Choose the file `database/schema.sql`
   - Click "Go" to import

   **OR** copy and paste the SQL from `database/schema.sql` into phpMyAdmin SQL tab and execute.

3. **Configure Database Credentials**
   - Create a `.env` file in the backend root directory
   - Copy from `.env.example` and update with your credentials:
     ```
     DB_HOST=localhost
     DB_USER=root
     DB_PASS=
     DB_NAME=dravya
     ```

## Database Tables

### 1. `transactions`
Stores all payout transactions with complete details.

**Columns:**
- `id` - Primary key
- `merchant_reference_id` - Unique transaction reference
- `payninja_transaction_id` - PayNinja's transaction ID
- Beneficiary information (name, phone, account details)
- Transaction details (type, amount, status)
- API responses and errors
- Timestamps

### 2. `transaction_logs`
Stores detailed logs of all API interactions.

**Columns:**
- `id` - Primary key
- `transaction_id` - Foreign key to transactions table
- `merchant_reference_id` - Transaction reference
- `log_type` - REQUEST, RESPONSE, ERROR, or STATUS_CHECK
- `log_data` - JSON data of the log
- `created_at` - Timestamp

### 3. `balance_history`
Stores history of balance checks.

**Columns:**
- `id` - Primary key
- `balance` - Account balance
- `currency` - Currency (default: INR)
- `api_response` - Full API response JSON
- `created_at` - Timestamp

### 4. `beneficiaries` (Optional)
Stores frequently used beneficiaries.

**Columns:**
- `id` - Primary key
- Beneficiary details (name, phone, account info)
- `transfer_type` - UPI, IMPS, or NEFT
- `is_active` - Active status
- Timestamps

## Testing Database Connection

Visit `http://localhost/backend/index.php` to see database connection status and table information.

## Troubleshooting

1. **Connection Failed**
   - Check MySQL service is running in XAMPP Control Panel
   - Verify database credentials in `.env` file
   - Ensure database `dravya` exists

2. **Tables Not Found**
   - Run the schema.sql file in phpMyAdmin
   - Check for SQL errors during import

3. **Permission Errors**
   - Ensure database user has CREATE, INSERT, UPDATE, SELECT permissions
   - For XAMPP, default `root` user should have all permissions

