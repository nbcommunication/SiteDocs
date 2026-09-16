<?php namespace ProcessWire;

/**
 * Tests for SiteDocs
 *
 * Skipped automatically (via allow()) if the module isn't installed.
 *
 */
class WireTest_SiteDocs extends WireTest {

	/**
	 * @var SiteDocs|null
	 *
	 */
	protected $siteDocs;

	public function allow() {
		return $this->wire()->modules->isInstalled('SiteDocs');
	}

	public function init() {
		$this->siteDocs = $this->wire()->modules->get('SiteDocs');
	}

	public function execute() {
		$this->testInstall();
		$this->testAccessControl();
		$this->testSearch();
	}

	protected function testInstall() {
		$fields = $this->wire()->fields;
		$templates = $this->wire()->templates;

		$this->check('body field exists', true, (bool) $fields->get('body'));
		$this->check('images field exists', true, (bool) $fields->get('images'));
		$this->check('sitedocs_roles field exists', true, (bool) $fields->get(SiteDocs::fieldRoles));

		foreach([SiteDocs::templateIndex, SiteDocs::templatePage] as $name) {
			$this->check("template exists: $name", true, (bool) $templates->get($name));
		}

		$templatePage = $templates->get(SiteDocs::templatePage);
		$this->check(
			'sitedocs-page allows itself as a child template',
			true,
			$templatePage && in_array($templatePage->id, $templatePage->childTemplates)
		);

		$index = $this->siteDocs->getIndexPage();
		$this->check('index page exists', true, $index->id > 0);
		$this->check('isSiteDocsPage() true for index page', true, $this->siteDocs->isSiteDocsPage($index));
	}

	protected function testAccessControl() {
		$pages = $this->wire()->pages;
		$index = $this->siteDocs->getIndexPage();
		if(!$index->id) return;

		$page = $pages->get('parent=' . $index->id . ', template=' . SiteDocs::templatePage . ', include=hidden');
		if(!$page->id) return;

		$superuser = $this->wire()->users->get($this->wire()->config->superUserPageID);
		$guest = $this->wire()->users->getGuestUser();

		$this->check('pageAllowedRoles() returns null when unrestricted', null, $this->siteDocs->pageAllowedRoles($page));
		$this->check('superuser can view an unrestricted page', true, $this->siteDocs->userCanView($page, $superuser));
		$this->check('guest can view an unrestricted page', true, $this->siteDocs->userCanView($page, $guest));
	}

	protected function testSearch() {
		$matches = $this->siteDocs->findCustom('page');
		$this->check('findCustom() returns a PageArray', true, $matches instanceof PageArray);
	}
}
