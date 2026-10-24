# Scemory — User Account & Data Deletion System Implementation

## 1. Executive Summary

A complete, production-ready, secure **User Account and Personal Data Deletion System** has been designed, implemented, and verified in Scemory (Laravel 11, Vue 3, Sanctum, MySQL/SQLite, Vite).

The solution fulfills three primary user-facing and regulatory objectives:
1. **Self-Service In-App Deletion**: Authenticated users can request, confirm, and execute permanent deletion and anonymization directly from their Scemory Profile under the "Account & Privacy" tab.
2. **Meta / Facebook Data Deletion Instructions Compliance**: A dedicated public page (`/{lang}/data-deletion`) available across all 13 supported languages without requiring authentication, accompanied by a status checking tool and a Meta-compliant callback endpoint (`/api/v1/facebook/data-deletion`).
3. **Financial Ledger & Third-Party Entitlement Protection**: Preserves accounting, PayPal transaction logs, creator commission records, and existing purchaser license entitlements by decoupling personal identification through cryptographic scrubbing and pseudonymization, preventing cascading SQL deletions or dangling foreign keys.

All 380 backend tests and 188 frontend tests pass with zero regressions.

---

## 2. Architecture Overview

The system isolates provider-agnostic data deletion logic into dedicated service layers, models, controllers, and jobs:

```
[User Profile UI]  ───┐
                      ├─► [UserDataDeletionController] ─► [UserDataDeletionService]
[Meta / Public Web] ───┘           │                                 │
                                   ├─► [Sanctum Revocation]          ├─► [User Anonymization & Soft Delete]
                                   ├─► [Audit & Reference Tracking]  ├─► [Storage Cleanup: Avatar / Drafts]
                                   └─► [Email OTP Verification]      ├─► [Financial Ledger Retention]
                                                                     └─► [Third-Party Entitlement Protection]
```

### Key Components:
- **`UserDataDeletionRequest`**: Eloquent model representing the lifecycle of deletion requests.
- **`UserDataDeletionService`**: Encapsulates eligibility checks, financial holds, identity verification, PII scrubbing, token revocation, and asset deletion.
- **`ProcessUserDataDeletionJob`**: Queueable, retryable job for asynchronous or admin-triggered deletion execution.
- **`UserDataDeletionController`**: Authenticated API endpoints for user submission, confirmation, status, and cancellation, plus public status lookup and Meta callback.
- **`AdminUserDataDeletionController`**: Administrative interface for inspecting, processing, and rejecting requests held under review.
- **`DataDeletion.vue`**: Localized public guidance page with embedded real-time reference tracking.
- **`profile.vue`**: Dedicated Tab 7 ("Account & Privacy") with danger zone styling, confirmation phrase modal, and password/OTP verification.

---

## 3. Database Changes

### Migration: `2026_10_24_120000_create_user_data_deletion_requests_table.php`

Creates the table `user_data_deletion_requests`:

| Column | Type | Description |
|---|---|---|
| `id` | `bigIncrements` | Primary key |
| `reference` | `string(64)` | Unique public/audit reference (e.g. `DEL-XXXXXX-XXXX`) |
| `user_id` | `foreignId` | Constrained to `users.id` with `nullOnDelete` |
| `user_identifier` | `string(128)` | SHA-256 hash of original user ID (persists after user PII is scrubbed) |
| `masked_email` | `string(255)` | Masked email for display (e.g. `j***e@example.com`) |
| `request_type` | `string(50)` | `full_account_deletion` or `facebook_data_deletion` |
| `status` | `string(50)` | Current lifecycle state |
| `reason` | `text` | Optional user-supplied exit reason |
| `requires_code` | `boolean` | True for social/passwordless accounts |
| `confirmation_code_hash`| `string(255)` | Bcrypt hash of 6-digit confirmation code |
| `confirmation_code_expires_at` | `timestamp` | Code expiration timestamp (15 minutes) |
| `confirmation_code_attempts` | `unsignedInteger` | Failed attempt counter (max 5) |
| `financial_obligations_detected` | `boolean` | Flag set when unsettled wallet balance or withdrawal exists |
| `financial_hold_reason` | `text` | Reason for administrative review |
| `retention_policy_applied` | `json` | Audit record of legal retention policies applied |
| `admin_notes` | `text` | Administrative review notes |
| `failure_reason` | `text` | Reason if status transitions to failed |
| `ip_address` | `string(45)` | Client IP address at submission |
| `user_agent` | `text` | Sanitized client User-Agent |
| `confirmed_at` | `timestamp` | User identity confirmation timestamp |
| `processed_at` | `timestamp` | Deletion processing start timestamp |
| `completed_at` | `timestamp` | Completion timestamp |
| `cancelled_at` | `timestamp` | User or admin cancellation timestamp |
| `timestamps` | `timestamps` | Standard Eloquent timestamps |

