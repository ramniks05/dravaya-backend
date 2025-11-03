# Vendor Payout Management API Documentation

Complete API reference for vendor payout management with beneficiary support and wallet deduction.

---

## Base URL
```
http://localhost/backend/api/vendor/payout/
```

---

## Setup

**Important:** Before using vendor payout API, run the schema update:

```sql
-- Run: database/add_vendor_id_to_transactions.sql
-- This adds vendor_id and beneficiary_id columns to transactions table
```

---

## Vendor Payout API

### Initiate Payout

**Endpoint:** `POST /api/vendor/payout/initiate.php`

Vendor can make payments using saved beneficiaries or manual entry. Amount is automatically deducted from vendor wallet.

#### Request Headers
```
Content-Type: application/json
```

#### Request Body (Using Saved Beneficiary)
```json
{
  "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
  "beneficiary_id": 1,
  "amount": 100,
  "narration": "pay"
}
```

**Note:** Make sure to send this as proper JSON with:
- Content-Type header: `application/json`
- All keys must be in double quotes
- Send as raw body, not form-data

#### Request Body (Manual Entry - UPI)
```json
{
  "vendor_id": "vendor-uuid-here",
  "amount": 1000.00,
  "transfer_type": "UPI",
  "ben_name": "John Doe",
  "ben_phone_number": "9876543210",
  "ben_vpa_address": "john.doe@upi",
  "narration": "Payment for services",
  "merchant_reference_id": "optional-custom-id"
}
```

#### Request Body (Manual Entry - IMPS/NEFT)
```json
{
  "vendor_id": "vendor-uuid-here",
  "amount": 1000.00,
  "transfer_type": "IMPS",
  "ben_name": "John Doe",
  "ben_phone_number": "9876543210",
  "ben_account_number": "1234567890",
  "ben_ifsc": "HDFC0001234",
  "ben_bank_name": "HDFC Bank",
  "narration": "Payment for services",
  "merchant_reference_id": "optional-custom-id"
}
```

#### Request Fields

**Required Fields (Using Beneficiary):**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `vendor_id` | string | Yes | Vendor UUID |
| `beneficiary_id` | integer | Yes | Saved beneficiary ID |
| `amount` | number | Yes | Amount to transfer (must be > 0) |

**Required Fields (Manual Entry):**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `vendor_id` | string | Yes | Vendor UUID |
| `amount` | number | Yes | Amount to transfer |
| `transfer_type` | string | Yes | `UPI`, `IMPS`, or `NEFT` |
| `ben_name` | string | Yes | Beneficiary name |
| `ben_phone_number` | string | Yes | 10-digit Indian mobile number |

**Conditional Fields (Manual Entry):**
- **For UPI:** `ben_vpa_address` (required)
- **For IMPS/NEFT:** `ben_account_number`, `ben_ifsc`, `ben_bank_name` (all required)

**Optional Fields:**
| Field | Type | Description |
|-------|------|-------------|
| `narration` | string | Payment description (default: "PAYNINJA Fund Transfer") |
| `merchant_reference_id` | string | Custom transaction ID (auto-generated if not provided) |

#### Response (200 OK - Success)
```json
{
  "status": "success",
  "message": "Payout initiated successfully",
  "data": {
    "transaction": {
      "merchant_reference_id": "PAYOUT_1704110400_abc12345",
      "payninja_transaction_id": "TXN123456",
      "amount": 1000.00,
      "transfer_type": "UPI",
      "status": "PENDING"
    },
    "wallet": {
      "balance_before": 10000.00,
      "balance_after": 9000.00,
      "deducted": true
    },
    "beneficiary_used": true,
    "payninja_response": {
      "status": "success",
      "data": {
        "transaction_id": "TXN123456",
        "message": "Transaction initiated"
      }
    }
  }
}
```

#### Response (400 Bad Request)
```json
{
  "status": "error",
  "message": "vendor_id is required"
}
```

```json
{
  "status": "error",
  "message": "Insufficient wallet balance. Available: 500.00"
}
```

```json
{
  "status": "error",
  "message": "Beneficiary not found or inactive"
}
```

```json
{
  "status": "error",
  "message": "Vendor account is not active"
}
```

