# PayNinja Payout API Documentation

This document describes the backend API endpoints for integrating with the React frontend payout application.

## Base URL

For XAMPP (Apache):
```
http://localhost/backend/api/payout/
```

For PHP Built-in Server:
```
http://localhost:8080/api/payout/
```

## Authentication

All API requests require PayNinja API credentials configured in `config.php` or `.env` file.

## API Endpoints

### 1. Get Account Balance

**Endpoint:** `GET /api/payout/balance.php`

**Description:** Retrieves the current account balance from PayNinja.

**Request:**
- Method: `GET`
- Headers: `Content-Type: application/json`

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

**Response (Error - 400):**
```json
{
  "status": "error",
  "message": "Error message here"
}
```

**Example (React/Axios):**
```javascript
const response = await axios.get('http://localhost/backend/api/payout/balance.php');
console.log(response.data);
```

---

### 2. Initiate Fund Transfer

**Endpoint:** `POST /api/payout/initiate.php`

**Description:** Initiates a fund transfer to a beneficiary via UPI, IMPS, or NEFT.

**Request:**
- Method: `POST`
- Headers: `Content-Type: application/json`

**Request Body (UPI Transfer):**
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

**Request Body (IMPS/NEFT Transfer):**
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

**Field Validation:**
- `ben_name`: Required, string
- `ben_phone_number`: Required, valid 10-digit Indian mobile number (starting with 6-9)
- `amount`: Required, positive number
- `merchant_reference_id`: Required, unique transaction identifier
- `transfer_type`: Required, one of: `UPI`, `IMPS`, `NEFT`
- `ben_vpa_address`: Required for UPI, valid UPI format (e.g., `user@upi`)
- `ben_account_number`: Required for IMPS/NEFT, string
- `ben_ifsc`: Required for IMPS/NEFT, valid 11-character IFSC code (e.g., `HDFC0001234`)
- `ben_bank_name`: Required for IMPS/NEFT, string
- `narration`: Optional, defaults to "PAYNINJA Fund Transfer"

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

**Response (Error - 400):**
```json
{
  "status": "error",
  "message": "Error message here"
}
```

**Example (React/Axios):**
```javascript
const transferData = {
  ben_name: "John Doe",
  ben_phone_number: "9876543210",
  ben_vpa_address: "john.doe@upi",
  amount: "1000.00",
  merchant_reference_id: "TXN" + Date.now(),
  transfer_type: "UPI",
  narration: "Payment for services"
};

const response = await axios.post(
  'http://localhost/backend/api/payout/initiate.php',
  transferData,
  {
    headers: { 'Content-Type': 'application/json' }
  }
);
console.log(response.data);
```

---

### 3. Check Transaction Status

**Endpoint:** `POST /api/payout/status.php`

**Description:** Checks the status of a previously initiated transaction.

**Request:**
- Method: `POST`
- Headers: `Content-Type: application/json`

**Request Body:**
```json
{
  "merchant_reference_id": "TXN123456789"
}
```

**Field Validation:**
- `merchant_reference_id`: Required, the same reference ID used during transaction initiation

**Response (Success - 200):**
```json
{
  "status": "success",
  "data": {
    "merchant_reference_id": "TXN123456789",
    "transaction_status": "SUCCESS",
    "transaction_id": "PAYNINJA_TXN_123",
    "amount": "1000.00",
    "created_at": "2024-01-01 12:00:00",
    "updated_at": "2024-01-01 12:01:00"
  }
}
```

**Response (Error - 400):**
```json
{
  "status": "error",
  "message": "Error message here"
}
```

**Example (React/Axios):**
```javascript
const statusData = {
  merchant_reference_id: "TXN123456789"
};

const response = await axios.post(
  'http://localhost/backend/api/payout/status.php',
  statusData,
  {
    headers: { 'Content-Type': 'application/json' }
  }
);
console.log(response.data);
```

---

## CORS Configuration

The backend is configured to accept requests from:
- `http://localhost:3000` (React default dev server)
- `http://localhost:3001`
- `http://127.0.0.1:3000`
- `http://127.0.0.1:3001`

To add your production frontend URL, update the `$allowedOrigins` array in `config.php`.

---

## Error Handling

All endpoints return consistent error responses:

```json
{
  "status": "error",
  "message": "Human-readable error message"
}
```

Common HTTP status codes:
- `200`: Success
- `400`: Bad Request (validation errors, API errors)
- `405`: Method Not Allowed (wrong HTTP method)
- `500`: Internal Server Error

---

## Security Notes

1. **Environment Variables**: Sensitive credentials should be stored in `.env` file (never commit to version control)
2. **CORS**: Configure allowed origins in production (don't use wildcard `*`)
3. **Input Validation**: All inputs are validated and sanitized
4. **Logging**: Errors are logged to `logs/error.log` and `logs/info.log`

---

## React Frontend Integration Example

```javascript
// api.js
import axios from 'axios';

const API_BASE_URL = 'http://localhost/backend/api/payout';

export const payoutAPI = {
  // Get balance
  getBalance: async () => {
    const response = await axios.get(`${API_BASE_URL}/balance.php`);
    return response.data;
  },

  // Initiate transfer
  initiateTransfer: async (transferData) => {
    const response = await axios.post(
      `${API_BASE_URL}/initiate.php`,
      transferData
    );
    return response.data;
  },

  // Check status
  checkStatus: async (merchantReferenceId) => {
    const response = await axios.post(
      `${API_BASE_URL}/status.php`,
      { merchant_reference_id: merchantReferenceId }
    );
    return response.data;
  }
};
```

---

## Testing

You can test the API endpoints using:

1. **cURL:**
```bash
# Get Balance
curl -X GET http://localhost/backend/api/payout/balance.php

# Initiate Transfer
curl -X POST http://localhost/backend/api/payout/initiate.php \
  -H "Content-Type: application/json" \
  -d '{"ben_name":"John Doe","ben_phone_number":"9876543210","ben_vpa_address":"john@upi","amount":"1000","merchant_reference_id":"TXN123","transfer_type":"UPI"}'

# Check Status
curl -X POST http://localhost/backend/api/payout/status.php \
  -H "Content-Type: application/json" \
  -d '{"merchant_reference_id":"TXN123"}'
```

2. **Postman/Thunder Client**
3. **Browser DevTools** (for GET requests)

---

## Support

For issues or questions, check the error logs in `logs/error.log`.

