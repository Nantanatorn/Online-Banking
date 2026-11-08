# Chino Bank — Online Banking Simulation

## About

**Chino Bank** is an online banking simulation web app. Users register with a 13-digit national ID and a 6-digit PIN, receive a bank account, and can deposit, withdraw, and transfer money to other accounts, review their transaction history, get notifications, and download receipts.

The project has two parts in a single codebase:

- **Backend**: a REST API built with Laravel, authenticated with JWT (`/api/*`)
- **Frontend**: Vue 3 + Inertia.js pages that call the API with a token

It was created by **Nantanatorn** in **2025** as a learning project, to study how Laravel works and to practice system analysis.

> This is a simulation. It is not connected to any real bank or payment system.

## Tech Stack

| Area | Technology |
|---|---|
| Backend | PHP 8.2+, Laravel 12 |
| Authentication | JWT via `php-open-source-saver/jwt-auth` (login with national ID + PIN) |
| Database | PostgreSQL (recommended, and required for the test suite); SQLite/MySQL can be used for casual experiments |
| Frontend | Vue 3, Inertia.js 2, Vite 6 |
| Styling | Tailwind CSS 4, DaisyUI |
| Frontend libraries | Chart.js (transaction charts), lucide-vue-next (icons), SweetAlert2, Swiper, date-fns, html-to-image (saving receipts as images) |
| Testing | PHPUnit via `php artisan test` |

## Features

**Accounts & security**
- Registration with a 13-digit national ID and a 6-digit PIN (the PIN is stored hashed)
- Login with national ID + PIN, rate limited to 5 attempts per minute to slow down brute-force attacks
- PIN confirmation for every withdrawal and transfer, verified on the server (also limited to 5 attempts per minute)
- Database row locking on every deposit, withdrawal, and transfer, so concurrent requests cannot corrupt balances

**Transactions**
- Deposit, withdraw, and transfer between accounts
- Daily transfer limit of 500,000 THB in total, reset every midnight (Asia/Bangkok time)
- Confirmation step before a transfer that shows the recipient's name (surname abbreviated) so it can be checked first
- Saved (bookmarked) recipient accounts for faster transfers; click one to open the transfer form pre-filled

**History & receipts**
- Transaction history with the running balance at the time of each transaction
- Search/filter by type and date range, with pagination
- Receipt after every transaction, automatically downloaded as a PNG; receipts can also be reopened later from the history table, printed, or saved as PDF
- Pie chart summarizing deposits, withdrawals, transfers, and received money for the current filter

**Notifications**
- In-app notifications (bell icon with an unread count) when money comes in or goes out
- Email notification for every transaction (by default the email is written to the log; see Installation & Setup)
- The dashboard refreshes itself when new activity arrives (checked every 10 seconds)

## Project Structure

```
Online-Banking/
├── app/
│   ├── Console/Commands/       # Artisan commands, e.g. limit:reset
│   ├── Http/Controllers/       # AuthController, TransactionController,
│   │                           # SavedAccountController, NotificationController
│   ├── Mail/                   # Transaction alert email
│   ├── Models/                 # User, BankAccount, Transaction,
│   │                           # SavedAccount, AppNotification
│   ├── Providers/              # AppServiceProvider (login rate limiter)
│   └── Services/               # TransactionNotifier (in-app notification + email)
├── config/                     # Laravel config, including jwt.php
├── database/
│   ├── migrations/             # Table definitions
│   └── seeders/                # Test user
├── docs/                       # Extra documents (e.g. bug report)
├── resources/
│   ├── js/
│   │   ├── pages/              # home, login, register, dashboard, Transaction, About
│   │   ├── components/         # Accsidebar, Tranbutton (deposit/withdraw/transfer + PIN),
│   │   │                       # ReceiptModal, NotificationBell,
│   │   │                       # HistoryFilters, HistoryPagination, ...
│   │   └── composables/        # useAuth (token, money formatting), useTransactionHistory
│   └── views/                  # Blade (Inertia root page and email template)
├── routes/
│   ├── api.php                 # REST API (/api/*)
│   └── web.php                 # Web pages
├── tests/Feature/              # Feature tests for the API
├── composer.json               # PHP dependencies
└── package.json                # JavaScript dependencies
```

