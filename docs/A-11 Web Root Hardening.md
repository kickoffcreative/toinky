# A-11 Installer, Tools And Upload Storage

November 13, 2026. Implementation evidence; production hosting verification and
Auditor sign-off are separate. No production files were changed by local tests.

## Deployment Boundary

- The public installer is absent from the deployment manifest/copy list and
  the deployment removes its old live copy. The repository's legacy installer
  refuses HTTP with a no-store 404 and security headers before any database
  setup. Removing `.installed` cannot enable it over HTTP. Legacy database
  name/user defaults are blank. CLI installation smoke tests remain available
  in the source checkout, outside the public deployment.
- Runtime tools are copied from the manifest, not the entire tools directory.
  Test/smoke PHP files, fixtures, JavaScript tests, example configurations and
  test compose files are excluded. Manifest cleanup removes stale installer,
  tools tests and exact example-suffix configurations. It does not scan arbitrary
  server-owned configuration names for deletion. Uploaded content, database/AI
  configuration and `.installed` stay preserved.
- Every PHP file under tools has a CLI guard before includes. The shared
  SimulationHarness implementation moved to app; Lab and simulation services
  include it there. A CLI-only tools shim preserves command compatibility.
  Destructive reset additionally requires CLI and a database ending `_test`;
  simulate's existing local-host/configured-DSN check remains. Its fallback
  history uses the A-07 safe-error helper instead of raw exception messages.
- For cPanel compatibility, private directories remain in the deployed tree.
  app, config, database, tools and public each have an independent deny-all
  `.htaccess`, in addition to the root routing protection. Infrastructure that
  ignores `.htaccess` requires equivalent server rules before serving this tree.

## Uploads And Permissions

- Upload `.htaccess` disables executable handlers/CGI/includes, forces no handler
  and permits only JPEG/PNG/WebP filenames. All other files, including PHP and
  configuration files, are denied. The real Apache test also demonstrates that
  a PHP/image double extension remains static bytes with a PHP handler active.
- New profile/media files and directories have maximum modes 0640/0750.
  `tools/harden_storage.php` defaults to dry run; deployment invokes `--apply`
  for app/config/database/tools/public. It changes permissions only, refuses
  linked storage/escapes, and preserves stricter modes (including 0600 secrets
  and 0700 directories). It leaves root wrappers, root assets, ownership and the
  external security key untouched. User uploads are never deleted by this tool.
- Image templates request uploaded images directly through theme-media.php.
  Existing URL rewrites remain as compatibility routes. PHP streams referenced
  files from bounded storage roots and verifies actual image MIME/extension;
  it never executes uploaded content. Profile photos require sign-in and are
  no-store. Referenced public legacy story/parallax images remain available;
  unpublished legacy artwork is restricted to its author/administrators and
  no-store. Existing library visibility checks remain in place.
- The current accepted upload formats already were JPEG/PNG/WebP. This change
  does not remove an existing video/audio upload format. A-17's broader cache,
  upload-resource and authorization review remains separate.

## Validation

- Packaging/permission guard builds and verifies a complete staged runtime
  manifest, checks CLI/private-directory rules, removes stale test artifacts,
  preserves configuration/uploads, caps modes, retains stricter secrets and
  refuses linked storage.
- Image HTTP guard uses disposable accounts/fiction/files. It checks signed-in
  profile access, anonymous refusal, public and unpublished legacy artwork,
  unreferenced uploads, PHP-containing image bytes and disguised non-images.
- Apache Docker fixture uses a digest-pinned official PHP 8.4/Apache image.
  A control proves PHP and double-extension handlers execute outside uploads.
  Eighteen checks verify private-path/installer denial, executable-upload
  refusal, static double-extension images, legacy PHP image routing and absence
  of execution markers.
  Only this isolated HTTP fixture removes the HTTPS redirect; production
  redirect and header policies are unchanged. Its Apache worker owns fixture
  storage. This does not establish cPanel's real user/group configuration.
- Both database CI suites run packaging/image guards; a separate Apache job
  exercises actual handler configuration. Engine/Lab/account/privacy/header
  regressions and fresh-install smoke tests must also pass before merge.

## Deployment Verification And Rollback

Owner deployment must verify public page/artwork rendering, signed-in profile
photos, Lab/simulation operation, CLI cron workers, denied private paths and
non-executing uploads on the actual host. PHP and the deployment user must have
the required owner/group access; do not loosen secret modes to fix a mismatch.
Static/public root assets remain outside permission hardening. Record actual
cPanel user/group/server rules privately under A-19. No live permission or
upload-execution claim follows from a local container result.

Rollback anchor: `codex/rollback-before-a11-2026-11-13` at
`68f107d945d9c3c3b49997429f174fddcfecd28f`. Code rollback preserves schema,
credentials, uploads and external key. It can restore the former installer,
tool-copy and direct-upload behavior; coordinate security consequences first.
Permission reductions are not automatically reversed. Retain stricter secret
modes and repair only evidence-backed ownership/group mismatches. Auditor
statuses and the Google handoff are unchanged by this implementation.
