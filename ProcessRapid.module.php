<?php namespace ProcessWire;

require_once __DIR__ . '/src/bootstrap.php';

/**
 * ProcessRapid
 *
 * Dashboard and block preview for the Rapid block editor.
 *
 * {adminUrl}/setup/rapid/dashboard/ — usage statistics
 * {adminUrl}/setup/rapid/preview/   — visual demo of all block types
 */
class ProcessRapid extends Process {

	public static function getModuleInfo(): array {
		return [
			'title'      => 'Rapid',
			'version'    => 125,
			'summary'    => 'Dashboard and block preview for the Rapid block editor.',
			'author'     => 'Maxim Semenov',
			'href'       => 'https://smnv.org',
			'icon'       => 'bolt',
			'requires'   => 'FieldtypeRapid',
			'permission' => 'page-edit',
			'page'       => [
				'name'   => 'rapid',
				'parent' => 'setup',
				'title'  => 'Rapid',
				'icon'   => 'bolt',
			],
			'nav' => [
				['url' => 'dashboard/', 'label' => 'Dashboard',    'icon' => 'bolt'],
				['url' => 'preview/',   'label' => 'Block preview', 'icon' => 'eye'],
			],
		];
	}

	public function ___execute(): string {
		$this->wire('session')->redirect('./dashboard/');
		return '';
	}

	// ── Dashboard ─────────────────────────────────────────────────────────

	public function ___executeDashboard(): string {
		$config   = $this->wire('config');
		$rapidUrl = $config->urls->get('Rapid');

		// Gather Rapid fields
		$fields = [];
		foreach ($this->wire('fields') as $field) {
			if ($field->type instanceof FieldtypeRapid) $fields[] = $field;
		}

		// Count pages and blocks — limit to 200 pages per field for performance
		$blockStats     = [];
		$pagesWithRapid = [];

		foreach ($fields as $field) {
			$fname = $field->name;
			$pages = $this->wire('pages')->find("$fname!='', include=all, limit=200");
			foreach ($pages as $p) {
				$value = $p->$fname;
				if (!$value instanceof RapidValue) continue;
				$pagesWithRapid[$p->id] = $p;
				foreach ($value->blocks() as $block) {
					$type = $block['type'] ?? 'unknown';
					$blockStats[$type] = ($blockStats[$type] ?? 0) + 1;
				}
			}
		}

		arsort($blockStats);

		// Bundle info
		$distDir    = $config->paths->get('Rapid') . 'assets/js/dist/';
		$bundleSize = is_readable($distDir . 'editor.js')
			? round(filesize($distDir . 'editor.js') / 1024) . ' KB'
			: 'n/a';
		$bundleHash = is_readable($distDir . 'version.txt')
			? trim(file_get_contents($distDir . 'version.txt'))
			: 'n/a';

		return $this->renderDashboard([
			'fields'          => $fields,
			'pages'           => array_values($pagesWithRapid),
			'blockStats'      => $blockStats,
			'totalBlocks'     => array_sum($blockStats),
			'totalPages'      => count($pagesWithRapid),
			'registeredTypes' => RapidRenderer::getRegisteredTypes(),
			'bundleSize'      => $bundleSize,
			'bundleHash'      => $bundleHash,
			'adminUrl'        => rtrim($config->urls->admin, '/'),
			'rapidUrl'        => $rapidUrl,
		]);
	}

