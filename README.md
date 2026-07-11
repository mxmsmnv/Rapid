# Rapid

Rapid adds a modern block editor field to ProcessWire. Editors compose pages with structured Editor.js blocks, while ProcessWire stores the content as JSON and renders clean HTML on the server.

![Rapid](assets/images/Rapid.png)

It is made for sites that need flexible editorial content without hiding structure inside a traditional rich-text field: articles, landing pages, documentation, portfolios, catalogs and content-driven applications.

**Author:** Maxim Semenov

**Website:** [smnv.org](https://smnv.org)

**Email:** [maxim@smnv.org](mailto:maxim@smnv.org)

If this project helps your work, consider supporting future development: [GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or [smnv.org/sponsor](https://smnv.org/sponsor/).

## What Rapid Does

- Adds an Editor.js block editor as a native ProcessWire fieldtype.
- Stores structured JSON instead of editor-generated page markup.
- Renders blocks to HTML on the server through extensible PHP renderers.
- Includes paragraphs, headings, quotes, lists, tables, code, images, attachments, embeds, alerts, toggles, link previews and layout blocks.
- Supports image resizing, cropping and WebP conversion through ProcessWire.
- Supports Vanilla CSS, Tailwind CSS, Bootstrap 5 and UIkit 3 output.
- Provides plain-text and raw-JSON access for search, metadata and APIs.
- Supports field-level block restrictions, heading levels, inline tools and upload rules.
- Includes optional authorized frontend editing.
- Sanitizes rich text, Raw blocks, URLs, SVG uploads and remote link previews.

## Admin Area

Rapid adds a dedicated ProcessWire admin section where site editors and developers can:

- view Rapid fields and pages that contain block content;
- inspect block usage statistics;
- preview all registered block renderers;
- verify the installed editor bundle and version hash;
- configure editor behavior, uploads, output and frontend permissions from field settings.

## Block Content

Rapid includes block tools for:

- paragraphs and headings;
- quotes, nested lists, checklists and tables;
- code, warnings, alerts and delimiters;
- images, galleries, slideshows and attachments;
- YouTube and Vimeo embeds;
- link preview cards;
- toggles, columns and layout sections;
- sanitized Raw HTML.

Custom PHP block renderers are discovered automatically, so projects can add their own structured content blocks without modifying the core renderer.

## Template Usage

Render a Rapid field like any other ProcessWire field:

```php
echo $page->body;
```

The value object also provides explicit helpers:

```php
echo $page->body->render();

$blocks = $page->body->blocks();
$text = $page->body->toText();
$json = $page->body->toJSON();
```

For frontend editing:

```php
$rapidEditor = $modules->get('ProcessRapidFrontend');
echo $rapidEditor->renderField($page, 'body');
```

Include the Vanilla frontend stylesheet when the site does not provide its own block styles:

```php
$config->styles->add($config->urls->Rapid . 'assets/css/rapid.css');
```

## Installation

Requirements: ProcessWire 3.0.200 or newer and PHP 8.2 or newer.

1. Copy the `Rapid` folder into `/site/modules/`.
2. In ProcessWire Admin, refresh modules.
3. Install `Rapid`.
4. Add a field with the `Rapid` field type to a template.
5. Configure available blocks, uploads and output in the field settings.

Installing the primary `Rapid` module automatically installs the required Fieldtype, Inputfield, admin process and frontend editor modules.

No Node.js build step is required for installation. A pre-built editor bundle is included in `assets/js/dist/`.

## Development

Editor source and npm dependencies live in `src/js/`. Rebuild the browser bundle after JavaScript changes:

```bash
cd src/js
npm install
npm run build
```

Run the security smoke tests with:

```bash
npm test
```

## Documentation

See [DOCUMENTATION.md](DOCUMENTATION.md) for complete setup, configuration, API and development guidance.

See [CHANGELOG.md](CHANGELOG.md) for release notes and security-related changes.

## Author

Maxim Semenov

[smnv.org](https://smnv.org)

[maxim@smnv.org](mailto:maxim@smnv.org)

## License

MIT
