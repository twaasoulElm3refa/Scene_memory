# Support conversations

The existing `contacts` table remains the ticket table. The migration adds status, assignment, activity, read position, and idempotency fields. `contact_messages` stores the original message and every subsequent reply. The migration copies legacy `contacts.message` and `contact_responds` into the new history with unique legacy identifiers so a partial backfill can be resumed safely. Historical rows are retained.

## Statuses

- Creation and any customer reply set `open`.
- A staff reply sets `pending`.
- Staff can set `open`, `pending`, or `resolved` from the existing Contacts admin screen. A customer reply to a resolved ticket reopens it.

## Access

- Authenticated users list only tickets with their `user_id`; reading and replying check ownership again.
- Guests receive a 64-character private access token at creation and by email. The browser keeps it for later visits. It expires after 30 days and can be revoked by clearing `guest_access_token` in the database. Staff replies extend its expiry and send a fresh link. The token is encrypted in the database and omitted from ordinary ticket responses.
- Admin routes use the existing Sanctum and `AdminMiddleware` checks. Read positions for administrators are tracked separately.
- Request and message retry UUIDs are unique. Reusing a UUID with different content returns 409.

## API

Existing `POST /api/v1/contacts/create` and admin `/api/v1/contacts` routes remain. The user conversation routes are `GET /api/v1/support/tickets`, `GET /api/v1/support/tickets/{id}`, and `POST /api/v1/support/tickets/{id}/messages`. Guest reads and replies pass `X-Support-Access`. The existing admin reply route remains; `PATCH /api/v1/contacts/{id}/status` manages status and assignment. Lists and message histories are paginated.

## Deployment

Run `php artisan migrate` before serving the new frontend bundle. Configure a working mail transport and queue worker for queued support notices. `SUPPORT_EMAIL` selects the staff inbox and defaults to the existing contact address. Set the public application URL correctly so emailed guest links reach the site. A mail queue failure does not undo a committed ticket or reply. The migration deliberately refuses rollback because dropping conversation messages would erase support history.