	private function renderDashboard(array $d): string {
		$adminUrl = $d['adminUrl'];

		// Stat cards
		$cards = '';
		foreach ([
			['Fields',       count($d['fields']),         'fields'],
			['Pages',        $d['totalPages'],             'page'],
			['Total blocks', $d['totalBlocks'],            'th-large'],
			['Block types',  count($d['registeredTypes']), 'cube'],
		] as [$label, $value, $icon]) {
			$cards .= "<div class='rapid-stat-card'>"
				. "<div class='rapid-stat-value'>$value</div>"
				. "<div class='rapid-stat-label'>$label</div>"
				. "</div>";
		}

		// Block usage bars
		$chartRows = '';
		foreach ($d['blockStats'] as $type => $count) {
			$pct  = $d['totalBlocks'] > 0 ? round($count / $d['totalBlocks'] * 100) : 0;
			$type = htmlspecialchars($type);
			$chartRows .= "<div class='rapid-chart-row'>"
				. "<span class='rapid-chart-label'>$type</span>"
				. "<div class='rapid-chart-bar-wrap'><div class='rapid-chart-bar' style='width:{$pct}%'></div></div>"
				. "<span class='rapid-chart-count'>$count</span>"
				. "</div>";
		}
		if (!$chartRows) $chartRows = '<p style="color:#999;padding:8px 0">No blocks yet.</p>';

		// Fields table
		$fieldRows = '';
		foreach ($d['fields'] as $field) {
			$name    = htmlspecialchars($field->name);
			$label   = htmlspecialchars($field->label ?: $field->name);
			$editUrl = "$adminUrl/setup/field/edit?id={$field->id}";
			$fieldRows .= "<tr><td><a href='$editUrl'>$name</a></td><td>$label</td></tr>";
		}
		if (!$fieldRows) $fieldRows = '<tr><td colspan="2" style="color:#999">No Rapid fields yet.</td></tr>';

		// Pages table
		$pageRows = '';
		foreach (array_slice($d['pages'], 0, 20) as $p) {
			$title   = htmlspecialchars($p->title ?: $p->name);
			$path    = htmlspecialchars($p->path);
			$editUrl = "$adminUrl/page/edit?id={$p->id}";
			$pageRows .= "<tr><td><a href='$editUrl'>$title</a></td><td style='color:#999;font-size:12px'>$path</td></tr>";
		}
		$morePages = count($d['pages']) > 20
			? '<p style="color:#999;font-size:12px;margin-top:6px">Showing first 20 of ' . count($d['pages']) . ' pages.</p>'
			: '';
		if (!$pageRows) $pageRows = '<tr><td colspan="2" style="color:#999">No pages with Rapid content yet.</td></tr>';

		// Registered types
		$types = $d['registeredTypes'];
		sort($types);
		$typeList = '';
		foreach ($types as $type) {
			$used     = $d['blockStats'][$type] ?? 0;
			$usedStr  = $used ? "<span style='color:#9ca3af;font-size:11px'>({$used}x)</span>" : '';
			$typeList .= "<li><code>" . htmlspecialchars($type) . "</code> $usedStr</li>";
		}

		$out = <<<HTML
		<style>
		.rapid-dashboard { max-width: 900px; }
		.rapid-stats { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:24px; }
		.rapid-stat-card { flex:1 1 140px; background:#fff; border:1px solid #e3e3e3; border-radius:6px; padding:16px 20px; text-align:center; }
		.rapid-stat-value { font-size:32px; font-weight:600; color:#1a1a1a; line-height:1; }
		.rapid-stat-label { font-size:12px; color:#888; margin-top:4px; text-transform:uppercase; letter-spacing:.05em; }
		.rapid-section { background:#fff; border:1px solid #e3e3e3; border-radius:6px; padding:20px 24px; margin-bottom:20px; }
		.rapid-section h3 { margin:0 0 16px; font-size:12px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:.06em; }
		.rapid-chart-row { display:flex; align-items:center; gap:10px; margin-bottom:8px; font-size:13px; }
		.rapid-chart-label { width:140px; flex-shrink:0; font-family:monospace; font-size:12px; }
		.rapid-chart-bar-wrap { flex:1; background:#f0f0f0; border-radius:3px; height:10px; }
		.rapid-chart-bar { height:100%; background:#3b82f6; border-radius:3px; min-width:2px; }
		.rapid-chart-count { width:36px; text-align:right; color:#9ca3af; font-size:12px; }
		.rapid-table { width:100%; border-collapse:collapse; font-size:13px; }
		.rapid-table td { padding:6px 0; border-bottom:1px solid #f3f3f3; vertical-align:top; }
		.rapid-table tr:last-child td { border-bottom:none; }
		.rapid-table td:first-child { width:200px; }
		.rapid-table a { color:#2563eb; text-decoration:none; }
		.rapid-table a:hover { text-decoration:underline; }
		.rapid-type-list { columns:3; gap:16px; margin:0; padding:0; list-style:none; font-size:13px; }
		.rapid-type-list li { margin-bottom:6px; }
		.rapid-type-list code { background:#f3f4f6; padding:1px 5px; border-radius:3px; font-size:12px; }
		.rapid-bundle { display:flex; gap:24px; font-size:13px; color:#555; flex-wrap:wrap; }
		.rapid-bundle b { color:#222; }
		</style>
		<div class="rapid-dashboard">
			<div class="rapid-stats">$cards</div>
			<div class="rapid-section"><h3>Block usage</h3>$chartRows</div>
			<div class="rapid-section"><h3>Fields</h3><table class="rapid-table">$fieldRows</table></div>
			<div class="rapid-section">
				<h3>Pages with Rapid content</h3>
				<table class="rapid-table">$pageRows</table>
				$morePages
			</div>
			<div class="rapid-section">
				<h3>Registered block types</h3>
				<ul class="rapid-type-list">$typeList</ul>
			</div>
			<div class="rapid-section">
				<h3>Bundle</h3>
				<div class="rapid-bundle">
					<span>Size: <b>{$d['bundleSize']}</b></span>
					<span>Hash: <b>{$d['bundleHash']}</b></span>
					<span>Path: <b>Rapid/assets/js/dist/editor.js</b></span>
				</div>
			</div>
		</div>
		HTML;

		// Prepend styles inline — avoids MIME type issues with $config->styles->add()
		$rapidUrl = $d['rapidUrl'];
		$css = "<link rel='stylesheet' href='{$rapidUrl}assets/css/editor.css'>";
		return $css . $out;
	}

	// ── Preview ───────────────────────────────────────────────────────────

	public function ___executePreview(): string {
		$config   = $this->wire('config');
		$rapidUrl = $config->urls->get('Rapid');

		$samples = [
			['type' => 'paragraph',  'data' => ['text' => 'A simple paragraph with <b>bold</b>, <i>italic</i> and <a href="#">a link</a>.'], 'tunes' => []],
			['type' => 'paragraph',  'data' => ['text' => 'A large paragraph variant.', 'large' => true], 'tunes' => []],
			['type' => 'header',     'data' => ['text' => 'Heading level 2', 'level' => 2], 'tunes' => []],
			['type' => 'header',     'data' => ['text' => 'Heading level 3', 'level' => 3], 'tunes' => []],
			['type' => 'quote',      'data' => ['text' => 'The best way to predict the future is to invent it.', 'caption' => '— Alan Kay'], 'tunes' => []],
			['type' => 'nestedList', 'data' => ['style' => 'unordered', 'items' => [
				['content' => 'First item', 'items' => [
					['content' => 'Nested A', 'items' => []],
					['content' => 'Nested B', 'items' => []],
				]],
				['content' => 'Second item', 'items' => []],
			]], 'tunes' => []],
			['type' => 'nestedList', 'data' => ['style' => 'ordered', 'items' => [
				['content' => 'Step one',   'items' => []],
				['content' => 'Step two',   'items' => []],
				['content' => 'Step three', 'items' => []],
			]], 'tunes' => []],
			['type' => 'table', 'data' => ['withHeadings' => true, 'content' => [
				['Name', 'Type', 'Required'],
				['title', 'string', 'yes'],
				['body', 'Rapid', 'no'],
			]], 'tunes' => []],
			['type' => 'checklist', 'data' => ['items' => [
				['text' => 'Write the module',  'checked' => true],
				['text' => 'Add a dashboard',   'checked' => true],
				['text' => 'Publish to GitHub', 'checked' => false],
			]], 'tunes' => []],
			['type' => 'warning',   'data' => ['title' => 'Important note', 'message' => 'This block draws attention to critical information.'], 'tunes' => []],
			['type' => 'delimiter', 'data' => [], 'tunes' => []],
			['type' => 'code',      'data' => ['code' => "echo \$page->body->render();", 'language' => 'php'], 'tunes' => []],
			['type' => 'image',     'data' => ['url' => 'https://picsum.photos/seed/rapid/800/300', 'alt' => 'Sample image', 'caption' => 'Image with caption'], 'tunes' => []],
			['type' => 'image',     'data' => ['url' => 'https://picsum.photos/seed/rapid2/600/300', 'alt' => 'Border', 'caption' => 'Image with border', 'withBorder' => true], 'tunes' => []],
			['type' => 'embed',     'data' => ['service' => 'youtube', 'embed' => 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'caption' => 'YouTube embed', 'width' => 16, 'height' => 9], 'tunes' => []],
			['type' => 'gallery',   'data' => ['files' => [
				'https://picsum.photos/seed/g1/400/300',
				'https://picsum.photos/seed/g2/400/300',
				'https://picsum.photos/seed/g3/400/300',
				'https://picsum.photos/seed/g4/400/300',
			], 'layout' => 'columns', 'columnWidthPx' => 160, 'gapPx' => 4], 'tunes' => []],
			['type' => 'imageSlideshow', 'data' => ['files' => [
				'https://picsum.photos/seed/s1/800/350',
				'https://picsum.photos/seed/s2/800/350',
				'https://picsum.photos/seed/s3/800/350',
			], 'loop' => true, 'autoplay' => false, 'effect' => 'slide', 'slidesPerView' => 1, 'gapPx' => 0], 'tunes' => []],
			['type' => 'attaches',  'data' => ['file' => ['url' => '#', 'name' => 'document.pdf', 'size' => 204800, 'extension' => 'pdf']], 'tunes' => []],
			['type' => 'layoutSection', 'data' => [
				'gap' => '1.5em', 'justify' => '', 'align' => 'flex-start', 'style' => [],
				'content' => ['blocks' => [
					['type' => 'paragraph', 'data' => ['text' => '<b>Left column.</b> Content in a layout section renders side by side.'], 'tunes' => []],
					['type' => 'paragraph', 'data' => ['text' => '<b>Right column.</b> Each child block gets equal flex space.'], 'tunes' => []],
				]],
			], 'tunes' => []],
			['type' => 'raw', 'data' => ['html' => '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:4px;padding:12px 16px;font-size:13px"><strong>Raw HTML block</strong> — outputs HTML as-is.</div>'], 'tunes' => []],
		];

		$registered = RapidRenderer::getRegisteredTypes();

		// Inline asset tags — avoids MIME type issues
		$assets  = "<link rel='stylesheet' href='{$rapidUrl}assets/css/editor.css'>";
		$assets .= "<link rel='stylesheet' href='{$rapidUrl}assets/js/vendor/swiper.min.css'>";
		$assets .= "<script src='{$rapidUrl}assets/js/vendor/swiper.min.js'></script>";

		$out  = '<style>';
		$out .= '.rp-preview { max-width:860px; }';
		$out .= '.rp-block { margin-bottom:28px; }';
		$out .= '.rp-type-label { font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin-bottom:6px;font-family:monospace;display:flex;align-items:center;gap:8px; }';
		$out .= '.rp-type-label::after { content:"";flex:1;height:1px;background:#e5e7eb; }';
		$out .= '.rp-output { padding:16px 20px;background:#fff;border:1px solid #e5e7eb;border-radius:6px; }';
		$out .= '.rp-missing { padding:12px 16px;background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;color:#92400e;font-size:13px; }';
		$out .= '</style>';
		$out .= '<div class="rp-preview">';

		foreach ($samples as $block) {
			$type     = $block['type'];
			$rendered = '';
			try {
				$rendered = RapidRenderer::fromJSON(json_encode(['blocks' => [$block]]));
			} catch (\Throwable $e) {
				$rendered = '<em style="color:#c00">Render error: ' . htmlspecialchars($e->getMessage()) . '</em>';
			}
			$out .= '<div class="rp-block">'
				. '<div class="rp-type-label">' . htmlspecialchars($type) . '</div>'
				. '<div class="rp-output">' . $rendered . '</div>'
				. '</div>';
		}

		// Registered types without a sample
		$sampled = array_column($samples, 'type');
		foreach ($registered as $type) {
			if (!in_array($type, $sampled, true)) {
				$out .= '<div class="rp-block">'
					. '<div class="rp-type-label">' . htmlspecialchars($type) . '</div>'
					. '<div class="rp-missing">No sample defined for this block type.</div>'
					. '</div>';
			}
		}

		$out .= '</div>';
		$out  = $assets . $out;
		$out .= <<<HTML
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('[data-rapid-slideshow]').forEach(function(el) {
				try {
					var cfg = JSON.parse(el.dataset.rapidSlideshow);
					new Swiper(el, {
						loop:          cfg.loop,
						slidesPerView: cfg.slidesPerView || 1,
						spaceBetween:  cfg.gap || 0,
						navigation:    { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
						pagination:    { el: '.swiper-pagination', clickable: true },
					});
				} catch(e) {}
			});
		});
		</script>
		HTML;

		return $out;
	}
}
