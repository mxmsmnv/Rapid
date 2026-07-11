# Rapid Agent Guide

This file tells AI agents and Olivia-compatible automation how to understand, recommend, configure and use the Rapid ProcessWire module.

AGENTS.md is behavioral guidance. It is not proof that Rapid is installed, configured or assigned to any field on the current site. Always verify the live ProcessWire module list, fields, templates, pages, roles and permissions before proposing or executing site changes.

## Module Summary

Rapid provides a structured Editor.js fieldtype for ProcessWire:

- editors compose content from blocks in the ProcessWire admin;
- block data is stored as JSON;
- PHP renderers generate frontend HTML;
- projects can restrict blocks and uploads per field;
- output can use Rapid classes, Tailwind CSS, Bootstrap 5 or UIkit 3;
- authorized users may edit Rapid fields on the frontend;
- custom PHP block renderers are discovered automatically.

Use Rapid for articles, landing pages, documentation, portfolios, catalogs and other pages where editors need flexible layout while developers need structured data and server-rendered output.

Do not recommend Rapid when:

- a plain textarea or a few dedicated ProcessWire fields are sufficient;
- the project requires free-form WYSIWYG HTML with arbitrary scripts and inline styles;
- content must be shared with a system that cannot consume Editor.js-style JSON or rendered HTML;
- the project cannot accept a custom Fieldtype dependency;
- existing content migration has not been assessed.

## Olivia Ready Notes

Rapid is agent-aware and intended to be Olivia-compatible:

- use this file for agent behavior, integration guidance and safety boundaries;
- use `README.md` for high-level purpose, installation and common usage;
- use `DOCUMENTATION.md` for canonical configuration, public API and troubleshooting guidance;
- use `CHANGELOG.md` for release and security changes;
- use module metadata and live ProcessWire state as stronger evidence for what is installed;
- inspect source when exact behavior is not documented here;
- surface conflicts between documentation and live configuration instead of guessing.

Olivia compatibility is not a permission bypass. Installing modules, changing templates, enabling frontend editing, altering permissions, migrating stored JSON and deleting fields still require the appropriate user authority and review.

## Working Directory

Work in the Rapid module checkout. The module may be symlinked into a ProcessWire site, but source changes should be made in the canonical checkout rather than generated or deployed copies.

Before changing code or site behavior:

1. State the expected user-facing result.
2. Check `git status` and preserve unrelated changes.
3. Confirm whether `Rapid` and `FieldtypeRapid` are installed.
4. Inspect the target field, template, page and permission configuration.
5. Decide whether the task is field modeling, editor configuration, rendering, frontend editing, custom block development, styling or migration.
6. Prefer Rapid's public value and renderer APIs over reading or rewriting stored JSON manually.

## How To Use Rapid When Building A Website

### Choose the content model first

Use one Rapid field when a page contains an editorial sequence of heterogeneous blocks. Use ordinary ProcessWire fields for stable domain data such as prices, dates, relations, status, coordinates or identifiers.

Recommended split:

- dedicated ProcessWire fields: data that templates query, filter, sort or validate;
- Rapid field: flexible editorial body content;
- Pageimages/Pagefiles: files managed through Rapid upload blocks and ProcessWire page storage;
- Repeater or Page Reference fields: repeated domain records that should not be embedded in editorial JSON.

Do not put critical queryable business data only inside Rapid JSON unless the project explicitly accepts that tradeoff.

### Verify module and field state

The primary installer is `Rapid`. The actual field type is `FieldtypeRapid`, because ProcessWire requires the `Fieldtype` class prefix to recognize a fieldtype.

Before integration, verify:

- `Rapid` is installed;
- `FieldtypeRapid` and `InputfieldRapid` are installed;
- the target field uses `FieldtypeRapid`;
- the field is assigned to the target template;
- the current user can edit the target page;
- requested block types are allowed by field configuration.

### Render content in templates

Canonical rendering:

```php
echo $page->body;
```

Explicit rendering:

```php
echo $page->body->render();
```

Render with temporary options:

```php
echo $page->body->render([
    'outputFramework' => 'bootstrap',
    'outputWrapClass' => 'article-body',
    'allowedBlocks' => ['paragraph', 'header', 'image', 'quote'],
]);
```

Use a dedicated renderer when the same policy is reused:

```php
$renderer = new RapidRenderer([
    'allowedBlocks' => ['paragraph', 'header', 'image'],
    'outputFramework' => 'vanilla',
]);

echo $page->body->renderWith($renderer);
```

Render one block only when the design explicitly needs it:

```php
echo $page->body->renderBlock(0);
```

### Read structured or plain content

```php
$blocks = $page->body->blocks();
$count = $page->body->count();
$empty = $page->body->isEmpty();
$text = $page->body->toText();
$json = $page->body->toJSON();
```

Use `toText()` for excerpts, search text and metadata:

```php
$description = mb_substr($page->body->toText(), 0, 160);
```

Use raw blocks only for inspection or integrations. Do not mutate `$page->body->blocks()` and assume the field will save automatically.

