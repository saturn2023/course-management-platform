## Latest progress update

### Completed backend/admin reliability work

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

- Green for successful/paid/link_sent
- Grey for pending/skipped
- Yellow for processing/link_created
- Red for failed/cancelled

### Admin recovery actions completed

The Orders table now includes admin actions for:

- Process Order
- Retry Order
- Resend student enrolment emails
- Resend purchaser confirmation email
- View invoice in Xero
- Edit order

The retry action was improved so that if an order is stuck at `link_created`, it can force-resend existing enrolment emails and purchaser confirmation instead of requiring Tinker.

### Real SMTP completed

Real SMTP email sending is now working through SMTP2GO.

Confirmed working:

- Raw Laravel SMTP test email
- Student enrolment email through the real order flow
- Purchaser confirmation email through the real order flow
- Resend student emails
- Resend purchaser confirmation email

Local `.env` uses SMTP2GO settings.

Important: `.env` must never be committed to Git.

### Security checkpoint completed

After accidentally exposing old secrets, the SMTP/Xero secrets were rotated.

Confirmed:

- New SMTP2GO credentials work
- New Xero secret works
- Laravel can still send real emails
- Laravel can still create Xero invoices
- Git working tree is clean
- `.env` is not committed

### Current confirmed backend flow

1. Admin creates an order
2. Admin adds billing details
3. Admin attaches one or more students
4. Admin adds order items
5. Admin marks order as paid
6. Admin clicks Process Order
7. Laravel creates a real Xero draft invoice
8. Laravel creates one enrolment link per student
9. Laravel sends real student enrolment emails through SMTP2GO
10. Laravel sends real purchaser confirmation email through SMTP2GO
11. Admin can resend student emails
12. Admin can resend purchaser confirmation email
13. Admin can retry failed or incomplete order processing

### Next planned tasks

Recommended next tasks:

1. Twilio SMS job
2. Secure real enrolment link/token flow
3. Replace fake `example.com/enrolment/...` links
4. Improve admin form safety
5. Auto-calculate or validate subtotal/total
6. Public course page and checkout planning
7. Pin Payments integration later