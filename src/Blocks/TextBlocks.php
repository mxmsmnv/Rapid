<?php namespace ProcessWire;

require_once __DIR__ . '/RapidBlockAbstract.php';

// ── Paragraph ─────────────────────────────────────────────────────────────

class RapidBlockParagraph extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$text    = self::text($block['data']['text'] ?? '');
		$classes = !empty($block['data']['large']) ? ['rapid-paragraph--large'] : [];
		return "<p " . self::attr($block, $classes) . ">$text</p>";
	}
	public static function toText(array $block): string {
		return strip_tags($block['data']['text'] ?? '');
	}
}
// ── Header ────────────────────────────────────────────────────────────────

class RapidBlockHeader extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$level = max(1, min(6, (int)($block['data']['level'] ?? 2)));
		$text  = self::text($block['data']['text'] ?? '');
		// Auto-generate anchor ID from text if not set via tunes
		if (empty($block['tunes']['id'] ?? null)) {
			$block['tunes']['id'] = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower(strip_tags($text))), '-');
		}
		return "<h{$level} " . self::attr($block) . ">$text</h{$level}>";
	}
	public static function toText(array $block): string {
		return strip_tags($block['data']['text'] ?? '');
	}
}

// ── Quote ─────────────────────────────────────────────────────────────────

class RapidBlockQuote extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$text    = self::text($block['data']['text']    ?? '');
		$caption = self::text($block['data']['caption'] ?? '');
		$cap     = $caption ? "<figcaption>$caption</figcaption>" : '';
		return "<blockquote " . self::attr($block) . "><figure><p>$text</p>$cap</figure></blockquote>";
	}
	public static function toText(array $block): string {
		return strip_tags(($block['data']['text'] ?? '') . ' ' . ($block['data']['caption'] ?? ''));
	}
}

// ── NestedList ────────────────────────────────────────────────────────────

class RapidBlockNestedList extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$tag   = ($block['data']['style'] ?? 'unordered') === 'ordered' ? 'ol' : 'ul';
		$inner = self::items((array)($block['data']['items'] ?? []), $tag);
		return "<div " . self::attr($block) . ">$inner</div>";
	}
	public static function toText(array $block): string {
		return self::itemsText((array)($block['data']['items'] ?? []));
	}
	private static function items(array $items, string $tag): string {
		if (!$items) return '';
		$h = "<$tag>";
		foreach ($items as $i) {
			$children = !empty($i['items']) ? self::items($i['items'], $tag) : '';
			$h .= '<li>' . self::text($i['content'] ?? '') . $children . '</li>';
		}
		return $h . "</$tag>";
	}
	private static function itemsText(array $items): string {
		$p = [];
		foreach ($items as $i) {
			$p[] = strip_tags($i['content'] ?? '');
			if (!empty($i['items'])) $p[] = self::itemsText($i['items']);
		}
		return implode(' ', array_filter($p));
	}
}

// ── Table ─────────────────────────────────────────────────────────────────

class RapidBlockTable extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$rows = (array)($block['data']['content'] ?? []);
		$h    = "<div " . self::attr($block) . "><table class='rapid-table'>";
		if (!empty($block['data']['withHeadings']) && $rows) {
			$head = array_shift($rows);
			$h   .= '<thead><tr>' . implode('', array_map(fn($c) => '<th>' . self::text((string)$c) . '</th>', $head)) . '</tr></thead>';
		}
		$h .= '<tbody>';
		foreach ($rows as $row) {
			$h .= '<tr>' . implode('', array_map(fn($c) => '<td>' . self::text((string)$c) . '</td>', $row)) . '</tr>';
		}
		return $h . '</tbody></table></div>';
	}
	public static function toText(array $block): string {
		$p = [];
		foreach ((array)($block['data']['content'] ?? []) as $row) {
			foreach ($row as $c) $p[] = strip_tags((string)$c);
		}
		return implode(' ', array_filter($p));
	}
}

// ── Code ──────────────────────────────────────────────────────────────────

class RapidBlockCode extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$code  = htmlspecialchars($block['data']['code'] ?? '');
		$lang  = self::plain($block['data']['language'] ?? 'none');
		$lines = !empty($block['data']['lineNumbers']) ? ' class="line-numbers"' : '';
		return "<div " . self::attr($block) . "><pre$lines><code class=\"language-$lang\">$code</code></pre></div>";
	}
	public static function toText(array $block): string {
		return $block['data']['code'] ?? '';
	}
}
