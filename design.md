# Frontend Design — Personal Finance Tracker

## 1. Overview

The frontend is an Indonesian-language React SPA for managing personal finances. It runs separately in `frontend/` and consumes this project's Laravel REST API. The design prioritizes scannable summaries, fast transaction entry, and a comfortable mobile experience.

See [`plan.md`](./plan.md) for the implementation scope and roadmap.

## 2. Design Principles

- **Make financial data clear:** income, expenses, balances, and transfers use consistent labels and visual styles; color alone must not distinguish them.
- **Keep primary actions within reach:** transaction entry is accessible from the main navigation and dashboard.
- **Prioritize readability:** use thousands separators, IDR currency formatting, and consistent numeric alignment in tables.
- **Be responsive:** the desktop sidebar becomes mobile navigation on small screens, and tables become lists or cards that retain essential information.
- **Make states visible:** every page has clear loading, empty, and error states with an appropriate recovery action.
- **Stay consistent with the API:** Laravel is the source of financial data; components must not calculate the same figures differently.

## 3. Visual Direction

- **Tone:** clean, calm, trustworthy, and less dense than a spreadsheet.
- **Background:** very light gray; white content surfaces with subtle borders.
- **Primary color:** indigo/blue for navigation and primary actions.
- **Income:** green with an icon and the “Income” label.
- **Expense:** soft red with an icon and the “Expense” label.
- **Transfer:** neutral blue with an arrow icon and the “Transfer” label.
- **Typography:** UI sans-serif; larger balance figures and tabular number styling in tables where available.
- **Shape:** moderately rounded cards, subtle shadows only for priority elements, and consistent spacing.

Define final colors as design tokens during implementation, with accessible contrast for text and controls.

## 4. Navigation and Routes

| Route | Page | Access |
| :--- | :--- | :--- |
| `/login` | Sign in | Public |
| `/register` | Register | Public |
| `/` | Dashboard | Authenticated |
| `/transactions` | Transaction list | Authenticated |
| `/transactions/new` | Add transaction | Authenticated |
| `/transactions/:id/edit` | Edit transaction | Authenticated |
| `/accounts` | Accounts and balances | Authenticated |
| `/categories` | Categories | Authenticated |
| `/reports` | Reports | Authenticated |

Unauthenticated users visiting a protected route are redirected to `/login`. Authenticated users visiting `/login` or `/register` are redirected to `/`.

## 5. Application Shell

### Desktop

```text
┌──────────────────┬──────────────────────────────────────────────┐
│ Brand / logo     │ Header: page title       period / profile    │
│ Dashboard        ├──────────────────────────────────────────────┤
│ Transactions     │                                              │
│ Accounts         │                Page content                  │
│ Categories       │                                              │
│ Reports          │                                              │
│                  │                                              │
│ Profile / logout │                                              │
└──────────────────┴──────────────────────────────────────────────┘
```

### Mobile

```text
┌──────────────────────────────┐
│ Page title          Profile  │
├──────────────────────────────┤
│                              │
│         Page content         │
│                              │
├──────────────────────────────┤
│ Home  Activity  +  Accounts ⋯│
└──────────────────────────────┘
```

The “More” menu contains Categories, Reports, and Logout to keep the bottom navigation compact. The add-transaction button must have an accessible label and be keyboard reachable.

## 6. Page Specifications

### 6.1 Sign in and registration

- Sign-in fields: email and password. Registration also requests a name and password confirmation.
- Show Laravel validation errors next to their fields and general authentication errors above the form.
- The submit button has a loading state and prevents duplicate submissions.
- Provide links to switch between sign-in and registration.

### 6.2 Dashboard

Content order:

1. Greeting and active period.
2. Total balance card using `total_net_balance`.
3. Current-month cashflow summary: income, expenses, and net cash flow.
4. Income/expense trend chart with a range supported by the API.
5. Active accounts and their current balances.
6. Five recent transactions and a link to all transactions.

When there are no accounts or transactions, show a concise message and a “Add account” or “Add transaction” call to action. The summary comes from `GET /dashboard/summary`; chart data comes from the reports endpoints.

### 6.3 Transactions

