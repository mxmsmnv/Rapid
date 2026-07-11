<?php namespace ProcessWire;

/**
 * Rapid
 *
 * Primary installer module for the Rapid Editor.js fieldtype suite.
 * The actual ProcessWire field type is provided by FieldtypeRapid,
 * whose required prefix lets ProcessWire recognize it as a Fieldtype.
 */
class Rapid extends WireData implements Module {

	public static function getModuleInfo(): array {
		return [
			'title'    => 'Rapid',
			'version'  => 125,
			'summary'  => 'Editor.js block editor fieldtype suite for ProcessWire.',
			'icon'     => 'bolt',
			'author'   => 'Maxim Semenov',
			'href'     => 'https://smnv.org',
			'installs' => ['FieldtypeRapid'],
			'requires' => 'ProcessWire>=3.0.200',
			'singular' => true,
			'autoload' => false,
		];
	}
}
