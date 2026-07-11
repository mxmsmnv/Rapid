<?php namespace ProcessWire;

/**
 * ProcessRapidFrontend
 *
 * Frontend editing helper for Rapid fields.
 *
 * Save requests are handled via a URL hook at the configured endpoint
 * (default: /rapid-save/), so no admin URL is needed — the frontend
 * session cookie works normally.
 *
 * Template usage:
 *
 *   $editor = $modules->get('ProcessRapidFrontend');
 *   echo $editor->renderField($page, 'body');
 *
 *   // or manually:
 *   if ($editor->canEdit($page, 'body')) {
 *       echo $editor->editorFor($page, 'body');
 *   } else {
 *       echo $page->body;
 *   }
 */
class ProcessRapidFrontend extends WireData implements Module {
	const EDITOR_SAVE_MAX_BYTES = 2097152;

	public static function getModuleInfo(): array {
		return [
			'title'    => 'Rapid Frontend Editor',
			'version'  => 125,
			'summary'  => 'Provides frontend editing support for Rapid fields.',
			'author'   => 'Maxim Semenov',
			'href'     => 'https://smnv.org',
			'icon'     => 'bolt',
			'singular' => true,
			'autoload' => true,   // needed for URL hook
			'requires' => 'FieldtypeRapid',
		];
	}

	// ── Init: register URL hook ────────────────────────────────────────

	public function init(): void {
		// Register save hook on every non-admin request.
		// Hook fires when URL matches /rapid-save/ (or custom path).
		$this->addHookBefore('ProcessPageView::execute', $this, 'handleSaveRequest');
	}

	public function handleSaveRequest(HookEvent $event): void {
		$config = $this->wire('config');
		$input  = $this->wire('input');

		// Determine all registered save endpoints across all Rapid fields
		$saveUrls = [];
		foreach ($this->wire('fields')->find('type=FieldtypeRapid') as $field) {
			if ($field->get('frontendEdit')) {
				$url = $this->saveUrlForField($field);
				if ($url) $saveUrls[$url] = true;
			}
		}

		if (!$saveUrls) return;

		$page = $this->wire('page');
		$requestPath = '/' . ltrim(($page ? $page->url : null) ?: $input->url, '/');
		// Normalize: compare path without subdirectory prefix
		$rootUrl = rtrim($config->urls->root, '/');
		$path    = '/' . ltrim(substr($requestPath, strlen($rootUrl)), '/');
		if (!str_ends_with($path, '/')) $path .= '/';

		if (!isset($saveUrls[$path]) && !isset($saveUrls[rtrim($path, '/')])) return;

		// This is a save request — handle it
		header('Content-Type: application/json');

		if ($input->requestMethod() !== 'POST') {
			echo json_encode(['success' => false, 'error' => 'Method not allowed']);
			exit;
		}

		$pageId    = (int)$input->post('pageId');
		$fieldName = $this->wire('sanitizer')->fieldName((string)$input->post('fieldName'));
		$nonce     = (string)$input->post('nonce');
		$json      = (string)$input->post('data');

		if (!$pageId || !$fieldName || !$json) {
			echo json_encode(['success' => false, 'error' => 'Missing parameters']);
			exit;
		}
		if (strlen($json) > self::EDITOR_SAVE_MAX_BYTES) {
			echo json_encode(['success' => false, 'error' => 'Editor data is too large']);
			exit;
		}

		// Verify nonce
		if (!$this->verifyNonce($nonce, $pageId, $fieldName)) {
			echo json_encode(['success' => false, 'error' => 'Invalid nonce']);
			exit;
		}

		$page  = $this->wire('pages')->get($pageId);
		$field = $this->wire('fields')->get($fieldName);

		if (!$page || !$page->id || !$field || !($field->type instanceof FieldtypeRapid)) {
			echo json_encode(['success' => false, 'error' => 'Page or field not found']);
			exit;
		}

		if (!$this->canEdit($page, $fieldName)) {
			echo json_encode(['success' => false, 'error' => 'Permission denied']);
			exit;
		}

		$data = json_decode($json, true);
		if (!is_array($data) || !isset($data['blocks']) || !is_array($data['blocks'])) {
			echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
			exit;
		}

		try {
			$page->setOutputFormatting(false);
			$page->set($fieldName, $json);
			$page->save($fieldName);
			echo json_encode(['success' => true]);
		} catch (\Throwable $e) {
			$this->wire('log')->error('Rapid frontend save failed: ' . $e->getMessage(), ['name' => 'rapid']);
			echo json_encode(['success' => false, 'error' => 'Unable to save editor data']);
		}

		exit;
	}

