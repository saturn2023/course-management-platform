# Course Management Platform — Project Status

## Current goal

Build a Laravel-based replacement for the AMS Training WordPress/WooCommerce/Zapier flow.

The system should support:

- Course pages and checkout later
- Admin manual orders
- Pin Payments later
- Xero invoice creation
- Enrolment link creation
- Student enrolment emails
- Purchaser confirmation emails
- SMS/Twilio later
- Admin monitoring and retry/resend actions

## Current confirmed backend flow

Manual admin order flow is working:

1. Admin creates order in Filament
2. Billing details are stored on order
3. Multiple students can be attached to one order
4. Order items are attached
5. Admin clicks Process Order
6. Laravel creates real Xero draft invoice
7. Laravel creates one enrolment link per student
8. Laravel sends/logs one enrolment email per student
9. Laravel sends/logs purchaser confirmation email to billing email
10. Integration logs record processing/success/failure/queued/skipped statuses

## Important confirmed Xero proof

Laravel successfully connects to Xero using OAuth.

Working pieces:

- Xero OAuth connect route
- Xero callback route
- XeroConnection table stores tenant/token details
- CreateXeroInvoiceJob creates real draft invoices
- Xero invoice ID and invoice number are saved on orders
- Duplicate Xero invoice creation is prevented

Confirmed real Xero invoice examples:

- 18034
- 18042
- 18048

## Current important models

- Course
- Student
- Order
- OrderItem
- OrderStudent
- Enrolment
- XeroConnection
- IntegrationLog

## Current important jobs

- ProcessOrderJob
- CreateXeroInvoiceJob
- CreateEnrolmentJob
- SendEnrolmentEmailJob
- SendPurchaserConfirmationEmailJob

## Current important mail classes

- EnrolmentLinkMail
- PurchaserConfirmationMail

## Current important routes

- /admin
- /xero/connect
- /xero/callback

## Current email setup

Local testing uses:

MAIL_MAILER=log

Emails are written to:

storage/logs/laravel.log

Search for:

- AMS Registration Form Student to complete
- AMS Training Registered Students

## Current admin flow

Filament admin is used for manual testing and will remain in final system.

Admin can:

- Create courses
- Create students
- Create orders
- Add billing details
- Add multiple students to order
- Add order items
- Process order
- View integration logs
- View enrolments

## Current business flow

Final intended public flow:

1. Student visits course page
2. Student clicks Enrol Now
3. Student goes to checkout
4. Student enters quantity
5. Student details fields appear based on quantity
6. Purchaser/billing details are entered
7. Payment is made using Pin Payments
8. Order becomes paid
9. ProcessOrderJob runs automatically
10. Xero invoice is created
11. Enrolment links are created
12. Student emails are sent
13. Purchaser confirmation email is sent

## Current limitation

Subtotal and total are still manually entered in admin.
Later they should be auto-calculated from order items.

## Next planned tasks

1. Clean Orders table columns
2. Add resend student enrolment email button
3. Add resend purchaser confirmation email button
4. Improve retry logic for failed jobs
5. Add Twilio SMS job
6. Replace fake example.com enrolment links with real AMS enrolment link logic
7. Real SMTP/email provider
8. Public course pages and checkout
9. Pin Payments integration