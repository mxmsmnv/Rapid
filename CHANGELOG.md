# Changelog

All notable changes to Rapid are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.2.5] — 2026-07-11

### Added

- Comprehensive `DOCUMENTATION.md` covering installation, configuration, public APIs, frontend editing, security, development and troubleshooting.
- Dashboard with Rapid field, page, block usage, registered renderer, and bundle statistics.
- Frontend field editing with configurable save URL and permission checks.
- CSS framework mappings for Tailwind CSS, Bootstrap 5, and UIkit 3.
- Alert, toggle, link preview, gallery, slideshow, columns, and layout renderers, including legacy block support.
- Per-field editor alignment, toolbar position, heading levels, inline tools, upload limits, image defaults, and output wrapper configuration.
- Security smoke tests for rich text, Raw HTML, URL, attribute, and CSS sanitization.

### Changed

- Module suite version updated to `1.2.5` (`125`).
- Added `Rapid.module.php` as the primary installer module. The actual field type remains `FieldtypeRapid.module.php`, retaining the required `Fieldtype` class prefix so ProcessWire recognizes it in the field type list.
- Block rendering split into reusable value, renderer, attribute, framework, and block-renderer components.
- Runtime PHP code is organized under `src/`, public styles and scripts under `assets/`, endpoint helpers are extracted into a reusable trait, and built-in block renderers are split into thematic files.
- Editor.js integration moved to a bundled, cache-busted frontend asset with server-side rendering.
- Image URLs are normalized for portable storage and resolved for the active page at render time.
- Upload configuration is resolved from the exact Rapid field rather than the first Rapid field on a template.
- Swiper updated to 14.0.5 and vulnerable transitive `uuid` versions overridden with 11.1.1.

### Security

- Rich text and Raw block HTML are sanitized with explicit element and attribute allowlists.
- Unsafe URL schemes, event handlers, executable attachments, CSS declaration injection, and unsafe SVG content are rejected.
- Link preview requests allow only public HTTP/HTTPS targets, reject private and reserved IPv4/IPv6 addresses, disable redirects, and limit response size.
- Frontend save nonces are bound to the authenticated user and expire after one hour.
- Save requests validate payload shape, size, permissions, and field assignment to the page template.
- Internal save exceptions are logged without exposing implementation details to clients.

### Fixed

- Custom frontend save URLs are now used consistently by both the editor and request hook.
- External attachment URLs retain their original host.
- Dashboard assets are returned correctly instead of being left behind unreachable code.
- Multiple Rapid fields on one template retain their independent upload restrictions.

## [1.0.2] — 2026-04-27

### Fixed

- **`ProcessRapidFrontend`** — PHP warning `Attempt to read property "url" on null` in Adminer and other non-PW request contexts. `$this->wire('page')` can return `null` when the request is not routed through ProcessWire; accessing `->url` on `null` produced a fatal-level warning. Added a null-guard before the property read.

- **`js/dist/editor.js`** — `TypeError: can't access property "blocks", y is undefined` on first keystroke in the admin editor. `onChange` was firing before Editor.js finished initializing (`isReady` promise not yet resolved), causing `api.saver.save()` to fail while `BlockManager` was still `undefined`. Added an `editorReady` flag that is set to `true` inside `isReady.then()` and checked at the top of the `onChange` handler.

### Changed

- **`rapid.css`** — Integrated with AdminThemeUikit Design System. All hardcoded hex colors replaced with `--pw-*` CSS custom properties. Added `--rapid-*` token block on `:root` for project-level overrides. Dark mode is automatic via CSS `light-dark()` — no media queries or extra classes needed. Affected components: table, code, quote, warning, checklist, attaches, embed, gallery, toggle, link preview, alert variants.

- **`js/editor.css`** — Same design system integration for the admin editor. All hardcoded colors replaced with `--pw-*` variables. Added overrides for Editor.js-injected `<style>` tags from `link-tool` (`link-tool__content--rendered`, `link-tool__*`) and `@editorjs/attaches` (`--color-bg`, `--color-line`, `--color-bg-secondary`) so those third-party plugins also respect the current color scheme.

- **`js/dist/editor.js`** — Rebuilt bundle. `RapidEmbedTool` inline styles now use `--pw-*` CSS custom properties instead of hardcoded hex values (wrapper border, URL input, caption input, label, Embed button, Remove button).

---

## [1.0.0] — 2026-04-16

