# Rapid Documentation

Rapid is an Editor.js fieldtype suite for ProcessWire. It stores structured block data as JSON and renders frontend HTML through PHP block renderers.

This document is the canonical integration and configuration guide for Rapid 1.2.5.

## Requirements

- ProcessWire 3.0.200 or newer
- PHP 8.2 or newer
- A modern browser supported by Editor.js

Node.js is required only when rebuilding the editor bundle. A compiled production bundle is included with the module.

## Module Structure

Rapid is installed as a suite:

- `Rapid` — primary installer module;
- `FieldtypeRapid` — ProcessWire fieldtype and field configuration;
- `InputfieldRapid` — Editor.js inputfield used in the admin;
- `ProcessFieldtypeRapid` — upload, link-preview and internal save endpoints;
- `ProcessRapid` — dashboard and block preview admin area;
- `ProcessRapidFrontend` — optional frontend editing.

The actual fieldtype class must remain named `FieldtypeRapid`. ProcessWire uses the `Fieldtype` prefix to recognize fieldtype modules.

## Installation

1. Copy the complete `Rapid` directory into `/site/modules/`.
2. Open **Modules** in ProcessWire Admin.
3. Select **Refresh** or **Check for new modules**.
4. Install `Rapid`.
5. Confirm that the supporting Rapid modules were installed automatically.
6. Create a field and select **Rapid** as its field type.
7. Assign the field to one or more templates.

Rapid creates a hidden admin process page for internal upload endpoints and a Rapid section under **Setup** for the dashboard and block preview.

## Basic Template Usage

Assume a Rapid field named `body` is assigned to the current page template.

Render the field:

```php
echo $page->body;
```

Explicit equivalent:

```php
echo $page->body->render();
```

Check whether the field contains blocks:

```php
if (!$page->body->isEmpty()) {
    echo $page->body;
}
```

Read the number of blocks:

```php
$count = $page->body->count();
```

## Frontend Styles

Rapid's default PHP renderers output `rapid-*` classes in Vanilla mode. Include the provided stylesheet when the site does not define its own block styles:

```php
$config->styles->add($config->urls->Rapid . 'assets/css/rapid.css');
```

The stylesheet uses `--pw-*` and `--rapid-*` custom properties. Projects may override Rapid tokens in their own stylesheet:

```css
:root {
    --rapid-accent: var(--pw-main-color);
    --rapid-code-bg: light-dark(#1e1e2e, #0d0d1a);
}
```

Do not include the Vanilla stylesheet automatically when the project uses complete Tailwind, Bootstrap, UIkit or custom component styling without first checking the rendered result.

## Field Configuration

Open **Setup > Fields > your Rapid field > Details**.

### Editor behavior

#### Allowed block types

Restricts the block tools available in the editor and the configured rendering policy. When no options are selected, all registered editor block types are available.

Some legacy or native types are not shown in the checklist but may still render when stored content contains them.

Changing allowed blocks does not delete existing JSON. Review pages containing previously allowed block types before tightening the setting.

#### Minimum height

Sets the minimum editor height in pixels. The default is 200.

#### Placeholder

Defines the prompt shown by an empty editor.

#### Editor alignment

Supported values:

- `left` — constrained editor aligned left;
- `center` — constrained editor centered;
- `full` — full available width.

#### Toolbar position

Places Editor.js controls on the left or right.

#### Autosave debounce

Controls how long the inputfield waits after editor changes before synchronizing JSON into the hidden ProcessWire form field.

This is not an independent page save. The ProcessWire page form still controls persistence.

### Heading block

Configure:

- allowed heading levels;
- default heading level.

Use semantic heading levels appropriate to the surrounding page template. Do not expose heading level 1 by default when the template already renders the page title as `<h1>`.

### Inline tools

The inline toolbar may expose:

- bold;
- italic;
- links;
- inline code;
- marker;
- underline.

An empty selection uses the module's default inline toolbar configuration.

### Uploads

#### Maximum upload size

Sets a per-field limit in megabytes. The effective limit never exceeds PHP's `upload_max_filesize` or `post_max_size`.

#### Allowed image types

