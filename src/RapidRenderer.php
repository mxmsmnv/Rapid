<?php namespace ProcessWire;

require_once __DIR__ . '/RapidAttr.php';

/**
 * RapidRenderer
 *
 * Standalone renderer for Editor.js JSON data.
 * Can be used without the Fieldtype — drop anywhere in a PW project.
 *
 * Architecture ported from Automad\Core\Blocks (automad.org).
 *
 *   // From a field value
 *   echo $page->body->render();
 *
 *   // From raw JSON string (static shortcut)
 *   echo RapidRenderer::fromJSON($json, $page);
 *
 *   // With options
 *   $r = new RapidRenderer(['allowedBlocks' => ['paragraph','header']]);
 *   echo $r->renderData($data, $page);
 *
 *   // Register a custom block type globally
 *   RapidRenderer::register('myBlock', MyBlockRenderer::class);
 *
 *   // Plain text from blocks
 *   echo RapidRenderer::blocksToText($data['blocks']);
 */
class RapidRenderer {

	const BLOCK_CLASS   = 'rapid-block';
	const DEFAULT_PREFIX = 'ejs';

	/** Real filesystem path to the Rapid module directory (set by FieldtypeRapid). */
	public static string $moduleDir = '';

	/** @var array<string,string> type → FQCN */
	private static array $globalRegistry = [];
	private static bool  $booted = false;

	private array  $allowedBlocks       = [];
	private string $cssPrefix;
	private string $outputWrapClass      = '';
	private string $outputFramework      = 'vanilla';
	private int    $imageDefaultWidth    = 0;
	private int    $imageDefaultHeight   = 0;
	private bool   $imageDefaultWebp     = false;
	private bool   $imageDefaultCrop     = false;
	private array  $options              = [];

	public function __construct(array $options = []) {
		$this->options             = $options;
		$this->cssPrefix           = $options['cssPrefix']           ?? self::DEFAULT_PREFIX;
		$this->allowedBlocks       = $options['allowedBlocks']       ?? [];
		$this->outputWrapClass     = $options['outputWrapClass']     ?? '';
		$this->outputFramework     = $options['outputFramework']     ?? 'vanilla';
		$this->imageDefaultWidth   = (int)($options['imageDefaultWidth']   ?? 0);
		$this->imageDefaultHeight  = (int)($options['imageDefaultHeight']  ?? 0);
		$this->imageDefaultWebp    = (bool)($options['imageDefaultWebp']   ?? false);
		$this->imageDefaultCrop    = (bool)($options['imageDefaultCrop']   ?? false);
		self::boot();
	}

	// ── Static API ────────────────────────────────────────────────────────

	public static function register(string $type, string $className): void {
		self::boot();
		self::$globalRegistry[$type] = $className;
	}

	public static function getRegisteredTypes(): array {
		self::boot();
		// Add types rendered natively in renderData (not via RapidBlock* classes)
		$native = ['toggle'];
		return array_unique(array_merge(array_keys(self::$globalRegistry), $native));
	}

	public static function fromJSON(string $json, ?Page $page = null, array $options = []): string {
		$data = json_decode($json, true);
		if (!is_array($data)) return '';
		return (new self($options))->renderData($data, $page);
	}

	public static function blocksToText(array $blocks): string {
		self::boot();
		$parts = [];
		foreach ($blocks as $block) {
			if (empty($block['type'])) continue;
			if (!isset($block['data'])) continue;
			$class = self::$globalRegistry[$block['type']] ?? null;
			if (!$class) continue;
			try {
				$text = $class::toText($block);
				if ($text !== '') $parts[] = $text;
			} catch (\Throwable $e) {}
		}
		return preg_replace('/\s+/', ' ', implode(' ', $parts)) ?? '';
	}

	// ── Instance API ──────────────────────────────────────────────────────

