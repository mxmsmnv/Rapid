<?php namespace ProcessWire;

require_once __DIR__ . '/RapidAttr.php';
require_once __DIR__ . '/RapidRenderer.php';

/**
 * RapidValue
 *
 * Value object returned when reading a FieldtypeRapid field.
 *
 *   echo $page->body;                          // render all blocks
 *   echo $page->body->render();
 *   echo $page->body->renderWith($renderer);   // custom renderer instance
 *   echo $page->body->renderBlock(0);          // single block by index
 *   $blocks = $page->body->blocks();           // raw array
 *   $text   = $page->body->toText();           // plain text (meta, search)
 *   $json   = $page->body->toJSON();           // raw JSON string
 *   if ($page->body->isEmpty()) { ... }
 */
class RapidValue extends Wire {

	protected array  $data    = ['blocks' => []];
	protected ?Page  $page    = null;
	protected ?Field $field   = null;
	protected array  $options = [];

	public function setData(array $data): void       { $this->data    = $data; }
	public function setPage(Page $p): void           { $this->page    = $p; }
	public function setField(Field $f): void         { $this->field   = $f; }
	public function setOptions(array $options): void { $this->options = $options; }
	public function getOptions(): array              { return $this->options; }

	public function blocks(): array  { return $this->data['blocks'] ?? []; }
	public function count(): int     { return count($this->blocks()); }
	public function isEmpty(): bool  { return empty($this->blocks()); }

	public function toJSON(): string {
		return json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
	}

	public function render(array $options = []): string {
		$merged = array_merge($this->options, $options);
		return (new RapidRenderer($merged))->renderData($this->data, $this->page, $this->field);
	}

	public function renderWith(RapidRenderer $renderer): string {
		return $renderer->renderData($this->data, $this->page, $this->field);
	}

	public function renderBlock(int $index, array $options = []): string {
		$blocks = $this->blocks();
		if (!isset($blocks[$index])) return '';
		return (new RapidRenderer($options))->renderSingle($blocks[$index], $this->page);
	}

	public function toText(): string {
		return RapidRenderer::blocksToText($this->blocks());
	}

	public function __toString(): string {
		try {
			return $this->render();
		} catch (\Throwable $e) {
			$msg = 'RapidValue::render() error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
			try {
				// Use Wire API (safer than global wire() function)
				$this->wire('log')->error($msg, ['name' => 'rapid']);
				if ($this->wire('config')->debug) throw $e;
			} catch (\Throwable $inner) {
				// Wire not available — silently ignore
			}
			return '';
		}
	}
}
