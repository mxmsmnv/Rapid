<?php namespace ProcessWire;

require_once __DIR__ . '/src/bootstrap.php';

/**
 * InputfieldRapid
 *
 * Admin Inputfield for FieldtypeRapid.
 * Loads a vendored Editor.js bundle — no CDN, no build step for end users.
 * Developers: run `cd src/js && npm install && npm run build` to update the bundle.
 */
class InputfieldRapid extends Inputfield {

	public static function getModuleInfo(): array {
		return [
			'title'   => 'Rapid Input',
			'version' => 125,
			'summary' => 'Admin Inputfield for Rapid. Uses vendored dist/editor.js.',
			'icon'    => 'bolt',
			'author'  => 'Maxim Semenov',
			'href'    => 'https://smnv.org',
			'requires' => 'FieldtypeRapid',
		];
	}

	protected ?Field $field = null;

	public function setField(Field $f): void { $this->field = $f; }

	// ── Render ────────────────────────────────────────────────────────────

	public function ___render(): string {
		$this->loadAssets();

		$id    = $this->attr('id');
		$name  = $this->attr('name');
		$value = $this->attr('value');

		if ($value instanceof RapidValue) {
			$json = $value->toJSON();
		} elseif (is_string($value) && $value !== '') {
			$decoded = json_decode($value, true);
			$json    = is_array($decoded) ? $value : json_encode(['blocks' => []]);
		} else {
			$json = json_encode(['blocks' => []]);
		}

		// Normalize image blocks: promote file.url → url so Editor.js renders them correctly
		$jsonData = json_decode($json, true);
		if (!empty($jsonData['blocks'])) {
			foreach ($jsonData['blocks'] as &$block) {
				if (($block['type'] ?? '') === 'image' && empty($block['data']['url']) && !empty($block['data']['file']['url'])) {
					$block['data']['url'] = $block['data']['file']['url'];
				}
			}
			unset($block);
			$json = json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		$field         = $this->field;
		$allowedBlocks = $field ? (array)($field->get('allowedBlocks')       ?: []) : [];
		$minHeight     = $field ? (int)($field->get('editorMinHeight')        ?: 200) : 200;
		$placeholder   = $field ? (string)($field->get('editorPlaceholder')   ?: '') : '';
		$debounce      = $field ? (int)($field->get('autosaveDebounce')       ?? 300) : 300;
		$maxUploadMB   = $field ? (int)($field->get('maxUploadSizeMB')        ?: 10) : 10;
		$inlineTools   = $field ? (array)($field->get('inlineTools')          ?: []) : [];
		$headerLevels  = $field ? (array)($field->get('headerLevels')         ?: []) : [];
		$headerDefault = $field ? (int)($field->get('headerDefaultLevel')     ?: 2) : 2;
		$editorAlign      = $field ? (string)($field->get('editorAlign')         ?: 'full') : 'full';
		$toolbarPosition  = $field ? (string)($field->get('toolbarPosition')    ?: 'left') : 'left';

		// Build upload URL
		$adminUrl  = rtrim($this->wire('config')->urls->admin, '/');
		$uploadUrl = $adminUrl . '/setup/rapid-upload/upload/';

		// Resolve ID of the page being edited (not the admin page)
		$pageId = 0;
		$input  = $this->wire('input');
		if ($input && (int)$input->get('id')) {
			$pageId = (int)$input->get('id');
		} elseif ($this->hasPage && $this->hasPage->id) {
			$pageId = (int)$this->hasPage->id;
		}

		$config = json_encode([
			'allowedBlocks' => $allowedBlocks,
			'minHeight'     => $minHeight,
			'placeholder'   => $placeholder ?: 'Start writing…',
			'debounce'      => $debounce,
			'maxUploadMB'   => $maxUploadMB,
			'inlineTools'   => $inlineTools,
			'headerLevels'  => $headerLevels  ?: [2, 3, 4],
			'headerDefault' => $headerDefault,
			'uploadUrl'     => $uploadUrl,
			'pageId'        => $pageId,
			'fieldName'     => $field ? (string)$field->name : '',
			'toolbarPosition' => $toolbarPosition,
			'i18n'            => $this->getI18n(),
		], JSON_UNESCAPED_UNICODE);

		// For editor display only: convert root-relative image URLs to absolute
		// This does NOT affect stored data — only what the JS editor receives
		$editorData = json_decode($json, true) ?: ['blocks' => []];
		$pwConfig   = $this->wire('config');
		$baseUrl    = ($pwConfig->https ? 'https' : 'http') . '://' . $pwConfig->httpHost;
		if (!empty($editorData['blocks'])) {
			foreach ($editorData['blocks'] as &$blk) {
				if (($blk['type'] ?? '') === 'image') {
					foreach (['url', 'file'] as $k) {
						if ($k === 'url' && !empty($blk['data']['url']) && str_starts_with($blk['data']['url'], '/')) {
							$blk['data']['url'] = $baseUrl . $blk['data']['url'];
						}
						if ($k === 'file' && !empty($blk['data']['file']['url']) && str_starts_with($blk['data']['file']['url'], '/')) {
							$blk['data']['file']['url'] = $baseUrl . $blk['data']['file']['url'];
						}
					}
				}
			}
			unset($blk);
		}

		$bootstrap = json_encode([
			'holderId' => "{$id}-holder",
			'valueId'  => $id,
			'data'     => $editorData,
			'config'   => json_decode($config, true),
			'pageId'   => $pageId,
		], JSON_UNESCAPED_UNICODE);

		// Editor alignment styles
		$alignStyle = match($editorAlign) {
			'left'   => 'max-width:680px;margin-right:auto;',
			'center' => 'max-width:680px;margin-left:auto;margin-right:auto;',
			default  => 'width:100%;',
		};

		// CSS class for full-width mode (removes Editor.js internal max-width)
		$alignClass    = $editorAlign === 'full' ? ' rapid-holder--full' : '';
		$toolbarClass  = $toolbarPosition === 'right' ? ' rapid-holder--toolbar-right' : '';

		$out  = "<div class='InputfieldRapid-wrap'>";
		$out .= "  <div id='{$id}-holder' class='rapid-holder{$alignClass}{$toolbarClass}' style='min-height:{$minHeight}px;{$alignStyle}'></div>";
		$out .= "  <textarea id='{$id}' name='{$name}' class='rapid-json' style='display:none'>" . htmlspecialchars($json) . "</textarea>";
		$out .= "  <script>window.EJSQueue=window.EJSQueue||[];window.EJSQueue.push($bootstrap);</script>";
		$out .= "</div>";

		return $out;
	}

	public function ___renderValue(): string {
		$value = $this->attr('value');
		if ($value instanceof RapidValue) return $value->render();
		if (is_string($value) && $value !== '') return RapidRenderer::fromJSON($value);
		return '';
	}

	// ── Value handling ────────────────────────────────────────────────────

	public function ___processInput(WireInputData $input) {
		parent::___processInput($input);
		$name = $this->attr('name');
		$raw  = $input->$name;
		if ($raw !== null) $this->setAttribute('value', (string)$raw);
		return $this;
	}

	// ── Assets ───────────────────────────────────────────────────────────

	protected function loadAssets(): void {
		$config  = $this->wire('config');
		$url     = $config->urls->get('Rapid');
		$distDir = $config->paths->get('Rapid') . 'assets/js/dist/';

		// Cache-bust via content hash written by esbuild.mjs
		$versionFile = $distDir . 'version.txt';
		$v           = is_readable($versionFile) ? trim(file_get_contents($versionFile)) : '1';

		$config->scripts->add($url . "assets/js/dist/editor.js?v=$v");
		$config->styles->add($url  . "assets/css/editor.css?v=$v");
	}

	// ── i18n ─────────────────────────────────────────────────────────────

	protected function getI18n(): array {
		return [
			'ui' => [
				'toolbar' => [
					'toolbox' => ['Add' => $this->_('Add block')],
				],
				'blockTunes' => [
					'toggler' => ['Click to tune' => $this->_('Tune or move')],
				],
				'inlineToolbar' => [
					'converter' => ['Convert to' => $this->_('Convert to')],
				],
			],
			'blockTunes' => [
				'delete'   => [
					'Delete'          => $this->_('Delete'),
					'Click to delete' => $this->_('Click to delete'),
				],
				'moveUp'   => ['Move up'   => $this->_('Move up')],
				'moveDown' => ['Move down' => $this->_('Move down')],
			],
		];
	}
}
