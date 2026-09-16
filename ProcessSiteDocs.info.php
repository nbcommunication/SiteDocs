<?php namespace ProcessWire;

/**
 * Site Docs (ProcessSiteDocs) Info
 *
 * @copyright 2026 NB Communication Ltd
 *
 */

$info = [
	'title' => 'Site Docs',
	'summary' => 'Manage and browse internal site documentation.',
	'version' => 2,
	'author' => 'nbcommunication',
	'icon' => 'book',
	'requires' => 'SiteDocs',
	'singular' => true,
	'autoload' => false,
	'permission' => 'page-edit',
	'page' => [
		'name' => 'docs',
		'title' => 'Docs',
	],
	'useNavJSON' => true,
	'nav' => [
		[
			'url' => '',
			'label' => 'Table of Contents',
			'icon' => 'list',
			'navJSON' => 'navJSON',
		],
		[
			'url' => 'manual/',
			'label' => 'Print manual',
			'icon' => 'print',
		],
	],
];
