<?php namespace ProcessWire;

/**
 * Site Docs (ProcessSiteDocs) Configuration
 *
 * @copyright 2026 NB Communication Ltd
 *
 */
class ProcessSiteDocsConfig extends ModuleConfig {

	/**
	 * Returns default values for module variables
	 *
	 * @return array
	 *
	 */
	public function getDefaults() {
		return [
			'customCssFile' => '',
			'customJsFile' => '',
			'engineer_instructions' => '',
			'siteName' => '',
		];
	}

	/**
	 * Returns inputs for module configuration
	 *
	 * @return InputfieldWrapper
	 *
	 */
	public function getInputfields() {

		$inputfields = parent::getInputfields();
		$modules = $this->wire()->modules;

		$inputfields->add([
			'type' => 'text',
			'name' => 'siteName',
			'label' => $this->_('Site Name'),
			'description' => $this->_('Specify the name of the site to be displayed in the documentation pages.'),
			'icon' => 'globe',
		]);

		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $this->_('Custom Files');
		$fieldset->notes = $this->_("Paths should be relative to your ProcessWire installation root (i.e. if site is running from a subdirectory, exclude that part).");

		$fieldset->add([
			'type' => 'text',
			'name' => 'customCssFile',
			'label' => $this->_('Custom CSS File'),
			'description' => $this->_('Specify a custom CSS file to style the documentation pages.'),
			'notes' => $this->_('Example: /site/templates/styles/sitedocs.css'),
			'icon' => 'css3',
		]);

		$fieldset->add([
			'type' => 'text',
			'name' => 'customJsFile',
			'label' => $this->_('Custom JavaScript File'),
			'description' => $this->_('Specify a custom JavaScript file to add interactivity to the documentation pages.'),
			'notes' => $this->_('Example: /site/templates/scripts/sitedocs.js'),
			'icon' => 'code',
		]);

		$inputfields->add($fieldset);

		if($modules->isInstalled('AgentTools')) {
			$inputfields->add([
				'type' => 'textarea',
				'name' => 'engineer_instructions',
				'label' => $this->_('Notes for Agent Tools'),
				'description' => $this->_('Enter any notes for Agent Tools to provide context about the site. This could include information about the types of content on the site, the intended audience, or any other relevant details that could help an AI agent understand how to generate effective documentation.'),
				'icon' => 'sticky-note',
				'rows' => 10,
				'collapsed' => 2,
			]);
		}

		return $inputfields;
	}
}
