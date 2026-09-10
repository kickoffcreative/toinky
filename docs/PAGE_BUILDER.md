# Page Builder

The Page Builder is the active editing surface for website page content. The old `admin.php?view=pages` route is no longer exposed from the sidebar; direct GET requests are redirected to `page-builder.php`.

The builder stores each editable section as a root `CONTAINER` row in `rows_json`. A Container is a shell only: it has no title, rich text, buttons, cards, or section image content. Its editable properties are shell-level style controls such as background color/image and inside spacing. Content is added by placing child rows inside the Container.

Each Container can contain content rows and nested Containers. This allows structures such as Container -> Top Text Only + Image On Right, or Container -> nested Container -> Story Cards. Recursive row normalization is bounded to depth 12.

The builder UI follows the Elementor-style split editor:

- left sidebar: selected item properties, split into `Content`, `Style`, and `Advanced`;
- right pane: live page preview in an iframe;
- floating `Structure` popup: compact movable multi-level hierarchy list used to select, add, or delete content rows and nested Containers.

Selecting an item in the Structure popup updates the left properties editor. Layout selection is handled by a movable popup, and color fields use compact popup controls with HEXA/RGB/HSLA modes and browser eye-dropper support where available.

Supported content presets are `Image On Right`, `Image On Left`, `Three Columns`, `Four Columns`, `Social Media Buttons`, `No Image, Centered Text`, `Top Text Only`, `Cards Only`, `Text With Bulleted List`, and `Story Cards`. `Container` remains a shell preset and should not render direct text content. `Story Cards` renders current public stories as an explicitly added content row; the old automatic Stories-page card block remains hidden.

Parent sections can be renamed directly in Page Builder. Default sections are marked `is_default=1`; they may be hidden or reordered, but deletion is rejected by `PageContentService::deleteSection()`. Migration 105 removes default-managed body sections while preserving `HERO` headers and all non-default sections. Non-default parent sections may be deleted. Reordering is performed with `PageContentService::reorderSections()` after the builder submits ordered section IDs.

## Terminal-first checks

Run from production:

```sh
cd /home/toinkyadmin/public_html
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
$PHP -n -l app/PageBuilderService.php
$PHP -n -l app/PageContentService.php
$PHP -n -l public/page-builder.php
$PHP -n -l public/admin.php
$PHP -n -l public/index.php
$PHP tools/page_builder_contract_test.php
$PHP tools/page_builder_recursive_contract_test.php
$PHP tools/migrate.php
```

If a page remains blank or shows old content after deployment, confirm the deployed `page-builder.php` wrapper, `public/page-builder.php`, Page Builder assets, and `public/index.php` renderer are from the same commit before inspecting database content.
