<?php namespace ProcessWire;

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Http/RapidEndpointSupport.php';

/**
 * ProcessFieldtypeRapid
 *
 * Image and file upload API endpoints for the Rapid block editor.
 *
 * POST {adminUrl}/setup/rapid-upload/upload/ — image upload (@editorjs/image)
 * POST {adminUrl}/setup/rapid-upload/attach/ — file upload (@editorjs/attaches)
 */
class ProcessFieldtypeRapid extends Process {
	use RapidEndpointSupport;

	const PAGE_NAME = 'rapid-upload';
	const LINK_PREVIEW_MAX_BYTES = 1048576;
	const EDITOR_SAVE_MAX_BYTES = 2097152;
	const BLOCKED_ATTACHMENT_EXTENSIONS = ['php','phtml','php3','php4','php5','php7','php8','phar','cgi','pl','py','rb','sh','bash','zsh','fish','exe','dll','com','bat','cmd','msi','scr','jar','htaccess','config'];

	public static function getModuleInfo(): array {
		return [
			'title'      => 'Rapid Upload',
			'version'    => 125,
			'summary'    => 'Image and file upload API for the Rapid block editor.',
			'author'     => 'Maxim Semenov',
			'href'       => 'https://smnv.org',
			'icon'       => 'bolt',
			'requires'   => 'FieldtypeRapid',
			'permission' => 'page-edit',
		];
	}

	public function ___execute(): string { return ''; }

	// ── Install / Uninstall ─────────────────────────────────────────────────

	public function ___install(): void {
		$pages  = $this->wire('pages');
		$admin  = $pages->get($this->wire('config')->adminRootPageID);
		$setup  = $pages->get('name=setup, parent=' . $admin->id . ', include=all');
		$parent = $setup->id ? $setup : $admin;

		$exists = $pages->get('name=' . self::PAGE_NAME . ', parent=' . $parent->id . ', include=all');
		if (!$exists->id) {
			$p           = new Page();
			$p->template = 'admin';
			$p->parent   = $parent;
			$p->name     = self::PAGE_NAME;
			$p->title    = 'Rapid Upload';
			$p->process  = $this;
			$p->addStatus(Page::statusHidden);
			$p->save();
		} elseif (!$exists->isHidden()) {
			// Ensure page stays hidden even if it was made visible
			$exists->addStatus(Page::statusHidden);
			$exists->save();
		}
	}

	public function ___uninstall(): void {
		$page = $this->wire('pages')->get('name=' . self::PAGE_NAME . ', include=all');
		if ($page->id) $this->wire('pages')->delete($page);
	}

	// ── Upload: images ────────────────────────────────────────────────────

	/**
	 * POST {adminUrl}/setup/rapid-upload/upload/
	 * Used by @editorjs/image.
	 * Returns: { "success": 1, "file": { "url": "...", "name": "..." } }
	 */
	public function ___executeUpload(): string {
		ob_start();
		header('Content-Type: application/json');

		if ($this->wire('input')->requestMethod() !== 'POST') {
			ob_end_clean();
			return $this->jsonError('Method not allowed');
		}

		$user = $this->wire('user');
		if (!$user->isLoggedin() || !$user->hasPermission('page-edit')) {
			return $this->jsonError('Forbidden');
		}

		$pageId = (int) $this->wire('input')->post('pageId');
		$page   = $pageId ? $this->wire('pages')->get($pageId) : null;
		$field  = $page ? $this->rapidFieldForRequest($page) : null;

		if (!$page || !$page->id) return $this->jsonError('Invalid page');
		if (!$page->editable())   return $this->jsonError('Page not editable');
		if (!$field)              return $this->jsonError('Invalid Rapid field');
		if (empty($_FILES['image'])) return $this->jsonError('No file received');

		$upload = $_FILES['image'];

		if ($upload['error'] !== UPLOAD_ERR_OK) {
			return $this->jsonError('Upload error: ' . $upload['error']);
		}

		$finfo    = new \finfo(FILEINFO_MIME_TYPE);
		$mimeType = $finfo->file($upload['tmp_name']);

		// Get allowed types from field config (fallback to safe defaults)
		$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
		$configured = (array)($field->get('allowedImageTypes') ?: []);
		if ($configured) $allowedMimes = $configured;
		// SVG always requires extra sanitization — skip unless explicitly allowed
		if ($mimeType === 'image/svg+xml' && !in_array('image/svg+xml', $allowedMimes, true)) {
			return $this->jsonError('SVG not allowed');
		}
		if (!in_array($mimeType, $allowedMimes, true)) {
			return $this->jsonError('File type not allowed: ' . $mimeType);
		}

		$mimeToExt = ['image/jpeg' => ['jpg','jpeg'], 'image/png' => ['png'], 'image/gif' => ['gif'], 'image/webp' => ['webp'], 'image/svg+xml' => ['svg']];
		$validExts = $mimeToExt[$mimeType] ?? [];
		$ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
		if ($validExts && !in_array($ext, $validExts, true)) {
			return $this->jsonError('Extension does not match file type');
		}

		$maxBytes = $this->getMaxUploadBytes($field);
		if ($upload['size'] > $maxBytes) {
			return $this->jsonError('File too large (max ' . round($maxBytes / 1048576) . 'MB)');
		}

		$fm       = $page->filesManager();
		$fm->path();
		$safeName = $this->uniqueFilename($fm->path(), $this->sanitizeFilename($upload['name']));

		$target = $fm->path() . $safeName;
		if (!move_uploaded_file($upload['tmp_name'], $target)) {
			return $this->jsonError('Failed to save file');
		}
		if ($mimeType === 'image/svg+xml' && !$this->sanitizeSvgFile($target)) {
			@unlink($target);
			return $this->jsonError('Invalid SVG file');
		}

		ob_end_clean();
		return json_encode([
			'success' => 1,
			'file'    => ['url' => $this->filesHttpUrl($fm) . $safeName, 'name' => $safeName],
		]);
	}

