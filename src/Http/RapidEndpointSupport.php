<?php namespace ProcessWire;

/** Shared validation and filesystem helpers for Rapid HTTP endpoints. */
trait RapidEndpointSupport {

	private function getMaxUploadBytes(?Field $field, int $defaultMB = 10): int {
		$mb = $field ? (int)($field->get('maxUploadSizeMB') ?: $defaultMB) : $defaultMB;
		$phpMax = min($this->toBytes(ini_get('upload_max_filesize')), $this->toBytes(ini_get('post_max_size')));
		return min($mb * 1048576, $phpMax);
	}

	private function toBytes(string $val): int {
		$val = trim($val);
		$num = (int)$val;
		return match(strtolower($val[-1] ?? '')) {
			'g' => $num * 1073741824,
			'm' => $num * 1048576,
			'k' => $num * 1024,
			default => $num,
		};
	}

	private function sanitizeFilename(string $name): string {
		$name = basename($name);
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$base = trim((string)preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($name, PATHINFO_FILENAME)), '_') ?: 'file';
		return $base . '.' . $ext;
	}

	private function rapidFieldForRequest(Page $page): ?Field {
		$input = $this->wire('input');
		$name = $this->wire('sanitizer')->fieldName((string)($input->post('fieldName') ?: $input->get('fieldName')));
		if ($name === '') return null;
		$field = $this->wire('fields')->get($name);
		return $field instanceof Field && $field->type instanceof FieldtypeRapid && $this->pageHasField($page, $field) ? $field : null;
	}

	private function verifyFrontendNonce(string $nonce, int $pageId, string $fieldName): bool {
		[$expires, $mac] = array_pad(explode('.', $nonce, 2), 2, '');
		if (!ctype_digit($expires) || (int)$expires < time() || (int)$expires > time() + 3700 || $mac === '') return false;
		$payload = $pageId . ':' . $fieldName . ':' . (int)$this->wire('user')->id . ':' . $expires;
		$expected = hash_hmac('sha256', $payload, (string)($this->wire('config')->userAuthSalt ?: 'rapid'));
		return hash_equals($expected, $mac);
	}

	private function normalizeExtensionList(string $raw): array {
		return array_values(array_filter(array_map(static fn(string $ext): string => ltrim(trim(strtolower($ext)), '.'), explode(',', $raw))));
	}

	private function pageHasField(Page $page, Field $field): bool {
		if (!$page->template || !$page->template->fieldgroup) return false;
		foreach ($page->template->fieldgroup as $templateField) if ((int)$templateField->id === (int)$field->id) return true;
		return false;
	}

	private function uniqueFilename(string $dir, string $name): string {
		if (!file_exists($dir . $name)) return $name;
		$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$base = pathinfo($name, PATHINFO_FILENAME);
		$i = 1;
		do { $name = $base . '_' . $i++ . '.' . $ext; } while (file_exists($dir . $name));
		return $name;
	}

	private function sanitizeSvgFile(string $path): bool {
		if (!class_exists('\\DOMDocument')) return false;
		$doc = new \DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$loaded = $doc->load($path, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if (!$loaded || !$doc->documentElement || strtolower($doc->documentElement->tagName) !== 'svg') return false;
		$this->sanitizeSvgNode($doc->documentElement);
		return (bool)$doc->save($path);
	}

	private function sanitizeSvgNode(\DOMNode $node): void {
		$blocked = ['script','foreignobject','iframe','object','embed','link','style'];
		if ($node instanceof \DOMElement) {
			if (in_array(strtolower($node->tagName), $blocked, true)) { $node->parentNode?->removeChild($node); return; }
			foreach (iterator_to_array($node->attributes) as $attr) {
				$name = strtolower($attr->nodeName);
				$value = trim(strtolower($attr->nodeValue));
				if (str_starts_with($name, 'on') || $name === 'style' || (in_array($name, ['href','xlink:href','src'], true) && $value !== '' && !str_starts_with($value, '#'))) $node->removeAttributeNode($attr);
			}
		}
		foreach (iterator_to_array($node->childNodes) as $child) $this->sanitizeSvgNode($child);
	}

	private function filesHttpUrl(PagefilesManager $fm): string {
		$config = $this->wire('config');
		return ($config->https ? 'https' : 'http') . '://' . $config->httpHost . $fm->url();
	}

	private function jsonError(string $msg, int $code = 0): string {
		ob_end_clean();
		return json_encode(['success' => 0, 'error' => $msg, 'code' => $code]);
	}
}
