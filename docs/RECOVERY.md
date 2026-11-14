# Toinky Backup & Restore Runbook

November 14, 2026. Operational procedure for A-12. No production backup,
off-server copy, provider rotation or measured restore drill is claimed by this
document. The previous runbook is preserved in docs/archive.

## What Is Backed Up

- Database: account identity, shared fiction, games, ledgers and operational
  records. Record the actual release SHA, migration list and table/row counts
  at backup time; do not rely on historical fixed table counts.
- Server-owned configuration: config/database.php, the optional AI loader,
  custom environment/configuration definitions and the external provider file.
- Recovery material: the A-08 encryption key or its managed environment
  equivalent. Losing it prevents recovery of encrypted authenticator secrets.
- Uploaded content: public/uploads, including photos and story/media images.
  Repository code is recoverable from its matching release; it is not a
  database, upload or credential backup.
- The optional .installed marker and any custom external paths belong in the
  private inventory. It no longer enables/disables HTTP installation.

Inventory paths and credential names privately. Never put actual passwords,
keys, dumps, personal exports or recovery codes into Git, public issues or chat.
The provider file and its versions are plaintext credentials protected by
owner-only permissions outside the web root; encrypt their backup bundle.

## Backup Procedure

Use a separate backup encryption key. Do not reuse the A-08 application key or
a provider password. Keep its protected recovery copy separately from encrypted
archives. The CLI key initializer refuses overwrite and never prints key bytes.

The following Bash templates describe the standard cPanel layout. Substitute
the actual account/database names and review custom external paths first.
Commands are instructions for the owner; they have not been run on production.

~~~bash
umask 077
task_home=/home/ACCOUNT
task_app="$task_home/public_html"
task_backup_dir="$task_home/.toinky-backups"
task_backup_key_dir="$task_home/.toinky-backup-key"
task_backup_key_file="$task_backup_key_dir/backup.key"
mkdir -p "$task_backup_dir" "$task_backup_key_dir"
chmod 700 "$task_backup_dir" "$task_backup_key_dir"
php "$task_app/tools/create_backup_key.php" "$task_backup_key_file"
~~~

Keep the MySQL backup client's existing credentials in an owner-only external
defaults file, for example .toinky-private/mysql-backup.cnf. Do not put a
password on the command line. Use an appropriately privileged backup account.
The encryption tool consumes stdin, avoiding a persistent plaintext SQL dump.

~~~bash
set -o pipefail
mariadb-dump --defaults-extra-file="$task_home/.toinky-private/mysql-backup.cnf" \
  --single-transaction --quick --routines --events --triggers DATABASE_NAME \
  | php "$task_app/tools/backup_stream.php" encrypt "$task_backup_key_file" \
  > "$task_backup_dir/database-YYYYMMDD.sql.enc"
~~~

Require a successful pipeline exit status. A failed dump can otherwise produce
a valid encrypted empty/partial input; file existence alone proves nothing.
Verify expected source activity/size and the migration/row-count manifest.

For the standard layout, bundle configuration, uploads and external application
keys/provider configuration together under encryption:

~~~bash
tar -C "$task_home" -cf - public_html/config public_html/public/uploads .toinky-private \
  | php "$task_app/tools/backup_stream.php" encrypt "$task_backup_key_file" \
  > "$task_backup_dir/files-YYYYMMDD.tar.enc"
~~~

Add .installed if present and any custom external credential/key paths or managed
environment exports identified in the private inventory. Include only paths that
exist and investigate missing required files. Keep the independent backup key
directory out of the file bundle. Both pipelines must complete successfully.

Copy the encrypted files and their restricted metadata manifest to an approved
private off-server destination; verify ciphertext hashes after transfer.
Restrict access, retention and version deletion there. Keep the independent
backup key in a separately protected recovery location. A server-local .enc
file is not an off-server backup. No remote destination or upload is configured
by these tools; the owner must supply and verify it.

