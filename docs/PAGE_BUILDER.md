# Page Builder

The page builder stores a page as ordered parent sections. Each section may contain a `rows_json` collection of child rows that share the parent background and can use their own layout and content fields. Existing sections remain compatible: when `rows_json` is empty, the parent section is rendered as its fallback row.

Supported presets are `Image On Right`, `Image On Left`, `Three Columns`, `Four Columns`, `Social Media Buttons`, `No Image, Centered Text`, `Top Text Only`, `Cards Only`, `Text With Bulleted List`, and `Container`. A Container row has no content of its own; it houses stacked child rows. Child rows are normalized to a maximum depth of three. The Story Worlds section remains live content sourced from current public stories and is not a static page-builder template.

Default sections are marked `is_default=1`. They may be hidden or reordered, but deletion is rejected by `PageContentService::deleteSection()`. Non-default sections may be deleted. Reordering is performed with `PageContentService::reorderSections()` after the admin UI submits the ordered section IDs.

## Terminal-first checks

Run from production:

```sh
cd /home/toinkyadmin/public_html
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
$PHP -n -l app/PageContentService.php
$PHP -n -l public/admin.php
$PHP -n -l public/index.php
$PHP tools/page_builder_contract_test.php
$PHP tools/migrate.php
```

If a page remains blank or shows the old layout after migration, inspect the deployed `public/index.php` renderer and the section's `rows_json`. The service and migration alone do not prove that a renderer is consuming child rows.

