# PayNinja Fund Transfer Webhook Documentation

## Overview

The webhook endpoint receives encrypted transaction status updates from PayNinja when a fund transfer status changes (pending → processing → success/failed).

## Webhook Endpoint

**URL:** `http://your-domain.com/backend/api/payout/webhook.php`  
**Method:** `POST`  
**Content-Type:** `application/json`

## Webhook Request Format

PayNinja sends encrypted data in the following format:

```json
{
  "data": "sHwbE6boBdx1A4uho7gspUY96m5dq2QcYsgfDZAic4brcL9I1s9T5ED4BrDRa5OKCf/sIDCg6QpNPgdaEyshUOhbz0WvKqYF2IoXETvvRaPJVL00bZduKaAtriBQHoDY",
  "iv": "Me1oBAA9N0wfFBDV"
}
```

### Decrypted Data Format

After decryption, the data contains:

```json
{
  "data": {
    "merchant_reference_id": "PAYOUT_1762274023_71338dd5",
    "utr": null,
    "amount": 100,
    "status": "pending"
  },
  "iv": "pdy4Z8nBUQ5iaNaz"
}
```

### Fields

- **merchant_reference_id** (required): The unique transaction reference ID you provided when initiating the payout
- **utr** (optional): Unique Transaction Reference from the bank (available after successful transaction)
- **amount**: Transaction amount
- **status**: Transaction status (`pending`, `processing`, `success`, `failed`, `reversed`)

## Status Mapping

The webhook maps PayNinja status values to our database status:

| PayNinja Status | Database Status |
|----------------|-----------------|
| `pending`      | `PENDING`      |
| `processing`   | `PROCESSING`    |
| `success`      | `SUCCESS`       |
| `failed`       | `FAILED`        |
| `reversed`     | `FAILED`        |

## Webhook Response

The webhook always returns HTTP 200 to acknowledge receipt (even on errors) to prevent PayNinja from retrying:

```json
{
  "status": "success",
  "message": "Webhook received and processed"
}
```

## Implementation Details

### Decryption

The webhook uses the same encryption method as the API:
- **Algorithm:** AES-256-CBC
- **Key:** Your `SECRET_KEY` from config
- **IV:** Provided in the webhook payload

### Database Updates

When a webhook is received:

1. **Decrypts** the payload using `SECRET_KEY` and provided `iv`
2. **Validates** the decrypted data structure
3. **Finds** the transaction by `merchant_reference_id`
4. **Updates** transaction status in database
5. **Stores** UTR (if provided) for successful transactions
6. **Logs** webhook activity in `transaction_logs` table

### Error Handling

- All errors are logged to `logs/error.log`
- Webhook always returns HTTP 200 to prevent retries
- Failed processing is logged for investigation
- Transaction not found errors are logged but don't prevent response

## Setup Instructions

### 1. Database Migration

Run the migration SQL to add webhook support:

```sql
-- Add UTR column
ALTER TABLE transactions 
ADD COLUMN utr VARCHAR(100) DEFAULT NULL;

-- Add index
ALTER TABLE transactions 
ADD INDEX idx_utr (utr);

-- Update log_type enum
ALTER TABLE transaction_logs 
MODIFY COLUMN log_type ENUM('REQUEST', 'RESPONSE', 'ERROR', 'STATUS_CHECK', 'WEBHOOK') NOT NULL;
```

Or run the migration file:
```bash
mysql -u root -p dravya < database/add_webhook_support.sql
```

### 2. Configure Webhook URL in PayNinja Dashboard

1. Log in to PayNinja Dashboard
2. Navigate to Settings → Webhooks
3. Enter your webhook URL: `http://your-domain.com/backend/api/payout/webhook.php`
4. Select event type: **Fund Transfer Status Update**
5. Save configuration

### 3. Test Webhook

You can test the webhook using curl:

```bash
curl -X POST http://localhost/backend/api/payout/webhook.php \
  -H "Content-Type: application/json" \
  -d '{
    "data": "encrypted_data_here",
    "iv": "16_character_iv"
  }'
```

## Logging

All webhook events are logged:

- **Incoming webhooks:** `logs/info.log`
- **Webhook errors:** `logs/error.log`
- **Transaction updates:** `transaction_logs` table with `log_type = 'WEBHOOK'`

### Example Log Entry

```
[2025-11-04 17:45:23] Webhook Received | Context: {"raw_input":"...","headers":{...}}
[2025-11-04 17:45:23] Webhook Decrypted | Context: {"decrypted_data":{"merchant_reference_id":"...","status":"success"}}
[2025-11-04 17:45:23] Webhook Processed Successfully | Context: {"merchant_reference_id":"PAYOUT_123","old_status":"PENDING","new_status":"SUCCESS"}
```

## Security Considerations

1. **HTTPS Required:** Always use HTTPS in production to encrypt webhook traffic
2. **IP Whitelisting:** Consider whitelisting PayNinja's webhook IP addresses
3. **Secret Key:** Never expose your `SECRET_KEY` - it's used for decryption
4. **Validation:** Always validate `merchant_reference_id` before updating transactions

## Troubleshooting

### Webhook Not Received

1. Check PayNinja dashboard webhook configuration
2. Verify webhook URL is accessible (not behind firewall)
3. Check server logs for incoming requests
4. Ensure CORS is properly configured

### Decryption Failed

1. Verify `SECRET_KEY` matches your PayNinja account
2. Check that IV is exactly 16 characters
3. Verify encrypted data format (base64 encoded)
4. Check logs for detailed error messages

### Transaction Not Found

1. Verify `merchant_reference_id` matches the transaction
2. Check that transaction was created before webhook
3. Review transaction logs for reference ID

### Status Not Updated

1. Check database connection
2. Verify transaction exists in database
3. Review error logs for update failures
4. Check transaction_logs for webhook activity

## Example Integration (Frontend)

The webhook runs automatically - no frontend integration needed. However, you can poll transaction status:

```javascript
// Check transaction status
const checkStatus = async (merchantRefId) => {
  const response = await fetch('http://localhost/backend/api/payout/status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ merchant_reference_id: merchantRefId })
  });
  return response.json();
};

// Poll every 5 seconds until success/failed
const pollStatus = async (merchantRefId) => {
  const status = await checkStatus(merchantRefId);
  if (status.data.status === 'SUCCESS' || status.data.status === 'FAILED') {
    return status;
  }
  setTimeout(() => pollStatus(merchantRefId), 5000);
};
```

## API Endpoints Reference

- **Webhook:** `POST /api/payout/webhook.php`
- **Check Status:** `POST /api/payout/status.php`
- **Initiate Payout:** `POST /api/payout/initiate.php`

## Support

For issues or questions:
1. Check logs: `logs/error.log` and `logs/info.log`
2. Review transaction_logs table
3. Contact PayNinja support for webhook delivery issues

