# Transaction Status Check API Documentation

## Endpoint

**POST** `/api/payout/status.php`

## Description

Checks the latest transaction status from PayNinja API and updates the database. Automatically updates UTR (Unique Transaction Reference) if it's not already set in the database.

**Important:** This API is **ONLY called when requested from the frontend**. It does NOT run automatically or in the background. The frontend must explicitly call this endpoint to check transaction status.

## Request

### Headers
```
Content-Type: application/json
```

### Request Body
```json
{
  "merchant_reference_id": "test123"
}
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `merchant_reference_id` | string | Yes | The merchant reference ID used during transaction initiation |

## Response

### Success Response (200 OK)

```json
{
  "status": "success",
  "message": "Payout request status fetched successfully",
  "data": {
    "merchant_reference_id": "test12353633",
    "amount": 1000,
    "status": "processing",
    "mode": "NEFT",
    "utr": null
  },
  "errors": null
}
```

**Note:** When transaction is successful, `utr` will contain the Unique Transaction Reference number.

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `status` | string | API response status: `"success"` or `"error"` |
| `message` | string | Human-readable message |
| `data.merchant_reference_id` | string | The merchant reference ID |
| `data.amount` | number | Transaction amount |
| `data.status` | string | Transaction status: `"pending"`, `"processing"`, `"success"`, `"failed"` |
| `data.mode` | string | Transfer mode: `"UPI"`, `"IMPS"`, or `"NEFT"` |
| `data.utr` | string\|null | Unique Transaction Reference (available after successful transaction) |
| `errors` | object\|null | Error details (if any) |

### Transaction Status Values

| Status | Description |
|--------|-------------|
| `pending` | Transaction is pending |
| `processing` | Transaction is being processed |
| `success` | Transaction completed successfully |
| `failed` | Transaction failed |

### Error Response (400/401/500)

```json
{
  "status": "error",
  "message": "Failed",
  "data": null,
  "errors": {
    "code": "BAD_REQUEST_ERROR",
    "description": "The id provided does not exist",
    "source": "business",
    "step": null,
    "reason": "input_validation_failed",
    "metadata": []
  }
}
```

## Frontend Integration Examples

### React/Axios Example

```javascript
import axios from 'axios';

const checkTransactionStatus = async (merchantReferenceId) => {
  try {
    const response = await axios.post(
      'http://localhost/backend/api/payout/status.php',
      {
        merchant_reference_id: merchantReferenceId
      },
      {
        headers: {
          'Content-Type': 'application/json'
        }
      }
    );

    if (response.data.status === 'success') {
      const transaction = response.data.data;
      
      console.log('Transaction Status:', transaction.status);
      console.log('Amount:', transaction.amount);
      console.log('Mode:', transaction.mode);
      console.log('UTR:', transaction.utr);
      
      return transaction;
    } else {
      throw new Error(response.data.message || 'Failed to check status');
    }
  } catch (error) {
    console.error('Error checking transaction status:', error);
    throw error;
  }
};

// Usage
checkTransactionStatus('test123')
  .then(transaction => {
    if (transaction.status === 'success') {
      console.log('Transaction successful! UTR:', transaction.utr);
    } else if (transaction.status === 'processing') {
      console.log('Transaction is processing...');
    } else if (transaction.status === 'failed') {
      console.log('Transaction failed');
    }
  })
  .catch(error => {
    console.error('Error:', error.message);
  });
```

### Fetch API Example

```javascript
const checkTransactionStatus = async (merchantReferenceId) => {
  try {
    const response = await fetch('http://localhost/backend/api/payout/status.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        merchant_reference_id: merchantReferenceId
      })
    });

    const data = await response.json();

    if (!response.ok || data.status === 'error') {
      throw new Error(data.message || 'Failed to check transaction status');
    }

    return data.data;
  } catch (error) {
    console.error('Error:', error);
    throw error;
  }
};

