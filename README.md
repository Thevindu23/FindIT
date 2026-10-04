# FindIT — Lost and Found Management System

A full-stack web application for reporting lost and found belongings in Sri Lanka. Users can browse reports, report items, submit ownership claims, and track resolution through an administrator review process.

Guests can browse items without an account. Registered users submit reports and claims, while administrators manage items, review claims, and track rewards in Sri Lankan rupees (LKR).

Built as a second-year university project using HTML, CSS, JavaScript, PHP, and MySQL.

## Features

- **Item lifecycle** — Lost / Found → Claim Pending → administrator review → Resolved.
- **Role-based access** — User and administrator permissions enforced by the PHP API.
- **User accounts** — Registration, login, logout, hashed passwords, and PHP sessions.
- **Public item listings** — Search by title or location, filter by category and status, sort, and browse pages.
- **Item images** — Optional PNG, JPEG, or WebP images up to 1 MB.
- **Claim management** — Submit claims on other users' reports; administrators approve or reject them.
- **Item management** — Administrators edit, remove, and resolve eligible reports.
- **Dashboard** — Database statistics for items, claims, and rewards.
- **Reward tracking** — Record simulated payouts in LKR. No real money is transferred.
- **Validation** — Form checks, duplicate-submission protection, and helpful error messages.

Rejecting the last pending claim restores the item's original Lost or Found status. Approving a claim resolves the item and rejects competing pending claims.

## Architecture

```text
Browser (HTML, CSS, JavaScript)
        |
        | fetch() requests / JSON responses
        | Session cookie
        v
PHP API
        |
        | PDO prepared statements
        v
MySQL database
```

| Layer | Technology | Purpose |
| --- | --- | --- |
| Frontend | HTML, CSS, JavaScript | Pages, forms, navigation, and displaying results |
| Backend | PHP | Validation, authentication, permissions, and application logic |
| Database | MySQL | Accounts, item reports, claims, and reward records |
| Sessions | PHP sessions | Login state stored outside the public website folder |
| Images | MySQL data URLs | Small report images stored with their items |

MySQL stores the application data; reports and accounts are not saved in browser localStorage. This version does not require Node.js, npm, Composer, or a frontend framework.

## Repository layout

```text
FindIT/
|-- public/                 # Website document root
|   |-- *.html              # Home, login, signup, reports, and admin pages
|   |-- css/                # Stylesheets
|   |-- js/                 # Browser JavaScript
|   |-- api/                # PHP API endpoints
|       |-- auth/           # Register, login, logout, session
|       |-- items/          # Browse and manage reports
|       |-- claims/         # Submit and review claims
|-- config/database.php     # Database configuration from .env
|-- includes/               # Shared database, validation, and login functions
|-- database/schema.sql     # Database table definitions
|-- scripts/setup.php       # Create or update the database
|-- scripts/admin.php       # Promote a registered user to administrator
|-- tests/integration.php   # Automated API and database checks
|-- storage/                # Local sessions and test logs (ignored by Git)
|-- backups/                # Local backups (ignored by Git)
|-- .env.example            # Example database settings
|-- start.bat               # Windows startup script
|-- TEST-CHECKLIST.md        # Manual browser checks
|-- README.md
```

## Prerequisites

- PHP 8.1 or newer with PDO MySQL, mbstring, and sessions.
- A running MySQL server.
- A MySQL account with database and table creation permissions for setup.
- PHP sodium for existing passwords migrated from the previous Node.js version.
- PHP curl to run the automated tests.

WAMP or XAMPP can provide PHP and MySQL for local development.

## Local development

### 1. Configure the database

From the project folder, copy the example configuration **only if you do not already have a working .env**:

```powershell
Copy-Item .env.example .env
```

Edit the values for your local MySQL installation:

```dotenv
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=your_mysql_password
DB_NAME=findit_db
```

Start MySQL before continuing.

### 2. Start the application

On Windows, double-click `start.bat`. It finds PHP in PATH or common WAMP/XAMPP folders, runs database setup, and starts the website.

Alternatively, with PHP on PATH:

```sh
php scripts/setup.php
php -S localhost:8000 -t public
```

