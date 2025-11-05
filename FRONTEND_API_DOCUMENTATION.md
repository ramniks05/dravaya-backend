# Frontend API Documentation

Complete API reference for frontend integration with PayNinja Payout Backend.

## Table of Contents

1. [Base Configuration](#base-configuration)
2. [Authentication APIs](#authentication-apis)
3. [Payout APIs](#payout-apis)
4. [Vendor APIs](#vendor-apis)
5. [Error Handling](#error-handling)
6. [Common Patterns](#common-patterns)

---

## Base Configuration

### Base URL

**Development:**
```
http://localhost/backend/api
```

**Production:**
```
http://dravya.hrntechsolutions.com/
```

### Headers

All API requests require:
```javascript
{
  'Content-Type': 'application/json'
}
```

### CORS

The backend supports CORS for these origins:
- `http://localhost:3000`
- `http://localhost:3001`
- `http://localhost:5173`
- `http://127.0.0.1:3000`
- `http://127.0.0.1:5173`

---

## Authentication APIs

### 1. Vendor Signup

**Endpoint:** `POST /auth/signup.php`

**Request:**
```json
{
  "email": "vendor@example.com",
  "password": "securePassword123",
  "role": "vendor"
}
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "message": "Vendor registered successfully",
  "data": {
    "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
    "email": "vendor@example.com",
    "status": "pending"
  }
}
```

**Response (Error - 400):**
```json
{
  "status": "error",
  "message": "Email already exists"
}
```

**Example:**
```javascript
const signup = async (email, password) => {
  const response = await fetch('http://localhost/backend/api/auth/signup.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      email,
      password,
      role: 'vendor'
    })
  });
  return response.json();
};
```

---

### 2. Vendor Login

**Endpoint:** `POST /auth/login.php`

**Request:**
```json
{
  "email": "vendor@example.com",
  "password": "securePassword123"
}
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
    "email": "vendor@example.com",
    "role": "vendor",
    "status": "active"
  }
}
```

**Response (Error - 401):**
```json
{
  "status": "error",
  "message": "Invalid credentials"
}
```

**Example:**
```javascript
const login = async (email, password) => {
  const response = await fetch('http://localhost/backend/api/auth/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  return response.json();
};
```

---

## Payout APIs

### 3. Get Account Balance

**Endpoint:** `GET /payout/balance.php`

**Response (Success - 200):**
```json
{
  "status": "success",
  "data": {
    "balance": "10000.00",
    "currency": "INR"
  }
}
```

**Example:**
```javascript
const getBalance = async () => {
  const response = await fetch('http://localhost/backend/api/payout/balance.php');
  return response.json();
};
```

---

### 4. Initiate Fund Transfer

**Endpoint:** `POST /payout/initiate.php`

**Request (UPI Transfer):**
```json
{
  "ben_name": "John Doe",
  "ben_phone_number": "9876543210",
  "ben_vpa_address": "john.doe@upi",
  "amount": "1000.00",
  "merchant_reference_id": "TXN123456789",
  "transfer_type": "UPI",
  "narration": "Payment for services"
}
```

**Request (IMPS/NEFT Transfer):**
```json
{
  "ben_name": "John Doe",
  "ben_phone_number": "9876543210",
  "ben_account_number": "1234567890",
  "ben_ifsc": "HDFC0001234",
  "ben_bank_name": "HDFC Bank",
  "amount": "1000.00",
  "merchant_reference_id": "TXN123456789",
  "transfer_type": "IMPS",
  "narration": "Payment for services"
}
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "data": {
    "transaction_id": "TXN123456789",
    "status": "PENDING",
    "message": "Transaction initiated successfully"
  }
}
```

**Example:**
```javascript
const initiatePayout = async (transferData) => {
  const response = await fetch('http://localhost/backend/api/payout/initiate.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(transferData)
  });
  return response.json();
};
```

---

### 5. Get Transaction Details

**Endpoint:** `GET` or `POST /payout/get.php`

**Request (GET):**
```
GET /payout/get.php?merchant_reference_id=PAYOUT_1762274023_71338dd5
```

**Request (POST):**
```json
{
  "merchant_reference_id": "PAYOUT_1762274023_71338dd5"
}
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "message": "Transaction retrieved successfully",
  "data": {
    "id": 1,
    "merchant_reference_id": "PAYOUT_1762274023_71338dd5",
    "payninja_transaction_id": "TXN123456",
    "utr": "UTR123456789",
    "beneficiary": {
      "name": "Ramesh kumar",
      "phone_number": "7903152429",
      "vpa_address": null,
      "account_number": "33618111989",
      "ifsc": "SBIN0008435",
      "bank_name": "state bank of india"
    },
    "transaction": {
      "transfer_type": "IMPS",
      "amount": 5.00,
      "narration": "pay test",
      "status": "PENDING",
      "payment_mode": null
    },
    "vendor": {
      "vendor_id": null,
      "beneficiary_id": null
    },
    "api_response": { ... },
    "api_error": null,
    "timestamps": {
      "created_at": "2025-11-04 17:33:43",
      "updated_at": "2025-11-04 17:33:45"
    },
    "logs": [ ... ]
  }
}
```

**Example:**
```javascript
const getTransaction = async (merchantRefId) => {
  const response = await fetch(
    `http://localhost/backend/api/payout/get.php?merchant_reference_id=${merchantRefId}`
  );
  return response.json();
};
```

---

### 6. Check Transaction Status (PayNinja API)

**Endpoint:** `POST /payout/status.php`

**Request:**
```json
{
  "merchant_reference_id": "PAYOUT_1762274023_71338dd5"
}
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "data": {
    "merchant_reference_id": "PAYOUT_1762274023_71338dd5",
    "transaction_status": "success",
    "utr": "UTR123456789"
  }
}
```

**Example:**
```javascript
const checkStatus = async (merchantRefId) => {
  const response = await fetch('http://localhost/backend/api/payout/status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ merchant_reference_id: merchantRefId })
  });
  return response.json();
};
```

---

## Vendor APIs

### 7. Get Vendor Wallet Balance

**Endpoint:** `GET /vendor/wallet/balance.php`

**Request:**
```
GET /vendor/wallet/balance.php?vendor_id=710d1abe-d3b3-4c9c-b56b-cb43959ba024
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "data": {
    "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
    "balance": "5000.00",
    "currency": "INR"
  }
}
```

**Example:**
```javascript
const getVendorBalance = async (vendorId) => {
  const response = await fetch(
    `http://localhost/backend/api/vendor/wallet/balance.php?vendor_id=${vendorId}`
  );
  return response.json();
};
```

---

### 8. Get Vendor Wallet Transactions

**Endpoint:** `GET /vendor/wallet/transactions.php`

**Request:**
```
GET /vendor/wallet/transactions.php?vendor_id=710d1abe-d3b3-4c9c-b56b-cb43959ba024&page=1&limit=20
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "data": {
    "transactions": [
      {
        "id": 1,
        "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
        "transaction_type": "deduction",
        "amount": 5.00,
        "balance_before": 5000.00,
        "balance_after": 4995.00,
        "reference_id": "PAYOUT_1762274023_71338dd5",
        "description": "Payout to Ramesh kumar",
        "created_at": "2025-11-04 17:33:45"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 1,
      "total_pages": 1
    }
  }
}
```

**Example:**
```javascript
const getWalletTransactions = async (vendorId, page = 1, limit = 20) => {
  const response = await fetch(
    `http://localhost/backend/api/vendor/wallet/transactions.php?vendor_id=${vendorId}&page=${page}&limit=${limit}`
  );
  return response.json();
};
```

---

### 9. Create Beneficiary

**Endpoint:** `POST /vendor/beneficiaries/create.php`

**Request (UPI Beneficiary):**
```json
{
  "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
  "name": "John Doe",
  "phone_number": "9876543210",
  "vpa_address": "john.doe@upi",
  "transfer_type": "UPI"
}
```

**Request (Bank Beneficiary):**
```json
{
  "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
  "name": "John Doe",
  "phone_number": "9876543210",
  "account_number": "1234567890",
  "ifsc": "HDFC0001234",
  "bank_name": "HDFC Bank",
  "transfer_type": "IMPS"
}
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "message": "Beneficiary created successfully",
  "data": {
    "id": 1,
    "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
    "name": "John Doe",
    "phone_number": "9876543210",
    "transfer_type": "UPI",
    "is_active": true
  }
}
```

**Example:**
```javascript
const createBeneficiary = async (vendorId, beneficiaryData) => {
  const response = await fetch('http://localhost/backend/api/vendor/beneficiaries/create.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      vendor_id: vendorId,
      ...beneficiaryData
    })
  });
  return response.json();
};
```

---

### 10. List Beneficiaries

**Endpoint:** `GET /vendor/beneficiaries/list.php`

**Request:**
```
GET /vendor/beneficiaries/list.php?vendor_id=710d1abe-d3b3-4c9c-b56b-cb43959ba024
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "data": {
    "beneficiaries": [
      {
        "id": 1,
        "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
        "name": "John Doe",
        "phone_number": "9876543210",
        "vpa_address": "john.doe@upi",
        "account_number": null,
        "ifsc": null,
        "bank_name": null,
        "transfer_type": "UPI",
        "is_active": true,
        "created_at": "2025-11-04 17:00:00"
      }
    ],
    "total": 1
  }
}
```

**Example:**
```javascript
const listBeneficiaries = async (vendorId) => {
  const response = await fetch(
    `http://localhost/backend/api/vendor/beneficiaries/list.php?vendor_id=${vendorId}`
  );
  return response.json();
};
```

---

### 11. Get Beneficiary

**Endpoint:** `GET /vendor/beneficiaries/get.php`

**Request:**
```
GET /vendor/beneficiaries/get.php?beneficiary_id=1&vendor_id=710d1abe-d3b3-4c9c-b56b-cb43959ba024
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
    "name": "John Doe",
    "phone_number": "9876543210",
    "vpa_address": "john.doe@upi",
    "transfer_type": "UPI",
    "is_active": true
  }
}
```

**Example:**
```javascript
const getBeneficiary = async (beneficiaryId, vendorId) => {
  const response = await fetch(
    `http://localhost/backend/api/vendor/beneficiaries/get.php?beneficiary_id=${beneficiaryId}&vendor_id=${vendorId}`
  );
  return response.json();
};
```

---

### 12. Update Beneficiary

**Endpoint:** `POST /vendor/beneficiaries/update.php`

**Request:**
```json
{
  "beneficiary_id": 1,
  "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
  "name": "John Doe Updated",
  "phone_number": "9876543210",
  "vpa_address": "john.doe.new@upi"
}
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "message": "Beneficiary updated successfully"
}
```

**Example:**
```javascript
const updateBeneficiary = async (beneficiaryId, vendorId, updateData) => {
  const response = await fetch('http://localhost/backend/api/vendor/beneficiaries/update.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      beneficiary_id: beneficiaryId,
      vendor_id: vendorId,
      ...updateData
    })
  });
  return response.json();
};
```

---

### 13. Delete Beneficiary

**Endpoint:** `POST /vendor/beneficiaries/delete.php`

**Request:**
```json
{
  "beneficiary_id": 1,
  "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024"
}
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "message": "Beneficiary deleted successfully"
}
```

**Example:**
```javascript
const deleteBeneficiary = async (beneficiaryId, vendorId) => {
  const response = await fetch('http://localhost/backend/api/vendor/beneficiaries/delete.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      beneficiary_id: beneficiaryId,
      vendor_id: vendorId
    })
  });
  return response.json();
};
```

---

### 14. Create Topup Request

**Endpoint:** `POST /vendor/topup/request.php`

**Request:**
```json
{
  "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
  "amount": 5000.00
}
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "message": "Topup request created successfully",
  "data": {
    "request_id": "TOPUP_1704110400_abc12345",
    "amount": 5000.00,
    "status": "pending"
  }
}
```

**Example:**
```javascript
const createTopupRequest = async (vendorId, amount) => {
  const response = await fetch('http://localhost/backend/api/vendor/topup/request.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      vendor_id: vendorId,
      amount: amount
    })
  });
  return response.json();
};
```

---

### 15. Initiate Vendor Payout (with Beneficiary)

**Endpoint:** `POST /vendor/payout/initiate.php`

**Request (Using Beneficiary):**
```json
{
  "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
  "beneficiary_id": 1,
  "amount": 1000.00,
  "transfer_type": "UPI",
  "narration": "Payment for services",
  "payment_mode": null
}
```

**Request (Manual Entry):**
```json
{
  "vendor_id": "710d1abe-d3b3-4c9c-b56b-cb43959ba024",
  "amount": 1000.00,
  "ben_name": "John Doe",
  "ben_phone_number": "9876543210",
  "ben_vpa_address": "john.doe@upi",
  "transfer_type": "UPI",
  "narration": "Payment for services"
}
```

**Response (Success - 200):**
```json
{
  "status": "success",
  "message": "Payout initiated successfully",
  "data": {
    "transaction": {
      "merchant_reference_id": "PAYOUT_1762274023_71338dd5",
      "payninja_transaction_id": "TXN123456",
      "amount": 1000.00,
      "transfer_type": "UPI",
      "status": "PENDING",
      "payninja_status": "pending",
      "payment_mode": null
    },
    "wallet": {
      "balance_before": 5000.00,
      "balance_after": 4000.00,
      "deducted": true
    },
    "beneficiary_used": true,
    "payninja_response": { ... }
  }
}
```

**Example:**
```javascript
const initiateVendorPayout = async (vendorId, payoutData) => {
  const response = await fetch('http://localhost/backend/api/vendor/payout/initiate.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      vendor_id: vendorId,
      ...payoutData
    })
  });
  return response.json();
};
```

---

## Error Handling

### Standard Error Response

All APIs return errors in this format:

```json
{
  "status": "error",
  "message": "Error message here"
}
```

### HTTP Status Codes

- `200` - Success
- `400` - Bad Request (validation errors, missing fields)
- `401` - Unauthorized (invalid credentials)
- `404` - Not Found (transaction, beneficiary, etc.)
- `405` - Method Not Allowed
- `500` - Internal Server Error

### Common Error Messages

| Error Message | Description | Solution |
|--------------|-------------|----------|
| `Invalid JSON request data` | Request body is not valid JSON | Check JSON syntax |
| `Missing required field: X` | Required field is missing | Include all required fields |
| `Invalid amount` | Amount must be positive | Use positive number |
| `Insufficient wallet balance` | Not enough balance for payout | Top up wallet first |
| `Transaction not found` | Merchant reference ID not found | Check merchant_reference_id |
| `Vendor not found` | Vendor ID invalid | Verify vendor_id |
| `Beneficiary not found` | Beneficiary ID invalid | Check beneficiary_id |

---

## Common Patterns

### 1. API Client Setup

```javascript
// api.js
const API_BASE_URL = 'http://localhost/backend/api';

