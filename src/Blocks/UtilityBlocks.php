<?php namespace ProcessWire;

require_once __DIR__ . '/RapidBlockAbstract.php';

// ── Delimiter ─────────────────────────────────────────────────────────────

class RapidBlockDelimiter extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$attr = self::attr($block);
		return "<hr $attr>";
	}
}

// ── Warning ───────────────────────────────────────────────────────────────

class RapidBlockWarning extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$title   = self::plain($block['data']['title']   ?? '');
		$message = self::text($block['data']['message']  ?? '');
		$attr    = self::attr($block, ['rapid-warning']);
		$head    = $title ? "<p class=\"rapid-warning__title\">$title</p>" : '';
		return "<div $attr>$head<p class=\"rapid-warning__message\">$message</p></div>";
	}
	public static function toText(array $block): string {
		return strip_tags(($block['data']['title'] ?? '') . ' ' . ($block['data']['message'] ?? ''));
	}
}

// ── Checklist ─────────────────────────────────────────────────────────────

class RapidBlockChecklist extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$items = (array)($block['data']['items'] ?? []);
		$attr  = self::attr($block, ['rapid-checklist']);
		$html  = "<div $attr>";
		foreach ($items as $item) {
			$checked = !empty($item['checked']) ? ' checked' : '';
			$text    = self::text($item['text'] ?? '');
			$html   .= "<label class=\"rapid-checklist__item\">"
			         . "<input type=\"checkbox\"$checked disabled>"
			         . "<span>$text</span>"
			         . "</label>";
		}
		return $html . "</div>";
	}
	public static function toText(array $block): string {
		$parts = [];
		foreach ((array)($block['data']['items'] ?? []) as $item) {
			$parts[] = strip_tags($item['text'] ?? '');
		}
		return implode(' ', array_filter($parts));
	}
}

// ── Raw ───────────────────────────────────────────────────────────────────

class RapidBlockRaw extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		return self::sanitizeHtml((string)($block['data']['html'] ?? ''), true);
	}
}

// ── Attaches ─────────────────────────────────────────────────────────────

class RapidBlockAttaches extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$file = $block['data']['file'] ?? [];
		$url  = self::resolveUrl($file['url'] ?? '', $page);
		$name = self::plain($file['name']            ?? 'Download');
		$ext  = self::plain($file['extension']       ?? '');
		$size = self::formatSize((int)($file['size'] ?? 0));
		if (!$url) return '';
		$attr = self::attr($block, ['rapid-attaches']);
		$badge = $ext ? "<span class=\"rapid-attaches__ext\">$ext</span>" : '';
		$meta  = $size ? "<span class=\"rapid-attaches__size\">$size</span>" : '';
		return "<div $attr>"
		     . "<a href=\"$url\" class=\"rapid-attaches__link\" download>"
		     . $badge
		     . "<span class=\"rapid-attaches__name\">$name</span>"
		     . $meta
		     . "</a></div>";
	}
	public static function toText(array $block): string {
		return $block['data']['file']['name'] ?? '';
	}
	private static function formatSize(int $bytes): string {
		if ($bytes <= 0) return '';
		if ($bytes < 1024) return $bytes . ' B';
		if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
		return round($bytes / 1048576, 1) . ' MB';
	}
}

// ── Alert ─────────────────────────────────────────────────────────────────

class RapidBlockAlert extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$data    = $block['data'];
		$message = self::text($data['message'] ?? '');
		$type    = preg_replace('/[^a-z]/', '', strtolower($data['type'] ?? 'info'));
		if (!$message) return '';
		$attr = self::attr($block, ['rapid-alert', "rapid-alert--$type"]);
		return "<div $attr>$message</div>";
	}
	public static function toText(array $block): string {
		return strip_tags($block['data']['message'] ?? '');
	}
}

// ── LinkTool ──────────────────────────────────────────────────────────────

class RapidBlockLinkTool extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$data  = $block['data'];
		$link  = $data['link']  ?? '';
		$meta  = $data['meta']  ?? [];
		if (!$link) return '';

		$url   = self::safeUrl((string)$link, true);
		if ($url === '') return '';
		$title = self::plain($meta['title']       ?? $link);
		$desc  = self::plain($meta['description'] ?? '');
		$img   = self::safeUrl((string)($meta['image']['url'] ?? ''));
		$host  = htmlspecialchars(parse_url($link, PHP_URL_HOST) ?: $link);

		$thumb = $img ? "<div class=\"rapid-link__image\"><img src=\"$img\" alt=\"\" loading=\"lazy\"></div>" : '';
		$ddesc = $desc ? "<p class=\"rapid-link__desc\">$desc</p>" : '';

		$attr = self::attr($block, ['rapid-link']);
		return "<div $attr>"
		     . "<a href=\"$url\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"rapid-link__anchor\">"
		     . $thumb
		     . "<div class=\"rapid-link__body\">"
		     . "<p class=\"rapid-link__title\">$title</p>"
		     . $ddesc
		     . "<p class=\"rapid-link__host\">$host</p>"
		     . "</div>"
		     . "</a>"
		     . "</div>";
	}
	public static function toText(array $block): string {
		return strip_tags($block['data']['meta']['title'] ?? $block['data']['link'] ?? '');
	}
}