## Prerequisites

Install the following first:

- **PHP 8.2 or newer** with these extensions: `bcmath`, `mbstring`, `openssl`, `pdo_pgsql` (or the driver for your chosen database), `fileinfo`, `ctype`, `tokenizer`, `xml`
- **Composer** 2.x
- **Node.js 20 or newer** and npm
- **PostgreSQL** (recommended), with an empty database created in advance

## Installation & Setup

1. **Clone the project and install dependencies**

   ```bash
   git clone <repository-url>
   cd Online-Banking

   composer install
   npm install
   ```

2. **Create the environment file**

   ```bash
   cp .env.example .env
   php artisan key:generate
   php artisan jwt:secret
   ```

3. **Configure the database in `.env`** (example for PostgreSQL)

   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=online_banking
   DB_USERNAME=postgres
   DB_PASSWORD=your_password
   ```

   `.env.example` defaults to SQLite. To use PostgreSQL, change the values above and create the `online_banking` database first.

4. **Create the tables and seed test data**

   ```bash
   php artisan migrate
   php artisan db:seed      # optional: creates a test user
   ```

   The seeded test user logs in with ID `1234567890123` and PIN `123456`. You can also register a new account on the Register page.

5. **(Optional) Configure email.** The default is `MAIL_MAILER=log`, so notification emails are written to `storage/logs/laravel.log` and not actually sent. To send real emails, set `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_FROM_ADDRESS` to your SMTP server.

6. **Run the project.** Open two terminals:

   ```bash
   # Terminal 1: Laravel
   php artisan serve

   # Terminal 2: Vite (frontend)
   npm run dev
   ```

   Then open <http://127.0.0.1:8000>.

   For production, run `npm run build` instead of `npm run dev`.

### Running the tests

The tests must run on a PostgreSQL database whose name ends with `_test`. They wipe every table, so the test suite refuses to run on any other database. Create a `online_banking_test` database, then run:

```bash
# Linux / macOS / Git Bash
DB_CONNECTION=pgsql DB_DATABASE=online_banking_test php artisan test
```

```powershell
# PowerShell
$env:DB_CONNECTION="pgsql"; $env:DB_DATABASE="online_banking_test"; php artisan test
```

### Useful commands

| Command | What it does |
|---|---|
| `php artisan limit:reset` | Reset the daily transfer limit for all users (history is kept) |
| `php artisan limit:reset --user=<national ID>` | Reset the limit for a single user |
| `npm run lint` | Lint and fix JavaScript with ESLint |
| `npm run format` | Format code with Prettier |

### API overview

Every endpoint below requires an `Authorization: Bearer <token>` header, except register and login.

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/register`, `/api/login` | Register / log in (returns a token) |
| POST | `/api/deposit` | Deposit money |
| POST | `/api/withdraw` | Withdraw money (requires `pin`) |
| POST | `/api/transfer` | Transfer money (requires `pin`) |
| GET | `/api/transfer/limit` | Remaining daily transfer limit |
| GET | `/api/transactionhis` | History (`type`, `from`, `to`, `page`, `per_page`) |
| GET | `/api/transactions/{id}/receipt` | Receipt data |
| GET | `/api/accounts/{id}` | Look up the owner of a destination account |
| GET / POST / DELETE | `/api/saved-accounts` | Saved recipient accounts |
| GET / POST | `/api/notifications`, `/api/notifications/read` | Notifications / mark all as read |

## Author

Created by **Nantanatorn** in 2025 as a learning project to study how Laravel works and to practice system analysis.
