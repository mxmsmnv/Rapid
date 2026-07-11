<?php namespace ProcessWire;

class Page {}
class RapidRenderer { const BLOCK_CLASS = 'rapid-block'; }

require_once __DIR__ . '/../src/RapidAttr.php';
require_once __DIR__ . '/../src/Blocks/RapidBlockAbstract.php';
foreach (glob(__DIR__ . '/../src/Blocks/*Blocks.php') ?: [] as $blockFile) require_once $blockFile;

function assertSafe(bool $condition, string $message): void {
	if (!$condition) {
		fwrite(STDERR, "FAIL: $message\n");
		exit(1);
	}
}

$renderers = array_filter(get_declared_classes(), static fn(string $class): bool =>
	str_starts_with($class, 'ProcessWire\\RapidBlock') && $class !== RapidBlockAbstract::class
);
assertSafe(count($renderers) === 19, 'not all built-in block renderers were loaded');

$paragraph = RapidBlockParagraph::render([
	'type' => 'paragraph',
	'data' => ['text' => '<b>safe</b><img src=x onerror=alert(1)><a href="javascript:alert(1)">link</a>'],
], null);
assertSafe(str_contains($paragraph, '<b>safe</b>'), 'allowed inline markup was removed');
assertSafe(!str_contains($paragraph, '<img'), 'unsafe element survived rich-text sanitization');
assertSafe(!str_contains($paragraph, 'javascript:'), 'unsafe URL survived rich-text sanitization');

$raw = RapidBlockRaw::render([
	'type' => 'raw',
	'data' => ['html' => '<section><p onclick="alert(1)">text</p><script>alert(1)</script></section>'],
], null);
assertSafe(str_contains($raw, '<p>text</p>'), 'safe Raw block markup was removed');
assertSafe(!str_contains($raw, '<script'), 'script survived Raw block sanitization');
assertSafe(!str_contains($raw, 'onclick'), 'event handler survived Raw block sanitization');

$link = RapidBlockLinkTool::render([
	'type' => 'linkTool',
	'data' => ['link' => 'javascript:alert(1)', 'meta' => []],
], null);
assertSafe($link === '', 'unsafe LinkTool URL was rendered');

$attr = RapidAttr::render(['spacing' => ['top' => '0; background:red']], ['ok', 'bad" onclick="x']);
assertSafe(!str_contains($attr, 'background'), 'CSS declaration injection survived');
assertSafe(!str_contains($attr, 'onclick='), 'attribute injection survived');

fwrite(STDOUT, "security smoke tests passed\n");