	// ── Upload: files ─────────────────────────────────────────────────────

	/**
	 * POST {adminUrl}/setup/rapid-upload/attach/
	 * Used by @editorjs/attaches.
	 * Returns: { "success": 1, "file": { "url": "...", "name": "...", "size": N, "extension": "..." } }
	 */
	public function ___executeAttach(): string {
		ob_start();
		header('Content-Type: application/json');

		if ($this->wire('input')->requestMethod() !== 'POST') {
			ob_end_clean();
			return $this->jsonError('Method not allowed');
		}

		$user = $this->wire('user');
		if (!$user->isLoggedin() || !$user->hasPermission('page-edit')) {
			return $this->jsonError('Forbidden');
		}

		// pageId comes as GET param for attaches (AttachesTool doesn't support additionalRequestData)
		$input  = $this->wire('input');
		$pageId = (int)($input->post('pageId') ?: $input->get('pageId'));
		$page   = $pageId ? $this->wire('pages')->get($pageId) : null;

		if (!$page || !$page->id || !$page->editable()) {
			return $this->jsonError('Invalid page');
		}
		$field = $this->rapidFieldForRequest($page);
		if (!$field) return $this->jsonError('Invalid Rapid field');

		if (empty($_FILES['file'])) return $this->jsonError('No file received');

		$upload = $_FILES['file'];

		if ($upload['error'] !== UPLOAD_ERR_OK) {
			return $this->jsonError('Upload error: ' . $upload['error']);
		}

		$maxBytes = $this->getMaxUploadBytes($field, 50);
		if ($upload['size'] > $maxBytes) {
			return $this->jsonError('File too large (max ' . round($maxBytes / 1048576) . 'MB)');
		}

		// Check allowed file extensions from field config
		$ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
		if (!$ext || in_array($ext, self::BLOCKED_ATTACHMENT_EXTENSIONS, true)) {
			return $this->jsonError('File type .' . ($ext ?: 'unknown') . ' is not allowed');
		}
		$allowedExts = [];
		$raw = trim((string)($field->get('allowedFileExtensions') ?: ''));
		if ($raw) $allowedExts = $this->normalizeExtensionList($raw);
		if ($allowedExts && !in_array($ext, array_values($allowedExts), true)) {
			return $this->jsonError('File type .' . $ext . ' is not allowed');
		}

		$fm       = $page->filesManager();
		$fm->path();
		$safeName = $this->uniqueFilename($fm->path(), $this->sanitizeFilename($upload['name']));

		if (!move_uploaded_file($upload['tmp_name'], $fm->path() . $safeName)) {
			return $this->jsonError('Failed to save file');
		}

		ob_end_clean();
		return json_encode([
			'success' => 1,
			'file'    => [
				'url'       => $this->filesHttpUrl($fm) . $safeName,
				'name'      => $safeName,
				'size'      => $upload['size'],
				'extension' => strtolower(pathinfo($safeName, PATHINFO_EXTENSION)),
			],
		]);
	}



/**
	 * Route: {adminUrl}/setup/rapid-upload/link/
	 * Used by @editorjs/link — fetches OpenGraph metadata for a URL.
	 */
	public function ___executeLink(): string {
		header('Content-Type: application/json');

		$user = $this->wire('user');
		if (!$user->isLoggedin() || !$user->hasPermission('page-edit')) {
			return json_encode(['success' => 0, 'error' => 'Forbidden']);
		}

		$url = (string)$this->wire('input')->get('url');
		if (!$this->isAllowedRemoteUrl($url)) {
			return json_encode(['success' => 0, 'error' => 'Invalid URL']);
		}

		$ctx  = stream_context_create(['http' => [
			'timeout'       => 5,
			'user_agent'    => 'Mozilla/5.0 (compatible; RapidLinkTool/1.0)',
			'ignore_errors' => true,
			'follow_location'=> 0,
			'max_redirects' => 0,
		]]);
		$html = @file_get_contents($url, false, $ctx, 0, self::LINK_PREVIEW_MAX_BYTES);
		if (!$html) return json_encode(['success' => 0, 'error' => 'Could not fetch URL']);

		$title = $desc = $image = '';

		if (preg_match('#<title[^>]*>([^<]+)</title>#i', $html, $m)) {
			$title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
		}
		if (preg_match('#property=.og:title.[^>]+content=.([^"\'<>]+)#i', $html, $m) ||
		    preg_match('#content=.([^"\'<>]+).[^>]+property=.og:title.#i', $html, $m)) {
			$title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
		}
		if (preg_match('#name=.description.[^>]+content=.([^"\'<>]+)#i', $html, $m) ||
		    preg_match('#content=.([^"\'<>]+).[^>]+name=.description.#i', $html, $m)) {
			$desc = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
		}
		if (preg_match('#property=.og:description.[^>]+content=.([^"\'<>]+)#i', $html, $m) ||
		    preg_match('#content=.([^"\'<>]+).[^>]+property=.og:description.#i', $html, $m)) {
			$desc = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
		}
		if (preg_match('#property=.og:image.[^>]+content=.([^"\'<>]+)#i', $html, $m) ||
		    preg_match('#content=.([^"\'<>]+).[^>]+property=.og:image.#i', $html, $m)) {
			$image = trim($m[1]);
		}

		$meta = ['title' => $title ?: (string)parse_url($url, PHP_URL_HOST)];
		if ($desc)  $meta['description'] = $desc;
		if ($image && $this->isAllowedRemoteUrl($image)) $meta['image'] = ['url' => $image];

		return json_encode(['success' => 1, 'meta' => $meta]);
	}

