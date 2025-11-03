# Vendor Management API Documentation

Complete API reference for vendor management endpoints.

---

## Base URL
```
http://localhost/backend/api/admin/
```

---

## 1. Get All Vendors

**Endpoint:** `GET /api/admin/vendors.php`

Get a list of all vendors with optional filtering and pagination.

### Query Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `status` | string | No | Filter by status: `pending`, `active`, `suspended` |
| `role` | string | No | Filter by role (default: `vendor`) |
| `page` | integer | No | Page number (default: `1`) |
| `limit` | integer | No | Items per page (default: `50`, max: `100`) |

### Example Requests
```
GET /api/admin/vendors.php
GET /api/admin/vendors.php?status=pending
GET /api/admin/vendors.php?status=active&page=2&limit=25
GET /api/admin/vendors.php?status=suspended
```

### Response (200 OK)
```json
{
  "status": "success",
  "data": {
    "vendors": [
      {
        "id": "uuid-here",
        "email": "vendor@example.com",
        "role": "vendor",
        "status": "pending",
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
      "status": "pending",
      "role": "vendor"
    }
  }
}
```

### Response Fields
- `vendors[]`: Array of vendor objects
  - `id`: Vendor UUID
  - `email`: Vendor email address
  - `role`: User role (`vendor` or `admin`)
  - `status`: Account status (`pending`, `active`, `suspended`)
  - `created_at`: Account creation timestamp
  - `updated_at`: Last update timestamp
- `pagination`: Pagination information
  - `page`: Current page number
  - `limit`: Items per page
  - `total`: Total number of vendors
  - `total_pages`: Total number of pages

---

## 2. Get Pending Vendors

**Endpoint:** `GET /api/admin/vendors/pending.php`

Get all vendors waiting for approval (status: `pending`).

### Query Parameters
None

### Example Request
```
GET /api/admin/vendors/pending.php
```

### Response (200 OK)
```json
{
  "status": "success",
  "data": {
    "vendors": [
      {
        "id": "uuid-here",
        "email": "vendor@example.com",
        "role": "vendor",
        "status": "pending",
        "created_at": "2024-01-01 12:00:00",
        "updated_at": "2024-01-01 12:00:00",
        "pending_since": "2024-01-01 12:00:00"
      }
    ],
    "count": 5
  }
}
```

### Response Fields
- `vendors[]`: Array of pending vendor objects
  - `id`: Vendor UUID
  - `email`: Vendor email address
  - `role`: User role (always `vendor`)
  - `status`: Account status (always `pending`)
  - `created_at`: Account creation timestamp
  - `updated_at`: Last update timestamp
  - `pending_since`: Same as `created_at` (for convenience)
- `count`: Total number of pending vendors

---

## 3. Approve Vendor

**Endpoint:** `POST /api/admin/vendors/approve.php`

Approve a vendor (changes status from `pending` to `active`).

### Request Headers
```
Content-Type: application/json
```

### Request Body
```json
{
  "vendor_id": "uuid-of-vendor"
}
```

### Request Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `vendor_id` | string | Yes | UUID of the vendor to approve |

### Example Request
```
POST /api/admin/vendors/approve.php
Content-Type: application/json

{
  "vendor_id": "123e4567-e89b-12d3-a456-426614174000"
}
```

### Response (200 OK - Success)
```json
{
  "status": "success",
  "message": "Vendor approved successfully",
  "data": {
    "vendor": {
      "id": "123e4567-e89b-12d3-a456-426614174000",
      "email": "vendor@example.com",
      "role": "vendor",
      "status": "active",
      "previous_status": "pending"
    }
  }
}
```

### Response (200 OK - Already Active)
```json
{
  "status": "success",
  "message": "Vendor is already active",
  "data": {
    "vendor": {
      "id": "123e4567-e89b-12d3-a456-426614174000",
      "email": "vendor@example.com",
      "status": "active"
    }
  }
}
```

### Response (400 Bad Request)
```json
{
  "status": "error",
  "message": "vendor_id is required"
}
```

```json
{
  "status": "error",
  "message": "Vendor not found"
}
```

---

## 4. Update Vendor Status

**Endpoint:** `POST /api/admin/vendors.php`

Update vendor status (approve, suspend, or activate).

### Request Headers
```
Content-Type: application/json
```

### Request Body
```json
{
  "vendor_id": "uuid-of-vendor",
  "action": "approve"
}
```