---

## 4. API Endpoints

### Authenticated User Endpoints (`auth:sanctum`):
1. **`POST /api/v1/users/data-deletion-request`** (Rate limit: 5/min)
   - Parameters: `reason` (string, optional), `confirmation_phrase` (string, required: must be `DELETE`).
   - Action: Initiates deletion request, evaluates financial holds, determines password vs. code requirement, generates reference, and emails 6-digit OTP if passwordless.
2. **`POST /api/v1/users/data-deletion-confirm`** (Rate limit: 5/min)
   - Parameters: `password` (string, optional), `code` (string, optional), `confirmation_phrase` (string, required: must be `DELETE`).
   - Action: Validates identity, executes deletion if clean, or transitions to `under_review` if financial obligations exist.
3. **`GET /api/v1/users/data-deletion-status`**
   - Action: Returns active deletion request details for the authenticated user.
4. **`POST /api/v1/users/data-deletion-resend-code`** (Rate limit: 3/min)
   - Action: Regenerates and re-emails 6-digit OTP for passwordless accounts.
5. **`POST /api/v1/users/data-deletion-cancel`** (Rate limit: 10/min)
   - Action: Cancels an active deletion request in `pending_confirmation`, `pending`, or `under_review` state.

### Public Endpoints:
1. **`GET /api/v1/data-deletion-status/{reference}`** (Rate limit: 30/min)
   - Returns sanitized status data (reference, status, request type, masked email, financial flag, timestamps) without exposing sensitive PII.
2. **`POST /api/v1/facebook/data-deletion`** (Rate limit: 30/min)
   - Responds to Meta/Facebook app removal callbacks according to Meta Platform Data Deletion specs:
     ```json
     {
       "url": "https://dev.scemory.com/en/data-deletion?reference=DEL-FB-XXXXXXXX",
       "confirmation_code": "DEL-FB-XXXXXXXX"
     }
     ```

### Admin Endpoints (`auth:sanctum`, `AdminMiddleware`):
1. **`GET /api/v1/admin/data-deletion-requests`**
   - Lists paginated deletion requests filterable by `status` and `request_type`.
2. **`GET /api/v1/admin/data-deletion-requests/{id}`**
   - Retrieves full audit record of a specific deletion request.
3. **`POST /api/v1/admin/data-deletion-requests/{id}/process`**
   - Authorizes admin to process and execute deletion after offline settlement.
4. **`POST /api/v1/admin/data-deletion-requests/{id}/reject`**
   - Rejects or fails a request with administrative justification notes.

---

## 5. Deletion Request Lifecycle

```
[User initiates: type 'DELETE']
               │
               ▼
   [status: pending_confirmation]
               │
     Identity Verification:
     • Password account: user supplies current password
     • Social account: user enters 6-digit email OTP
               │
               ▼
    Financial Hold Check?
   ┌───────────┴───────────┐
   ▼                       ▼
[No Hold]              [Hold Detected]
   │                       │
   ▼                       ▼
[status: pending]      [status: under_review]
   │                       │
   ▼                   [Admin settles offline]
[status: processing]       │
   │                       ▼
   ▼                   [Admin calls /process]
[status: completed] ◄──────┘
(PII scrubbed, tokens revoked, sessions purged)
```

At any point prior to `processing`, the user may cancel the request, transitioning it to `cancelled`.

---

## 6. Identity Verification and Security

1. **Strict Ownership Proof**:
   - Authenticated endpoints determine user ID exclusively from Sanctum session tokens (`$request->user()->id`). Client-supplied user IDs are never accepted.
2. **Dual Verification Mechanism**:
   - **Password Accounts**: User must enter their current password (`Hash::check($password, $user->password)`).
   - **Social-Only Accounts (Google/Facebook)**: `GoogleAccountService::hasUsablePasswordCredential($user)` detects absence of a usable password. The system generates a cryptographically secure 6-digit one-time code (`random_int(100000, 999999)`), stores its Bcrypt hash, and emails it to the user.
3. **Typing Confirmation Phrase**:
   - Both submit and confirm require typing the exact word `DELETE`.
4. **Rate Limiting & Anti-Bruteforce**:
   - Submissions throttled at 5 requests/minute.
   - Code verification locks after 5 failed attempts (`MAX_CODE_ATTEMPTS`).
   - OTP codes expire after 15 minutes.
