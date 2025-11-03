# Beneficiary Management API Documentation

Complete API reference for managing beneficiaries (frequent payees).

---

## Base URL
```
http://localhost/backend/api/vendor/beneficiaries/
```

---

## Setup

**Important:** Before using beneficiary APIs, run the schema update to add `vendor_id` column:

```sql
-- Run: database/beneficiary_schema_update.sql
```

This adds `vendor_id` column to link beneficiaries to specific vendors.

---

## Vendor APIs

### 1. Create Beneficiary

**Endpoint:** `POST /api/vendor/beneficiaries/create.php`

Create a new beneficiary for faster transfers.

#### Request Headers
```
Content-Type: application/json
```

#### Request Body (UPI Transfer)
```json
{
  "vendor_id": "vendor-uuid-here",
  "name": "John Doe",
  "phone_number": "9876543210",
  "transfer_type": "UPI",
  "vpa_address": "john.doe@upi",
  "is_active": true
}
```

#### Request Body (IMPS/NEFT Transfer)
```json
{
  "vendor_id": "vendor-uuid-here",
  "name": "John Doe",
  "phone_number": "9876543210",
  "transfer_type": "IMPS",
  "account_number": "1234567890",
  "ifsc": "HDFC0001234",
  "bank_name": "HDFC Bank",
  "is_active": true
}
```

#### Request Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `vendor_id` | string | Yes | Vendor UUID |
| `name` | string | Yes | Beneficiary name |
| `phone_number` | string | Yes | 10-digit Indian mobile number |
| `transfer_type` | string | Yes | `UPI`, `IMPS`, or `NEFT` |
| `vpa_address` | string | Yes (for UPI) | UPI ID (e.g., `user@upi`) |
| `account_number` | string | Yes (for IMPS/NEFT) | Bank account number |
| `ifsc` | string | Yes (for IMPS/NEFT) | IFSC code (e.g., `HDFC0001234`) |
| `bank_name` | string | Yes (for IMPS/NEFT) | Bank name |
| `is_active` | boolean | No | Active status (default: `true`) |

#### Response (200 OK)
```json
{
  "status": "success",
  "message": "Beneficiary created successfully",
  "data": {
    "beneficiary": {
      "id": 1,
      "vendor_id": "vendor-uuid-here",
      "name": "John Doe",
      "phone_number": "9876543210",
      "vpa_address": "john.doe@upi",
      "account_number": null,
      "ifsc": null,
      "bank_name": null,
      "transfer_type": "UPI",
      "is_active": true,
      "created_at": "2024-01-01 12:00:00",
      "updated_at": "2024-01-01 12:00:00"
    }
  }
}
```

#### Response (400 Bad Request)
```json
{
  "status": "error",
  "message": "vpa_address is required for UPI transfers"
}
```

---

### 2. List Beneficiaries

**Endpoint:** `GET /api/vendor/beneficiaries/list.php`

Get list of all beneficiaries for a vendor.

#### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `vendor_id` | string | Yes | Vendor UUID |
| `transfer_type` | string | No | Filter by type: `UPI`, `IMPS`, `NEFT` |
| `is_active` | boolean | No | Filter by active status |
| `page` | integer | No | Page number (default: 1) |
| `limit` | integer | No | Items per page (default: 50, max: 100) |

#### Example Requests
```
GET /api/vendor/beneficiaries/list.php?vendor_id=uuid-here
GET /api/vendor/beneficiaries/list.php?vendor_id=uuid-here&transfer_type=UPI
GET /api/vendor/beneficiaries/list.php?vendor_id=uuid-here&is_active=true&page=1&limit=20
```

#### Response (200 OK)
```json
{
  "status": "success",
  "data": {
    "vendor_id": "vendor-uuid-here",
    "beneficiaries": [
      {
        "id": 1,
        "vendor_id": "vendor-uuid-here",
        "name": "John Doe",
        "phone_number": "9876543210",
        "vpa_address": "john.doe@upi",
        "account_number": null,
        "ifsc": null,
        "bank_name": null,
        "transfer_type": "UPI",
        "is_active": true,
        "created_at": "2024-01-01 12:00:00",
        "updated_at": "2024-01-01 12:00:00"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 50,
      "total": 10,
      "total_pages": 1
    },
    "filters": {
      "transfer_type": null,
      "is_active": null
    }
  }
}
```

---

### 3. Get Beneficiary by ID

**Endpoint:** `GET /api/vendor/beneficiaries/get.php`

Get a specific beneficiary by ID.

#### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Beneficiary ID |
| `vendor_id` | string | Yes | Vendor UUID |

#### Example Request
```
GET /api/vendor/beneficiaries/get.php?id=1&vendor_id=uuid-here
```

#### Response (200 OK)
```json
{
  "status": "success",
  "data": {
    "beneficiary": {
      "id": 1,
      "vendor_id": "vendor-uuid-here",
      "name": "John Doe",
      "phone_number": "9876543210",
      "vpa_address": "john.doe@upi",
      "account_number": null,
      "ifsc": null,
      "bank_name": null,
      "transfer_type": "UPI",
      "is_active": true,
      "created_at": "2024-01-01 12:00:00",
      "updated_at": "2024-01-01 12:00:00"
    }
  }
}
```