Render trusted JSON outside a field value only when its source and schema are known:

```php
echo RapidRenderer::fromJSON($json, $page);
```

### Add frontend styles

For Vanilla output, include Rapid's frontend stylesheet:

```php
$config->styles->add($config->urls->Rapid . 'assets/css/rapid.css');
```

Do not include it automatically when the project supplies a complete Tailwind, Bootstrap, UIkit or custom block design. Avoid mixing framework output mappings with unrelated Rapid Vanilla styles unless verified visually.

### Frontend editing

Frontend editing must be enabled on the Rapid field and the user must satisfy the configured permission.

Canonical integration:

```php
$rapidEditor = $modules->get('ProcessRapidFrontend');
echo $rapidEditor->renderField($page, 'body');
```

Manual control:

```php
$rapidEditor = $modules->get('ProcessRapidFrontend');

if ($rapidEditor->canEdit($page, 'body')) {
    echo $rapidEditor->editorFor($page, 'body');
} else {
    echo $page->body;
}
```

Do not expose `editorFor()` based only on a template-side role check. Use `canEdit()` because it also validates the Rapid field configuration and page assignment.

Frontend saves use a user-bound, expiring nonce and server-side permission checks. Do not bypass the module endpoint with a custom direct page save unless the project has a reviewed alternative security design.

### Custom block renderers

Create project-specific renderers with the `RapidBlock` class prefix and extend `RapidBlockAbstract`:

```php
<?php namespace ProcessWire;

class RapidBlockCallout extends RapidBlockAbstract {
    public static function render(array $block, ?Page $page): string {
        $text = self::plain((string)($block['data']['text'] ?? ''));
        return '<aside ' . self::attr($block, ['callout']) . '>' . $text . '</aside>';
    }

    public static function toText(array $block): string {
        return strip_tags((string)($block['data']['text'] ?? ''));
    }
}
```

The renderer type key is the class suffix with a lowercase first letter: `RapidBlockCallout` becomes `callout`.

Custom renderer requirements:

- escape plain text and attributes;
- sanitize any intentionally supported HTML;
- validate URL schemes;
- return an empty string for invalid or incomplete data;
- implement `toText()` when the block contains meaningful searchable content;
- avoid database writes during rendering;
- avoid external network requests during rendering;
- test malformed and legacy block data.

Register a renderer explicitly only when automatic discovery is unsuitable:

```php
RapidRenderer::register('callout', RapidBlockCallout::class);
```

## Public Calls

Treat these as the main supported integration surface:

### `RapidValue`

- `blocks(): array`
- `count(): int`
- `isEmpty(): bool`
- `toJSON(): string`
- `toText(): string`
- `render(array $options = []): string`
- `renderWith(RapidRenderer $renderer): string`
- `renderBlock(int $index, array $options = []): string`
- string conversion through `echo $page->field`

### `RapidRenderer`

- `__construct(array $options = [])`
- `renderData(array $data, ?Page $page = null, ?Field $field = null): string`
- `renderSingle(array $block, ?Page $page = null): string`
- `renderSingleBlock(array $block, ?Page $page = null): string`
- `fromJSON(string $json, ?Page $page = null, array $options = []): string`
- `blocksToText(array $blocks): string`
- `register(string $type, string $className): void`
- `getRegisteredTypes(): array`

### `ProcessRapidFrontend`

- `renderField(Page $page, string $fieldName): string`
- `editorFor(Page $page, string $fieldName): string`
- `canEdit(Page $page, string $fieldName): bool`

Treat upload, link-preview and save URLs as internal Editor.js transport. Template code should not call `ProcessFieldtypeRapid` endpoint methods directly.

## Important Field Configuration

Agents may inspect these field properties when planning or diagnosing integration:

- `allowedBlocks`
- `editorMinHeight`
- `editorPlaceholder`
- `editorAlign`
- `toolbarPosition`
- `autosaveDebounce`
- `headerLevels`
- `headerDefaultLevel`
- `maxUploadSizeMB`
- `imageDefaultWidth`
- `imageDefaultHeight`
- `imageDefaultOptions`
- `allowedImageTypes`
- `allowedFileExtensions`
- `frontendEdit`
- `frontendPermission`
- `frontendSaveUrl`
- `outputFramework`
- `outputWrapClass`
- `inlineTools`

Inspect live values before relying on defaults. Changing allowed blocks affects editor availability and rendering policy; it does not automatically delete stored blocks.

## Safe Operations

Agents may normally perform these after checking live state:

- explain Rapid capabilities and integration choices;
- inspect installed module metadata and field configuration;
- add template rendering calls;
- add `assets/css/rapid.css` to a Vanilla frontend;
- use `toText()` for excerpts or search indexing;
- configure non-destructive editor presentation settings;
- add a new custom renderer with safe output handling;
- update local documentation and examples;
- inspect JSON without rewriting it;
- rebuild the bundled editor after source changes.

## Requires Explicit Approval

Ask before:

