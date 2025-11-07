# Admin Dashboard Overview API

Endpoint: `GET /api/admin/dashboard/overview.php`

Provides a consolidated snapshot for the admin dashboard.

## Response Format

```json
{
  "status": "success",
  "data": {
    "payninja_balance": {
      "data": {
        "balance": 12345.67,
        "currency": "INR",
        "raw": { "status": "success", "data": { "balance": 12345.67, "currency": "INR" } }
      },
      "error": null
    },
    "vendor_wallets": [
      {
        "vendor_id": "uuid-123",
        "email": "vendor@example.com",
        "status": "active",
        "balance": 4520.25,
        "currency": "INR",
        "updated_at": "2025-11-07 12:34:56"
      }
    ],
    "vendor_status_counts": {
      "pending": 2,
      "active": 15,
      "suspended": 1,
      "total": 18
    },
    "recent_transactions": [
      {
        "merchant_reference_id": "PN-12345",
        "payninja_transaction_id": "TXN-7890",
        "vendor_id": "uuid-123",
        "vendor_email": "vendor@example.com",
        "amount": 1000,
        "status": "SUCCESS",
        "transfer_type": "IMPS",
        "created_at": "2025-11-07 10:00:00",
        "updated_at": "2025-11-07 10:05:00"
      }
    ],
    "top_vendors": [
      {
        "vendor_id": "uuid-123",
        "email": "vendor@example.com",
        "transaction_count": 42,
        "total_amount": 98000
      }
    ],
    "transaction_stats": {
      "overall": {
        "SUCCESS": { "count": 120, "amount": 250000 },
        "PENDING": { "count": 8, "amount": 15000 },
        "FAILED": { "count": 5, "amount": 7000 },
        "PROCESSING": { "count": 2, "amount": 3000 }
      },
      "today": {
        "SUCCESS": { "count": 5, "amount": 11000 },
        "PENDING": { "count": 2, "amount": 4000 },
        "FAILED": { "count": 1, "amount": 1500 },
        "PROCESSING": { "count": 0, "amount": 0 }
      },
      "totals": {
        "success_amount": 250000,
        "success_count": 120
      }
    }
  }
}
```

### Fields

- `status`: `success` or `error`.
- `payninja_balance.data`: live balance from PayNinja. `error` contains the failure reason if fetch fails.
- `vendor_wallets`: list of every vendor wallet with balance, email, and last update.
- `vendor_status_counts`: totals of vendors grouped by status.
- `recent_transactions`: 20 most recent payouts with IDs, status, and amounts.
- `top_vendors`: top 10 vendors by total successful payout amount.
- `transaction_stats`: counts and sums of payouts overall, today, and overall success totals.

> Notes
- All timestamps are IST (Asia/Kolkata).
- Amounts are returned as decimals.
- When PayNinja balance fetch fails, `payninja_balance.data` will be `null` and `error` populated.


