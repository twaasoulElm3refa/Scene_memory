# Gift earnings and withdrawals

## Payment and earnings lifecycle

Wallet gifts use the existing atomic wallet debit. PayPal gifts remain pending until the server verifies a completed capture; a browser return is navigation only. On verified payment, delivery and the recipient's 85% earning entry are recorded with `paid_at` and `release_at` in UTC. The platform's 15% commission and PayPal processing expense remain separate.

New gifts use a minimum hold of 24 hours after verified `paid_at` (`GIFT_HOLDING_HOURS` may increase this, but cannot lower it). The existing hourly `gifts:release-earnings` scheduler moves pending earnings to available only after `release_at` and 24 hours from `paid_at`, verified paid and delivered status, an active recipient, and no dispute or review restriction. A late scheduler run can delay availability beyond 24 hours. The movement key and row locks make retries idempotent.

Existing pending entries keep their stored `release_at`, including a longer seven-day hold. There is no backfill or historical timestamp rewrite. Already settled entries are unchanged. Refunds and disputes can still occur after release; reversal writes compensating movements, and a post-payout reversal records recovery for administrator review.

## Recipient profile

Profile → Gifts & Earnings has Sent Gifts and Earnings & Withdrawals tabs. Sent history is scoped to the authenticated sender. Recipient totals use verified paid gifts less refunds; available and pending amounts come from the earning ledger. Wallet spending balance is separate. The withdrawal form uses integer minor units and the server's eligibility checks. Payout details are encrypted at rest. A successful request atomically reserves eligible available earnings and creates a withdrawal record; it does not send money.

## Manual withdrawal review

Admin → Gift Withdrawal Requests is protected by the existing admin middleware. The list masks destinations; the detail endpoint exposes them only to an authenticated administrator. Actions and allocated gift ledger events are auditable.

States: `pending → approved → processing → paid`, with direct `approved → paid` available when a confirmed external transfer is already complete. `pending` or `approved` may be rejected. `processing → failed` retains the reservation for reconciliation; `failed → paid` requires confirmation and an external reference, while `failed → rejected` releases the reservation. A recipient can cancel only a pending request. Rejection or cancellation unreserves exactly once. `paid` settles exactly once and cannot be changed; repeated submission with the same reference is idempotent. Administrator rejection and failure require reasons. Approval and processing never claim that a transfer succeeded. Processing denotes preparation for a manual transfer; administrators must reconcile any in-flight external transfer before treating a failure as final or rejecting it.

No PayPal Payouts call is made. The administrator sends funds through the chosen external method, confirms completion, records a unique external reference, then marks the request paid. Refunds and chargebacks remain subject to the existing recovery and review process.

## Deployment

Apply the additive withdrawal review migration through the normal deployment process; it does not alter existing financial rows. Keep the Laravel scheduler running hourly. Verify the admin role assignment and review any processing or failed requests before external payment. No production migration or real payout is needed for local tests.
