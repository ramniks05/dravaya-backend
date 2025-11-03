# PayNinja Payout Backend - Setup Guide

## Prerequisites

- XAMPP installed and running (Apache + MySQL)
- PHP 7.4 or higher
- MySQL/MariaDB running

## Quick Setup Steps

### 1. Database Setup

1. **Start XAMPP Services**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

2. **Create Database**
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Create database named `dravya`:
     ```sql
     CREATE DATABASE dravya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     ```

3. **Import Schema**
   - Select the `dravya` database in phpMyAdmin
   - Click "Import" tab
   - Select file: `database/schema.sql`
   - Click "Go"

   **OR** copy/paste SQL from `database/schema.sql` into SQL tab

### 2. Configuration

1. **Environment Variables (Optional)**
   - Create `.env` file in backend root directory
   - Add your database credentials:
     ```
     DB_HOST=localhost
     DB_USER=root
     DB_PASS=
     DB_NAME=dravya
     
     API_BASE_URL=https://dashboard.payninja.in
     API_KEY=your_api_key
     SECRET_KEY=your_secret_key
     API_CODE=810
     ```
   
   **Note:** If `.env` file doesn't exist, the system will use default values from `config.php`

2. **Default Configuration (if no .env file)**
   - Database: `localhost`, user: `root`, password: `` (empty), database: `dravya`
   - These defaults work for XAMPP setup

### 3. Verify Installation

1. **Access Status Page**
   - Open browser: `http://localhost/backend/` or `http://localhost/backend/index.php`
   - Check all status indicators:
     - ✅ Database connection
     - ✅ Required PHP extensions
     - ✅ Database tables
     - ✅ API configuration

2. **Test API Endpoint**
   - Click "Test Balance API" button on status page
   - Should see API response or error message

### 4. API Endpoints

Your backend API is now ready at:
- **Base URL:** `http://localhost/backend/api/payout/`
- **Balance:** `GET /api/payout/balance.php`
- **Initiate:** `POST /api/payout/initiate.php`
- **Status:** `POST /api/payout/status.php`

## React Frontend Integration

In your React app, use these endpoints:

```javascript
const API_BASE_URL = 'http://localhost/backend/api/payout';

// Get Balance
fetch(`${API_BASE_URL}/balance.php`)
  .then(res => res.json())
  .then(data => console.log(data));

// Initiate Transfer
fetch(`${API_BASE_URL}/initiate.php`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    ben_name: "John Doe",
    ben_phone_number: "9876543210",
    ben_vpa_address: "john@upi",
    amount: "1000",
    merchant_reference_id: "TXN123",
    transfer_type: "UPI"
  })
})
  .then(res => res.json())
  .then(data => console.log(data));
```

## Troubleshooting

### Database Connection Failed
- ✅ Check MySQL is running in XAMPP Control Panel
- ✅ Verify database `dravya` exists
- ✅ Check database credentials in `config.php` or `.env`
- ✅ Ensure MySQLi extension is enabled in PHP

### Tables Not Found
- ✅ Run `database/schema.sql` in phpMyAdmin
- ✅ Check for SQL errors during import
- ✅ Verify you selected the correct database

### CORS Errors (React Frontend)
- ✅ Check `config.php` has your React app URL in `$allowedOrigins`
- ✅ Default: `http://localhost:3000` is already configured

### API Errors
- ✅ Check API credentials are correct
- ✅ Verify PayNinja API is accessible
- ✅ Check `logs/error.log` for detailed errors

## File Structure

```
backend/
├── api/
│   └── payout/
│       ├── balance.php      # Get account balance
│       ├── initiate.php     # Initiate fund transfer
│       └── status.php       # Check transaction status
├── database/
│   ├── schema.sql          # Database schema
│   ├── functions.php       # Database helper functions
│   └── README.md           # Database documentation
├── logs/                   # Application logs (auto-created)
├── config.php              # Main configuration file
├── index.php               # Status dashboard
├── router.php              # PHP built-in server router
├── .htaccess               # Apache configuration
└── API_DOCUMENTATION.md    # Full API documentation
```

## Security Notes

1. **Production Deployment:**
   - Change default database password
   - Update CORS origins to your production domain
   - Use `.env` file for all sensitive credentials
   - Never commit `.env` file to version control

2. **Environment Variables:**
   - The `.env` file is already in `.gitignore`
   - Always use `.env` for production credentials

## Support

- Check `logs/error.log` for detailed error messages
- Review `API_DOCUMENTATION.md` for API usage examples
- Check database status on `index.php` status page

