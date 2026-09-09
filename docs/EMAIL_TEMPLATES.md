# Email Templates

Email branding is managed by an administrator at **Website Settings → Brand → Email Settings**.

The page controls the sender address and name, and stores one enabled template
for each system message: welcome, account invitation, password reset, game
invitation, and email-change verification. Each template has a subject, HTML
body, plain-text fallback, and optional CSS. The HTML editor has a Visual mode
and an HTML mode; the server sanitizes HTML and CSS before saving.

## Template keys

Templates may use these keys:

`{{recipient_name}}`, `{{action_url}}`, `{{action_label}}`, `{{message}}`,
`{{message_html}}`, `{{site_name}}`, `{{site_url}}`, `{{logo_url}}`,
`{{story_title}}`, `{{game_code}}`, `{{expiry_text}}`,
`{{sender_name}}`, `{{brand_primary}}`, `{{brand_secondary}}`,
`{{brand_accent}}`, `{{brand_surface}}`, `{{brand_text}}`,
`{{brand_heading}}`, `{{brand_title_font}}`, and `{{brand_body_font}}`.

Unknown keys are rejected when settings are saved. `message_html` is generated
from application text and is the only placeholder inserted into HTML without
escaping. Links and all other values are escaped for the output format.

## Terminal-first verification

After deploying the migration and application files, run:

```bash
cd /home/toinkyadmin/public_html
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
$PHP tools/migrate.php
$PHP -n -l app/EmailTemplateService.php
$PHP -n -l app/AuthService.php
$PHP -n -l app/UserProfileService.php
$PHP -n -l public/admin.php
$PHP tools/email_template_test.php
```

The contract test creates missing default rows, composes every template, and
checks that HTML and plain-text output contain no unresolved keys. To verify a
real controlled delivery, use an existing account reset or invitation and then
inspect the latest `email_delivery_log` record. Do not print SMTP passwords or
send a test to an address without permission.

`GAME2_MAIL_FROM` remains an optional deployment override. When it is set, it
takes precedence over the Brand page sender address; otherwise the saved
`MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` settings are used. The existing PHP
`mail()` fallback remains the default transport when SMTP environment values
are not configured.

## Brand assets

The shared website shell uses `public/assets/toinky-logo.svg` for the gold
wordmark. Email templates use `public/assets/toinky-logo.png` because PNG has
more predictable support in mail clients. `public/assets/toinky-icon.png` is
used for browser and mobile app icons. Keep these files in sync with the
approved Toinky brand assets when deploying.
