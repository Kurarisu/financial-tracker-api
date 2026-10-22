# API Documentation

Dokumentasi endpoint API untuk Personal Finance Tracker backend.

Base URL:
- Local development: `http://localhost:8000/api`

Autentikasi:
- Semua endpoint di bawah ini kecuali register/login memerlukan token Sanctum.
- Header:
  - `Authorization: Bearer <token>`

Format respons standar:
```json
{
  "success": true,
  "message": "Deskripsi respons",
  "data": {}
}
```

Error response:
```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

---

## 1. Auth API

### 1.1 Register
- Method: `POST`
- Endpoint: `/auth/register`
- Body:
```json
{
  "name": "Alice Tester",
  "email": "alice@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!"
}
```

Response 201:
```json
{
  "success": true,
  "message": "User registered successfully.",
  "data": {
    "user": {
      "id": 1,
      "name": "Alice Tester",
      "email": "alice@example.com"
    },
    "token": "<sanctum-token>"
  }
}
```

### 1.2 Login
- Method: `POST`
- Endpoint: `/auth/login`
- Body:
```json
{
  "email": "alice@example.com",
  "password": "Password123!"
}
```

Response 200:
```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "user": {
      "id": 1,
      "name": "Alice Tester",
      "email": "alice@example.com"
    },
    "token": "<sanctum-token>"
  }
}
```

### 1.3 Me
- Method: `GET`
- Endpoint: `/auth/me`
- Header: `Authorization: Bearer <token>`

Response 200:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Alice Tester",
    "email": "alice@example.com"
  }
}
```

### 1.4 Logout
- Method: `POST`
- Endpoint: `/auth/logout`
- Header: `Authorization: Bearer <token>`

Response 200:
```json
{
  "success": true,
  "message": "Logged out successfully."
}
```


### 1.5 Update profile
- Method: `PATCH`
- Endpoint: `/auth/me`
- Header: `Authorization: Bearer <token>`
- Body:
```json
{
  "name": "Alice Tester",
  "email": "alice@example.com"
}
```

The email must be unique. Changing the email clears `email_verified_at`.

### 1.6 Update password
- Method: `PUT`
- Endpoint: `/auth/password`
- Header: `Authorization: Bearer <token>`
- Body:
```json
{
  "current_password": "CurrentPassword123!",
  "password": "NewPassword123!",
  "password_confirmation": "NewPassword123!"
}
```

Updating the password revokes the user's other API tokens while keeping the current session active.

### 1.7 Delete account
- Method: `DELETE`
- Endpoint: `/auth/me`
- Header: `Authorization: Bearer <token>`
- Body:
```json
{
  "password": "CurrentPassword123!"
}
```

Deleting an account revokes all of the user's API tokens and permanently deletes user-owned data through the configured database cascades.
---

## 2. Account API

### 2.1 List Accounts
- Method: `GET`
- Endpoint: `/accounts`
- Header: `Authorization: Bearer <token>`

Response 200:
```json
{
  "success": true,
  "message": "Accounts retrieved successfully.",
  "data": {
    "total_net_balance": 1750,
    "breakdown": {
      "bank": 1500,
      "ewallet": 250,
      "cash": 0,
      "investment": 0,
      "other": 0
    },
    "accounts": [
      {
        "id": 1,
        "user_id": 1,
        "name": "BCA",
        "type": "bank",
        "account_number": null,
        "initial_balance": 1000,
        "current_balance": 1500,
        "color": "#3B82F6",
        "icon": "wallet",
        "is_active": true,
        "created_at": "2026-10-01T00:00:00.000000Z",
        "updated_at": "2026-10-01T00:00:00.000000Z"
      }
    ]
  }
}
```

### 2.2 Create Account
- Method: `POST`
- Endpoint: `/accounts`
- Header: `Authorization: Bearer <token>`
- Body:
```json
{
  "name": "Mandiri",
  "type": "bank",
  "account_number": "130-000",
  "initial_balance": 3000,
  "color": "#0066CC",
  "icon": "landmark",
  "is_active": true
}
```

### 2.3 Show Account
- Method: `GET`
- Endpoint: `/accounts/{id}`

### 2.4 Update Account
- Method: `PUT` / `PATCH`
- Endpoint: `/accounts/{id}`
- Body contoh:
```json
{
  "name": "BCA Baru",
  "color": "#00529B",
  "icon": "landmark",
  "is_active": true
}
```

### 2.5 Delete / Archive Account
- Method: `DELETE`
- Endpoint: `/accounts/{id}`

### 2.6 Reconcile Balance
- Method: `POST`
- Endpoint: `/accounts/{id}/reconcile`
- Body:
```json
{
  "current_balance": 2500000
}
```

### 2.7 Set Opening Cash Balance
- Method: `POST`
- Endpoint: `/accounts/{id}/opening-balance`
- Use this once during new-user setup, before the cash account has any transactions. It sets both `initial_balance` and `current_balance`.
- Body:
```json
{
  "initial_balance": 250000
}
```

