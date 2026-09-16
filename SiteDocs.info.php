<?php namespace ProcessWire;

/**
 * Site Docs Info
 *
 * @copyright 2026 NB Communication Ltd
 *
 */

$info = [
	'title' => 'Site Docs',
	'summary' => 'Implements internal site documentation management.',
	'version' => 3,
	'author' => 'nbcommunication',
	'href' => 'https://github.com/nbcommunication/SiteDocs',
	'icon' => 'question-circle',
	'requires' => 'ProcessWire>=3.0.240,AdminThemeUikit',
	'installs' => 'ProcessSiteDocs',
	'singular' => true,
	'autoload' => 'template=admin',
];
