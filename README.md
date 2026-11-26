# GroceryShare

A personal web app for splitting weekly grocery bills equally three ways — between you and your two sisters. You pay upfront each Sunday, and the app tracks what each sister owes, notifies them by email, and lets you record whatever amount each sister pays back.

## Features

- **Dashboard** — Cards showing each sister's running balance (total shares minus total payments; overpayments show as credit toward next week), a list of all weeks, and a payment history
- **Add a week's bill** — Enter the total grocery bill; the app floors it to the nearest dollar per share (total ÷ 3) and emails both sisters their amount automatically
- **Edit a week** — Correct a bill amount or date after the fact; sister share amounts update automatically
- **Record payment** — Log any amount a sister gives you (full, partial, or extra); delete a payment to undo it
- **Sisters management** — Add, edit, or remove sisters (name, email, and optional phone number) from the Sisters tab
- **SMS notifications** — Sisters with a phone number on file receive a text message alongside the email when a new week is added
- **Test SMS** — Send a one-off test message to verify a sister's phone number is configured correctly
- **WCAG AA accessible** — All text/background colour combinations meet the 4.5:1 contrast ratio standard

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.4 |
| Framework | Laravel 13 |
| Reactive UI | Livewire 4 (single-file components) |
| Styling | Tailwind CSS 4 |
| Database | SQLite |
| Email | Laravel Mail (log driver by default) |
| SMS | Twilio (API key auth) |
| Testing | PHPUnit 12 |
| Local server | Laravel Herd |

## Getting Started

### Requirements

- PHP 8.4
- Composer
- Node.js & npm
- [Laravel Herd](https://herd.laravel.com)

### Installation

```bash
git clone <repo-url> GroceryShare
cd GroceryShare

composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate

npm run build
```

The app is served by Herd at **http://groceryshare.test** automatically.

### First-time setup

1. Open http://groceryshare.test
2. Go to the **Sisters** tab and add both sisters' names and email addresses
3. Return to the **Dashboard** and click **Add Week's Bill** to enter your first grocery total

## Email Configuration

By default, emails are written to `storage/logs/laravel.log` (the `log` mail driver) so no mail server is needed during development. To send real emails, update your `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourmailprovider.com
MAIL_PORT=587
MAIL_USERNAME=you@example.com
MAIL_PASSWORD=yourpassword
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=you@example.com
MAIL_FROM_NAME="GroceryShare"
```

## SMS Configuration

SMS notifications are sent via Twilio using API key authentication. Add the following to your `.env`:

```env
TWILIO_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_API_KEY=SKxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_API_SECRET=your_api_secret
TWILIO_FROM=+15551234567
```

Sisters without a phone number on file will only receive email notifications. The **Test SMS** button in the Sisters tab lets you verify a number before any bills are added.

## Database

SQLite is used via `database/database.sqlite`. To wipe all data and start fresh:

```bash
php artisan migrate:fresh
```

### Export & Import

Export sisters, weeks and shares to `storage/app/private/exports/`:

```bash
# Single JSON file (can be re-imported)
php artisan app:export-data

# One CSV file per table, for spreadsheets
php artisan app:export-data --format=csv

# Write somewhere else
php artisan app:export-data --path=/path/to/folder
```

Restore a JSON export:

```bash
# Into an empty database
php artisan app:import-data storage/app/private/exports/groceryshare-export-2026-11-21_120000.json

# Replace existing sisters, weeks and shares
php artisan app:import-data path/to/export.json --force
```

The import runs in a single transaction, so if anything fails nothing is changed. Only JSON exports can be imported; CSV is for viewing only.

## Key Files

```
app/Models/
  Sister.php          # name, email, phone; outstandingTotal() = shares − payments
  GroceryWeek.php     # week_date, total_amount, share_amount, notes
  GroceryShare.php    # links a week to a sister with the amount owed
  Payment.php         # money a sister paid you: amount, paid_on, notes

app/Mail/
  ShareNotification.php   # email sent to each sister when a week is added

app/Services/
  TwilioService.php       # wraps Twilio SDK for sending SMS messages

app/Console/Commands/
  ExportData.php          # app:export-data (JSON or CSV)
  ImportData.php          # app:import-data (JSON, --force to overwrite)

resources/views/
  layouts/app.blade.php                  # HTML shell with header
  welcome.blade.php                      # mounts the Dashboard component
  components/⚡dashboard.blade.php       # main Livewire SFC (all UI)
  emails/share-notification.blade.php    # markdown email template

database/factories/
  SisterFactory.php        # includes withPhone() state
  GroceryWeekFactory.php
  GroceryShareFactory.php
  PaymentFactory.php

tests/
  Unit/SisterTest.php          # outstandingTotal() (partial, credit) and shares relationship
  Unit/GroceryWeekTest.php     # shares relationship
  Feature/DashboardTest.php    # all Livewire component methods (29 tests)
  Feature/ShareNotificationTest.php  # mailable subject, content, properties
  Feature/ExportDataCommandTest.php  # JSON/CSV export output
  Feature/ImportDataCommandTest.php  # round trip, --force, rollback, bad input
```

## Running Tests

```bash
# Run all tests
php artisan test --compact

# Run a specific file
php artisan test --compact tests/Feature/DashboardTest.php

# Filter by test name
php artisan test --compact --filter=test_add_week_creates_grocery_week_and_shares_for_all_sisters
```

The test suite uses an in-memory SQLite database and fakes for mail and Twilio, so no external services are required.