App URL: [http://localhost:8000](http://localhost:8000)

Keep the server running while using the app. Press Ctrl+C to stop it. Open the app URL rather than opening HTML files directly.

For old accounts that need sodium, enable it in PHP or start with:

```sh
php -d extension=sodium -S localhost:8000 -t public
```

The Windows launcher adds this option when sodium is not already loaded.

## User and administrator accounts

### Users

Select **Sign up** and register through the website. Public registration creates a normal user account. New passwords must be 8 to 72 bytes and are stored as bcrypt hashes.

### Administrators

Register an account first, then run:

```sh
php scripts/admin.php your-email@example.com
```

Log in to access the administrator dashboard. There is no default administrator password. If PHP is not on PATH, use the full path to your `php.exe` when running commands.

## Environment variables

Configuration is stored in the root `.env` file. See [.env.example](.env.example) for the template.

| Variable | Purpose |
| --- | --- |
| DB_HOST | MySQL server hostname |
| DB_PORT | MySQL server port |
| DB_USER | MySQL username |
| DB_PASSWORD | MySQL password |
| DB_NAME | FindIT database name |

The real `.env`, backups, and session files are excluded from Git. Keep credentials outside the public website folder.

## Database and access rules

The main tables are `users`, `items`, and `claims`. A supporting `login_attempts` table limits repeated login attempts.

- Reporter contact details require login.
- Claim details are restricted to the claimant, report owner, and administrators.
- Users cannot claim their own reports or submit duplicate pending claims for the same item.
- A simulated reward payout requires a resolved item, an approved claim, and a positive reward. Repeating it does not count the reward twice.
- Paid records are retained.
- PHP sessions expire after eight hours and use HttpOnly, SameSite cookies.

Setup preserves existing accounts, reports, and claims and backs up existing records before the first PHP migration. Supported old password hashes upgrade during login. Old Node session rows may remain in MySQL but are no longer used; users sign in again.

## Deployment overview

Use a server or hosting account with PHP and MySQL:

1. Upload the project and set the website document root to `public/`.
2. Configure the database connection outside the public folder.
3. Run `php scripts/setup.php` before serving the application.
4. Allow PHP to write session files under `storage/`.
5. Register and promote the initial administrator.
6. Serve the website over HTTPS.

The PHP built-in server is for local development. Previous Node/Express startup and deployment instructions do not apply to this version.

## Tests

```sh
php -d extension=sodium tests/integration.php
```

Omit the sodium option if the extension is already enabled. Tests require PHP curl and permission to create and drop a temporary database.

The suite covers authentication, sessions, role permissions, input validation, reports, search, claims, simulated rewards, deletion, and logout. Each run creates a uniquely named `findit_php_test_...` database and removes only that test database afterward.

See [TEST-CHECKLIST.md](TEST-CHECKLIST.md) for manual browser checks. The [health endpoint](http://localhost:8000/api/health.php) checks database connectivity and required tables.

## Learning the code

Follow one report from the browser to the database:

1. `public/report.html` contains the form.
2. `public/js/script1.js` reads the form and sends a request.
3. `public/api/items/create.php` checks the request and saves the report.
4. Shared functions in `includes/` handle validation, authentication, and database queries.
5. JavaScript displays the response.

The main pages are `index.html`, `signup.html`, `adminlogin.html`, `report.html`, `item.html`, `adminhome.html`, `itemmanagement.html`, and `bountymanagement.html`, all inside `public/`.

## Troubleshooting

| Problem | What to check |
| --- | --- |
| PHP is not found | Add PHP to PATH or update the PHP location in start.bat. |
| Database connection fails | Start MySQL and check the host, port, username, and password in .env. |
| A PHP extension is missing | Enable it in the PHP installation used by the launcher. |
| Port 8000 is busy | Stop the other server or choose another port in the PHP server command. |
| Admin access is denied | Register first and promote the correct email using scripts/admin.php. |
| An old login needs sodium | Enable sodium or use the startup command shown above. |

## Project scope

FindIT is an educational lost-and-found application. It does not include real payment processing, email delivery, or password recovery. Image storage is intended for a small project dataset.

Repository: [Thevindu23/FindIT](https://github.com/Thevindu23/FindIT)