Major release. Full rewrite of field settings UI, new block types, frontend editing, file restrictions, and CSS framework support.

### Added

**Block types**
- `alert` (editorjs-alert) — 8 color variants: info, success, warning, danger, etc.
- `toggle` (editorjs-toggle-block) — spoiler/accordion rendered as `<details><summary>`
- `linkTool` (@editorjs/link) — link preview card with OG metadata fetched server-side

**Field settings — Editor behaviour**
- Toolbar position — Left or Right
- `toggle`, `linkTool`, `alert` always available regardless of allowed blocks filter

**Field settings — File uploads**
- Allowed image types — per-field MIME type whitelist (JPEG / PNG / GIF / WebP / SVG)
- Allowed file extensions — comma-separated whitelist for file attachments

**Field settings — Output / rendering**
- CSS framework — Vanilla, Tailwind CSS, Bootstrap 5, UIkit 3
- Output wrapper CSS class — scoped CSS hook (e.g. `prose`)
- Frontend editing — checkbox + permission select

**Frontend editing (`ProcessRapidFrontend`)**
- `renderField($page, $field)` — auto-detects permission, renders editor or plain HTML
- `editorFor($page, $field)` — explicit inline editor with Save button
- `canEdit($page, $field)` — permission check
- Save endpoint at `/rapid-save/` via `addHookBefore('ProcessPageView::execute')` — no admin URL required
- HMAC nonce protection (no CSRF token needed from frontend)
- Assets injected automatically on first `editorFor()` call

**Image handling**
- Default image width/height — all images resized on render via `Pageimage::size()`
- WebP conversion and crop-to-fit options
- Root-relative URLs stored in DB; absolutized for editor display only (`sleepValue` strips domain)
- `processImage()` fallback to original URL if resize fails

**Plugins**
- `editorjs-undo` — Ctrl+Z / Ctrl+Y

**`ProcessFieldtypeRapid`**
- `/rapid-upload/save/` — nonce-verified frontend save endpoint

### Changed

- Image URLs normalized to root-relative on save (`sleepValue` runs `normalizeBlocks`)
- `normalizeBlocks` runs on both `wakeupValue` and `sleepValue`
- `getRegisteredTypes()` includes `toggle` (rendered natively in `renderData`)
- `full` editor alignment now applies `width:100%` and removes Editor.js internal max-width
- DragDrop init uses retry with backoff (waits for `.ce-toolbar` in DOM)
- `findPageimage()` validates `filename` before returning temporary Pageimage
- `processImage()` skips WebP-only without width/height; strips domain before `findPageimage()`
- `executeSave` checks `frontendPermission` field setting

### Removed

- `GalleryTool` — custom multi-image gallery (removed from editor; PHP renderer kept for legacy data)
- `ImageSlideshowTool` — custom image slideshow (same)
- `LayoutSectionTool` — nested Editor.js layout section (caused DOMException)
- `RapidResizeTune` — custom Tune (incompatible API)
- Save endpoint URL field setting — endpoint hardcoded to `/rapid-save/`

### Fixed

- `Block «image» skipped because saved data is invalid` — `@editorjs/image` requires absolute URL; `normalizeBlocks` now absolutizes only for editor, not for DB storage
- `Pageimage: Original image does not exist` — `findPageimage` returns `null` if `$pageimage->filename` invalid
- `ProcessFieldtypeRapid::getMaxUploadBytes does not exist` — helper methods restored after accidental deletion
- CSS added via `$config->styles->add()` overridden by AdminTheme — switched to inline `<style>` block
- `getModuleConfigInputfields()` return type hint removed (PW compatibility)
- `declare(strict_types=1)` removed from all module files (conflicts with PW FileCompiler)

---

## [0.1.0] — 2026-04-04

Initial release.

### Modules

- `FieldtypeRapid` — Fieldtype storing Editor.js block content as JSON.
- `InputfieldRapid` — Admin editor with pre-built JS bundle and cache-busting.
- `ProcessFieldtypeRapid` — Upload endpoints for images and files.

### Block renderers

`paragraph`, `header`, `quote`, `nestedList`, `table`, `code`, `delimiter`, `warning`, `checklist`, `raw`, `image`, `attaches`, `embed`

### JS tools

Editor.js 2.31.5 with header, nested-list, table, quote, image, embed, code, delimiter, warning, checklist, raw, attaches, inline-code, marker, underline.
