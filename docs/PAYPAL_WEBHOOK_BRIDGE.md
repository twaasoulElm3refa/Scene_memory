# Existing PayPal Sandbox webhook bridge

The Sandbox subscriptions send checkout events to
`https://alimconsultancy.com/__scemory_checkout_webhook` and wallet events to
`https://alimconsultancy.com/__scemory_wallet_webhook`. The existing Apache HTTPS
reverse proxy on that same server forwards them to
`https://dev.scemory.com/api/v1/paypal/webhook` and
`https://dev.scemory.com/api/v1/wallet/webhook`, respectively. Keep the two
subscriptions and their existing webhook IDs separate. The application verifies
each raw signed event against its configured ID; the public host is not part of
the PayPal signature payload.

This bridge is server configuration outside Git. No application release should
replace CWP vhost files, certificates, or PayPal dashboard settings. On a CWP
host, locate the **active** virtual host and included SSL files with
`httpd -S` (or `apachectl -S`) and `httpd -t -D DUMP_INCLUDES` before editing.
CWP installations often regenerate vhosts from templates; record the actual
active paths and regeneration mechanism in the deployment runbook. Do not
assume a path from this repository is active on the server.

## Configuration requirements

- Preserve HTTPS with certificate and hostname verification both for the public
  endpoint and for the proxy connection to `dev.scemory.com`. Do not disable
  `SSLProxyVerify`, `SSLProxyCheckPeerName`, or application PayPal TLS checks.
- Preserve the POST method, raw request body and PayPal transmission headers.
  Do not rewrite the JSON body or forward an internally generated simulator
  event as if it were a signed PayPal delivery.
- The checkout path must reach only the checkout route; the wallet path must
  reach only the wallet route. Preserve the configured checkout and wallet
  webhook IDs, and keep application signature verification enabled.
- The proxy should forward a sane `Host`/forwarded-host configuration for the
  target application. Validate Laravel's trusted proxy settings in the deployed
  environment without changing TLS or signature checks.

Sanitized routing sketch for review only (not a drop-in CWP vhost):

```apache
SSLProxyEngine On
SSLProxyVerify require
SSLProxyCheckPeerName On
ProxyPass /__scemory_checkout_webhook https://dev.scemory.com/api/v1/paypal/webhook
ProxyPassReverse /__scemory_checkout_webhook https://dev.scemory.com/api/v1/paypal/webhook
ProxyPass /__scemory_wallet_webhook https://dev.scemory.com/api/v1/wallet/webhook
ProxyPassReverse /__scemory_wallet_webhook https://dev.scemory.com/api/v1/wallet/webhook
```

## Validation and rollback at deployment review

1. Save the active CWP/Apache configuration and check `httpd -t` before any
   approved reload. Keep the previous vhost available for immediate rollback.
2. Send a real signed Sandbox checkout event and a real signed Sandbox wallet
   event through their respective public URLs. Confirm a 2xx response, an
   endpoint-reached diagnostic, a verified event row, and exactly one matching
   payment settlement. Repeat the same event to confirm idempotency.
3. Confirm an invalid signature is rejected and creates no settlement. PayPal's
   webhook simulator may omit the signature context of a real subscription;
   use it for transport diagnostics only, not as proof of signed delivery.
4. If delivery breaks, revert the reviewed vhost change and reload only after
   `httpd -t` passes. Leave existing payment, ledger, entitlement and webhook
   audit rows untouched. Recheck delivery in the PayPal Sandbox event history.

The application logs arrival time, body length, event ID/type, webhook path and
presence of signature headers. It does not log webhook IDs, raw bodies, tokens
or secret headers. A TCP reset before Apache receives an HTTP request will not
produce an endpoint-reached log entry.
