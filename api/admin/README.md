# Admin Vendor Management API

This API allows admin users to manage vendor accounts (approve, suspend, activate).

## Base URL
```
http://localhost/backend/api/admin/
```

---

## Endpoints

### 1. Get All Vendors

**GET** `/api/admin/vendors.php`

Returns a list of all vendors with optional filtering.

#### Query Parameters
- `status` (optional): Filter by status (`pending`, `active`, `suspended`)
- `role` (optional): Filter by role (default: `vendor`)
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 50, max: 100)

#### Example Requests
```javascript
// Get all pending vendors
GET /api/admin/vendors.php?status=pending

// Get all active vendors (page 2)
GET /api/admin/vendors.php?status=active&page=2&limit=25

// Get all vendors
GET /api/admin/vendors.php
```

#### Response (Success - 200)
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

---

### 2. Get Pending Vendors Only

**GET** `/api/admin/vendors/pending.php`

Convenience endpoint to get all vendors waiting for approval.

#### Response (Success - 200)
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

---

### 3. Approve Vendor

**POST** `/api/admin/vendors/approve.php`

Approves a vendor (changes status from `pending` to `active`).

#### Request Body
```json
{
  "vendor_id": "uuid-of-vendor"
}
```

#### Response (Success - 200)
```json
{
  "status": "success",
  "message": "Vendor approved successfully",
  "data": {
    "vendor": {
      "id": "uuid-here",
      "email": "vendor@example.com",
      "role": "vendor",
      "status": "active",
      "previous_status": "pending"
    }
  }
}
```

---

### 4. Update Vendor Status

**POST** `/api/admin/vendors.php`

Updates vendor status (approve, suspend, or activate).

#### Request Body
```json
{
  "vendor_id": "uuid-of-vendor",
  "action": "approve"  // or "suspend" or "activate"
}
```

#### Actions
- `approve`: Changes status to `active` (same as activate)
- `activate`: Changes status to `active`
- `suspend`: Changes status to `suspended`

#### Response (Success - 200)
```json
{
  "status": "success",
  "message": "Vendor approved successfully",
  "data": {
    "vendor": {
      "id": "uuid-here",
      "email": "vendor@example.com",
      "role": "vendor",
      "status": "active",
      "previous_status": "pending"
    }
  }
}
```

---

### 5. Vendor Statistics

**GET** `/api/admin/vendors/stats.php`

Returns counts of vendors by status.

#### Response (Success - 200)
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

---

## React Integration Examples

### Fetch Pending Vendors
```javascript
const fetchPendingVendors = async () => {
  try {
    const response = await fetch('http://localhost/backend/api/admin/vendors/pending.php', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json'
      }
    });
    
    const result = await response.json();
    
    if (result.status === 'success') {
      return result.data.vendors;
    } else {
      throw new Error(result.message);
    }
  } catch (error) {
    console.error('Error fetching pending vendors:', error);
    throw error;
  }
};
```

### Approve Vendor
```javascript
const approveVendor = async (vendorId) => {
  try {
    const response = await fetch('http://localhost/backend/api/admin/vendors/approve.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        vendor_id: vendorId
      })
    });
    
    const result = await response.json();
    
    if (result.status === 'success') {
      return result.data.vendor;
    } else {
      throw new Error(result.message);
    }
  } catch (error) {
    console.error('Error approving vendor:', error);
    throw error;
  }
};
```

### Get Vendor Statistics
```javascript
const getVendorStats = async () => {
  try {
    const response = await fetch('http://localhost/backend/api/admin/vendors/stats.php');
    const result = await response.json();
    
    if (result.status === 'success') {
      return result.data.statistics;
    }
  } catch (error) {
    console.error('Error fetching stats:', error);
  }
};
```

### Complete Admin Approval Component Example
```javascript
import { useState, useEffect } from 'react';

const VendorApprovalPage = () => {
  const [pendingVendors, setPendingVendors] = useState([]);
  const [loading, setLoading] = useState(true);
  
  useEffect(() => {
    loadPendingVendors();
  }, []);
  
  const loadPendingVendors = async () => {
    try {
      setLoading(true);
      const vendors = await fetchPendingVendors();
      setPendingVendors(vendors);
    } catch (error) {
      alert('Failed to load vendors: ' + error.message);
    } finally {
      setLoading(false);
    }
  };
  
  const handleApprove = async (vendorId) => {
    if (!confirm('Approve this vendor?')) return;
    
    try {
      await approveVendor(vendorId);
      alert('Vendor approved successfully!');
      loadPendingVendors(); // Refresh list
    } catch (error) {
      alert('Failed to approve: ' + error.message);
    }
  };
  
  if (loading) return <div>Loading...</div>;
  
  return (
    <div>
      <h1>Pending Vendor Approvals ({pendingVendors.length})</h1>
      <table>
        <thead>
          <tr>
            <th>Email</th>
            <th>Created At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {pendingVendors.map(vendor => (
            <tr key={vendor.id}>
              <td>{vendor.email}</td>
              <td>{new Date(vendor.created_at).toLocaleString()}</td>
              <td>
                <button onClick={() => handleApprove(vendor.id)}>
                  Approve
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default VendorApprovalPage;
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

Common HTTP status codes:
- `200`: Success
- `400`: Bad Request (invalid input)
- `405`: Method Not Allowed
- `500`: Server Error

---

## Security Notes

1. **Admin Authentication**: Currently, these endpoints don't verify admin role. You should add authentication middleware to ensure only admins can access these endpoints.

2. **Input Validation**: All inputs are validated and sanitized.

3. **SQL Injection Protection**: All queries use prepared statements.

4. **Prevent Admin Modification**: The API prevents modifying admin accounts.

---

## Next Steps

To add authentication, create a middleware file and check for admin token/role:

```php
// api/admin/middleware.php
function requireAdmin() {
    // Check if user is logged in and is admin
    // Get token from header, verify, check role
    // If not admin, return 403
}
```

Then include it at the top of each admin endpoint:
```php
require_once __DIR__ . '/middleware.php';
requireAdmin();
```