---

## 3. Category API

### 3.1 List Categories
- Method: `GET`
- Endpoint: `/categories?type=expense`
- Header: `Authorization: Bearer <token>`

Response 200:
```json
{
  "success": true,
  "message": "Categories retrieved successfully.",
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "name": "Food",
      "type": "expense",
      "color": "#EF4444",
      "icon": "utensils",
      "current_month_spent": 1800,
      "monthly_budget": 2600,
      "budget_used_percentage": 69.2,
      "spending_change_percentage": 4.5,
      "created_at": "2026-10-01T00:00:00.000000Z",
      "updated_at": "2026-10-01T00:00:00.000000Z"
    }
  ]
}
```

For expense categories, `current_month_spent` is the user's total expense transactions for the current calendar month. `monthly_budget` is specific to the authenticated user and may be `null` until set. `budget_used_percentage` is `null` when no positive budget is set. `spending_change_percentage` compares this month's spending with the previous calendar month and is `null` if there was no spending in that previous month. These summary fields are zero or `null` for income categories.

### 3.2 Set Category Monthly Budget
- Method: `PUT`
- Endpoint: `/categories/{id}/budget`
- Header: `Authorization: Bearer <sanctum-token>`
- Body:
```json
{
  "monthly_budget": 2600
}
```

Budgets can be set on expense categories, including shared categories, and are stored separately for each user.

### 3.3 Create Category
- Method: `POST`
- Endpoint: `/categories`
- Header: `Authorization: Bearer <token>`
- Body:
```json
{
  "name": "Salary",
  "type": "income",
  "icon": "money-bill",
  "color": "#10B981"
}
```

### 3.4 Update Category
- Method: `PUT` / `PATCH`
- Endpoint: `/categories/{id}`

### 3.5 Delete Category
- Method: `DELETE`
- Endpoint: `/categories/{id}`

---

## 4. Transaction API

### 4.1 List Transactions
- Method: `GET`
- Endpoint: `/transactions`
- Query params:
  - `month` (optional, 1-12)
  - `year` (optional, 1-9999)
  - `account_id` (optional, must belong to the authenticated user)
  - `category_id` (optional, must belong to the user or be a shared category)
  - `type` (optional: `income`, `expense`, or `transfer`)
  - `search` (optional, maximum 255 characters)
  - `per_page` (optional, 1-100; defaults to 15)

Contoh:
```http
GET /transactions?type=income&month=10&year=2026&per_page=15
```

### 4.2 Create Transaction
- Method: `POST`
- Endpoint: `/transactions`
- Header: `Authorization: Bearer <token>`
- Body income/expense:
```json
{
  "account_id": 1,
  "category_id": 2,
  "type": "income",
  "amount": 250000,
  "transaction_date": "2026-10-01",
  "notes": "Monthly salary"
}
```

Body transfer:
```json
{
  "account_id": 1,
  "to_account_id": 2,
  "type": "transfer",
  "amount": 50000,
  "transaction_date": "2026-10-01",
  "notes": "Top up e-wallet"
}
```

Response 201:
```json
{
  "success": true,
  "message": "Transaction created successfully.",
  "data": {
    "id": 1,
    "user_id": 1,
    "account_id": 1,
    "to_account_id": null,
    "category_id": 2,
    "type": "income",
    "amount": "250000.00",
    "transaction_date": "2026-10-01",
    "notes": "Monthly salary",
    "attachment": null,
    "created_at": "2026-10-01T00:00:00.000000Z",
    "updated_at": "2026-10-01T00:00:00.000000Z"
  }
}
```

### 4.3 Show Transaction
- Method: `GET`
- Endpoint: `/transactions/{id}`

### 4.4 Update Transaction
- Method: `PUT` / `PATCH`
- Endpoint: `/transactions/{id}`

### 4.5 Delete Transaction
- Method: `DELETE`
- Endpoint: `/transactions/{id}`

Catatan:
- Akun sumber dan tujuan harus milik pengguna yang sedang login. Kategori harus milik pengguna tersebut atau merupakan kategori bersama, dan tipe kategori harus sesuai dengan tipe transaksi.
- `amount` harus lebih besar dari 0 dan maksimal 2 digit desimal.
- Transaksi `expense` dan `transfer` ditolak jika saldo akun sumber tidak mencukupi; saldo tidak akan dibuat negatif oleh transaksi tersebut.
- Kesalahan validasi mengembalikan HTTP `422` beserta detail pada field yang bermasalah.
- Pada tipe `expense` dan `income`, saldo akun akan otomatis berubah sesuai logika service layer.
- Pada tipe `transfer`, saldo sumber dan tujuan akan berubah secara atomik dalam satu transaksi database.

---

## 5. Dashboard API

### 5.1 Summary
- Method: `GET`
- Endpoint: `/dashboard/summary`
- Header: `Authorization: Bearer <token>`

