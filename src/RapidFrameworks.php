<?php namespace ProcessWire;

/**
 * RapidFrameworks — CSS class mappings for supported frameworks.
 *
 * Each framework maps Rapid block types to arrays of CSS classes.
 * Classes are applied via RapidAttr when a framework is active.
 */
class RapidFrameworks {

	/**
	 * Returns class list for a given block type and framework.
	 * Returns null if no mapping exists (fall back to rapid-* classes).
	 *
	 * @param string $framework  'tailwind' | 'bootstrap' | 'uikit' | 'vanilla'
	 * @param string $type       Block type (e.g. 'header', 'paragraph')
	 * @param array  $data       Block data (for variant selection)
	 * @return string[]|null
	 */
	public static function classes(string $framework, string $type, array $data = []): ?array {
		if ($framework === 'vanilla' || $framework === '') return null;
		$map = self::map($framework);
		return $map[$type] ?? null;
	}

	/** Full class map for a framework. */
	private static function map(string $fw): array {
		return match($fw) {
			'tailwind'  => self::tailwind(),
			'bootstrap' => self::bootstrap(),
			'uikit'     => self::uikit(),
			default     => [],
		};
	}

	// ── Tailwind ──────────────────────────────────────────────────────────

	private static function tailwind(): array {
		return [
			'paragraph'     => ['mb-4', 'leading-relaxed'],
			'header'        => ['font-bold', 'leading-tight', 'mb-3', 'mt-6'],
			'quote'         => ['border-l-4', 'pl-4', 'italic', 'text-gray-600', 'my-4'],
			'nestedList'    => ['pl-5', 'space-y-1', 'my-3'],
			'table'         => ['w-full', 'border-collapse', 'text-sm', 'my-4'],
			'code'          => ['bg-gray-900', 'text-green-400', 'rounded', 'p-4', 'overflow-x-auto', 'text-sm', 'font-mono'],
			'delimiter'     => ['border-t', 'border-gray-200', 'my-6'],
			'warning'       => ['border-l-4', 'border-yellow-400', 'bg-yellow-50', 'p-4', 'my-4'],
			'checklist'     => ['space-y-2', 'list-none', 'p-0'],
			'image'         => ['my-4'],
			'attaches'      => ['inline-flex', 'items-center', 'gap-2', 'p-3', 'border', 'border-gray-200', 'rounded', 'text-sm', 'no-underline'],
			'embed'         => ['my-4'],
			'gallery'       => ['grid', 'gap-1', 'my-4'],
			'imageSlideshow'=> ['my-4'],
			'alert'         => ['p-4', 'rounded', 'my-4', 'text-sm'],
			'toggle'        => ['border', 'border-gray-200', 'rounded', 'my-4'],
			'linkTool'      => ['my-4'],
			'raw'           => [],
		];
	}

	// ── Bootstrap 5 ───────────────────────────────────────────────────────

	private static function bootstrap(): array {
		return [
			'paragraph'     => ['mb-3'],
			'header'        => ['mb-2', 'mt-4'],
			'quote'         => ['blockquote', 'border-start', 'border-3', 'ps-3', 'text-muted', 'my-3'],
			'nestedList'    => ['my-2'],
			'table'         => ['table', 'table-bordered', 'table-sm'],
			'code'          => ['bg-dark', 'text-success', 'p-3', 'rounded', 'overflow-auto', 'small', 'font-monospace'],
			'delimiter'     => ['border-top', 'my-4'],
			'warning'       => ['alert', 'alert-warning'],
			'checklist'     => ['list-unstyled'],
			'image'         => ['my-3'],
			'attaches'      => ['d-inline-flex', 'align-items-center', 'gap-2', 'p-2', 'border', 'text-decoration-none', 'text-body', 'small'],
			'embed'         => ['ratio', 'ratio-16x9', 'my-3'],
			'gallery'       => ['row', 'g-1', 'my-3'],
			'imageSlideshow'=> ['my-3'],
			'alert'         => ['alert', 'my-3'],
			'toggle'        => ['my-3'],
			'linkTool'      => ['card', 'my-3'],
			'raw'           => [],
		];
	}

	// ── UIkit 3 ───────────────────────────────────────────────────────────

	private static function uikit(): array {
		return [
			'paragraph'     => [],
			'header'        => [],
			'quote'         => ['uk-blockquote'],
			'nestedList'    => ['uk-list', 'uk-list-disc'],
			'table'         => ['uk-table', 'uk-table-divider', 'uk-table-small'],
			'code'          => [],
			'delimiter'     => ['uk-divider-icon'],
			'warning'       => ['uk-alert', 'uk-alert-warning'],
			'checklist'     => ['uk-list'],
			'image'         => [],
			'attaches'      => ['uk-button', 'uk-button-default', 'uk-button-small'],
			'embed'         => [],
			'gallery'       => ['uk-grid', 'uk-grid-small', 'uk-child-width-1-3@s'],
			'imageSlideshow'=> ['uk-slideshow'],
			'alert'         => ['uk-alert'],
			'toggle'        => [],
			'linkTool'      => ['uk-card', 'uk-card-default'],
			'raw'           => [],
		];
	}
}