class PayNinjaAPI {
  constructor(baseUrl = API_BASE_URL) {
    this.baseUrl = baseUrl;
  }

  async request(endpoint, options = {}) {
    const url = `${this.baseUrl}${endpoint}`;
    const config = {
      headers: {
        'Content-Type': 'application/json',
        ...options.headers
      },
      ...options
    };

    try {
      const response = await fetch(url, config);
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'API request failed');
      }

      return data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }

  // Authentication
  async signup(email, password) {
    return this.request('/auth/signup.php', {
      method: 'POST',
      body: JSON.stringify({ email, password, role: 'vendor' })
    });
  }

  async login(email, password) {
    return this.request('/auth/login.php', {
      method: 'POST',
      body: JSON.stringify({ email, password })
    });
  }

  // Payout
  async getBalance() {
    return this.request('/payout/balance.php');
  }

  async initiatePayout(payoutData) {
    return this.request('/payout/initiate.php', {
      method: 'POST',
      body: JSON.stringify(payoutData)
    });
  }

  async getTransaction(merchantRefId) {
    return this.request(`/payout/get.php?merchant_reference_id=${merchantRefId}`);
  }

  async checkStatus(merchantRefId) {
    return this.request('/payout/status.php', {
      method: 'POST',
      body: JSON.stringify({ merchant_reference_id: merchantRefId })
    });
  }

  // Vendor Wallet
  async getVendorBalance(vendorId) {
    return this.request(`/vendor/wallet/balance.php?vendor_id=${vendorId}`);
  }

  async getWalletTransactions(vendorId, page = 1, limit = 20) {
    return this.request(
      `/vendor/wallet/transactions.php?vendor_id=${vendorId}&page=${page}&limit=${limit}`
    );
  }

  // Beneficiaries
  async createBeneficiary(vendorId, beneficiaryData) {
    return this.request('/vendor/beneficiaries/create.php', {
      method: 'POST',
      body: JSON.stringify({ vendor_id: vendorId, ...beneficiaryData })
    });
  }

  async listBeneficiaries(vendorId) {
    return this.request(`/vendor/beneficiaries/list.php?vendor_id=${vendorId}`);
  }

  async getBeneficiary(beneficiaryId, vendorId) {
    return this.request(
      `/vendor/beneficiaries/get.php?beneficiary_id=${beneficiaryId}&vendor_id=${vendorId}`
    );
  }

  async updateBeneficiary(beneficiaryId, vendorId, updateData) {
    return this.request('/vendor/beneficiaries/update.php', {
      method: 'POST',
      body: JSON.stringify({ beneficiary_id: beneficiaryId, vendor_id: vendorId, ...updateData })
    });
  }

  async deleteBeneficiary(beneficiaryId, vendorId) {
    return this.request('/vendor/beneficiaries/delete.php', {
      method: 'POST',
      body: JSON.stringify({ beneficiary_id: beneficiaryId, vendor_id: vendorId })
    });
  }

  // Topup
  async createTopupRequest(vendorId, amount) {
    return this.request('/vendor/topup/request.php', {
      method: 'POST',
      body: JSON.stringify({ vendor_id: vendorId, amount })
    });
  }

  // Vendor Payout
  async initiateVendorPayout(vendorId, payoutData) {
    return this.request('/vendor/payout/initiate.php', {
      method: 'POST',
      body: JSON.stringify({ vendor_id: vendorId, ...payoutData })
    });
  }
}