The stream format uses PHP Sodium secretstream with bounded authenticated
frames and a required final tag. Decryption stages data in a temporary
owner-only file and emits nothing until the whole stream is authenticated.
Tampering, truncation, wrong keys and extra trailing data are rejected.
[PHP Sodium documentation](https://www.php.net/manual/en/function.sodium-crypto-secretstream-xchacha20poly1305-init-push.php)
describes the underlying authenticated stream primitive.

## Restore Procedure

1. First restore to a disposable database/storage area, with mail, payments and
   outbound AI disabled. Use a separate test database; do not point a drill at
   production.
2. Recover the independent backup key privately. Check the archive hashes and
   decrypt into an owner-only staging directory outside the web root. Provide
   enough private temporary disk space for the entire plaintext; decryption
   authenticates before release.
3. Require successful decryption before importing or extracting. Do not stream a
   failed/partial restore directly into a live database.
4. Restore the database, uploads, server configuration and A-08 application key
   into the isolated environment. Restore the matching code release and explicit
   external configuration paths. Preserve stricter file permissions.
5. Reconcile credentials against current provider state; an old backup can
   contain values that have since been rotated/revoked. Do not reactivate an
   old provider key merely to match a backup. A-12 migration stops conflicting
   legacy/private values for private operator resolution.
6. Verify the query set and representative flows below. Measure the result.
7. Only after an authorized production recovery plan, stop writes/enter
   maintenance, take a fresh protected recovery point and restore the approved
   state. Revoke restored sessions so an old active-session snapshot does not
   reinstate access. Verify email/TOTP recovery before reopening sign-in.
8. Reapply privacy erasures/corrections since the backup from the restricted
   request log, respecting shared-fiction policy and other people's privacy.
   Restore tests and plaintext staging must follow the agreed cleanup policy.

Decryption example for an isolated restore directory:

~~~bash
umask 077
php "$task_app/tools/backup_stream.php" decrypt "$task_backup_key_file" \
  < "$task_backup_dir/database-YYYYMMDD.sql.enc" > /PRIVATE_STAGING/restore.sql
~~~

Check its exit status before importing into the designated disposable database.
Keep plaintext staging private and remove it after the approved drill/recovery.
No production database restore command is supplied as a one-step shortcut.

## Verification Query Set

Compare actual values with the restricted pre-backup manifest:

- SELECT migration FROM schema_migrations ORDER BY migration;
- SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE();
- Row counts for simulations, games, characters, decisions,
  ai_reasoner_interactions, host_occasion_ledger and users.
- Foreign-key integrity across the restored schema.
- No known provider-secret values remain in platform_settings after A-12
  migration; provider configuration is available only through approved external
  files/environment. Do not print values to establish this.
- Homepage/dashboard, sign-in/second factor, profile photo/artwork, Lab creation,
  representative game playback and CLI worker operation.
- Payments and mail stay disabled in the drill until a separate sandbox-only
  verification is explicitly configured.

Table/migration counts change with releases. Record them, do not invent them.

## Measured Drill Results

| Date | Backup Duration | Restore Duration | Verification | Notes |
| --- | --- | --- | --- | --- |
| Not Yet Run On Production | — | — | — | Local encryption/authentication guards are not a production restore drill. |

Record actual timings, failure points, recovery owner and encrypted off-server
artifact references privately. Do not put personal records or credentials in
this public table.

## Retention

Follow the current approved privacy/operational schedule and the restricted
request log. The A-06 privacy retention service handles account-linked records
and aged AI text; legacy prune jobs have narrower scopes. Do not infer that
retaining usage metrics permits indefinite retention of mixed-person text.

Backups can contain information removed from the live database. Restrict their
use, expire them under the approved schedule and reapply erasures before a
restored copy becomes active. Keep the keys needed for retained archives and
authenticator data; never silently rotate/delete them as deployment cleanup.

## Provider Rotation And Rollback

Use the owner’s Stripe, webhook, mail and AI control panels to rotate credentials
after rollout; retire unused reCAPTCHA credentials. Test replacements before
revocation, preserve sign-in recovery, and record completion without values.
Stripe API keys and webhook signing secrets are distinct. Restricted keys should
cover only the tested Checkout Sessions calls used by this code.
[Stripe restricted-key guidance](https://docs.stripe.com/keys/restricted-api-keys)
describes permission review and rotation.

Hosted Checkout reduces card-data handling and PCI obligations; it does not
remove merchant compliance responsibilities.
[Stripe integration security guidance](https://docs.stripe.com/security/guide)
explains that shared responsibility.

After A-12 migration, older Stripe code reads the now-removed database secrets.
A code-only rollback therefore leaves payments unconfigured. Prefer a forward
fix preserving the server-managed adapter. Any emergency legacy-storage restore
requires private maintenance and reinstates the old storage weakness. Preserve
external provider versions, AI loader, application key and independent backup
key; none are recovered from Git alone.
