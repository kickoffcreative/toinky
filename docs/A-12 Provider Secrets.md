# A-12 Server-Managed Provider Credentials

November 14, 2026. Implementation evidence; deployment, provider rotations,
off-server backup placement and Auditor sign-off remain separate operations.

## Chosen Boundary

Provider secrets use environment variables or an owner-only PHP file outside
the repository and application/web root. The admin UI shows configured status;
it has no secret editor. Secret-field POST attempts are refused with HTTP 403
for administrators and Super Admins, and the service rejects those fields too.
The audit's encrypted, reauthenticated web-editor alternative is not selected.
The public Stripe publishable key and non-secret pricing controls remain editable.

`ServerSecretsService` prefers a nonempty `GAME2_<KEY>` environment value. File
configuration uses `GAME2_SECRETS_FILE` if provided; otherwise it uses the same
external `.toinky-private` directory as A-08, with an application-path hash and
`.secrets.php` suffix. Files must be regular, readable, outside the application,
not symlinks, with no group/other permissions. New directory/file modes are
0700/0600. There is no plaintext database fallback for Stripe credentials.

The file holds STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET and migrated legacy
credential fields. The original AI array remains under `ai`; a server-owned
config/ai.php loader contains only the external path. Existing SMTP environment
credentials/local mail transport are unchanged. Database connection configuration
remains the A-11-protected server-owned file; A-16 database-account separation is
not claimed by this provider-credential change.

## Migration And Editing

Deployment runs tools/migrate_provider_secrets.php --apply after normal schema
migration and external-key initialization. No new schema migration is required.
The command defaults to a count-only dry run; --path prints only the selected
external filename, without reading/printing credential values.

The migration locks legacy setting rows and its private file operation, writes
owner-only external versions, installs the private configuration and AI loader,
then removes the known secret rows from platform_settings. Conflicting private
and legacy values stop migration without guessing. A late database failure
restores previous file/AI contents and rolls back rows. Repeats are idempotent.
An existing AI loader with a missing selected private file stops relocation;
preserve the original external path with GAME2_SECRETS_FILE before moving an
installation. Do not regenerate or discard the A-08 encryption key.

Find the file privately with the --path command, then edit only the necessary
credential values in that existing file or the server's environment manager.
Preserve its AI endpoint, approved-host policy, model and unrelated settings.
Do not replace it with a sample array, paste it into chat, commit it, or place it
under public_html. Validate PHP syntax privately before replacing a working
file. Keep a protected previous version. Reload PHP workers/environment as
required by the host, and account for the short AI diagnostic cache.

## Provider Operations Still Required

- Rotate Stripe API/webhook, mail and AI credentials after rollout; retire the
  obsolete reCAPTCHA secret rather than re-enabling the removed challenge.
  These changes require the owner's provider account access. No key was created,
  revealed, rotated or revoked by this implementation's tests.
- The code calls only POST/GET Checkout Sessions. Start a sandbox restricted
  key with Checkout Sessions write permission (which includes read), verify
  create/retrieve and fulfillment, and inspect actual request logs before
  reproducing the minimum working scope in live mode. Do not grant unrelated
  account/payout access. Scope suitability is an inference from this code;
  actual provider permissions remain unverified. See Stripe's
  [restricted-key guidance](https://docs.stripe.com/keys/restricted-api-keys).
- A webhook signing secret is separate from the API key. Test verified event
  handling and duplicate-event behavior when rotating it. Preserve email/TOTP
  recovery access while rotating mail; test the replacement before retiring the
  previous credential. Update ngrok and its private client configuration together.
- Hosted Checkout keeps card collection on Stripe, reducing PCI obligations;
  it does not eliminate merchant compliance responsibilities. See Stripe's
  [integration security guide](https://docs.stripe.com/security/guide).
- Encrypt dumps/file bundles, keep the independent backup key separately, copy
  encrypted artifacts to an approved private off-server destination, and perform
  a measured restore drill. RECOVERY.md gives the procedure. No off-server
  destination, live backup, rotation or restore evidence is claimed here.

## Validation And Rollback

The provider guard uses fake credentials and disposable database/HTTP fixtures:
dry run, preserved values, owner-only location, removal of DB secrets, private
versions, idempotence, conflict refusal, relocation refusal, late-failure rollback,
environment precedence, unsafe/symlink refusal, configured-only UI, immutable
POSTs and secret-free logs. Backup guards verify authenticated round trips,
tampering/truncation/wrong keys/trailing data, no unauthenticated output, key
permissions and non-overwrite. No real provider key or personal backup was used.

Rollback anchor: codex/rollback-before-a12-2026-11-13 at
8fd7858a028cc5faebdfe064844a2f6579be8e5d. Preserve external credential versions,
AI loader, A-08 key and independent backup key. Older Stripe code reads database
secrets; a code-only rollback after migration leaves payments unconfigured.
Prefer a forward fix retaining the new adapter. Any emergency restoration of
legacy database storage needs a private maintenance procedure and restores the
old security weakness. Credential values remain recoverable in private versions;
they are not automatically copied back into the database. Auditor statuses and
the Google handoff are unchanged.