// Usage
const transaction = await checkTransactionStatus('test123');
console.log('Status:', transaction.status);
console.log('UTR:', transaction.utr);
```

### Polling Example (Optional - Frontend Only)

**Note:** This polling is implemented entirely in the frontend. The backend API does NOT poll automatically. Use this only if you want to check status periodically from the frontend.

```javascript
const pollTransactionStatus = async (merchantReferenceId, onUpdate, maxAttempts = 20) => {
  let attempts = 0;
  
  const checkStatus = async () => {
    attempts++;
    
    try {
      const response = await fetch('http://localhost/backend/api/payout/status.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          merchant_reference_id: merchantReferenceId
        })
      });

      const data = await response.json();

      if (data.status === 'success') {
        const transaction = data.data;
        
        // Call update callback
        onUpdate(transaction);
        
        // Continue polling if transaction is still pending/processing
        if ((transaction.status === 'pending' || transaction.status === 'processing') && attempts < maxAttempts) {
          setTimeout(checkStatus, 3000); // Check every 3 seconds
        } else if (attempts >= maxAttempts) {
          console.warn('Max polling attempts reached');
        }
      } else {
        throw new Error(data.message || 'Failed to check status');
      }
    } catch (error) {
      console.error('Error polling status:', error);
      if (attempts < maxAttempts) {
        setTimeout(checkStatus, 3000);
      }
    }
  };

  // Start polling (frontend only - backend does NOT poll)
  checkStatus();
};

// Usage
pollTransactionStatus('test123', (transaction) => {
  console.log('Status update:', transaction.status);
  
  if (transaction.status === 'success') {
    console.log('Transaction completed! UTR:', transaction.utr);
    // Show success message to user
  } else if (transaction.status === 'failed') {
    console.log('Transaction failed');
    // Show error message to user
  }
});
```

### React Hook Example (Manual Call or Optional Frontend Polling)

```javascript
import { useState, useEffect } from 'react';

const useTransactionStatus = (merchantReferenceId, autoPoll = false) => {
  const [transaction, setTransaction] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const checkStatus = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch('http://localhost/backend/api/payout/status.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          merchant_reference_id: merchantReferenceId
        })
      });

      const data = await response.json();

      if (data.status === 'success') {
        setTransaction(data.data);
      } else {
        throw new Error(data.message || 'Failed to check status');
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (merchantReferenceId) {
      // Initial check (manual call)
      checkStatus();

      // Optional: Frontend polling (only if autoPoll is true)
      // Backend does NOT poll automatically
      if (autoPoll) {
        const interval = setInterval(() => {
          checkStatus();
        }, 3000); // Poll every 3 seconds

        return () => clearInterval(interval);
      }
    }
  }, [merchantReferenceId, autoPoll]);

  return { transaction, loading, error, refetch: checkStatus };
};

// Usage in component - Manual call only (no polling)
function TransactionStatus({ merchantRefId }) {
  const { transaction, loading, error, refetch } = useTransactionStatus(merchantRefId, false);

  if (loading) return <div>Checking status...</div>;
  if (error) return <div>Error: {error}</div>;
  if (!transaction) return null;

  return (
    <div>
      <h3>Transaction Status</h3>
      <p>Status: {transaction.status}</p>
      <p>Amount: ₹{transaction.amount}</p>
      <p>Mode: {transaction.mode}</p>
      {transaction.utr && <p>UTR: {transaction.utr}</p>}
      <button onClick={refetch}>Refresh Status</button>
    </div>
  );
}
```

## Notes

1. **Manual Call Only**: This API **does NOT run automatically**. It must be explicitly called from the frontend when the user wants to check transaction status. There are no background processes, scheduled tasks, or automatic polling on the backend.

2. **UTR Update**: The API automatically updates the UTR in the database if it's not already set. This ensures UTR is captured when PayNinja provides it.

3. **Status Mapping**: The API maps PayNinja status values to database status:
   - `pending` → `PENDING`
   - `processing` → `PROCESSING`
   - `success` → `SUCCESS`
   - `failed` → `FAILED`
   - `reversed` → `FAILED`

4. **Database Update**: The transaction status in the database is updated only when this endpoint is called from the frontend.

5. **Frontend Polling (Optional)**: If you want real-time updates in your frontend, you can implement polling in your frontend code (see examples above). The backend does NOT poll automatically - all polling must be implemented in the frontend.

6. **Error Handling**: Always handle errors appropriately in your frontend application. Network errors, invalid reference IDs, and API errors should all be handled gracefully.

