<?php namespace ProcessWire;

require_once __DIR__ . '/RapidBlockAbstract.php';

// ── Columns ───────────────────────────────────────────────────────────────

class RapidBlockColumns extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page = null, ?Field $field = null): string {
		$cols = $block['data']['cols'] ?? [];
		if (empty($cols)) return '';

		$count  = count($cols);
		$cls    = "rapid-columns rapid-columns--{$count}";
		$inner  = '';

		foreach ($cols as $colData) {
			$blocks  = $colData['blocks'] ?? [];
			$colHtml = '';

			if ($blocks) {
				// Recursively render each column's blocks using RapidRenderer
				$colHtml = (new \ProcessWire\RapidRenderer())->renderData(
					['blocks' => $blocks, 'time' => 0, 'version' => ''],
					$page,
					$field
				);
			}

			$inner .= "<div class='rapid-col'>$colHtml</div>";
		}

		return "<div class='" . htmlspecialchars($cls) . "'>$inner</div>";
	}

	public static function toText(array $block): string {
		$cols  = $block['data']['cols'] ?? [];
		$parts = [];
		foreach ($cols as $col) {
			foreach ($col['blocks'] ?? [] as $b) {
				$type  = $b['type'] ?? '';
				$cls   = 'ProcessWire\\RapidBlock' . ucfirst($type);
				if (class_exists($cls) && method_exists($cls, 'toText')) {
					$parts[] = $cls::toText($b);
				}
			}
		}
		return implode(' ', array_filter($parts));
	}
}