- Header with page title, period filters, and an “Add transaction” button.
- Filters for type, account, category, month, year, and search; apply filters to the API query.
- Desktop: table columns for date, description/category, account, type, and amount.
- Mobile: compact transaction cards with the amount easy to find.
- Use paginator metadata from the API for pagination controls.
- Row/card actions provide details/edit and delete with a confirmation dialog.
- Amount styling: `+` for income, `−` for expense, and a “Transfer” label for transfers. Always format amounts as IDR.

### 6.4 Transaction form

Use one form for creating and editing transactions:

1. Select a type: income, expense, or transfer.
2. Enter the amount and transaction date.
3. For income/expense, select a source account and a category of the matching type.
4. For transfers, select source and destination accounts; they must differ, and the category field is hidden.
5. Add optional notes.
6. Show a review summary before submission and a saving indicator.

Client-side validation improves the experience, but server validation remains authoritative. After a successful mutation, close the form and invalidate affected transaction, account, report, and dashboard data.

### 6.5 Accounts

- Show total balance and a balance breakdown by account type.
- Account cards/lists show name, type, current balance, API color/icon, and a masked account number when available.
- Create and edit forms include name, type, optional account number, color, and icon. Initial balance is only available on creation.
- “Reconcile balance” asks for the latest balance and sends `POST /accounts/{id}/reconcile`.
- Label the delete action “Archive” because the API deactivates the account through `DELETE`.

### 6.6 Categories

- Separate income categories from expense categories.
- Mark built-in categories (`user_id` is null) and do not show edit/delete actions for them.
- Allow users to create, edit, and delete their own categories.
- Before deletion, explain that transactions using the category may lose their category relationship, according to API behavior.

### 6.7 Reports

- Use one month/year selector for all report panels.
- Show total income, total expense, and net savings (`net_savings`).
- Visualize expense category breakdown using `breakdown` and colors from the API.
- Visualize cashflow using `income`, `expense`, and `net` values for each `period`.
- Show expense distribution by account.
- Download CSV for the selected period.
- For empty datasets, show an empty state instead of a confusing blank chart.

## 7. Components and Responsibilities

- `AppShell`: responsive layout, navigation, header, and route outlet.
- `ProtectedRoute`: session checks and redirects.
- `Money`: IDR formatting and type-based sign/color display.
- `PeriodFilter`: reusable month/year selector.
- `TransactionForm`: dynamic fields for income, expense, and transfer.
- `TransactionList`: desktop table and mobile list.
- `AccountCard`, `CategoryForm`, `ConfirmDialog`: focused domain components.
- `PageState`: consistent loading, error, and empty state variants.
- `apiClient`: base URL, bearer token, and centralized API error handling.

Presentation components do not access the API directly; queries and mutations belong in their relevant features or hooks.

## 8. Data and Error Behavior

- Format currency with `Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' })`. API amounts may be decimal strings or numbers.
- Format transaction dates in local time without shifting `YYYY-MM-DD` dates.
- Read transaction rows from `data.data`, not directly from `data`.
- Laravel validation responses generally contain `message` and an `errors` map; show relevant field errors.
- On `401`, end the frontend session and redirect to sign-in. For `403/404`, explain that the data is unavailable or unauthorized.
- Network errors provide a retry action and must not be silently replaced with empty data.
- Use `sessionStorage` for the MVP token. Avoid storing financial information beyond temporary cache data; do not store tokens in `localStorage` without evaluating XSS risks and cross-browser session requirements.
- The CSV export returns a file response directly; download it as a blob and use the `Content-Disposition` filename when available.

## 9. Responsive Design and Accessibility

- Target three breakpoints: mobile (< 640 px), tablet (640–1023 px), and desktop (>= 1024 px).
- Prevent horizontal overflow; transform transaction tables into cards on narrow screens.
- Every field has a label, errors are associated with their fields, and interactive controls have visible keyboard focus.
- Dialogs manage focus and can be closed with Escape; destructive confirmations have a clear cancel action.
- Charts have titles/labels and a text summary or companion table so information is not conveyed only visually.
- Never rely on color alone to indicate transaction type.

## 10. Out of Scope for the MVP

- Receipt file uploads: the API currently accepts `attachment` as a string and does not document a file upload flow.
- Budgets, recurring transactions, multiple currencies, notifications, and bank synchronization.
- Arbitrary transaction date-range filters; the API currently supports month and year.
- Transaction history within account details; the current account detail endpoint returns only the account object.