	public function renderData(array $data, ?Page $page = null, ?Field $field = null): string {
		if (empty($data['blocks'])) return '';

		$allowed = $this->allowedBlocks;
		if (!$allowed && $field) {
			$allowed = (array)($field->get('allowedBlocks') ?: []);
		}

		RapidAttr::resetUniqueIds();

		$html     = '';
		$flexOpen = false;
		$blocks   = array_values($data['blocks']);
		$total    = count($blocks);
		$i        = 0;

		while ($i < $total) {
			$block = $blocks[$i];
			$i++;

			if (empty($block['type'])) continue;
			if (!isset($block['data'])) continue;
			// Types hidden from the field config UI are always rendered regardless of allowedBlocks.
			// paragraph is Editor.js built-in default — never shown in the allowed-blocks checklist
			// but must always pass through.
			$alwaysAllowed = ['paragraph', 'layoutSection', 'gallery', 'imageSlideshow', 'columns'];
			if ($allowed && !in_array($block['type'], $allowed, true) && !in_array($block['type'], $alwaysAllowed, true)) {
				// Still need to skip toggle children even if toggle itself is not allowed
				if ($block['type'] === 'toggle') $i += (int)($block['data']['items'] ?? 0);
				continue;
			}

			// ── Toggle: collect next N blocks as children ─────────────────
			if ($block['type'] === 'toggle') {
				$childCount = (int)($block['data']['items'] ?? 0);
				$children   = array_slice($blocks, $i, $childCount);
				$i         += $childCount;

				$childData = ['blocks' => $children];
				$childHtml = (new self($this->options))->renderData($childData, $page);

				$text = htmlspecialchars(strip_tags($block['data']['text'] ?? ''));
				$open = isset($block['data']['status']) && $block['data']['status'] === 'open' ? ' open' : '';
				$attr = RapidAttr::render($block['tunes'] ?? [], ['rapid-toggle']);

				$html .= "<details $attr$open>\n";
				$html .= "\t<summary class=\"rapid-toggle__title\">$text</summary>\n";
				$html .= "\t<div class=\"rapid-toggle__content\">$childHtml\t</div>\n";
				$html .= "</details>\n";
				continue;
			}

			// ── Regular block ─────────────────────────────────────────────
			$tunes      = $block['tunes'] ?? [];
			$stretched  = (bool)($tunes['layout']['stretched'] ?? false);
			$width      = (string)($tunes['layout']['width']   ?? '');
			$isFlexItem = ($width !== '' && !$stretched);

			if (!$flexOpen && $isFlexItem)  { $html .= '<div class="rapid-flex">'; $flexOpen = true; }
			if ($flexOpen  && !$isFlexItem) { $html .= '</div>'; $flexOpen = false; }

			$blockHtml = $this->generateHtml($block, $page, $this->outputFramework);
			if ($blockHtml === '') continue;

			if ($stretched) {
				$blockHtml = '<div class="rapid-stretched">' . $blockHtml . '</div>';
			} elseif ($width !== '') {
				$w         = str_replace('/', '-', $width);
				$blockHtml = '<div class="rapid-w-' . htmlspecialchars($w) . '">' . $blockHtml . '</div>';
			}

			$html .= $blockHtml . "\n";
		}

		if ($flexOpen) $html .= "</div>\n";

		// Apply outputWrapClass from field config if not set in options
		$wrapClass = $this->outputWrapClass;
		if (!$wrapClass && $field) {
			$wrapClass = (string)($field->get('outputWrapClass') ?: '');
		}
		if ($wrapClass) {
			$wrapClass = htmlspecialchars(preg_replace('/[^a-zA-Z0-9_\- ]/', '', $wrapClass));
			$html = "<div class=\"$wrapClass\">\n$html</div>\n";
		}

		return "\n" . $html;
	}

	public static function renderSingleBlock(array $block, ?Page $page = null): string {
		self::boot();
		$type  = $block['type'] ?? '';
		$class = self::$globalRegistry[$type] ?? null;
		if (!$class) return '';
		try {
			$html = $class::render($block, $page);
			return $html ? "<div class='rapid-block rapid-block--{$type}'>$html</div>" : '';
		} catch (\Throwable $e) { return ''; }
	}

	public function renderSingle(array $block, ?Page $page = null): string {
		if (empty($block['type'])) return '';
		if (!isset($block['data'])) return '';
		return $this->generateHtml($block, $page);
	}

	// ── Internal ──────────────────────────────────────────────────────────

	private function generateHtml(array $block, ?Page $page, string $framework = 'vanilla'): string {
		$class = self::$globalRegistry[$block['type']] ?? null;
		if (!$class) return '';
		try {
			$type    = $block['type'];
			// Inject framework class overrides into block before rendering
			if ($framework !== 'vanilla') {
				$fwClasses = RapidFrameworks::classes($framework, $type, $block['data'] ?? []);
				if ($fwClasses !== null) {
					$block['_fwClasses'] = $fwClasses;
				}
			}
			// Inject default image resize settings
			if ($type === 'image') {
				$block['_imgDefaults'] = [
					'width'  => $this->imageDefaultWidth,
					'height' => $this->imageDefaultHeight,
					'webp'   => $this->imageDefaultWebp,
					'crop'   => $this->imageDefaultCrop,
				];
			}
			$inner   = $class::render($block, $page);
			if ($inner === '') return '';
			// Indent inner HTML for readability
			$indented = implode("\n\t", explode("\n", rtrim($inner)));
			return "\t" . $indented . "\n";
		} catch (\Throwable $e) {
			$debug = false;
			try {
				if (function_exists('ProcessWire\\wire')) {
					$cfg = \ProcessWire\wire('config');
					$debug = $cfg && $cfg->debug;
				}
			} catch (\Throwable $inner) {}
			if ($debug) {
				return '<!-- Rapid [' . htmlspecialchars($block['type']) . ']: ' . htmlspecialchars($e->getMessage()) . ' -->';
			}
			return '';
		}
	}

	private static function boot(): void {
		if (self::$booted) return;
		self::$booted = true;

		// Block files are loaded via require_once in FieldtypeRapid.module.php.
		// Here we just scan already-declared classes and register them.
		// Also try loading from src/Blocks in case renderer is used standalone.
		$dir = self::$moduleDir ? self::$moduleDir . '/src/Blocks/' : __DIR__ . '/Blocks/';
		if (is_dir($dir)) {
			foreach (glob($dir . '*.php') ?: [] as $file) {
				require_once $file;
			}
		}

		// Register all RapidBlock* classes found in the ProcessWire namespace
		foreach (get_declared_classes() as $fqcn) {
			$short = strpos($fqcn, '\\') !== false
				? substr($fqcn, strrpos($fqcn, '\\') + 1)
				: $fqcn;
			if (strpos($short, 'RapidBlock') !== 0) continue;
			if ($short === 'RapidBlockAbstract') continue;
			if (!is_subclass_of($fqcn, 'ProcessWire\\RapidBlockAbstract')) continue;
			$type = lcfirst(substr($short, strlen('RapidBlock')));
			if (!isset(self::$globalRegistry[$type])) {
				self::$globalRegistry[$type] = $fqcn;
			}
		}
	}
}
