# A-04 AI Destination Policy

Implemented November 12, 2026. Baseline/rollback: A-03 merge
`2361a7c1692d97fa1b46beee9aee0b4b4358898c`, branch
`codex/rollback-before-a04-2026-11-12`. No new database migration.

## Result

- The owner confirmed `endurable-cleft-wildlife.ngrok-free.dev` as the
  intended AI destination. The earlier website-host approval was clarified.
  `config/ai-policy.php` contains that non-secret, versioned allowlist and is
  copied and hash-verified during deployment. This separate configuration file
  provides the same server-controlled approval as the work order's suggested
  `config/ai.php`; the web settings page cannot edit it. A server-owned
  `config/ai.php` may explicitly supply `allowed_hosts` instead.
- No endpoint is assumed. Resolution order is `GAME2_AI_ENDPOINT`, server-owned
  `config/ai.php` endpoint, then an explicitly saved database endpoint. Without
  one, AI reports “not configured” and makes no request. Previous tunnel/default
  localhost fallbacks are removed from both writer and game AI clients.
- URLs require HTTPS on port 443 and an exact approved hostname. IP literals,
  credentials, queries, fragments, unapproved hosts and malformed URLs are refused.
  Every A/AAAA answer must be public. Loopback, private, link-local, metadata,
  mapped/translated IPs, multicast, documentation and other special-use ranges
  are refused. Mixed public/private DNS responses fail closed.
- Both clients and status requests resolve before adding access headers. cURL
  pins the validated addresses, preserves TLS peer/hostname verification, allows
  HTTPS only, disables redirects and environmental proxies, and uses a fresh
  handle. Public failures contain fixed messages; connection details go to logs.
- AI settings require a current Super Admin in the page and service. Ordinary
  admins no longer see the AI Settings sidebar link. Proposal review, structured
  response validation and escaped display remain in place.

The transport follows the [libcurl resolution API](https://curl.se/libcurl/c/CURLOPT_RESOLVE.html)
and [OWASP SSRF guidance](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html).
The modern HTTPS protocol option is used when available; older cURL versions use
the HTTPS-only protocol bitmask. No TLS verification is disabled.

## Deployment Configuration

The confirmed AI API base URL is:
`https://endurable-cleft-wildlife.ngrok-free.dev`.
Keep this explicitly configured in the existing server-owned AI configuration,
environment or saved AI Settings. It is approved deployment configuration, not
an implicit fallback in the application code. No tunnel or AI route was created
by this code change.

A credential-free GET to `/api/tags` on November 12 returned HTTP 401 with
`WWW-Authenticate: Basic realm="ngrok"`. This confirms that the endpoint is
reachable and enforces Basic Authentication. It does not verify authenticated
Ollama responses or that hosted Toinky has matching credentials. The user also
provided local Ollama output listing `qwen3:8b` and a successful local tags request.

ngrok documents its assigned dev domains as static and account-owned; using this
ngrok domain is compatible with the exact-host policy. See
[ngrok domain ownership](https://ngrok.com/docs/gateway/domains). The security
change removes implicit destinations, insecure transport and redirect behavior;
it does not require moving the AI service onto the website's domain.

Preserve the existing private `basic_user` / `basic_password` values in
`config/ai.php`, or their corresponding environment variables. No password was
read, copied into Git or submitted in the endpoint check. If a server-owned
`allowed_hosts` override exists, it must include this exact confirmed hostname.

Use `GAME2_AI_ENDPOINT` or ignored, server-owned `config/ai.php` for the endpoint.
For example, that file can return an array containing `endpoint` and optionally
`allowed_hosts`, `access_client_id`, `access_client_secret`, `basic_user`,
`basic_password`, `bearer_token`, `model` and `timeout_seconds`. Keep credentials
outside Git and chat. The existing `.gitignore` excludes this file; the deployment
allowlist explicitly preserves it. Server endpoint configuration takes precedence
over UI changes; the UI can still manage the model and timeout.

Bearer authentication now uses `GAME2_AI_BEARER_TOKEN` / `bearer_token`. If the
previous tunnel-specific token environment/key was used, rename it server-side
before enabling the connection. Existing Cloudflare Access and basic-auth keys
keep their names. Proxy-dependent hosting needs a separately reviewed design;
ambient proxy environment variables cannot bypass this policy.

## Verification And Remaining Evidence

`tools/ai_endpoint_guard_test.php` is wired into both MariaDB CI jobs. It checks
unsafe endpoint saves, owner-only configuration, public/special-use IPv4/IPv6,
DNS pinning and actual cURL option acceptance, no redirects, HTTPS/TLS, fixed
unconfigured failures and shared policy enforcement before prompt logging. It
makes no outbound AI request. Local Inky parser/source and deployment guards,
plus full PHP syntax checks, also passed. Live AI availability is not claimed.

The existing AI-log pruning tool retains a 90-day payload policy. Scheduling it,
the production evidence and privacy-policy disclosure remain A-17/A-19 and A-06;
this item does not claim a live cron or approved legal wording. Full CI is separate
from local verification. The Auditor retains the shared artifact status.

## Rollback

Keep migrations 142/143. The baseline restores the old destination behavior;
prefer a forward fix to retain these controls. Server-owned AI configuration and
credentials must remain outside Git. The approved-host policy file contains no
secret and is versioned with the code for review and rollback.