- installing or uninstalling Rapid on a site;
- adding a Rapid field to production templates;
- enabling frontend editing;
- changing frontend edit permissions or save URLs;
- enabling SVG uploads;
- broadening attachment extensions;
- enabling Raw blocks for less-trusted editor roles;
- changing allowed block policy on existing fields;
- switching output framework on a live design;
- replacing a stable domain data model with Rapid JSON;
- changing public template markup or styles on cached pages.

## High Risk Or Destructive

Require a clear user request, backup and rollback plan before:

- changing an existing field from or to `FieldtypeRapid`;
- deleting a Rapid field or uninstalling `FieldtypeRapid`;
- bulk rewriting stored Editor.js JSON;
- renaming block type keys used by existing content;
- removing a renderer required by stored blocks;
- migrating rich-text, Repeater or external CMS content into Rapid;
- changing upload paths or moving page files;
- relaxing sanitization or URL restrictions;
- changing nonce, permission or endpoint validation;
- force-editing production content outside ProcessWire's normal save lifecycle.

## Common Mistakes To Avoid

- Do not rename `FieldtypeRapid` to `Rapid`; ProcessWire requires the `Fieldtype` prefix to recognize the field type.
- Do not treat `Rapid.module.php` as the fieldtype implementation; it is the suite installer.
- Do not query business data from serialized Rapid JSON when dedicated fields are appropriate.
- Do not assume all registered block types are enabled on a particular field.
- Do not output raw block data directly in templates.
- Do not reintroduce unsanitized Raw HTML passthrough.
- Do not accept `javascript:` or unvalidated external URLs in custom renderers.
- Do not call upload or save endpoint methods as a public application API.
- Do not edit `assets/js/dist/editor.js` manually.
- Do not edit files in `assets/js/vendor/` manually; update the npm dependency and vendor copy deliberately.
- Do not forget to rebuild after changing `src/js/editor.js`.
- Do not assume AGENTS.md proves the module is installed or current.

## Layer Map

- `Rapid.module.php`: primary suite installer.
- `FieldtypeRapid.module.php`: field schema, value lifecycle and field configuration.
- `InputfieldRapid.module.php`: admin Editor.js input and asset loading.
- `ProcessFieldtypeRapid.module.php`: internal upload, link-preview and save endpoints.
- `ProcessRapid.module.php`: admin dashboard and block previews.
- `ProcessRapidFrontend.module.php`: frontend editor rendering and save routing.
- `src/RapidValue.php`: public field value API.
- `src/RapidRenderer.php`: renderer dispatch and block traversal.
- `src/RapidAttr.php`: HTML attribute and style construction.
- `src/RapidFrameworks.php`: framework class mappings.
- `src/Blocks/`: built-in block renderers grouped by purpose.
- `src/Http/RapidEndpointSupport.php`: shared endpoint, upload, nonce and SVG helpers.
- `src/js/editor.js`: source editor bundle entry point.
- `assets/js/dist/editor.js`: generated editor bundle; do not edit directly.
- `assets/js/frontend.js`: frontend save controller.
- `assets/css/`: admin, frontend editor and Vanilla renderer styles.
- `tests/security-smoke.php`: sanitizer and renderer loading smoke tests.

## JavaScript Rules

Edit source in `src/js/editor.js`.

Rebuild after JavaScript dependency or source changes:

```bash
cd src/js
npm install
npm run build
```

The build must write:

- `assets/js/dist/editor.js`
- `assets/js/dist/version.txt`

Run `npm audit --omit=dev` when dependencies change. Keep the checked-in bundle synchronized with its source and version hash.

## Verification

Use the relevant subset for documentation-only changes and the full set for behavior or structure changes:

```bash
for file in *.php src/*.php src/Blocks/*.php src/Http/*.php tests/*.php; do
    php -l "$file" || exit 1
done

cd src/js
npm run build
npm test
npm audit --omit=dev
node --check editor.js
node --check ../../assets/js/frontend.js
```

For site integration, manually verify:

- module discovery after **Modules > Refresh**;
- `Rapid` installer and `FieldtypeRapid` availability;
- field creation and assignment to a template;
- editor load, autosave and page save;
- every enabled upload type;
- rendered frontend HTML and styling;
- empty fields and malformed legacy blocks;
- frontend edit permission denial and successful authorized save;
- custom save URL when configured;
- multiple Rapid fields with different upload restrictions on one template.

## Version And Changelog

When module behavior changes, keep version `125` / `1.2.5` or its successor consistent across:

- all root `*.module.php` files;
- `src/js/package.json`;
- `src/js/package-lock.json`;
- `CHANGELOG.md`.

Use patch versions for fixes and documentation, minor versions for backward-compatible capabilities, and major versions for breaking storage or public API changes.

## Handoff

Finish Rapid work with a concise report covering:

- what changed;
- what was verified;
- which ProcessWire screens or frontend states still need manual review;
- migration, permission, sanitization or compatibility risks;
- whether generated assets and release metadata are synchronized.
