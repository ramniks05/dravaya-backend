# Topup Request & Wallet Management API Documentation

Complete API reference for topup requests and wallet management system.

---

## Base URLs

**Vendor APIs:**
```
http://localhost/backend/api/vendor/
```

**Admin APIs:**
```
http://localhost/backend/api/admin/topup/
```

---

## Vendor APIs

### 1. Get Wallet Balance

**Endpoint:** `GET /api/vendor/wallet/balance.php`

Get the current wallet balance for a vendor.

#### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `vendor_id` | string | Yes | Vendor UUID |

#### Example Request
```
GET /api/vendor/wallet/balance.php?vendor_id=uuid-here
```

#### Response (200 OK)
```json
{
  "status": "success",
  "data": {
    "vendor_id": "uuid-here",
    "vendor_email": "vendor@example.com",
    "balance": 5000.00,
    "currency": "INR",
    "updated_at": "2024-01-01 12:00:00"
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

---

### 2. Submit Topup Request

**Endpoint:** `POST /api/vendor/topup/request.php`

Submit a request to add funds to wallet (requires admin approval).

#### Request Headers
```
Content-Type: application/json
```

#### Request Body
```json
{
  "vendor_id": "uuid-here",
  "amount": 10000.00,
  "request_id": "optional-custom-request-id"
}
```

#### Request Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `vendor_id` | string | Yes | Vendor UUID |
| `amount` | number | Yes | Amount to topup (must be > 0 and <= 10,00,000) |
| `request_id` | string | No | Custom request ID (auto-generated if not provided) |

#### Example Request
```
POST /api/vendor/topup/request.php
Content-Type: application/json

