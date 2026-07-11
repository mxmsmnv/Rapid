<?php namespace ProcessWire;

require_once __DIR__ . '/src/bootstrap.php';

// Store the real module path before FileCompiler potentially changes __DIR__
\ProcessWire\RapidRenderer::$moduleDir = __DIR__;

/**
 * FieldtypeRapid
 *
 * Stores Editor.js block-editor content as JSON.
 * Rendering via RapidRenderer (separate helper class).
 *
 * Block rendering architecture inspired by Automad CMS (automad.org).
 *
 * @copyright 2026
 * @license MIT
 */
class FieldtypeRapid extends Fieldtype {

	public static function getModuleInfo(): array {
		return [
			'title'    => 'Rapid',
			'version'  => 125,
			'summary'  => 'Editor.js block editor field for ProcessWire.',
			'icon'     => 'bolt',
			'author'   => 'Maxim Semenov',
			'href'     => 'https://smnv.org',
			'installs' => ['InputfieldRapid', 'ProcessFieldtypeRapid', 'ProcessRapid', 'ProcessRapidFrontend'],
			'requires' => 'ProcessWire>=3.0.200',
			'autoload' => false,
		];
	}

	// ── Schema ────────────────────────────────────────────────────────────

	public function getDatabaseSchema(Field $field): array {
		$schema = parent::getDatabaseSchema($field);
		$schema['data'] = 'mediumtext NOT NULL';
		$schema['keys']['data'] = 'FULLTEXT KEY `data` (`data`)';
		return $schema;
	}

	// ── Value lifecycle ───────────────────────────────────────────────────

	public function getBlankValue(Page $page, Field $field): RapidValue {
		return $this->newValue($page, $field);
	}

	public function sanitizeValue(Page $page, Field $field, $value) {
		if ($value instanceof RapidValue) return $value;
		if (empty($value)) return $this->newValue($page, $field);
		if (is_array($value)) $value = json_encode($value);
		if (!is_string($value)) return $this->newValue($page, $field);
		$decoded = json_decode($value, true);
		if (!isset($decoded['blocks'])) return $this->newValue($page, $field);
		$v = $this->newValue($page, $field);
		$v->setData($decoded);
		return $v;
	}

	public function ___wakeupValue(Page $page, Field $field, $value): RapidValue {
		$v = $this->newValue($page, $field);
		if (!empty($value)) {
			$decoded = json_decode($value, true);
			if (is_array($decoded)) {
				$decoded = $this->normalizeBlocks($decoded);
				$v->setData($decoded);
			}
		}
		// Pass field-level renderer options
		$imgOpts = (array)($field->get('imageDefaultOptions') ?: []);
		$v->setOptions([
			'outputFramework'    => (string)($field->get('outputFramework')    ?: 'vanilla'),
			'outputWrapClass'    => (string)($field->get('outputWrapClass')    ?: ''),
			'allowedBlocks'      => (array)($field->get('allowedBlocks')       ?: []),
			'imageDefaultWidth'  => (int)($field->get('imageDefaultWidth')     ?: 0),
			'imageDefaultHeight' => (int)($field->get('imageDefaultHeight')    ?: 0),
			'imageDefaultWebp'   => in_array('webp', $imgOpts, true),
			'imageDefaultCrop'   => in_array('crop', $imgOpts, true),
		]);
		return $v;
	}

	/**
	 * Normalize block data for forward compatibility.
	 * @editorjs/image stores URL in data.file.url on first save,
	 * then normalizes to data.url on subsequent loads. We do it here
	 * so Editor.js always receives valid data on wakeup.
	 */
	private function normalizeBlocks(array $data): array {
		if (empty($data['blocks'])) return $data;

		$config  = $this->wire('config');
		$baseUrl = ($config->https ? 'https' : 'http') . '://' . $config->httpHost;

		foreach ($data['blocks'] as &$block) {
			if (($block['type'] ?? '') === 'image') {
				// Strip absolute base URL — always store root-relative
				foreach (['url', 'file'] as $k) {
					if ($k === 'url' && !empty($block['data']['url'])) {
						$block['data']['url'] = str_replace($baseUrl, '', $block['data']['url']);
					}
					if ($k === 'file' && !empty($block['data']['file']['url'])) {
						$block['data']['file']['url'] = str_replace($baseUrl, '', $block['data']['file']['url']);
					}
				}
				// Promote file.url to data.url if missing
				if (empty($block['data']['url']) && !empty($block['data']['file']['url'])) {
					$block['data']['url'] = $block['data']['file']['url'];
				}
			}
		}
		unset($block);
		return $data;
	}