Supported image MIME types include JPEG, PNG, GIF, WebP and optionally SVG.

SVG is rejected unless explicitly allowed. Accepted SVG files are parsed and sanitized before storage. Scripts, event attributes, embedded objects, external resource references and unsafe style content are removed.

#### Allowed attachment extensions

Use a comma-separated list:

```text
pdf,doc,docx,xls,xlsx,zip
```

Executable and server-sensitive extensions remain blocked even when no explicit list is configured.

#### Default image processing

Rapid can apply default width, height, WebP conversion and crop settings when rendering images through ProcessWire's image API.

Use zero width or height to leave that dimension unrestricted. Verify image quality and generated variation storage before enabling global resizing on existing content.

### Output

#### Framework

Supported output modes:

- `vanilla`
- `tailwind`
- `bootstrap`
- `uikit`

Framework mappings are defined in `src/RapidFrameworks.php`.

#### Wrapper class

Adds a sanitized CSS class list around rendered content. This is useful for typography scopes such as:

```text
article-body prose
```

### Frontend editing

Frontend editing is disabled by default. Configuration includes:

- enable frontend editing;
- required permission;
- frontend save URL.

Enabling this option changes public page behavior for authorized users. Confirm caching, permissions and layout before enabling it on a live site.

## RapidValue API

A formatted Rapid field returns `RapidValue`.

### `blocks(): array`

Returns the raw block array:

```php
$blocks = $page->body->blocks();
```

Treat the returned array as read-only unless you deliberately assign and save a complete new field value through ProcessWire.

### `count(): int`

```php
$count = $page->body->count();
```

### `isEmpty(): bool`

```php
if ($page->body->isEmpty()) {
    // Render an empty state.
}
```

### `toJSON(): string`

```php
$json = $page->body->toJSON();
```

Useful for APIs, exports and diagnostics. Do not expose private content merely because JSON serialization is available.

### `toText(): string`

Returns whitespace-normalized plain text from blocks that implement text conversion:

```php
$description = mb_substr($page->body->toText(), 0, 160);
```

Use it for excerpts, metadata and indexing. It is not a lossless content export.

### `render(array $options = []): string`

```php
echo $page->body->render([
    'outputFramework' => 'tailwind',
    'outputWrapClass' => 'prose max-w-none',
]);
```

Supported renderer options include:

- `allowedBlocks`
- `outputFramework`
- `outputWrapClass`
- `imageDefaultWidth`
- `imageDefaultHeight`
- `imageDefaultWebp`
- `imageDefaultCrop`

Options passed to `render()` override corresponding field defaults for that call.

### `renderWith(RapidRenderer $renderer): string`

```php
$renderer = new RapidRenderer([
    'allowedBlocks' => ['paragraph', 'header', 'quote'],
]);

echo $page->body->renderWith($renderer);
```

### `renderBlock(int $index, array $options = []): string`

```php
echo $page->body->renderBlock(0);
```

Returns an empty string when the index does not exist or the block cannot be rendered.

## RapidRenderer API

### Render JSON

```php
echo RapidRenderer::fromJSON($json, $page, [
    'outputFramework' => 'bootstrap',
]);
```

Invalid JSON returns an empty string.

### Render an Editor.js data array

```php
$data = json_decode($json, true);
$renderer = new RapidRenderer();

echo $renderer->renderData($data, $page);
```

Expected shape:

```php
[
    'blocks' => [
        [
            'type' => 'paragraph',
            'data' => ['text' => 'Hello'],
        ],
    ],
]
```

### Convert blocks to text

```php
$text = RapidRenderer::blocksToText($blocks);
```

### Inspect registered types

```php
$types = RapidRenderer::getRegisteredTypes();
```

### Register a renderer

```php
RapidRenderer::register('callout', RapidBlockCallout::class);
```

Explicit registration replaces the renderer associated with that type for the current request.

## Built-in Blocks

Rapid includes PHP renderers for:

