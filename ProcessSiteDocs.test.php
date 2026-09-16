<?php namespace ProcessWire;

/**
 * Tests for ProcessSiteDocs
 *
 * Skipped automatically (via allow()) if the module isn't installed.
 *
 */
class WireTest_ProcessSiteDocs extends WireTest {

	/**
	 * @var ProcessSiteDocs|null
	 *
	 */
	protected $processSiteDocs;

	public function allow() {
		return $this->wire()->modules->isInstalled('ProcessSiteDocs');
	}

	public function init() {
		$this->processSiteDocs = $this->wire()->modules->get('ProcessSiteDocs');
	}

	public function execute() {
		$this->testInstall();
		$this->testConfig();
	}

	protected function testInstall() {
		$page = $this->wire()->pages->get('process=ProcessSiteDocs');
		$this->check('admin page exists', true, $page->id > 0);
	}

	protected function testConfig() {
		$this->check(
			'getSiteName() falls back to httpHost when unset',
			$this->processSiteDocs->siteName ?: $this->wire()->config->httpHost,
			$this->processSiteDocs->getSiteName()
		);
	}
}