#### Response (400 Bad Request)
```json
{
  "status": "error",
  "message": "Beneficiary not found"
}
```

---

### 4. Update Beneficiary

**Endpoint:** `POST /api/vendor/beneficiaries/update.php`  
**Also supports:** `PUT`, `PATCH`

Update beneficiary information.

#### Request Headers
```
Content-Type: application/json
```

#### Request Body
```json
{
  "id": 1,
  "vendor_id": "vendor-uuid-here",
  "name": "John Doe Updated",
  "phone_number": "9876543210",
  "vpa_address": "john.updated@upi",
  "is_active": true
}
```

#### Request Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | integer | Yes | Beneficiary ID |
| `vendor_id` | string | Yes | Vendor UUID |
| `name` | string | No | Beneficiary name |
| `phone_number` | string | No | Phone number |
| `transfer_type` | string | No | Transfer type (if changing) |
| `vpa_address` | string | No | UPI address (for UPI) |
| `account_number` | string | No | Account number (for IMPS/NEFT) |
| `ifsc` | string | No | IFSC code (for IMPS/NEFT) |
| `bank_name` | string | No | Bank name (for IMPS/NEFT) |
| `is_active` | boolean | No | Active status |

#### Response (200 OK)
```json
{
  "status": "success",
  "message": "Beneficiary updated successfully",
  "data": {
    "beneficiary": {
      "id": 1,
      "vendor_id": "vendor-uuid-here",
      "name": "John Doe Updated",
      "phone_number": "9876543210",
      "vpa_address": "john.updated@upi",
      "transfer_type": "UPI",
      "is_active": true,
      "created_at": "2024-01-01 12:00:00",
      "updated_at": "2024-01-01 12:05:00"
    }
  }
}
```

---

### 5. Delete Beneficiary

**Endpoint:** `POST /api/vendor/beneficiaries/delete.php`  
**Also supports:** `DELETE`

Delete a beneficiary.

#### Request Headers
```
Content-Type: application/json
```

#### Request Body
```json
{
  "id": 1,
  "vendor_id": "vendor-uuid-here"
}
```

#### Request Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | integer | Yes | Beneficiary ID |
| `vendor_id` | string | Yes | Vendor UUID |

#### Response (200 OK)
```json
{
  "status": "success",
  "message": "Beneficiary deleted successfully"
}
```

#### Response (400 Bad Request)
```json
{
  "status": "error",
  "message": "Beneficiary not found"
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

### Common Error Messages
- `"vendor_id is required"`
- `"name is required"`
- `"phone_number is required"`
- `"transfer_type must be UPI, IMPS, or NEFT"`
- `"vpa_address is required for UPI transfers"`
- `"account_number is required for IMPS/NEFT transfers"`
- `"Beneficiary with this phone number and transfer type already exists"`
- `"Invalid phone number format"`
- `"Vendor not found"`
- `"Vendor account is not active"`
- `"Beneficiary not found"`

---

## API Endpoint Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/vendor/beneficiaries/create.php` | Create new beneficiary |
| `GET` | `/api/vendor/beneficiaries/list.php` | List all beneficiaries |
| `GET` | `/api/vendor/beneficiaries/get.php` | Get beneficiary by ID |
| `POST/PUT/PATCH` | `/api/vendor/beneficiaries/update.php` | Update beneficiary |
| `POST/DELETE` | `/api/vendor/beneficiaries/delete.php` | Delete beneficiary |

---

## Notes

1. **Vendor Isolation**: Each vendor can only see and manage their own beneficiaries.

2. **Transfer Type Validation**: 
   - UPI requires `vpa_address`
   - IMPS/NEFT require `account_number`, `ifsc`, and `bank_name`

3. **Phone Number Uniqueness**: Phone number + transfer type combination must be unique per vendor.

4. **Active Status**: Set `is_active` to `false` to temporarily disable a beneficiary without deleting it.

5. **Authentication**: Currently, endpoints accept `vendor_id` as a parameter. Implement proper authentication middleware to get this from tokens/sessions.

6. **CORS**: All endpoints support CORS for frontend integration.

---

## Example Workflow

1. **Create UPI Beneficiary:**
   ```json
   POST /api/vendor/beneficiaries/create.php
   {
     "vendor_id": "...",
     "name": "John Doe",
     "phone_number": "9876543210",
     "transfer_type": "UPI",
     "vpa_address": "john@upi"
   }
   ```

2. **List All Beneficiaries:**
   ```
   GET /api/vendor/beneficiaries/list.php?vendor_id=...
   ```

3. **Update Beneficiary:**
   ```json
   POST /api/vendor/beneficiaries/update.php
   {
     "id": 1,
     "vendor_id": "...",
     "name": "John Doe Updated"
   }
   ```

4. **Use Beneficiary for Transfer:**
   - Get beneficiary details using GET endpoint
   - Use the beneficiary data in the payout initiate API