#### Response (400 Bad Request - API Error)
```json
{
  "status": "error",
  "message": "Fund transfer request failed"
}
```

**Note:** If PayNinja API fails, wallet is NOT deducted and transaction is saved with FAILED status.

---

## Complete Workflow Example

### Step 1: Create Beneficiary (Optional)
```
POST /api/vendor/beneficiaries/create.php
{
  "vendor_id": "...",
  "name": "John Doe",
  "phone_number": "9876543210",
  "transfer_type": "UPI",
  "vpa_address": "john@upi"
}
```

### Step 2: Check Wallet Balance
```
GET /api/vendor/wallet/balance.php?vendor_id=...
```

### Step 3: Initiate Payout Using Beneficiary
```
POST /api/vendor/payout/initiate.php
{
  "vendor_id": "...",
  "beneficiary_id": 1,
  "amount": 1000.00,
  "narration": "Monthly payment"
}
```

### Step 4: Or Initiate Payout with Manual Entry
```
POST /api/vendor/payout/initiate.php
{
  "vendor_id": "...",
  "amount": 1000.00,
  "transfer_type": "UPI",
  "ben_name": "John Doe",
  "ben_phone_number": "9876543210",
  "ben_vpa_address": "john@upi",
  "narration": "Monthly payment"
}
```

### Step 5: Check Transaction Status
```
POST /api/payout/status.php
{
  "merchant_reference_id": "PAYOUT_1704110400_abc12345"
}
```

---

## Important Notes

1. **Wallet Balance Check**: API automatically checks wallet balance before initiating payout.

2. **Automatic Deduction**: Amount is deducted from vendor wallet ONLY after successful PayNinja API call.

3. **Beneficiary Validation**: 
   - Beneficiary must belong to the vendor
   - Beneficiary must be active
   - Transfer type must match beneficiary's transfer type

4. **Transaction Linking**: 
   - Transactions are linked to vendor via `vendor_id`
   - If beneficiary is used, `beneficiary_id` is also saved

5. **Error Handling**: 
   - If PayNinja API fails, wallet is NOT deducted
   - Transaction is saved with FAILED status for audit

6. **Merchant Reference ID**: 
   - Auto-generated if not provided: `PAYOUT_{timestamp}_{random}`
   - Must be unique across all transactions

7. **Validation**: 
   - Phone number must be valid 10-digit Indian number
   - Amount must be greater than 0
   - Vendor must be active
   - Wallet must have sufficient balance

---

## Error Responses

All endpoints return errors in this format:

```json
{
  "status": "error",
  "message": "Error message here"
}
```

### HTTP Status Codes
- `200`: Success
- `400`: Bad Request (validation errors, insufficient balance, etc.)
- `405`: Method Not Allowed

### Common Error Messages
- `"vendor_id is required"`
- `"amount is required"`
- `"Insufficient wallet balance. Available: X.XX"`
- `"Vendor not found"`
- `"Vendor account is not active"`
- `"Beneficiary not found or inactive"`
- `"Transfer type does not match beneficiary transfer type"`
- `"ben_vpa_address is required for UPI transfers"`
- `"Account details (account_number, ifsc, bank_name) are required for IMPS/NEFT transfers"`
- `"Invalid phone number format"`
- `"Amount must be greater than 0"`

---

## API Endpoint Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/vendor/payout/initiate.php` | Initiate payout (with beneficiary or manual entry) |

---

## Database Changes Required

Run this SQL to add vendor tracking to transactions:

```sql
-- database/add_vendor_id_to_transactions.sql
ALTER TABLE transactions 
ADD COLUMN vendor_id VARCHAR(36) DEFAULT NULL,
ADD COLUMN beneficiary_id INT DEFAULT NULL,
ADD INDEX idx_vendor_id (vendor_id),
ADD INDEX idx_beneficiary_id (beneficiary_id);
```

---

## Security Notes

1. **Authentication**: Currently accepts `vendor_id` as parameter. Implement token-based authentication.

2. **Authorization**: Vendor can only make payouts from their own wallet.

3. **Validation**: All inputs are validated and sanitized.

4. **Audit Trail**: All transactions are logged with vendor_id for tracking.