- `paragraph`
- `header`
- `quote`
- `nestedList`
- `table`
- `code`
- `image`
- `gallery`
- `imageSlideshow`
- `embed`
- `layoutSection`
- `delimiter`
- `warning`
- `checklist`
- `raw`
- `attaches`
- `alert`
- `linkTool`
- `columns`
- native `toggle` handling in `RapidRenderer`

Some renderers remain available for legacy stored data even when their editor tools are not exposed.

## Custom Block Renderers

Create a class whose name starts with `RapidBlock` and extends `RapidBlockAbstract`:

```php
<?php namespace ProcessWire;

class RapidBlockCallout extends RapidBlockAbstract {
    public static function render(array $block, ?Page $page): string {
        $text = self::plain((string)($block['data']['text'] ?? ''));
        if ($text === '') return '';

        return '<aside ' . self::attr($block, ['callout']) . '>' . $text . '</aside>';
    }

    public static function toText(array $block): string {
        return strip_tags((string)($block['data']['text'] ?? ''));
    }
}
```

`RapidBlockCallout` maps to the block type `callout`.

Renderer guidelines:

- return an empty string for unusable data;
- escape plain content and attributes;
- sanitize intentionally supported HTML;
- allow only expected URL schemes;
- implement `toText()` for searchable content;
- do not write data or call external services while rendering;
- handle malformed and legacy data defensively.

Project-specific classes must be loaded before the first renderer registry boot, or registered explicitly with `RapidRenderer::register()`.

## Frontend Editing API

Enable frontend editing in the Rapid field settings before using these calls.

### Automatic editor or rendered output

```php
$editor = $modules->get('ProcessRapidFrontend');
echo $editor->renderField($page, 'body');
```

Authorized users receive the editor. Everyone else receives rendered content.

### Permission check

```php
$canEdit = $editor->canEdit($page, 'body');
```

The check verifies:

- the field exists;
- the field uses `FieldtypeRapid`;
- the field belongs to the page template;
- frontend editing is enabled;
- the current user has the configured permission.

### Explicit editor output

```php
if ($editor->canEdit($page, 'body')) {
    echo $editor->editorFor($page, 'body');
}
```

Always call `canEdit()` before explicitly rendering an editor in custom authorization flows.

Frontend assets are emitted once per request. Save nonces are bound to the current user and expire after one hour.

### Caching

Do not full-page cache authorized frontend editor markup or user-bound save nonces. Either bypass caching for editing users or render the editable fragment outside the cached region.

## Internal HTTP Endpoints

The editor uses internal admin endpoints for:

- image uploads;
- attachment uploads;
- link preview metadata;
- internal save handling.

The frontend editor uses the configured frontend save URL, defaulting to:

```text
/rapid-save/
```

These routes are transport internals, not a general public REST API. Template code should use the documented PHP APIs rather than invoking endpoint methods directly.

## Security Model

Rapid applies several defense layers:

- rich text is sanitized with explicit element and attribute allowlists;
- Raw blocks use a broader but still explicit allowlist;
- unsafe URL schemes are rejected;
- SVG uploads are parsed and sanitized;
- executable attachment extensions are blocked;
- uploads are validated against the exact Rapid field configuration;
- link previews accept only public HTTP and HTTPS targets;
- private and reserved IPv4 and IPv6 targets are rejected;
- link-preview redirects are disabled and response size is limited;
- save payload size, shape, page, field and permissions are checked;
- frontend nonces are user-bound and expire;
- internal exception details are logged rather than returned to clients.

Custom renderers remain responsible for validating their own block-specific data.

## Rendering And Data Notes

### Unknown blocks

Blocks without a registered renderer produce no HTML. Their stored JSON is not automatically deleted.

### Allowed blocks

Renderer restrictions skip disallowed block types. Paragraph and selected legacy layout types may remain renderable for compatibility.

### Toggle blocks

Toggle blocks identify a number of following blocks as children. Avoid manually reordering raw JSON without preserving this relationship.

### Images

Rapid normalizes same-site absolute image URLs to root-relative paths when values are stored. The admin and frontend editors temporarily restore absolute URLs for Editor.js display requirements.

### Output formatting

ProcessWire field values may behave differently when output formatting is disabled during programmatic writes. Use the normal ProcessWire field save lifecycle and test imports on a copy of production data.