{
  "vendor_id": "123e4567-e89b-12d3-a456-426614174000",
  "amount": 5000.00
}
```

#### Response (200 OK)
```json
{
  "status": "success",
  "message": "Topup request submitted successfully. Waiting for admin approval.",
  "data": {
    "request": {
      "id": 1,
      "request_id": "TOPUP_1704110400_abc12345",
      "vendor_id": "123e4567-e89b-12d3-a456-426614174000",
      "vendor_email": "vendor@example.com",
      "amount": 5000.00,
      "currency": "INR",
      "status": "pending",
      "created_at": "2024-01-01 12:00:00"
    }
  }
}
```

#### Response (400 Bad Request)
```json
{
  "status": "error",
  "message": "amount is required"
}
```

```json
{
  "status": "error",
  "message": "Amount must be greater than 0"
}
```

```json
{
  "status": "error",
  "message": "Vendor account is not active. Cannot submit topup request."
}
```

---

### 3. Get Wallet Transaction History

**Endpoint:** `GET /api/vendor/wallet/transactions.php`

Get transaction history for vendor wallet.

#### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `vendor_id` | string | Yes | Vendor UUID |
| `page` | integer | No | Page number (default: 1) |
| `limit` | integer | No | Items per page (default: 50, max: 100) |

#### Example Request
```
GET /api/vendor/wallet/transactions.php?vendor_id=uuid-here&page=1&limit=20
```

#### Response (200 OK)
```json
{
  "status": "success",
  "data": {
    "vendor_id": "uuid-here",
    "transactions": [
      {
        "id": 1,
        "transaction_type": "topup",
        "amount": 5000.00,
        "currency": "INR",
        "balance_before": 0.00,
        "balance_after": 5000.00,
        "reference_id": "TOPUP_1704110400_abc12345",
        "description": "Topup request approved - Request ID: TOPUP_1704110400_abc12345",
        "created_at": "2024-01-01 12:30:00"
      },
      {
        "id": 2,
        "transaction_type": "deduction",
        "amount": 1000.00,
        "currency": "INR",
        "balance_before": 5000.00,
        "balance_after": 4000.00,
        "reference_id": "TXN123456789",
        "description": "Payout transaction",
        "created_at": "2024-01-01 13:00:00"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 2,
      "total_pages": 1
    }
  }
}
```

#### Transaction Types
- `topup`: Amount added to wallet (approved topup request)
- `deduction`: Amount deducted from wallet (payout transactions)
- `refund`: Amount refunded to wallet
- `adjustment`: Manual adjustment by admin

---

## Admin APIs

### 4. List Topup Requests

**Endpoint:** `GET /api/admin/topup/list.php`

Get list of all topup requests with optional filtering.

#### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | Filter by status: `pending`, `approved`, `rejected` |
| `vendor_id` | string | No | Filter by vendor ID |
| `page` | integer | No | Page number (default: 1) |
| `limit` | integer | No | Items per page (default: 50, max: 100) |

#### Example Requests
```
GET /api/admin/topup/list.php
GET /api/admin/topup/list.php?status=pending
GET /api/admin/topup/list.php?status=pending&page=1&limit=25
GET /api/admin/topup/list.php?vendor_id=uuid-here
```

#### Response (200 OK)
```json
{
  "status": "success",
  "data": {
    "requests": [
      {
        "id": 1,
        "request_id": "TOPUP_1704110400_abc12345",
        "vendor_id": "123e4567-e89b-12d3-a456-426614174000",
        "vendor_email": "vendor@example.com",
        "vendor_status": "active",
        "amount": 5000.00,
        "currency": "INR",
        "status": "pending",
        "admin_id": null,
        "admin_email": null,
        "admin_notes": null,
        "rejection_reason": null,
        "created_at": "2024-01-01 12:00:00",
        "updated_at": "2024-01-01 12:00:00",
        "processed_at": null
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 50,
      "total": 10,
      "total_pages": 1
    },
    "filters": {
      "status": "pending",
      "vendor_id": null
    }
  }
}
```

---

### 5. Approve/Reject Topup Request

**Endpoint:** `POST /api/admin/topup/approve.php`

Approve or reject a topup request. When approved, amount is automatically added to vendor wallet.

#### Request Headers
```
Content-Type: application/json
```

#### Request Body
```json
{
  "request_id": "TOPUP_1704110400_abc12345",
  "action": "approve",
  "admin_id": "admin-uuid-here",
  "admin_notes": "Approved after verification",
  "rejection_reason": null
}
```

#### Request Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `request_id` | string | Yes | Topup request ID |
| `action` | string | Yes | Action: `approve` or `reject` |
| `admin_id` | string | Yes | Admin UUID (who is processing) |
| `admin_notes` | string | No | Admin notes (for approved requests) |
| `rejection_reason` | string | No | Reason for rejection (for rejected requests) |

#### Example Requests

**Approve:**
```json
{
  "request_id": "TOPUP_1704110400_abc12345",
  "action": "approve",
  "admin_id": "admin-uuid-here",
  "admin_notes": "Verified payment received"
}
```

**Reject:**
```json
{
  "request_id": "TOPUP_1704110400_abc12345",
  "action": "reject",
  "admin_id": "admin-uuid-here",
  "rejection_reason": "Payment verification failed"
}
```

#### Response (200 OK - Approved)
```json
{
  "status": "success",
  "message": "Topup request approved successfully",
  "data": {
    "request": {
      "id": 1,
      "request_id": "TOPUP_1704110400_abc12345",
      "vendor_id": "123e4567-e89b-12d3-a456-426614174000",
      "vendor_email": "vendor@example.com",
      "amount": 5000.00,
      "status": "approved",
      "admin_id": "admin-uuid-here",
      "admin_notes": "Verified payment received",
      "rejection_reason": null,
      "processed_at": "2024-01-01 12:30:00"
    },
    "wallet_balance": 5000.00
  }
}
```

#### Response (200 OK - Rejected)
```json
{
  "status": "success",
  "message": "Topup request rejected successfully",
  "data": {
    "request": {
      "id": 1,
      "request_id": "TOPUP_1704110400_abc12345",
      "vendor_id": "123e4567-e89b-12d3-a456-426614174000",
      "vendor_email": "vendor@example.com",
      "amount": 5000.00,
      "status": "rejected",
      "admin_id": "admin-uuid-here",
      "admin_notes": null,
      "rejection_reason": "Payment verification failed",
      "processed_at": "2024-01-01 12:30:00"
    },
    "wallet_balance": null
  }
}
```

#### Response (400 Bad Request)
```json
{
  "status": "error",
  "message": "request_id is required"
}
```

```json
{
  "status": "error",
  "message": "action must be either \"approve\" or \"reject\""
}
```

```json
{
  "status": "error",
  "message": "Topup request not found"
}
```

```json
{
  "status": "error",
  "message": "Topup request is already approved"
}
```

```json
{
  "status": "error",
  "message": "Cannot process topup request: Vendor account is not active"
}
```

---

### 6. Get Topup Request Statistics

**Endpoint:** `GET /api/admin/topup/stats.php`

Get counts and statistics of topup requests.

#### Example Request
```
GET /api/admin/topup/stats.php
```

#### Response (200 OK)
```json
{
  "status": "success",
  "data": {
    "statistics": {
      "pending": {
        "count": 5,
        "total_amount": 25000.00
      },
      "approved": {
        "count": 20,
        "total_amount": 100000.00
      },
      "rejected": {
        "count": 2,
        "total_amount": 5000.00
      },
      "total": {
        "count": 27,
        "total_amount": 130000.00
      }
    },
    "summary": {
      "pending_requests": 5,
      "pending_amount": 25000.00,
      "approved_requests": 20,
      "approved_amount": 100000.00,
      "rejected_requests": 2
    }
  }
}
```

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
- `400`: Bad Request (invalid input, missing fields, validation errors)
- `405`: Method Not Allowed (wrong HTTP method)
- `500`: Internal Server Error

---

## API Endpoint Summary

### Vendor Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/vendor/wallet/balance.php` | Get wallet balance |
| `POST` | `/api/vendor/topup/request.php` | Submit topup request |
| `GET` | `/api/vendor/wallet/transactions.php` | Get transaction history |

### Admin Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/admin/topup/list.php` | List all topup requests |
| `POST` | `/api/admin/topup/approve.php` | Approve/reject topup request |
| `GET` | `/api/admin/topup/stats.php` | Get topup statistics |

---

## Database Setup

Run the wallet schema SQL file to create required tables:

```sql
-- Run: database/wallet_schema.sql
```

This creates:
- `vendor_wallets` - Stores wallet balances
- `topup_requests` - Stores topup requests
- `wallet_transactions` - Stores all wallet transactions

---

## Notes

1. **Authentication**: Currently, endpoints accept `vendor_id` and `admin_id` as parameters. You should implement proper authentication middleware to get these from tokens/sessions.

2. **Wallet Auto-Creation**: If a vendor doesn't have a wallet, it's automatically created with balance 0.00 when first accessed.

3. **Automatic Wallet Update**: When a topup request is approved, the amount is automatically added to the vendor's wallet balance and a transaction record is created.

4. **Transaction Types**:
   - `topup`: Funds added via approved topup request
   - `deduction`: Funds deducted for payout transactions
   - `refund`: Funds refunded
   - `adjustment`: Manual adjustments

5. **Status Flow**:
   - Vendor submits request → `pending`
   - Admin approves → `approved` (wallet credited)
   - Admin rejects → `rejected` (wallet not credited)

6. **Amount Validation**:
   - Minimum: 0.01
   - Maximum: 10,00,000 (can be adjusted in code)

7. **CORS**: All endpoints support CORS for frontend integration.

---

## Example Workflow

1. **Vendor submits topup request:**
   ```
   POST /api/vendor/topup/request.php
   { "vendor_id": "...", "amount": 5000 }
   → Status: pending
   ```

2. **Admin views pending requests:**
   ```
   GET /api/admin/topup/list.php?status=pending
   → Returns list of pending requests
   ```

3. **Admin approves request:**
   ```
   POST /api/admin/topup/approve.php
   { "request_id": "...", "action": "approve", "admin_id": "..." }
   → Status: approved
   → Wallet balance: +5000
   ```

4. **Vendor checks balance:**
   ```
   GET /api/vendor/wallet/balance.php?vendor_id=...
   → Returns updated balance
   ```

