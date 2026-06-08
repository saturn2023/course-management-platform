# Project Status — AMS Laravel Course Management System

## Latest Progress Update

This Laravel project is rebuilding key AMS Training WordPress/WooCommerce workflows into a more controlled Laravel-based system.

The current system now has working backend/admin flows for:

- Course management
- Orders
- Students
- Xero invoice creation
- Secure enrolment links
- Laravel enrolment form
- RTO Data enrolment API
- File uploads
- Queue-based processing
- Filament admin monitoring

---

## Completed Backend/Admin Reliability Work

The Laravel admin backend now has a more usable Orders table.

Orders table now shows:

- Order ID
- Company
- Billing email
- Student count
- Subtotal
- Total
- Order status
- Xero status
- Enrolment status
- Xero invoice number
- Purchaser email sent status
- Created date

Status badges now use colours:

- Green for successful / paid / link_sent
- Grey for pending / skipped
- Yellow for processing / link_created
- Red for failed / cancelled

---

## Admin Recovery Actions Completed

The Orders table now includes admin actions for:

- Process Order
- Retry Order
- Resend student enrolment emails
- Resend purchaser confirmation email
- View invoice in Xero
- Edit order

The retry action was improved so that if an order is stuck at `link_created`, it can force-resend existing enrolment emails and purchaser confirmation instead of requiring Tinker.

---

## Real SMTP Completed

Real SMTP email sending is now working through SMTP2GO.

Confirmed working:

- Raw Laravel SMTP test email
- Student enrolment email through the real order flow
- Purchaser confirmation email through the real order flow
- Resend student emails
- Resend purchaser confirmation email

Local `.env` uses SMTP2GO settings.

Important: `.env` must never be committed to Git.

---

## Xero Integration Completed

Xero invoice creation is working.

Confirmed:

- New Xero credentials work
- Laravel can create real Xero draft invoices
- Xero invoice number is stored/displayed
- Admin can view invoice in Xero

Important: Xero secrets must remain in `.env` only.

---

## Security Checkpoint Completed

After accidentally exposing old secrets, the SMTP/Xero secrets were rotated.

Confirmed:

- New SMTP2GO credentials work
- New Xero secret works
- Laravel can still send real emails
- Laravel can still create Xero invoices
- Git working tree was checked
- `.env` is not committed

Important: RTO Data public key was also pasted during testing. Rotate it if RTO Data allows, or at minimum avoid committing it or sharing it again.

---

## Secure Enrolment Link Flow Completed

The old fake enrolment link flow has been replaced.

Laravel now generates secure links like:

```text
/enrol/{token}