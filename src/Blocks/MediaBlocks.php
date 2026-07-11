<?php namespace ProcessWire;

require_once __DIR__ . '/RapidBlockAbstract.php';

// ── Image ─────────────────────────────────────────────────────────────────

class RapidBlockImage extends RapidBlockAbstract {

	public static function render(array $block, ?Page $page): string {
		$data = $block['data'];

		// @editorjs/image stores URL in data.file.url (upload response) or data.url (after reload)
		$url = $data['url'] ?? $data['file']['url'] ?? '';
		if (empty($url)) return '';

		// Normalise: always keep url at top level for processImage()
		$data['url'] = $url;

		$alt     = self::plain($data['alt']     ?? '');
		$cap     = !empty($data['caption']) ? '<figcaption>' . self::text($data['caption']) . '</figcaption>' : '';
		$classes = array_filter([
			!empty($data['withBorder'])     ? 'rapid-image--border'    : '',
			!empty($data['withBackground']) ? 'rapid-image--bg'        : '',
			!empty($data['stretched'])      ? 'rapid-image--stretched' : '',
		]);

		// Resize: per-block settings fall back to field defaults
		$resize   = $data['_resize'] ?? $block['tunes']['rapidResize'] ?? [];
		$defaults = $block['_imgDefaults'] ?? [];
		$width  = (int)($resize['width']  ?? 0) ?: (int)($defaults['width']  ?? 0);
		$height = (int)($resize['height'] ?? 0) ?: (int)($defaults['height'] ?? 0);
		$webp   = !empty($resize['webp']) || !empty($defaults['webp']);
		$crop   = !empty($resize['crop']) || !empty($defaults['crop']);

		$src = self::processImage($data['url'], $page, $width, $height, $webp, $crop);

		// Always output width/height attributes for CLS prevention
		$widthAttr  = $width  ? " width=\"$width\""  : '';
		$heightAttr = $height ? " height=\"$height\"" : '';

		$img = "<img src=\"$src\" alt=\"$alt\" loading=\"lazy\"$widthAttr$heightAttr>";

		if (!empty($data['link'])) {
			$target = !empty($data['openInNewTab']) ? ' target="_blank" rel="noopener"' : '';
			$link = self::safeUrl((string)$data['link'], true);
			if ($link !== '') $img = "<a href=\"$link\"$target>$img</a>";
		}

		return "<figure " . self::attr($block, $classes) . ">$img$cap</figure>";
	}

	/**
	 * Resize and/or convert image via PW Pageimage::size().
	 * Falls back to original URL if page not available or file not found.
	 */
	protected static function processImage(
		string $url,
		?Page $page,
		int $width,
		int $height,
		bool $webp,
		bool $crop
	): string {
		// No processing needed
		if (!$width && !$height && !$webp) {
			return self::resolveUrl($url, $page);
		}

		// WebP-only without resize is not meaningful — skip processing
		if ($webp && !$width && !$height) {
			return self::resolveUrl($url, $page);
		}

		// Need a page with files to use Pageimage
		if (!$page || !$page->id) {
			return self::resolveUrl($url, $page);
		}

		// Strip domain from URL for findPageimage (it uses basename internally)
		$localUrl  = preg_replace('#^https?://[^/]+#', '', $url);
		$pageimage = self::findPageimage($localUrl, $page);
		if (!$pageimage) {
			return self::resolveUrl($url, $page);
		}

		$options = [];

		if ($crop && $width && $height) {
			$options['cropping'] = true;
		} else {
			$options['cropping'] = false;
		}

		if ($webp) {
			$options['webpAdd'] = true;
		}

		try {
			$sized = $pageimage->size(
				$width  ?: 0,
				$height ?: 0,
				$options
			);

			// Verify sized file actually exists on disk
			if (!$sized || !file_exists($sized->filename)) {
				return self::resolveUrl($url, $page);
			}

			// If webp requested, prefer the .webp version
			if ($webp) {
				$webpPath = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $sized->filename);
				if (file_exists($webpPath)) {
					$webpUrl  = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $sized->url);
					return htmlspecialchars($webpUrl);
				}
			}

			return htmlspecialchars($sized->url);
		} catch (\Throwable $e) {
			return self::resolveUrl($url, $page);
		}
	}

	/**
	 * Find a Pageimage object by URL within a page's files directory.
	 *
	 * Strategy:
	 * 1. Extract filename from URL
	 * 2. Verify file exists on disk
	 * 3. Search page's Pageimages fields for a match
	 * 4. Fall back to constructing a temporary Pageimages collection
	 */
	private static function findPageimage(string $url, Page $page): ?\ProcessWire\Pageimage {
		$filesPath = rtrim($page->filesManager()->path(), '/') . '/';
		$filename  = basename(parse_url($url, PHP_URL_PATH) ?: $url);

		if (!$filename) return null;

		$filepath = $filesPath . $filename;
		if (!file_exists($filepath)) return null;

		// Search through all Pageimages fields on this page
		foreach ($page->fields as $field) {
			if (!$field->type instanceof \ProcessWire\FieldtypeImage) continue;
			$images = $page->get($field->name);
			if (!$images instanceof \ProcessWire\Pageimages) continue;
			$found = $images->get($filename);
			if ($found instanceof \ProcessWire\Pageimage) return $found;
		}

		// File exists on disk but not in any Images field —
		// uploaded via Rapid upload endpoint. Create a temporary Pageimage.
		try {
			$pageimages = new \ProcessWire\Pageimages($page);
			$pageimage  = new \ProcessWire\Pageimage($pageimages, $filepath);
			// Pageimage uses $this->filename to locate the original.
			// Without setting pagefiles it won't resolve the path correctly —
			// force the basepath so size() can find the file.
			if (!$pageimage->filename || !file_exists($pageimage->filename)) {
				return null;
			}
			return $pageimage;
		} catch (\Throwable $e) {
			return null;
		}
	}

	public static function toText(array $block): string {
		return trim(($block['data']['alt'] ?? '') . ' ' . ($block['data']['caption'] ?? ''));
	}
}

