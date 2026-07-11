<?php namespace ProcessWire;

/**
 * RapidAttr — HTML attribute builder for Editor.js block wrappers.
 * Port of Automad\Blocks\Utils\Attr.
 */
class RapidAttr {

	private static array $usedIds = [];

	public static function resetUniqueIds(): void { self::$usedIds = []; }

	public static function render(array $tunes, array $classes = [], array $styles = [], array $fwClasses = []): string {
		$parts = array_filter([
			self::buildId($tunes),
			self::buildClass($tunes, $classes, $fwClasses),
			self::buildStyle($tunes, $styles),
		]);
		return implode(' ', $parts);
	}

	/**
	 * Render attributes from a full block array.
	 * Picks up _fwClasses injected by RapidRenderer for framework support.
	 */
	public static function fromBlock(array $block, array $extraClasses = [], array $styles = []): string {
		$tunes    = $block['tunes'] ?? [];
		$fwClasses = $block['_fwClasses'] ?? [];
		return self::render($tunes, $extraClasses, $styles, $fwClasses);
	}

	public static function renderClasses(array $classes): string {
		$classes = array_values(array_filter(array_map(static function($class): string {
			return trim((string)preg_replace('/[^a-zA-Z0-9_\-: ]/', '', (string)$class));
		}, $classes)));
		return $classes ? 'class="' . htmlspecialchars(implode(' ', $classes), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
	}

	public static function renderStyles(array $styles): string {
		if (!$styles) return '';
		$rules = [];
		foreach ($styles as $prop => $val) {
			$val = trim((string)$val);
			// camelCase → kebab-case, but skip CSS custom properties (--foo)
			if (!str_starts_with((string)$prop, '--')) {
				$prop = strtolower((string)preg_replace('/([A-Z])/', '-$1', (string)$prop));
			}
			if (!preg_match('/^(?:--[a-z0-9_-]+|[a-z][a-z0-9-]*)$/', (string)$prop)) continue;
			if ($val === '' || preg_match('/[;{}<>]/', $val) || preg_match('/(?:expression\s*\(|@import|javascript\s*:)/i', $val)) continue;
			$rules[] = "$prop: $val;";
		}
		return 'style="' . implode(' ', $rules) . '"';
	}

	private static function buildId(array $tunes): string {
		$id = self::uniqueId((string)($tunes['id'] ?? ''));
		return $id ? 'id="' . htmlspecialchars($id) . '"' : '';
	}

	private static function buildClass(array $tunes, array $extra, array $fwClasses = []): string {
		if ($fwClasses) {
			// Framework mode: replace rapid-* base with framework classes, keep extra
			$classes = array_merge($fwClasses, $extra);
		} else {
			$classes = array_merge([RapidRenderer::BLOCK_CLASS], $extra);
		}
		if (!empty($tunes['className'])) {
			$classes[] = preg_replace('/[<>"\']/', '', $tunes['className']);
		}
		return self::renderClasses($classes);
	}

	private static function buildStyle(array $tunes, array $extra): string {
		return self::renderStyles(array_merge(self::spacingStyles($tunes), $extra));
	}

	private static function spacingStyles(array $tunes): array {
		$styles = [];
		foreach (['top', 'right', 'bottom', 'left'] as $side) {
			$val = (string)($tunes['spacing'][$side] ?? '');
			if ($val !== '' && preg_match('/^-?\d+(?:\.\d+)?(?:px|rem|em|%)?$/i', $val)) $styles["padding-$side"] = $val;
		}
		return $styles;
	}

	private static function uniqueId(string $id): string {
		if ($id === '') return '';
		$base = $id; $suffix = 1;
		while (in_array($id, self::$usedIds, true)) $id = "$base-" . $suffix++;
		self::$usedIds[] = $id;
		return $id;
	}
}