export default PayNinjaAPI;
```

---

### 2. React Hook Example

```javascript
// usePayNinja.js
import { useState, useEffect } from 'react';
import PayNinjaAPI from './api';

const api = new PayNinjaAPI();

export const usePayNinja = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleRequest = async (requestFn) => {
    setLoading(true);
    setError(null);
    try {
      const result = await requestFn();
      return result;
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  return {
    loading,
    error,
    // Authentication
    signup: (email, password) => handleRequest(() => api.signup(email, password)),
    login: (email, password) => handleRequest(() => api.login(email, password)),
    
    // Payout
    getBalance: () => handleRequest(() => api.getBalance()),
    initiatePayout: (data) => handleRequest(() => api.initiatePayout(data)),
    getTransaction: (refId) => handleRequest(() => api.getTransaction(refId)),
    checkStatus: (refId) => handleRequest(() => api.checkStatus(refId)),
    
    // Vendor
    getVendorBalance: (vendorId) => handleRequest(() => api.getVendorBalance(vendorId)),
    getWalletTransactions: (vendorId, page, limit) => 
      handleRequest(() => api.getWalletTransactions(vendorId, page, limit)),
    
    // Beneficiaries
    createBeneficiary: (vendorId, data) => 
      handleRequest(() => api.createBeneficiary(vendorId, data)),
    listBeneficiaries: (vendorId) => 
      handleRequest(() => api.listBeneficiaries(vendorId)),
    
    // Topup
    createTopupRequest: (vendorId, amount) => 
      handleRequest(() => api.createTopupRequest(vendorId, amount)),
    
    // Vendor Payout
    initiateVendorPayout: (vendorId, data) => 
      handleRequest(() => api.initiateVendorPayout(vendorId, data))
  };
};
```

---

### 3. Transaction Status Polling

```javascript
const pollTransactionStatus = async (merchantRefId, onUpdate, maxAttempts = 20) => {
  let attempts = 0;
  
  const poll = async () => {
    try {
      const result = await api.getTransaction(merchantRefId);
      const status = result.data.transaction.status;
      
      onUpdate(result.data);
      
      // Continue polling if still pending
      if (status === 'PENDING' || status === 'PROCESSING') {
        attempts++;
        if (attempts < maxAttempts) {
          setTimeout(poll, 5000); // Poll every 5 seconds
        } else {
          onUpdate({ ...result.data, polling_timeout: true });
        }
      }
    } catch (error) {
      console.error('Polling error:', error);
      onUpdate({ error: error.message });
    }
  };
  
  poll();
};

// Usage
pollTransactionStatus('PAYOUT_123', (transaction) => {
  console.log('Transaction update:', transaction);
  if (transaction.transaction.status === 'SUCCESS') {
    // Handle success
  } else if (transaction.transaction.status === 'FAILED') {
    // Handle failure
  }
});
```

---

### 4. Complete Payout Flow Example

```javascript
const handlePayout = async (vendorId, payoutData) => {
  try {
    // Step 1: Check wallet balance
    const balance = await api.getVendorBalance(vendorId);
    if (balance.data.balance < payoutData.amount) {
      throw new Error('Insufficient balance');
    }

    // Step 2: Initiate payout
    const result = await api.initiateVendorPayout(vendorId, payoutData);
    const merchantRefId = result.data.transaction.merchant_reference_id;

    // Step 3: Poll for status updates
    pollTransactionStatus(merchantRefId, (transaction) => {
      updateUI(transaction);
      
      if (transaction.transaction.status === 'SUCCESS') {
        showSuccessMessage('Payout successful!');
      } else if (transaction.transaction.status === 'FAILED') {
        showErrorMessage('Payout failed');
      }
    });

    return result;
  } catch (error) {
    console.error('Payout error:', error);
    showErrorMessage(error.message);
    throw error;
  }
};
```

---

### 5. Form Validation

```javascript
const validatePayoutData = (data, transferType) => {
  const errors = {};

  // Required fields
  if (!data.ben_name?.trim()) {
    errors.ben_name = 'Beneficiary name is required';
  }

  if (!data.ben_phone_number?.trim()) {
    errors.ben_phone_number = 'Phone number is required';
  } else if (!/^[6-9][0-9]{9}$/.test(data.ben_phone_number.replace(/\D/g, ''))) {
    errors.ben_phone_number = 'Invalid Indian mobile number';
  }

  if (!data.amount || parseFloat(data.amount) <= 0) {
    errors.amount = 'Amount must be greater than 0';
  }

  // Transfer type specific validation
  if (transferType === 'UPI') {
    if (!data.ben_vpa_address?.trim()) {
      errors.ben_vpa_address = 'VPA address is required for UPI';
    } else if (!/^[\w.-]+@[\w]+$/.test(data.ben_vpa_address)) {
      errors.ben_vpa_address = 'Invalid UPI format';
    }
  } else {
    if (!data.ben_account_number?.trim()) {
      errors.ben_account_number = 'Account number is required';
    }
    if (!data.ben_ifsc?.trim()) {
      errors.ben_ifsc = 'IFSC code is required';
    } else if (!/^[A-Z]{4}0[A-Z0-9]{6}$/.test(data.ben_ifsc.toUpperCase())) {
      errors.ben_ifsc = 'Invalid IFSC code format';
    }
    if (!data.ben_bank_name?.trim()) {
      errors.ben_bank_name = 'Bank name is required';
    }
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors
  };
};
```

---

## Webhook Information

The backend automatically receives webhook notifications from PayNinja when transaction status changes. No frontend action is required for webhooks.

However, you can poll transaction status using:
- `GET /payout/get.php` - Fast database lookup
- `POST /payout/status.php` - Latest status from PayNinja API

---

## Quick Reference

### Endpoint Summary

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/auth/signup.php` | POST | Vendor signup |
| `/auth/login.php` | POST | Vendor login |
| `/payout/balance.php` | GET | Get account balance |
| `/payout/initiate.php` | POST | Initiate payout |
| `/payout/get.php` | GET/POST | Get transaction details |
| `/payout/status.php` | POST | Check transaction status |
| `/vendor/wallet/balance.php` | GET | Get vendor wallet balance |
| `/vendor/wallet/transactions.php` | GET | Get wallet transactions |
| `/vendor/beneficiaries/create.php` | POST | Create beneficiary |
| `/vendor/beneficiaries/list.php` | GET | List beneficiaries |
| `/vendor/beneficiaries/get.php` | GET | Get beneficiary |
| `/vendor/beneficiaries/update.php` | POST | Update beneficiary |
| `/vendor/beneficiaries/delete.php` | POST | Delete beneficiary |
| `/vendor/topup/request.php` | POST | Create topup request |
| `/vendor/payout/initiate.php` | POST | Initiate vendor payout |

---

## Support

For issues or questions:
1. Check API response errors in browser console
2. Review backend logs: `logs/error.log` and `logs/info.log`
3. Verify all required fields are included in requests
4. Ensure correct HTTP methods are used

---

**Last Updated:** November 2025

