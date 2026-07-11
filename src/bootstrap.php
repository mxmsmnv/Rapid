<?php namespace ProcessWire;

require_once __DIR__ . '/RapidAttr.php';
require_once __DIR__ . '/Blocks/RapidBlockAbstract.php';
foreach (glob(__DIR__ . '/Blocks/*Blocks.php') ?: [] as $blockFile) require_once $blockFile;
require_once __DIR__ . '/RapidFrameworks.php';
require_once __DIR__ . '/RapidRenderer.php';
require_once __DIR__ . '/RapidValue.php';