### Request Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `vendor_id` | string | Yes | UUID of the vendor to update |
| `action` | string | Yes | Action to perform: `approve`, `suspend`, or `activate` |

### Actions
- `approve`: Changes status to `active` (same as `activate`)
- `activate`: Changes status to `active`
- `suspend`: Changes status to `suspended`

### Example Requests

**Approve Vendor:**
```json
{
  "vendor_id": "123e4567-e89b-12d3-a456-426614174000",
  "action": "approve"
}
```

**Suspend Vendor:**
```json
{
  "vendor_id": "123e4567-e89b-12d3-a456-426614174000",
  "action": "suspend"
}
```

**Activate Vendor:**
```json
{
  "vendor_id": "123e4567-e89b-12d3-a456-426614174000",
  "action": "activate"
}
```

### Response (200 OK)
```json
{
  "status": "success",
  "message": "Vendor approved successfully",
  "data": {
    "vendor": {
      "id": "123e4567-e89b-12d3-a456-426614174000",
      "email": "vendor@example.com",
      "role": "vendor",
      "status": "active",
      "previous_status": "pending"
    }
  }
}
```

### Response (400 Bad Request)
```json
{
  "status": "error",
  "message": "vendor_id is required"
}
```

```json
{
  "status": "error",
  "message": "action must be one of: approve, suspend, activate"
}
```

```json
{
  "status": "error",
  "message": "Vendor not found"
}
```

```json
{
  "status": "error",
  "message": "Cannot modify admin accounts"
}
```

---

## 5. Get Vendor Statistics

**Endpoint:** `GET /api/admin/vendors/stats.php`

Get counts of vendors by status.

### Query Parameters
None

### Example Request
```
GET /api/admin/vendors/stats.php
```

### Response (200 OK)
```json
{
  "status": "success",
  "data": {
    "statistics": {
      "pending": 5,
      "active": 20,
      "suspended": 2,
      "total": 27
    },
    "summary": {
      "pending_approvals": 5,
      "active_vendors": 20,
      "suspended_vendors": 2
    }
  }
}
```

### Response Fields
- `statistics`: Counts by status
  - `pending`: Number of pending vendors
  - `active`: Number of active vendors
  - `suspended`: Number of suspended vendors
  - `total`: Total number of vendors
- `summary`: Human-readable summary (same data, different format)

---

## Error Responses

All endpoints return errors in this format:

### Error Response Format
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
- `"vendor_id is required"` - Missing vendor_id in request body
- `"action must be one of: approve, suspend, activate"` - Invalid action value
- `"Vendor not found"` - Vendor ID doesn't exist in database
- `"Cannot modify admin accounts"` - Attempted to modify an admin user
- `"Invalid JSON request data"` - Request body is not valid JSON
- `"Failed to update vendor status"` - Database error during update

---

## API Endpoint Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/admin/vendors.php` | Get all vendors (with filters) |
| `GET` | `/api/admin/vendors/pending.php` | Get pending vendors only |
| `POST` | `/api/admin/vendors/approve.php` | Approve a vendor |
| `POST` | `/api/admin/vendors.php` | Update vendor status |
| `GET` | `/api/admin/vendors/stats.php` | Get vendor statistics |

---

## CORS

All endpoints support CORS and can be called from:
- `http://localhost:5173` (Vite default)
- `http://localhost:3000` (React default)
- `http://localhost:3001`
- `http://127.0.0.1:5173`

Headers included:
- `Access-Control-Allow-Origin`: Based on request origin
- `Access-Control-Allow-Methods`: GET, POST, OPTIONS
- `Access-Control-Allow-Headers`: Content-Type
- `Access-Control-Allow-Credentials`: true

---

## Notes

1. **Authentication**: Currently, endpoints don't verify admin authentication. You should implement authentication middleware on the frontend and backend.

2. **Vendor ID Format**: Vendor IDs are UUIDs (e.g., `123e4567-e89b-12d3-a456-426614174000`).

3. **Status Values**: 
   - `pending`: New signup, waiting for approval
   - `active`: Approved, can login
   - `suspended`: Temporarily blocked

4. **Admin Protection**: The API prevents modifying admin accounts (only vendor accounts can be managed).

5. **Case Sensitivity**: Email matching is case-insensitive, but all other fields are case-sensitive.

---

## Testing

You can test all endpoints using:
- Browser: Open `http://localhost/backend/index.php` and use the "Vendor Management API Tests" section
- Postman/Insomnia: Use the request examples above
- cURL: Convert the examples to cURL commands