5. **Session Revocation**:
   - On completion, all Sanctum tokens (`$user->tokens()->delete()`) and active database sessions (`sessions` table) are immediately purged.

---

## 7. Actual Deletion Processing

Execution occurs inside an atomic database transaction (`DB::transaction`):
1. **Avatar & Storage**: User avatar stored on `public` disk is permanently deleted.
2. **Shopping Cart & Wishlists**: All cart items and wishlist rows belonging to the user are purged.
3. **Registration OTPs**: Stored OTP codes are deleted.
4. **Notifications**: User-specific notification records are cleared.
5. **Event Marketplace Listings**: User-created events are deactivated (`is_active = false`), removing them from search results, discovery feeds, and the marketplace.
6. **PII Anonymization**:
   - `name`: Scrubbed to `'Deleted User #' . $user->id`.
   - `email`: Scrubbed to `'deleted-' . $user->id . '-' . Str::random(10) . '@deleted.scemory.local'`.
   - `phone`, `country`, `position`, `date_of_birth`, `image`, `slug`: Set to `null`.
   - `google_id`, `google_avatar_url`, `facebook_id`, `facebook_avatar_url`: Set to `null`.
   - `password`: Overwritten with a high-entropy random 64-character hash.
   - `is_active`: Set to `false`.
   - `deleted_at`: Set to `now()`.
7. **Cache Invalidation**:
   - User profile tags and dashboard caches (`my_events_user_id_...`) are flushed.

---

## 8. Financial Ledger and Legal Retention

Scemory operates creator monetization, virtual gifts, media licensing, and PayPal checkout. Commercial tax laws and GDPR Article 17(3)(b) mandate the retention of financial transaction ledgers:
- **Ledger Rows Preserved**: `purchases`, `payments`, `wallet_transactions`, and `withdraws` remain in the database.
- **Foreign Key Safety**: Because `User` is pseudonymized rather than hard-deleted with SQL `DELETE`, foreign keys referencing `users.id` remain valid, preventing database crashes or orphaned accounting ledgers.
- **Privacy Preservation**: Because the user record is scrubbed of all PII, financial records can no longer be tied back to the individual's real identity.
- **Financial Obligation Safeguards**:
  - If a user has a positive wallet balance (`balance_minor > 0` or `amount > 0`) or pending withdrawal (`Withdraw::whereIn('status', ['pending', 'processing'])`), deletion is paused in `under_review` status until settled.

---

## 9. Third-Party Content Preservation

When a creator uploads media that is subsequently purchased by third-party community members:
- The purchaser holds an active license entitlement (`entitlements` table).
- The deletion processor **does not delete purchased media files**, ensuring the paying customer's access is preserved.
- The creator's public profile and event are unpublished and archived, and public metadata reflects an anonymized creator.

---

## 10. Frontend Implementation

### Profile Page: `resources/js/views/home/profile.vue`
- Added Tab 7: "Account & Privacy" (`profilePage.tabs.privacy`).
- Direct URL access support via query parameter: `https://dev.scemory.com/{lang}/profile?tab=privacy`.
- Danger Zone Card: Red-accented container clearly explaining the permanent consequences of deletion.
- Modal Dialog:
  - Enforces typing `DELETE`.
  - Prompts for account password (if password account) or email verification code (if social account).
  - Optional exit feedback reason.
  - Submits to `/api/v1/users/data-deletion-request` and `/data-deletion-confirm`.
- On completion: Clears local authentication storage, removes tokens via `clearAuthToken()` and `clearGoogleOAuthVerifier()`, and redirects to `/{lang}/home` with confirmation.

---

## 11. Public Data Deletion Instructions Page

### Path: `/{lang}/data-deletion` (Component: `resources/js/views/home/DataDeletion.vue`)
Available without login across all 13 languages, featuring:
- **Header**: Scemory logo, compliance subtitle, effective date.
- **Interactive Reference Status Tool**: Input field allowing users to enter a reference code (or auto-populated from `?reference=...`) to check real-time request status.
- **Section A**: Step-by-step instructions on deleting account and data from within Scemory Profile.
- **Section B**: Alternative manual request instructions via official privacy email: `scemorygmail@gmail.com`.
- **Section C**: Clarification on removing Scemory via Facebook Settings (Apps & Websites).
- **Section D**: Specific categories of personal data deleted.
- **Section E**: Specific categories of data retained for legal, financial, and tax compliance.
- **Section F**: Processing timelines (immediate for eligible accounts; up to 30 days for accounts requiring financial reconciliation).
- **Section G**: Reference code tracking instructions.
- **Related Links**: Direct links to Privacy Policy, Terms of Service, and Contact page.