Response 200:
```json
{
  "success": true,
  "message": "Dashboard summary retrieved successfully.",
  "data": {
    "total_net_balance": 1680,
    "cashflow": {
      "current_month_income": 300,
      "current_month_expense": 120,
      "net_cash_flow": 180
    },
    "accounts": [
      {
        "id": 1,
        "name": "BCA",
        "type": "bank",
        "current_balance": 1000,
        "color": "#3B82F6",
        "icon": "wallet"
      }
    ],
    "recent_transactions": [
      {
        "id": 2,
        "type": "expense",
        "amount": "120.00",
        "notes": "Lunch",
        "transaction_date": "2026-10-01"
      }
    ]
  }
}
```

---

## 6. Activity Log API

### 6.1 List Activity Logs
- Method: `GET`
- Endpoint: `/activity-logs`
- Header: `Authorization: Bearer <sanctum-token>`
- Query params:
  - `per_page` (optional, default `20`, maximum `100`)

Activity logs are created for authenticated API requests. Each entry contains the HTTP method, path, response status, IP address, user agent, and timestamp. Request bodies and query parameters are not stored. Results only include the authenticated user's activity and are ordered newest first.

Response 200:
```json
{
  "success": true,
  "message": "Activity logs retrieved successfully.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "user_id": 1,
        "method": "GET",
        "path": "/api/dashboard/summary",
        "status_code": 200,
        "ip_address": "127.0.0.1",
        "user_agent": "Example API client",
        "created_at": "2026-10-05T00:00:00.000000Z",
        "updated_at": "2026-10-05T00:00:00.000000Z"
      }
    ],
    "per_page": 20,
    "total": 1
  }
}
```

## 7. Reports API

### 7.1 Monthly Summary
- Method: `GET`
- Endpoint: `/reports/monthly`
- Query params:
  - `month`
  - `year`

Contoh:
```http
GET /reports/monthly?month=10&year=2026
```

Response 200:
```json
{
  "success": true,
  "message": "Monthly report retrieved successfully.",
  "data": {
    "month": 10,
    "year": 2026,
    "total_income": 800,
    "total_expense": 200,
    "net_savings": 600
  }
}
```

### 7.2 Category Breakdown
- Method: `GET`
- Endpoint: `/reports/category-breakdown`
- Query params:
  - `month`
  - `year`
  - `type=expense`

Response 200:
```json
{
  "success": true,
  "message": "Category breakdown retrieved successfully.",
  "data": {
    "period": "10-2026",
    "total_expense": 200,
    "breakdown": [
      {
        "category_id": 1,
        "name": "Food",
        "color": "#EF4444",
        "total_amount": 200,
        "percentage": 100
      }
    ]
  }
}
```

### 7.3 Cashflow Trend
- Method: `GET`
- Endpoint: `/reports/cashflow-trend`
- Query params:
  - `months` (default `6`)

Response 200:
```json
{
  "success": true,
  "message": "Cashflow trend retrieved successfully.",
  "data": [
    {
      "period": "2026-05",
      "income": 2000,
      "expense": 1200,
      "net": 800
    }
  ]
}
```

### 7.4 Account Distribution
- Method: `GET`
- Endpoint: `/reports/account-distribution`
- Query params:
  - `month`
  - `year`

Response 200:
```json
{
  "success": true,
  "message": "Account distribution retrieved successfully.",
  "data": [
    {
      "account_id": 1,
      "name": "BCA",
      "type": "bank",
      "total_amount": 350
    }
  ]
}
```

---

## 8. Notes

- Semua transaksi keuangan disimpan dalam tipe `DECIMAL(15,2)` di database.
- Semua query yang berkaitan dengan data user harus dibatasi berdasarkan `user_id`.
- Transfer antar akun dijamin aman dengan database transaction dan saldo tersinkronisasi.
- Untuk frontend, endpoint dashboard dan report sudah disesuaikan agar mudah dikonsumsi untuk chart dan summary UI.

---

## 9. Quick Example

```bash
# Register
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Alice Tester",
    "email": "alice@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!"
  }'

# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "alice@example.com",
    "password": "Password123!"
  }'
```

## Receipt scanning

- `POST /api/receipts/scan` (Sanctum bearer token, multipart `receipt`): accepts JPEG, PNG, or WebP up to 10 MB, runs local Tesseract with `ind+eng`, stores a private temporary image, and returns `data.receipt_token` plus editable `data.extracted` suggestions. This endpoint never creates a transaction.
- `POST /api/transactions` may include the `receipt_token` returned by the scan. The normal transaction fields are validated again and the image is attached as part of the confirmed transaction creation flow.
- `GET /api/transactions/{transaction}/attachment` streams the private image only to its owning user.

Install the `tesseract-ocr` and `tesseract-ocr-ind` system packages (both application Dockerfiles install them). Set `TESSERACT_BINARY` only when the executable is not available as `tesseract` on `PATH`. Receipt extraction is heuristic; users must verify each field before saving.