## Development

### PHP source

Shared PHP runtime files live under `src/`:

```text
src/
├── Blocks/
├── Http/
├── RapidAttr.php
├── RapidFrameworks.php
├── RapidRenderer.php
├── RapidValue.php
└── bootstrap.php
```

ProcessWire module files remain in the repository root so module discovery works correctly.

### JavaScript source

JavaScript source and npm metadata live in `src/js/`.

Install dependencies and rebuild:

```bash
cd src/js
npm install
npm run build
```

Generated files:

```text
assets/js/dist/editor.js
assets/js/dist/version.txt
```

Do not edit the generated bundle manually.

### Vendor browser assets

Browser-only Swiper files are stored in `assets/js/vendor/`. When updating Swiper:

1. update the npm dependency;
2. review breaking changes;
3. update the checked-in browser vendor files;
4. test slideshow behavior;
5. run `npm audit --omit=dev`.

### Tests

Run the security smoke test:

```bash
cd src/js
npm test
```

Run PHP syntax checks from the module root:

```bash
for file in *.php src/*.php src/Blocks/*.php src/Http/*.php tests/*.php; do
    php -l "$file" || exit 1
done
```

Run JavaScript syntax checks:

```bash
node --check src/js/editor.js
node --check assets/js/frontend.js
```

## Troubleshooting

### Rapid does not appear as a field type

1. Confirm `FieldtypeRapid.module.php` exists in the module root.
2. Refresh ProcessWire modules.
3. Confirm `FieldtypeRapid` is installed.
4. Check ProcessWire and PHP version requirements.
5. Inspect ProcessWire module logs for load errors.

Do not rename the fieldtype class to `Rapid`; the `Fieldtype` prefix is required.

### Editor area is empty

1. Check the browser console for JavaScript errors.
2. Confirm `assets/js/dist/editor.js` is reachable.
3. Confirm the bundle hash file exists.
4. Refresh ProcessWire modules and browser caches.
5. Validate stored JSON.
6. Check that the field is assigned to the edited page template.

### Images upload but do not render

1. Confirm the page is saved and has a valid files directory.
2. Check the allowed image MIME types.
3. Verify the stored URL and ProcessWire page files.
4. Disable default resizing temporarily to isolate image variation errors.
5. Check filesystem permissions and image engine support.

### Attachment upload is rejected

Check:

- configured extension allowlist;
- permanently blocked executable extensions;
- field and PHP upload size limits;
- page edit permission;
- whether the request includes the correct Rapid field name.

### Link preview fails

Rapid rejects invalid URLs, local hosts, private networks, redirects and oversized responses. Confirm the target uses a direct public HTTP or HTTPS URL and serves metadata without redirecting.

### Frontend save fails

Check:

- frontend editing is enabled on the field;
- the field belongs to the page template;
- the current user has the configured permission;
- the save URL matches the configured route;
- the page is not serving a cached expired nonce;
- the JSON payload is valid and within the size limit;
- the `rapid` ProcessWire log for internal save errors.

### A block disappears from output

Check whether:

- the block type is allowed;
- a renderer is registered;
- the block contains the expected `data` structure;
- its renderer rejected invalid content;
- the block is a toggle child whose parent metadata is malformed.

## Upgrade And Removal

Before upgrading:

1. back up the database and page files;
2. review `CHANGELOG.md`;
3. test existing block JSON and frontend output in staging;
4. rebuild only when changing source rather than installing the packaged release;
5. verify frontend permissions and uploads after upgrade.

Before uninstalling:

1. identify all fields using `FieldtypeRapid`;
2. export or migrate content that must be retained;
3. remove field assignments safely;
4. confirm no templates call Rapid APIs;
5. uninstall the suite only after the data migration is verified.

Uninstalling a fieldtype or deleting its fields can make stored content inaccessible. Treat removal and fieldtype migration as destructive operations.

## Related Files

- [README.md](README.md) — concise module overview
- [AGENTS.md](AGENTS.md) — AI agent behavior and safety guidance
- [CHANGELOG.md](CHANGELOG.md) — release notes and security changes