---

## 12. Meta / Facebook Data Deletion Compliance

Scemory satisfies both Meta App Review requirements:

1. **Data Deletion Instructions URL**:
   `https://dev.scemory.com/en/data-deletion` (or any of the 13 supported language codes: `ar`, `de`, `es`, `fa`, `fr`, `hi`, `it`, `ja`, `ru`, `tr`, `ur`, `zh`).
2. **Data Deletion Callback URL**:
   `https://dev.scemory.com/api/v1/facebook/data-deletion`
   - Accepts Facebook POST callbacks and responds with:
     ```json
     {
       "url": "https://dev.scemory.com/en/data-deletion?reference=DEL-FB-XXXXXXXX",
       "confirmation_code": "DEL-FB-XXXXXXXX"
     }
     ```

---

## 13. Admin Workflows

- Admins can query `GET /api/v1/admin/data-deletion-requests?status=under_review` to monitor accounts with pending balances.
- Once the balance is refunded, forfeited, or paid out offline, the admin calls `POST /api/v1/admin/data-deletion-requests/{id}/process` with `admin_notes` to execute the deletion.
- If a fraudulent or invalid request is identified, the admin calls `POST /api/v1/admin/data-deletion-requests/{id}/reject`.

---

## 14. Verification and Test Results

### Backend Test Suite (`php artisan test`):
- `Tests\Feature\Api\UserDataDeletionTest`: **13 passed** (70 assertions)
  - Unauthenticated access denied (401).
  - Validation: typing phrase `DELETE` required (422).
  - Password account requests require password verification.
  - Social-only accounts request email OTP verification (`DataDeletionConfirmationMail`).
  - Idempotent submission: duplicate requests return existing reference.
  - Incorrect password rejected, user record preserved.
  - Correct password execution deletes avatar, wishlists, cart, revokes Sanctum tokens, and anonymizes PII.
  - OTP verification for social accounts.
  - Financial holds: active wallet balance or pending withdrawal moves request to `under_review`.
  - User cancellation of pending request.
  - Public status lookup endpoint sanitized.
  - Admin inspection and processing of `under_review` requests.
- Full Suite: **380 passed, 9 skipped, 0 failed** (2,853 assertions).
- Google & Facebook Auth Suites: **57 passed** (33 Google, 24 Facebook).

### Frontend Test Suite (`npm run test`):
- `tests/Frontend/dataDeletion.test.js`: **5 passed**.
  - English public page renders compliance notice and sections A through G.
  - Arabic public page renders RTL layout and Arabic text.
  - Global footer contains Data Deletion Instructions link under Support & Contact in English and Arabic.
  - Interactive status checker responds to reference query parameter.
- Full Suite: **21 test files passed, 188 tests passed, 0 failed**.

### Asset Compilation:
- `npm run build`: Vite v6.4.3 production build succeeded in 26.01s with zero errors.

---

## 15. Deployed URLs and Configuration Requirements

### Production / Development URLs:
- **Public Instructions Page**: `https://dev.scemory.com/en/data-deletion`
  - Arabic: `https://dev.scemory.com/ar/data-deletion`
  - German: `https://dev.scemory.com/de/data-deletion`
  - Spanish: `https://dev.scemory.com/es/data-deletion`
  - Persian: `https://dev.scemory.com/fa/data-deletion`
  - French: `https://dev.scemory.com/fr/data-deletion`
  - Hindi: `https://dev.scemory.com/hi/data-deletion`
  - Italian: `https://dev.scemory.com/it/data-deletion`
  - Japanese: `https://dev.scemory.com/ja/data-deletion`
  - Russian: `https://dev.scemory.com/ru/data-deletion`
  - Turkish: `https://dev.scemory.com/tr/data-deletion`
  - Urdu: `https://dev.scemory.com/ur/data-deletion`
  - Chinese: `https://dev.scemory.com/zh/data-deletion`
- **Profile Deletion Tab**: `https://dev.scemory.com/{lang}/profile?tab=privacy`
- **Meta Data Deletion Callback URL**: `https://dev.scemory.com/api/v1/facebook/data-deletion`
- **Public Status Check API**: `https://dev.scemory.com/api/v1/data-deletion-status/{reference}`
- **Official Privacy Channel**: `scemorygmail@gmail.com`

### Meta App Dashboard Configuration:
In Facebook App Settings > Basic:
- Set **Data Deletion Instructions URL** to: `https://dev.scemory.com/en/data-deletion`
- (Optional) Set **Data Deletion Callback URL** to: `https://dev.scemory.com/api/v1/facebook/data-deletion`
