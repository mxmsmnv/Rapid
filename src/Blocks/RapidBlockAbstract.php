<?php namespace ProcessWire;

/**
 * RapidBlockAbstract — base class for all Editor.js block renderers.
 */
abstract class RapidBlockAbstract {

	abstract public static function render(array $block, ?Page $page): string;

	public static function toText(array $block): string { return ''; }

	protected static function text(string $raw): string {
		return self::sanitizeHtml($raw, false);
	}

	protected static function plain(string $raw): string {
		return htmlspecialchars(strip_tags($raw), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/** Sanitize Editor.js rich text (or the broader Raw block subset). */
	protected static function sanitizeHtml(string $raw, bool $rawBlock = false): string {
		if ($raw === '') return '';
		$allowed = $rawBlock
			? ['a','abbr','b','blockquote','br','caption','code','col','colgroup','dd','del','details','div','dl','dt','em','figcaption','figure','h1','h2','h3','h4','h5','h6','hr','i','ins','li','mark','ol','p','pre','small','span','strong','sub','summary','sup','table','tbody','td','tfoot','th','thead','tr','u','ul']
			: ['a','b','br','code','del','em','i','ins','mark','span','strong','sub','sup','u'];

		if (!class_exists('\\DOMDocument')) {
			return strip_tags(htmlspecialchars_decode($raw, ENT_QUOTES), '<' . implode('><', $allowed) . '>');
		}

		$doc = new \DOMDocument('1.0', 'UTF-8');
		$previous = libxml_use_internal_errors(true);
		$loaded = $doc->loadHTML('<?xml encoding="utf-8" ?><div id="rapid-root">' . $raw . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if (!$loaded) return self::plain($raw);

		$root = $doc->getElementById('rapid-root');
		if (!$root) return self::plain($raw);
		self::sanitizeNode($root, $allowed);
		$html = '';
		foreach ($root->childNodes as $child) $html .= $doc->saveHTML($child);
		return $html;
	}

	private static function sanitizeNode(\DOMNode $node, array $allowed): void {
		foreach (iterator_to_array($node->childNodes) as $child) {
			if (!$child instanceof \DOMElement) continue;
			$tag = strtolower($child->tagName);
			if (!in_array($tag, $allowed, true)) {
				if (in_array($tag, ['script','style','iframe','object','embed','svg','math','template'], true)) {
					$child->parentNode?->removeChild($child);
					continue;
				}
				self::sanitizeNode($child, $allowed);
				while ($child->firstChild) $child->parentNode?->insertBefore($child->firstChild, $child);
				$child->parentNode?->removeChild($child);
				continue;
			}
			foreach (iterator_to_array($child->attributes) as $attr) {
				$name = strtolower($attr->nodeName);
				if ($tag === 'a' && in_array($name, ['href','title','target','rel'], true)) continue;
				if (in_array($name, ['class','colspan','rowspan'], true)) continue;
				$child->removeAttributeNode($attr);
			}
			if ($tag === 'a') {
				$href = $child->getAttribute('href');
				if ($href !== '' && !self::isSafeUrl($href, true)) $child->removeAttribute('href');
				if ($child->getAttribute('target') === '_blank') $child->setAttribute('rel', 'noopener noreferrer');
				else $child->removeAttribute('target');
			}
			self::sanitizeNode($child, $allowed);
		}
	}

	protected static function isSafeUrl(string $url, bool $allowMail = false): bool {
		$url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
		if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, './') || str_starts_with($url, '../')) return true;
		$scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
		$allowed = $allowMail ? ['http','https','mailto','tel'] : ['http','https'];
		return in_array($scheme, $allowed, true);
	}

	protected static function safeUrl(string $url, bool $allowMail = false): string {
		return self::isSafeUrl($url, $allowMail)
			? htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
			: '';
	}

	protected static function attr(array $block, array $classes = [], array $styles = []): string {
		return RapidAttr::render($block['tunes'] ?? [], $classes, $styles, $block['_fwClasses'] ?? []);
	}

	/**
	 * Resolve a file URL for use in HTML output.
	 *
	 * Always returns root-relative URLs so they work regardless of domain
	 * (lqrs.com, lqrs.pw, localhost — same result).
	 *
	 * Handles three input formats:
	 *   - https://example.com/path/file.jpg  → as-is (external URL)
	 *   - /site/assets/files/123/file.jpg    → root-relative, use as-is
	 *   - file.jpg                           → prepend page filesManager url
	 */
	protected static function resolveUrl(string $url, ?Page $page): string {
		if (empty($url)) return '';

		if (!self::isSafeUrl($url)) return '';

		// Already an absolute external URL — leave unchanged
		if (preg_match('#^https?://#', $url)) {
			return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}

		// Already root-relative — use as-is
		if (str_starts_with($url, '/')) {
			return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}

		// Bare filename — prepend page files URL
		if ($page && $page->id) {
			$filesUrl = rtrim($page->filesManager()->url(), '/') . '/';
			return htmlspecialchars($filesUrl . $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}

		return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