// ── Gallery ───────────────────────────────────────────────────────────────

class RapidBlockGallery extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$files = (array)($block['data']['files'] ?? []);
		if (!$files) return '';
		$colW  = (int)($block['data']['columnWidthPx'] ?? 250);
		$gap   = (int)($block['data']['gapPx']         ?? 4);
		$css   = "style=\"--rapid-col-width:{$colW}px;--rapid-gap:{$gap}px\"";
		$items = '';
		foreach ($files as $file) {
			$src    = self::resolveUrl((string)$file, $page);
			$items .= "<a href=\"$src\" class=\"rapid-gallery__item\"><img src=\"$src\" loading=\"lazy\" alt=\"\"></a>";
		}
		return "<div " . self::attr($block, ['rapid-gallery']) . " $css>$items</div>";
	}
}

// ── ImageSlideshow ────────────────────────────────────────────────────────

class RapidBlockImageSlideshow extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$files = (array)($block['data']['files'] ?? []);
		if (!$files) return '';
		$d        = $block['data'];
		$settings = htmlspecialchars(json_encode([
			'loop'         => $d['loop']         ?? true,
			'autoplay'     => $d['autoplay']      ?? false,
			'effect'       => $d['effect']        ?? 'slide',
			'delay'        => $d['delay']         ?? 3000,
			'slidesPerView'=> $d['slidesPerView'] ?? 1,
			'gap'          => $d['gapPx']         ?? 0,
		], JSON_UNESCAPED_SLASHES));
		$slides = '';
		foreach ($files as $file) {
			$src     = self::resolveUrl((string)$file, $page);
			$slides .= "<div class=\"swiper-slide rapid-slideshow__slide\"><img src=\"$src\" loading=\"lazy\" alt=\"\"></div>";
		}
		$id   = 'rs-' . substr(md5(uniqid()), 0, 8);
		$attr = self::attr($block, ['rapid-slideshow', 'swiper']);
		$init = "<script>
(function(){
	var el=document.getElementById('$id');
	if(!el||typeof Swiper==='undefined')return;
	var cfg=JSON.parse(el.dataset.rapidSlideshow);
	new Swiper(el,{
		loop:cfg.loop,effect:cfg.effect,
		slidesPerView:cfg.slidesPerView||1,
		spaceBetween:cfg.gap||0,
		autoplay:cfg.autoplay?{delay:cfg.delay}:false,
		navigation:{nextEl:'#$id .swiper-button-next',prevEl:'#$id .swiper-button-prev'},
		pagination:{el:'#$id .swiper-pagination',clickable:true},
	});
})();
</script>";
		return "<div id='$id' $attr data-rapid-slideshow='$settings'>"
		     . "<div class=\"swiper-wrapper\">$slides</div>"
		     . "<div class=\"swiper-button-prev\"></div>"
		     . "<div class=\"swiper-button-next\"></div>"
		     . "<div class=\"swiper-pagination\"></div>"
		     . "</div>"
		     . $init;
	}
}

// ── Embed ─────────────────────────────────────────────────────────────────

class RapidBlockEmbed extends RapidBlockAbstract {
	public static function render(array $block, ?Page $page): string {
		$data    = $block['data'];
		$embed   = $data['embed'] ?? '';
		if (!$embed) return '';
		// Normalize YouTube URLs to nocookie domain to avoid Error 153
		$embed   = str_replace('https://www.youtube.com/embed/', 'https://www.youtube-nocookie.com/embed/', $embed);
		$service = self::plain($data['service'] ?? '');
		$caption = self::text($data['caption'] ?? '');
		$cap     = $caption ? "<figcaption>$caption</figcaption>" : '';
		$ratio   = self::ratio((int)($data['width'] ?? 16), (int)($data['height'] ?? 9));
		$src  = htmlspecialchars($embed);
		$attr = self::attr($block, ['rapid-embed', "rapid-embed--$service"]);
		// referrerpolicy required for YouTube Error 153 fix
		$allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen';
		return "<figure $attr><div class=\"rapid-embed__ratio\" style=\"aspect-ratio:$ratio\">"
		     . "<iframe src=\"$src\" frameborder=\"0\" allow=\"$allow\" allowfullscreen loading=\"lazy\" referrerpolicy=\"strict-origin-when-cross-origin\"></iframe>"
		     . "</div>$cap</figure>";
	}
	public static function toText(array $block): string {
		return strip_tags($block['data']['caption'] ?? '');
	}
	private static function ratio(int $w, int $h): string {
		if (!$w || !$h) return '16/9';
		$g = self::gcd($w, $h);
		return ($w/$g) . '/' . ($h/$g);
	}
	private static function gcd(int $a, int $b): int {
		return $b === 0 ? $a : self::gcd($b, $a % $b);
	}
}