	private function isAllowedRemoteUrl(string $url): bool {
		if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) return false;
		if (!in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http','https'], true)) return false;
		$host = trim(strtolower((string)parse_url($url, PHP_URL_HOST)), '[]');
		if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) return false;
		$ips = [];
		if (filter_var($host, FILTER_VALIDATE_IP)) $ips[] = $host;
		else {
			foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
				if (!empty($record['ip'])) $ips[] = $record['ip'];
				if (!empty($record['ipv6'])) $ips[] = $record['ipv6'];
			}
		}
		if (!$ips) return false;
		foreach (array_unique($ips) as $ip) {
			if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
		}
		return true;
	}


	/**
	 * POST {adminUrl}/setup/rapid-upload/save/
	 * Frontend save endpoint for ProcessRapidFrontend.
	 */
	public function ___executeSave(): string {
		ob_start();
		header('Content-Type: application/json');

		if ($this->wire('input')->requestMethod() !== 'POST') {
			ob_end_clean();
			return json_encode(['success' => false, 'error' => 'Method not allowed']);
		}

		$input     = $this->wire('input');
		$pageId    = (int)$input->post('pageId');
		$fieldName = $this->wire('sanitizer')->fieldName((string)$input->post('fieldName'));
		$nonce     = (string)$input->post('nonce');
		$json      = (string)$input->post('data');

		if (!$pageId || !$fieldName || !$json) {
			ob_end_clean();
			return json_encode(['success' => false, 'error' => 'Missing parameters']);
		}
		if (strlen($json) > self::EDITOR_SAVE_MAX_BYTES) {
			ob_end_clean();
			return json_encode(['success' => false, 'error' => 'Editor data is too large']);
		}

		// Verify nonce
		if (!$this->verifyFrontendNonce($nonce, $pageId, $fieldName)) {
			ob_end_clean();
			return json_encode(['success' => false, 'error' => 'Invalid nonce']);
		}

		$page  = $this->wire('pages')->get($pageId);
		$field = $this->wire('fields')->get($fieldName);

		if (!$page || !$page->id || !$field || !($field->type instanceof FieldtypeRapid)) {
			ob_end_clean();
			return json_encode(['success' => false, 'error' => 'Page or field not found']);
		}
		if (!$this->pageHasField($page, $field)) {
			ob_end_clean();
			return json_encode(['success' => false, 'error' => 'Field is not assigned to this page template']);
		}

		// Permission check
		$user = $this->wire('user');
		$perm = (string)($field->get('frontendPermission') ?: 'page-edit');
		$canEdit = $perm === 'superuser'
			? $user->isSuperuser()
			: ($user->isLoggedin() && ($user->hasPermission($perm, $page) || $user->hasPermission('page-edit', $page)));

		if (!$canEdit) {
			ob_end_clean();
			return json_encode(['success' => false, 'error' => 'Permission denied']);
		}

		$data = json_decode($json, true);
		if (!is_array($data) || !isset($data['blocks']) || !is_array($data['blocks'])) {
			ob_end_clean();
			return json_encode(['success' => false, 'error' => 'Invalid JSON']);
		}

		try {
			$page->setOutputFormatting(false);
			$page->set($fieldName, $json);
			$page->save($fieldName);
		} catch (\Throwable $e) {
			$this->wire('log')->error('Rapid save failed: ' . $e->getMessage(), ['name' => 'rapid']);
			ob_end_clean();
			return json_encode(['success' => false, 'error' => 'Unable to save editor data']);
		}

		ob_end_clean();
		return json_encode(['success' => true]);
	}

}