	public function ___sleepValue(Page $page, Field $field, $value): string {
		$json = '';
		if ($value instanceof RapidValue) {
			$json = $value->toJSON();
		} elseif (is_array($value)) {
			$json = json_encode($value) ?: '';
		} else {
			$json = (string) $value;
		}

		// Strip absolute base URL from image blocks before saving
		if ($json) {
			$data = json_decode($json, true);
			if (is_array($data)) {
				$data = $this->normalizeBlocks($data);
				$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $json;
			}
		}

		return $json;
	}

	public function getInputfield(Page $page, Field $field): InputfieldRapid {
		/** @var InputfieldRapid $f */
		$f = $this->wire('modules')->get('InputfieldRapid');
		$f->setField($field);
		return $f;
	}


	// ── Field config UI ───────────────────────────────────────────────────

	public function ___getConfigInputfields(Field $field): InputfieldWrapper {
		$inputfields = parent::___getConfigInputfields($field);
		$modules     = $this->wire('modules');

		// ── Editor behaviour (open by default) ──────────────────────────────

		$fs1 = $modules->get('InputfieldFieldset');
		$fs1->label     = $this->_('Editor behaviour');
		$fs1->collapsed = Inputfield::collapsedNo;

		// Row 1: Allowed blocks (100%)
		$f = $modules->get('InputfieldCheckboxes');
		$f->attr('name', 'allowedBlocks');
		$f->label         = $this->_('Allowed block types');
		$f->description   = $this->_('Leave all unchecked to allow every registered block type.');
		$f->optionColumns = 3;
		$f->columnWidth   = 100;
		// Types hidden from UI (no editor tool, render-only for legacy data)
		// paragraph is Editor.js built-in default block — always available, not filterable
		$hiddenTypes = ['layoutSection', 'gallery', 'imageSlideshow', 'columns', 'paragraph'];
		foreach (RapidRenderer::getRegisteredTypes() as $type) {
			if (in_array($type, $hiddenTypes, true)) continue;
			// Friendly labels for compound type names
			$labels = [
				'nestedList'    => 'NestedList',
				'linkTool'      => 'LinkTool',
				'imageSlideshow'=> 'ImageSlideshow',
			];
			$label = $labels[$type] ?? ucfirst($type);
			$f->addOption($type, $label);
		}
		$f->value = (array)($field->get('allowedBlocks') ?: []);
		$fs1->add($f);

		// Row 2: Min height / Placeholder / Align / Debounce (25/25/25/25)
		$t = $modules->get('InputfieldInteger');
		$t->attr('name', 'editorMinHeight');
		$t->label       = $this->_('Min height (px)');
		$t->attr('value', (int)($field->get('editorMinHeight') ?: 200));
		$t->columnWidth = 20;
		$fs1->add($t);

		$p = $modules->get('InputfieldText');
		$p->attr('name', 'editorPlaceholder');
		$p->label       = $this->_('Placeholder text');
		$p->attr('value', $field->get('editorPlaceholder') ?: '');
		$p->placeholder = 'Start writing…';
		$p->columnWidth = 20;
		$fs1->add($p);

		$al = $modules->get('InputfieldSelect');
		$al->attr('name', 'editorAlign');
		$al->label = $this->_('Editor alignment');
		$al->addOption('left',   $this->_('Left'));
		$al->addOption('center', $this->_('Center'));
		$al->addOption('full',   $this->_('Full width'));
		$al->attr('value', $field->get('editorAlign') ?: 'full');
		$al->columnWidth = 20;
		$fs1->add($al);

		$tb = $modules->get('InputfieldSelect');
		$tb->attr('name', 'toolbarPosition');
		$tb->label = $this->_('Toolbar position');
		$tb->addOption('left',  $this->_('Left'));
		$tb->addOption('right', $this->_('Right'));
		$tb->attr('value', $field->get('toolbarPosition') ?: 'left');
		$tb->columnWidth = 20;
		$fs1->add($tb);

		$d = $modules->get('InputfieldInteger');
		$d->attr('name', 'autosaveDebounce');
		$d->label       = $this->_('Autosave debounce (ms)');
		$d->attr('value', (int)($field->get('autosaveDebounce') ?? 300));
		$d->min         = 0;
		$d->max         = 5000;
		$d->columnWidth = 20;
		$fs1->add($d);

		$inputfields->add($fs1);

		// ── Header block (collapsed) ──────────────────────────────────────

		$fs2 = $modules->get('InputfieldFieldset');
		$fs2->label     = $this->_('Header block');
		$fs2->collapsed = Inputfield::collapsedYes;

		$hl = $modules->get('InputfieldCheckboxes');
		$hl->attr('name', 'headerLevels');
		$hl->label         = $this->_('Allowed heading levels');
		$hl->description   = $this->_('Leave all unchecked to allow h2–h4 (default).');
		$hl->optionColumns = 1;
		foreach ([1 => 'H1', 2 => 'H2', 3 => 'H3', 4 => 'H4', 5 => 'H5', 6 => 'H6'] as $n => $lbl) {
			$hl->addOption($n, $lbl);
		}
		$hl->value       = (array)($field->get('headerLevels') ?: []);
		$hl->columnWidth = 67;
		$fs2->add($hl);

		$hd = $modules->get('InputfieldSelect');
		$hd->attr('name', 'headerDefaultLevel');
		$hd->label = $this->_('Default level');
		foreach ([1 => 'H1', 2 => 'H2', 3 => 'H3', 4 => 'H4'] as $n => $lbl) {
			$hd->addOption($n, $lbl);
		}
		$hd->attr('value', (int)($field->get('headerDefaultLevel') ?: 2));
		$hd->columnWidth = 33;
		$fs2->add($hd);

		$inputfields->add($fs2);

		// ── File uploads (collapsed) ──────────────────────────────────────

		$fs3 = $modules->get('InputfieldFieldset');
		$fs3->label     = $this->_('File uploads');
		$fs3->collapsed = Inputfield::collapsedYes;

		$ms = $modules->get('InputfieldInteger');
		$ms->attr('name', 'maxUploadSizeMB');
		$ms->label       = $this->_('Max upload size (MB)');
		$ms->description = $this->_('Applies to images and file attachments. Server PHP limits may be lower.');
		$ms->attr('value', (int)($field->get('maxUploadSizeMB') ?: 10));
		$ms->min         = 1;
		$ms->max         = 100;
		$ms->columnWidth = 34;
		$fs3->add($ms);

		$iw = $modules->get('InputfieldInteger');
		$iw->attr('name', 'imageDefaultWidth');
		$iw->label       = $this->_('Default image width (px)');
		$iw->description = $this->_('Resize all images to this width on render. 0 = no resize.');
		$iw->attr('value', (int)($field->get('imageDefaultWidth') ?: 0));
		$iw->min         = 0;
		$iw->max         = 9999;
		$iw->columnWidth = 22;
		$fs3->add($iw);

		$ih = $modules->get('InputfieldInteger');
		$ih->attr('name', 'imageDefaultHeight');
		$ih->label       = $this->_('Default image height (px)');
		$ih->description = $this->_('0 = proportional.');
		$ih->attr('value', (int)($field->get('imageDefaultHeight') ?: 0));
		$ih->min         = 0;
		$ih->max         = 9999;
		$ih->columnWidth = 22;
		$fs3->add($ih);

		$iopt = $modules->get('InputfieldCheckboxes');
		$iopt->attr('name', 'imageDefaultOptions');
		$iopt->label       = $this->_('Image options');
		$iopt->optionColumns = 1;
		$iopt->addOption('webp', 'Convert to WebP');
		$iopt->addOption('crop', 'Crop to fit');
		$iopt->value       = (array)($field->get('imageDefaultOptions') ?: []);
		$iopt->columnWidth = 22;
		$fs3->add($iopt);

		// Row: allowed image types / allowed file extensions
		$ait = $modules->get('InputfieldCheckboxes');
		$ait->attr('name', 'allowedImageTypes');
		$ait->label         = $this->_('Allowed image types');
		$ait->description   = $this->_('Leave all unchecked to allow all types.');
		$ait->optionColumns = 1;
		foreach (['image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/gif' => 'GIF', 'image/webp' => 'WebP', 'image/svg+xml' => 'SVG'] as $mime => $label) {
			$ait->addOption($mime, $label);
		}
		$ait->value       = (array)($field->get('allowedImageTypes') ?: []);
		$ait->columnWidth = 50;
		$fs3->add($ait);

		$afe = $modules->get('InputfieldText');
		$afe->attr('name', 'allowedFileExtensions');
		$afe->label       = $this->_('Allowed file extensions');
		$afe->description = $this->_('Comma-separated. Leave empty to allow all non-executable extensions. Executable extensions are always blocked. Example: pdf,doc,xlsx,zip');
		$afe->attr('value', $field->get('allowedFileExtensions') ?: '');
		$afe->placeholder = 'pdf,doc,xlsx,zip';
		$afe->columnWidth = 50;
		$fs3->add($afe);

		$inputfields->add($fs3);

		// ── Output / rendering (collapsed) ───────────────────────────────

		$fs4 = $modules->get('InputfieldFieldset');
		$fs4->label     = $this->_('Output / rendering');
		$fs4->collapsed = Inputfield::collapsedYes;

		// ── Frontend editing ─────────────────────────────────────────────
		$fe = $modules->get('InputfieldCheckbox');
		$fe->attr('name', 'frontendEdit');
		$fe->label       = $this->_('Enable frontend editing');
		$fe->description = $this->_('Renders an editable Rapid editor on the frontend for authorized users.');
		$fe->attr('checked', (bool)$field->get('frontendEdit'));
		$fe->columnWidth = 34;
		$fs4->add($fe);

		$fp = $modules->get('InputfieldSelect');
		$fp->attr('name', 'frontendPermission');
		$fp->label = $this->_('Who can edit');
		foreach ($this->wire('permissions')->find('sort=name') as $perm) {
			$fp->addOption($perm->name, $perm->name);
		}
		$fp->addOption('page-edit',   'page-edit (default)');
		$fp->addOption('superuser',   'Superuser only');
		$fp->attr('value', $field->get('frontendPermission') ?: 'page-edit');
		$fp->columnWidth = 50;
		$fs4->add($fp);

		$fsu = $modules->get('InputfieldText');
		$fsu->attr('name', 'frontendSaveUrl');
		$fsu->label       = $this->_('Frontend save URL');
		$fsu->description = $this->_('URL path used by the frontend editor save hook.');
		$fsu->attr('value', $field->get('frontendSaveUrl') ?: '/rapid-save/');
		$fsu->placeholder = '/rapid-save/';
		$fsu->columnWidth = 50;
		$fsu->showIf = 'frontendEdit=1';
		$fs4->add($fsu);

		$fw = $modules->get('InputfieldSelect');
		$fw->attr('name', 'outputFramework');
		$fw->label       = $this->_('CSS framework');
		$fw->description = $this->_('Maps block output to framework classes instead of rapid-* classes.');
		$fw->addOption('vanilla',   'Vanilla (rapid-*)');
		$fw->addOption('tailwind',  'Tailwind CSS');
		$fw->addOption('bootstrap', 'Bootstrap 5');
		$fw->addOption('uikit',     'UIkit 3');
		$fw->attr('value', $field->get('outputFramework') ?: 'vanilla');
		$fw->columnWidth = 50;
		$fs4->add($fw);

		$css = $modules->get('InputfieldText');
		$css->attr('name', 'outputWrapClass');
		$css->label       = $this->_('Output wrapper CSS class');
		$css->description = $this->_('Added to the wrapping <div> around rendered blocks. Useful for scoped CSS.');
		$css->attr('value', $field->get('outputWrapClass') ?: '');
		$css->placeholder = 'e.g. prose prose-lg';
		$css->columnWidth = 50;
		$fs4->add($css);

		$inputfields->add($fs4);

		// ── Inline toolbar (collapsed) ────────────────────────────────────

		$fs5 = $modules->get('InputfieldFieldset');
		$fs5->label     = $this->_('Inline toolbar');
		$fs5->collapsed = Inputfield::collapsedYes;

		$it = $modules->get('InputfieldCheckboxes');
		$it->attr('name', 'inlineTools');
		$it->label         = $this->_('Visible inline tools');
		$it->description   = $this->_('Leave all unchecked to show all available inline tools.');
		$it->optionColumns = 3;
		$it->columnWidth   = 100;
		foreach ([
			'bold'          => 'Bold',
			'italic'        => 'Italic',
			'underline'     => 'Underline',
			'strikeThrough' => 'Strikethrough',
			'inlineCode'    => 'Inline code',
			'marker'        => 'Marker (highlight)',
			'link'          => 'Link',
		] as $key => $lbl) {
			$it->addOption($key, $lbl);
		}
		$it->value = (array)($field->get('inlineTools') ?: []);
		$fs5->add($it);

		$inputfields->add($fs5);

		return $inputfields;
	}

	// ── Internal ──────────────────────────────────────────────────────────

	private function newValue(Page $page, Field $field): RapidValue {
		$v = $this->wire(new RapidValue());
		$v->setPage($page);
		$v->setField($field);
		return $v;
	}
}