	// ── Permission check ───────────────────────────────────────────────

	public function canEdit(Page $page, string $fieldName): bool {
		$user  = $this->wire('user');
		$field = $this->wire('fields')->get($fieldName);
		if (!$field || !($field->type instanceof FieldtypeRapid)) return false;
		if (!$this->pageHasField($page, $field)) return false;
		if (!(bool)$field->get('frontendEdit')) return false;

		$perm = (string)($field->get('frontendPermission') ?: 'page-edit');
		if ($perm === 'superuser') return $user->isSuperuser();

		return $user->isLoggedin() && ($user->hasPermission($perm, $page) || $user->hasPermission('page-edit', $page));
	}

	// ── Render helpers ─────────────────────────────────────────────────

	public function renderField(Page $page, string $fieldName): string {
		if ($this->canEdit($page, $fieldName)) return $this->editorFor($page, $fieldName);
		$value = $page->get($fieldName);
		return $value ? (string)$value : '';
	}

	public function editorFor(Page $page, string $fieldName): string {
		$field = $this->wire('fields')->get($fieldName);
		if (!$field || !($field->type instanceof FieldtypeRapid) || !$this->pageHasField($page, $field)) return '';

		$config    = $this->wire('config');
		$value     = $page->get($fieldName);
		$json      = $value instanceof RapidValue ? $value->toJSON() : (string)$value;

		$moduleUrl = $config->urls->Rapid;
		$adminUrl  = rtrim($config->urls->admin, '/');
		$uploadUrl = $adminUrl . '/setup/rapid-upload/upload/';
		$saveUrl   = rtrim((string)$config->urls->root, '/') . $this->saveUrlForField($field);
		$nonce     = $this->generateNonce((int)$page->id, $fieldName);
		$debounce  = (int)($field->get('autosaveDebounce') ?? 500);
		$minHeight = (int)($field->get('editorMinHeight') ?: 200);
		$bundleVer = trim(@file_get_contents($config->paths->Rapid . 'assets/js/dist/version.txt') ?: '1');

		// Absolutize image URLs for editor display only
		$editorData = json_decode($json, true) ?: ['blocks' => []];
		$base = ($config->https ? 'https' : 'http') . '://' . $config->httpHost;
		if (!empty($editorData['blocks'])) {
			foreach ($editorData['blocks'] as &$blk) {
				if (($blk['type'] ?? '') === 'image') {
					if (!empty($blk['data']['url']) && str_starts_with($blk['data']['url'], '/')) {
						$blk['data']['url'] = $base . $blk['data']['url'];
					}
					if (!empty($blk['data']['file']['url']) && str_starts_with($blk['data']['file']['url'], '/')) {
						$blk['data']['file']['url'] = $base . $blk['data']['file']['url'];
					}
				}
			}
			unset($blk);
		}

		$holderId  = 'rapid-fe-' . $page->id . '-' . $fieldName;

		$bootstrap = json_encode([
			'holderId' => $holderId,
			'valueId'  => $holderId . '-json',
			'data'     => $editorData,
			'config'   => [
				'uploadUrl'     => $uploadUrl,
				'pageId'        => (int)$page->id,
				'fieldName'     => $fieldName,
				'minHeight'     => $minHeight,
				'placeholder'   => 'Start writing…',
				'debounce'      => 0,   // no autosave on frontend — user clicks Save
				'allowedBlocks' => (array)($field->get('allowedBlocks') ?: []),
				'headerLevels'  => (array)($field->get('headerLevels') ?: []),
				'headerDefault' => (int)($field->get('headerDefaultLevel') ?: 2),
				'maxUploadMB'   => (int)($field->get('maxUploadSizeMB') ?: 10),
				'inlineTools'   => (array)($field->get('inlineTools') ?: []),
			],
			'pageId' => (int)$page->id,
		], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

		$saveConfig = json_encode([
			'saveUrl'   => $saveUrl,
			'pageId'    => (int)$page->id,
			'fieldName' => $fieldName,
			'nonce'     => $nonce,
			'holderId'  => $holderId,
		], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

		$fieldAttr = $this->h($fieldName);
		$holderAttr = $this->h($holderId);
		$out  = "<div class='rapid-frontend-editor' data-field='{$fieldAttr}'>";
		$out .= "  <div id='{$holderAttr}' class='rapid-holder' style='min-height:{$minHeight}px'></div>";
		$out .= "  <input type='hidden' id='{$holderAttr}-json' value='" . $this->h($json) . "'>";
		$out .= "  <div class='rapid-fe-toolbar'>";
		$out .= "    <button type='button' class='rapid-fe-save' data-config='" . $this->h($saveConfig) . "'>Save</button>";
		$out .= "    <span class='rapid-fe-status'></span>";
		$out .= "  </div>";
		$out .= "  <script>window.EJSQueue=window.EJSQueue||[];window.EJSQueue.push($bootstrap);</script>";
		$out .= "</div>";

		// Assets — only once per page load
		static $assetsOutput = false;
		if (!$assetsOutput) {
			$assetsOutput = true;
			$v = $bundleVer;
			$out = "<link rel='stylesheet' href='{$moduleUrl}assets/js/vendor/swiper.min.css'>"
			     . "<link rel='stylesheet' href='{$moduleUrl}assets/css/editor.css?v={$v}'>"
			     . "<link rel='stylesheet' href='{$moduleUrl}assets/css/frontend.css?v={$v}'>"
			     . "<script src='{$moduleUrl}assets/js/vendor/swiper.min.js'></script>"
			     . "<script src='{$moduleUrl}assets/js/dist/editor.js?v={$v}'></script>"
			     . "<script src='{$moduleUrl}assets/js/frontend.js?v={$v}' defer></script>"
			     . $out;
		}

		return $out;
	}

	// ── Nonce ──────────────────────────────────────────────────────────

	private function generateNonce(int $pageId, string $fieldName): string {
		$expires = time() + 3600;
		$payload = $pageId . ':' . $fieldName . ':' . (int)$this->wire('user')->id . ':' . $expires;
		$mac = hash_hmac('sha256', $payload, (string)($this->wire('config')->userAuthSalt ?: 'rapid'));
		return $expires . '.' . $mac;
	}

	private function saveUrlForField(Field $field): string {
		$url = trim((string)($field->get('frontendSaveUrl') ?: '/rapid-save/'));
		$url = '/' . ltrim($url ?: '/rapid-save/', '/');
		return str_ends_with($url, '/') ? $url : $url . '/';
	}

	private function pageHasField(Page $page, Field $field): bool {
		if (!$page->template || !$page->template->fieldgroup) return false;
		foreach ($page->template->fieldgroup as $templateField) if ((int)$templateField->id === (int)$field->id) return true;
		return false;
	}

	private function h($value): string {
		return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function verifyNonce(string $nonce, int $pageId, string $fieldName): bool {
		[$expires, $mac] = array_pad(explode('.', $nonce, 2), 2, '');
		if (!ctype_digit($expires) || (int)$expires < time() || (int)$expires > time() + 3700 || $mac === '') return false;
		$payload = $pageId . ':' . $fieldName . ':' . (int)$this->wire('user')->id . ':' . $expires;
		$expected = hash_hmac('sha256', $payload, (string)($this->wire('config')->userAuthSalt ?: 'rapid'));
		return hash_equals($expected, $mac);
	}

}
