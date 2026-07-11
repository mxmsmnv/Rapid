<?php namespace ProcessWire;

require_once __DIR__ . '/RapidBlockAbstract.php';

// ── LayoutSection ─────────────────────────────────────────────────────────

class RapidBlockLayoutSection extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$data  = $block['data'];
		$inner = '';
		if (!empty($data['content']['blocks'])) {
			// Pass same options to nested renderer (allowedBlocks, etc.)
			$opts  = !empty($data['allowedBlocks']) ? ['allowedBlocks' => $data['allowedBlocks']] : [];
			$inner = (new RapidRenderer($opts))->renderData($data['content'], $page);
		}

		$styleKeys = ['backgroundColor','backgroundBlendMode','borderWidth','borderRadius','borderStyle','paddingTop','paddingBottom'];
		$ds      = (array)($data['style'] ?? []);
		$styles  = [];
		$classes = ['rapid-layout-section'];

		foreach ($styleKeys as $k) {
			if (!empty($ds[$k])) $styles[$k] = preg_replace('/[<>]/', '', $ds[$k]);
		}
		if (!empty($data['gap']))          $styles['--rapid-flex-gap']            = preg_replace('/[<>]/', '', $data['gap']);
		if (!empty($data['minBlockWidth'])) $styles['--rapid-flex-min-block-width'] = preg_replace('/[<>]/', '', $data['minBlockWidth']);
		if (!empty($ds['backgroundImage'])) {
			$backgroundImage = self::safeUrl((string)$ds['backgroundImage']);
			if ($backgroundImage !== '') $styles['backgroundImage'] = "url('$backgroundImage')";
		}
		if (!empty($ds['overflowHidden']))  $styles['overflow']  = 'hidden';
		if (!empty($ds['matchRowHeight']))  $styles['height']    = '100%';
		if (!empty($ds['shadow']))          $styles['boxShadow'] = 'var(--rapid-section-shadow,0 2px 8px rgba(0,0,0,.15))';
		if (!empty($ds['color']))           $styles['--rapid-section-color']        = preg_replace('/[<>]/', '', $ds['color']);
		if (!empty($ds['borderColor']))     $styles['--rapid-section-border-color'] = preg_replace('/[<>]/', '', $ds['borderColor']);
		if (!empty($ds['card']))            $classes[] = 'rapid-card';
		if (!empty($data['justify']))       $classes[] = 'rapid-justify-' . preg_replace('/[^a-z\-]/', '', $data['justify']);
		if (!empty($data['align']))         $classes[] = 'rapid-align-'   . preg_replace('/[^a-z\-]/', '', $data['align']);

		$attr = RapidAttr::render($block['tunes'] ?? [], $classes, $styles);
		return "<section><div $attr>$inner</div></section>";
	}
	public static function toText(array $block): string {
		return RapidRenderer::blocksToText($block['data']['content']['blocks'] ?? []);
	}
}

// ── Code ──────────────────────────────────────────────────────────────────
// (already defined above as RapidBlockCode)
