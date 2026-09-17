<?php namespace ProcessWire;

/**
 * Site Docs (ProcessSiteDocs)
 *
 * Manages a hierarchy of internal site documentation pages (index > page > page),
 * with per-page role-based view restrictions and a print-friendly manual.
 *
 * See ProcessSiteDocs.info.php for module info, and API.md for full API documentation.
 *
 * @property string $customCssFile The path to a custom CSS file for the documentation pages.
 * @property string $customJsFile The path to a custom JavaScript file for the documentation pages.
 * @property string $siteName The name of the site to be displayed in the documentation pages.
 *
 * @copyright 2026 NB Communication Ltd
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 *
 */
class ProcessSiteDocs extends Process {

	/**
	 * @var SiteDocs|null The SiteDocs module
	 *
	 */
	protected $siteDocs = null;

	/**
	 * @var array The docs hierarchy as a collection of pages
	 *
	 */
	protected $siteDocsNav = [];

	/**
	 * @var Page|null Page currently being viewed, set by executeView()
	 *
	 */
	protected $pageView = null;

	/**
	 * Init
	 *
	 */
	public function init() {

		parent::init(); // auto-includes ProcessSiteDocs.css/.js when present

		$this->siteDocs = $this->wire()->modules->get('SiteDocs');

		// Include custom assets
		$config = $this->wire()->config;
		$files = $this->wire()->files;
		$user = $this->wire()->user;

		foreach([
			'customCssFile' => ['ext' => 'css', 'add' => 'styles'],
			'customJsFile' => ['ext' => 'js', 'add' => 'scripts'],
		] as $property => $info) {

			$path = trim((string) $this->$property);
			if($path === '') continue;
			if(strpos($path, '..') !== false) continue; // reject traversal
			if(strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== $info['ext']) continue;

			$file = rtrim($config->paths->root, '/') . '/' . ltrim($path, '/');
			if(!$files->exists($file)) continue;

			$url = rtrim($config->urls->root, '/') . '/' . ltrim($path, '/');
			$config->{$info['add']}->add($url);
		}

		// Get the docs this user has access to
		$cacheName = ['nav'];
		foreach($user->roles->sort('id') as $role) {
			$cacheName[] = $role->id;
		}
		if(isset($user->language)) {
			$cacheName[] = $user->language->name;
		}
		$this->siteDocsNav = $this->wire()->cache->getFor(
			$this,
			implode('-', $cacheName),
			'template=' . SiteDocs::templateIndex . '|' . SiteDocs::templatePage,
			function() {
				$siteDocsNav = [];
				$pageIndex = $this->siteDocs->getIndexPage();
				if($pageIndex->id) {
					foreach($this->pageChildren($pageIndex) as $child) {
						$siteDocsNav[] = $this->menuItem($child);
					}
				}
				return $siteDocsNav;
			}
		);
	}

	/**
	 * Index landing — introduction plus navigation tree
	 *
	 * @return string
	 *
	 */
	public function ___execute() {

		$pageIndex = $this->siteDocs->getIndexPage();
		if(!$pageIndex->id) {
			// If the index page could not be found, show an error and redirect to the admin homepage
			$this->error($this->_('The Site Docs index page could not be found.'));
			$this->wire()->session->location($this->wire()->pages->get(2)->url);
			return;
		}

		return $this->renderIndex();
	}

	/**
	 * Render a single documentation page
	 *
	 * @return string
	 *
	 */
	public function ___executeView() {

		$page = $this->wire()->pages->get($this->wire()->input->get->int('id'));

		if(!$page || !$page->id || !$this->siteDocs->isSiteDocsPage($page)) {
			return $this->errorRedirect($this->_('Sorry, this page could not be found.'));
		}

		if(!$this->siteDocs->userCanView($page)) {
			return $this->errorRedirect($this->_('Sorry, you do not have access to this page.'));
		}

		$this->pageView = $page;

		return $this->renderView($this->renderPage($page));
	}

	/**
	 * Print-friendly manual — full accessible documentation as a single document
	 *
	 * @return string
	 *
	 */
	public function ___executeManual() {
		$sanitizer = $this->wire()->sanitizer;
		$title = $sanitizer->entities1($this->getSiteName()) . ' - ' . $this->_('Manual');
		$this->headline($title);
		$this->browserTitle(str_replace(['---', '--'], '-', $sanitizer->pageName(sprintf("%s-%s", str_replace(' ', '-', $title), date('Y-m-d-H-i-s')))));
		return '<h1 class="no-print">' . $title . '</h1>' .
			$this->renderManual() .
			'<div class="uk-grid uk-grid-small uk-child-width-auto no-print" data-uk-grid>' .
				'<div>' .
					'<button type="button" class="uk-button uk-button-primary sitedocs-print-button">' . $this->_('Print') . '</button>' .
				'</div>' .
				'<div>' .
					'<a href="' . $this->wire()->page->url . '" class="uk-button uk-button-default">' . $this->_('Back to Docs') . '</a>' .
				'</div>' .
			'</div>';
	}

	/**
	 * Provide items for admin theme nav JSON dropdown
	 *
	 * @param array $options
	 * @return string
	 *
	 */
	public function ___executeNavJSON(array $options = []) {

		$options['add'] = '';
		$options['edit'] = $this->wire()->page->url . 'view/?id={id}';
		$options['itemLabel'] = 'title';
		$options['sort'] = false;

		$options['items'] = [];
		foreach($this->siteDocsNav as $item) {
			$options['items'][] = [
				'id' => $item['id'],
				'name' => $item['name'],
				'title' => $item['title'],
			];
		}

		return parent::___executeNavJSON($options);
	}

	/**
	 * Rendering
	 *
	 */

	/**
	 * Render the index landing page
	 *
	 * @return string
	 *
	 */
	public function ___renderIndex() {
		$image = $this->renderIndexImage();
		return $this->renderView(
			($image ? '<div class="sitedocs-banner">' . $image . '</div>' : '') .
			$this->siteDocs->getContent($this->siteDocs->getIndexPage())
		);
	}

	/**
	 * Render the index page image
	 *
	 * @return string
	 *
	 */
	public function ___renderIndexImage() {
		$image = $this->getIndexImage();
		if($image) {
			return $image->render();
		}
		return '';
	}

	/**
	 * Render the full print/manual document
	 *
	 * @return string
	 *
	 */
	public function ___renderManual() {

		$sanitizer = $this->wire()->sanitizer;
		$siteDocs = $this->siteDocs;

		$pageIndex = $siteDocs->getIndexPage();
		if(!$pageIndex->id) return '';

		$config = $this->wire()->config;
		$siteName = $this->getSiteName();

		$out = '<div class="sitedocs-manual">';

			// splash page
			$image = $this->renderIndexImage();
			$out .= "<section class='sitedocs-splash'>";
				$out .= $image ? '<div class="sitedocs-splash-banner">' . $image . '</div>' : '';
				$out .= "<h1>" . $sanitizer->entities1($siteName) . "</h1>";
				$out .= "<p>" . $sanitizer->entities1($this->wire()->pages->get('/')->httpUrl) . "</p>";
				$out .= "<p><small>" . sprintf($this->_('Generated %s'), date('Y-m-d H:i')) . "</small></p>";
			$out .= "</section>";

			// introduction + TOC
			$out .= "<section class='sitedocs-toc'>";
				$content = $siteDocs->getContent($pageIndex);
				if($content) $out .= "<div class='sitedocs-intro'>{$content}</div>";
				$out .= "<h2>" . $this->_('Table of Contents') . "</h2>";
				$out .= '<ul>' . $this->renderNav($this->siteDocsNav, true) . '</ul>';
			$out .= "</section>";

			foreach($this->pageChildren($pageIndex) as $child) {
				$out .= $this->renderManualPage($child);
			}

		$out .= '</div>';

		return $out;
	}

	public function ___renderManualPage(Page $page, $depth = 2) {

		if($depth > 6) $depth = 6; // Limit the maximum heading depth to 6

		$content = $this->siteDocs->getContent($page);

		// Attempt to shift headings e.g. h1 -> h2.h1, h2 -> h3.h2, etc.

		if(extension_loaded('dom')) {

			// Parse the page content to generate on page nav
			$dom = $this->domLoad($content);

			$xpath = new \DOMXPath($dom);

			// Snapshot the nodes first — XPath queries are "live" against the DOM,
			// and we don't want the query re-evaluating mid-loop as we swap nodes out.
			$headings = $xpath->query('//h1|//h2|//h3|//h4|//h5');

			// Collect into a plain array before mutating anything.
			$nodes = [];
			foreach($headings as $node) {
				$nodes[] = $node;
			}

			foreach($nodes as $node) {
				$originalLevel = (int) substr($node->tagName, 1); // "h3" -> 3
				$newLevel = min($originalLevel + $depth - 1, 6);      // cap at h6
				$newTag = 'h' . $newLevel;

				if($newTag === $node->tagName) {
					continue; // no change needed
				}

				$newNode = $dom->createElement($newTag);

				// Copy attributes (id, class, data-*, etc.)
				foreach($node->attributes as $attr) {
					$newNode->setAttribute($attr->nodeName, $attr->nodeValue);
				}

				// Move all children over (importNode not needed — same document)
				while($node->firstChild) {
					$newNode->appendChild($node->firstChild);
				}

				// Swap the new node in where the old one was
				$node->parentNode->replaceChild($newNode, $node);
			}

			$content = $this->domSave($dom);
		}

		$children = $this->pageChildren($page);
		if($children->count) {
			foreach($children as $child) {
				$content .= $this->renderManualPage($child, min($depth + 1, 6));
			}
		}

		return '<section id="sitedocs-' . $page->id . '" class="sitedocs-manual-section">' .
			'<h' . $depth . '>' . $page->getFormatted('title') . '</h' . $depth . '>' .
			$content .
		'</section>';
	}

	/**
	 * Render the navigation items (<li> elements) for a page's children
	 *
	 * @param array $items
	 * @param bool $isManual Whether the navigation is for the manual view
	 * @return string
	 *
	 */
	public function ___renderNav(array $items, bool $isManual = false) {
		return $this->renderNavItems($items, $isManual);
	}

	/**
	 * Render the navigation items (<li> elements) for a page's children
	 *
	 * @param array $items
	 * @param bool $isManual Whether the navigation is for the manual view
	 * @return string
	 *
	 */
	protected function renderNavItems(array $items, bool $isManual = false) {

		if(!count($items)) return '';

		$sanitizer = $this->wire()->sanitizer;

		$siteDocs = $this->siteDocs;
		$currentPage = $this->pageView ?: null;
		$currentPageId = $currentPage->id ?? 0;

		$out = '';
		foreach($items as $item) {

			$isCurrentPage = $item['id'] === $currentPageId;
			$isActive = $isCurrentPage;
			if(!$isActive && $currentPageId) {
				// The active page has the item as a parent
				$isActive = $currentPage->parents->has("id={$item['id']}");
			}

			$childItems = $item['items'];
			$isParent = count($childItems) > 0;

			$topClasses = ['sitedocs-nav-item'];
			$overviewClasses = $topClasses;

			if(!$isManual) {

				if($isActive) {
					$topClasses[] = 'uk-active';
					if($isCurrentPage) {
						$overviewClasses[] = 'uk-active';
					}
				}

				if($isParent) {
					$topClasses[] = 'uk-parent';
					if($isActive) {
						$topClasses[] = 'uk-open';
					}
				}
			}

			$url = $isManual ? '#sitedocs-' . $item['id'] : $item['url'];

			$out .= '<li class="' . implode(' ', $topClasses) . '">';
				$out .= '<a href="' . ($isParent ? '#' : $url) . '">' .
					$sanitizer->entities1($item['title']) .
					($isParent && !$isManual ? '<span data-uk-nav-parent-icon></span>' : '') .
				'</a>';
				if($isParent) {
					$out .= '<ul' . (!$isManual ? ' class="uk-nav-sub sitedocs-nav-sub" data-uk-nav="multiple: true"' : '') . '>' .
						($isParent && $item['hasContent'] ?
							'<li class="' . implode(' ', $overviewClasses) . '">' .
								'<a href="' . $url . '">' .
									$this->_('Overview') .
								'</a>' .
							'</li>' :
							''
						) .
						$this->renderNavItems($childItems, $isManual) .
					'</ul>';
				}
			$out .= '</li>';
		}

		return $out;
	}

	/**
	 * Render the current documentation page
	 *
	 * @param Page|null $page Page to render, or the last page passed to executeView() if omitted
	 * @return string
	 *
	 */
	public function ___renderPage(?Page $page = null) {

		if($page === null) $page = $this->pageView;
		if(!$page || !$page->id) return '';

		$content = $this->siteDocs->getContent($page);
		return $content ?
			'<article class="sitedocs-page">' .
				'<div class="sitedocs-content">' . $content . '</div>' .
			'</article>' :
			'';
	}

	/**
	 * Render the main view for a single documentation page
	 *
	 * @param string $content
	 * @return string
	 *
	 */
	public function ___renderView($content = '') {

		$sanitizer = $this->wire()->sanitizer;
		$user = $this->wire()->user;

		$siteDocs = $this->siteDocs;
		$pageIndex = $siteDocs->getIndexPage();
		$pageView = $this->pageView;

		$templatePageName = SiteDocs::templatePage;
		$isView = $pageView && $pageView->template->name === $templatePageName;
		$processUrl = $this->wire()->page->url;

		$thisPage = $isView ? $pageView : $pageIndex;
		$thisPage->of(true); // enable output formatting for this page

		// Whether the current user can edit this page — used both to show the
		// edit/add links below, and to decide whether heading copy-link icons
		// are added to the content further down.
		$userCanEdit = $thisPage->editable();

		// Set the page title and headline
		// Headline is hidden but still set for accessibility purposes
		$indexTitle = $sanitizer->entities1($pageIndex->title);
		$title = $sanitizer->entities1($this->getSiteName()) . ' - ' . $indexTitle;
		$this->headline($title);

		$browserTitle = $title;
		if($isView && $thisPage->title) {
			$browserTitle = $sanitizer->entities1($thisPage->title) . " - $title";
		}
		$this->browserTitle($browserTitle);

		// Set the breadcrumb navigation
		$this->breadcrumb($processUrl, $indexTitle);
		if($isView && $pageView->parent->template->name === $templatePageName) {

			$ancestors = [];
			$p = $pageView->parent;
			while($p && $p->id && $p->template->name === $templatePageName) {
				$ancestors[] = $p;
				$p = $p->parent;
			}

			if(count($ancestors)) {
				foreach(array_reverse($ancestors) as $ancestor) {
					$this->breadcrumb($ancestor->urlViewSiteDoc, $sanitizer->entities1($ancestor->getFormatted('title')));
				}
			}
		}

		// Render the navigation section
		$nav = '<ul class="uk-nav uk-nav-default sitedocs-nav">' .
			'<li>' .
				'<ul class="uk-nav-sub" data-uk-nav="multiple: true">' .
					$this->renderNav($this->siteDocsNav) .
				'</ul>' .
			'</li>' .
		'</ul>';

		// Replace h1 in content with h2 for proper on-page navigation
		$content = preg_replace('/<h1(.*?)>(.*?)<\/h1>/', '<h2$1>$2</h2>', $content);

		// Generate the on-page navigation items based on the content headings
		$onPageNavItems = [];
		if(extension_loaded('dom')) {

			// Parse the page content to generate on page nav
			$dom = $this->domLoad($content);

			$xpath = new \DOMXPath($dom);

			$_anchorLinkHeading = function($dom, $node) {
				$fragment = $dom->createDocumentFragment();
				$fragment->appendXML('<div class="uk-position-relative sitedocs-anchor-heading">' .
					$dom->saveHTML($node) .
					'<a class="sitedocs-anchor-link" href="#' . $node->getAttribute('id') . '" aria-hidden="true">' .
						wireIconMarkup('link') .
					'</a>' .
				'</div>');
				if($node->nextSibling === null) {
					$newNode = $node->parentNode->appendChild($fragment);
				} else {
					$newNode = $node->parentNode->insertBefore($fragment, $node->nextSibling);
				}
				$node->parentNode->removeChild($node);
				return $newNode;
			};

			// Top-level query: all h2 elements
			$h2s = $xpath->query('//h2');
			$i = 0;
			foreach($h2s as $h2) {

				$i++;

				$h2Title = trim($h2->textContent);
				$h2Id = $sanitizer->pageName("$i-$h2Title");

				$h2->setAttribute('id', $h2Id);

				if($userCanEdit) {
					$h2 = $_anchorLinkHeading($dom, $h2);
				}

				$children = [];

				$h3s = [];
				$node = $h2->nextSibling;

				while($node !== null) {
					if($node->nodeType === XML_ELEMENT_NODE) {
						if($node->nodeName === 'h2') {
							break; // hit the next section, stop
						}
						if($node->nodeName === 'h3') {
							$h3s[] = $node;
						}
					}
					$node = $node->nextSibling;
				}

				$j = 0;
				foreach($h3s as $h3) {

					$j++;

					$h3Title = trim($h3->textContent);
					$h3Id = $sanitizer->pageName("$i-$j-$h3Title");

					$h3->setAttribute('id', $h3Id);

					if($userCanEdit) {
						$_anchorLinkHeading($dom, $h3);
					}

					$children[] = [
						'id' => $h3Id,
						'title' => $h3Title,
					];
				}

				$onPageNavItems[] = [
					'id' => $h2Id,
					'title' => $h2Title,
					'children' => $children,
				];
			}

			// Add styling classes to all tables
			$tables = $dom->getElementsByTagName('table');
			foreach($tables as $table) {
				$table->setAttribute('class', trim(($table->getAttribute('class') . ' uk-table uk-table-justify sitedocs-content-table')));
			}

			// Wrap all tables in <div class="uk-overflow-auto">
			foreach($tables as $table) {
				$wrapper = $dom->createElement('div');
				$wrapper->setAttribute('class', 'uk-overflow-auto');
				$table->parentNode->insertBefore($wrapper, $table);
				$wrapper->appendChild($table);
			}

			$content = $this->domSave($dom);
		}

		$children = $this->pageChildren($thisPage);
		if($children->count()) {

			$sectionHeading = $isView ? $this->_('In this section') : $this->_('Table of Contents');
			$sectionId = $sanitizer->pageName($sectionHeading);

			$content .= '<h2 id="' . $sectionId . '">' . $sectionHeading . '</h2>';
			$content .= '<ul>';
			foreach($children as $child) {
				$content .= '<li><a href="' . $child->urlViewSiteDoc . '">' . $sanitizer->entities1($child->getFormatted('title')) . '</a></li>';
			}
			$content .= '</ul>';

			$onPageNavItems[] = [
				'id' => $sectionId,
				'title' => $sectionHeading,
				'children' => [],
			];
		}

		$onPageNav = '';
		foreach($onPageNavItems as $item) {
			$onPageSubNav = '';
			if(count($item['children'])) {
				$onPageSubNav .= '<ul class="uk-nav-sub">';
				foreach($item['children'] as $subsection) {
					$onPageSubNav .= '<li><a href="#' . $subsection['id'] . '">' . $sanitizer->entities1($subsection['title']) . '</a></li>';
				}
				$onPageSubNav .= '</ul>';
			}
			$onPageNav .= '<li' . (count($item['children']) ? ' class="uk-parent"' : '') . '>' .
				'<a href="#' . $item['id'] . '">' . $sanitizer->entities1($item['title']) . '</a>' .
				$onPageSubNav .
			'</li>';
		}

		if(!$isView) {
			// Add the print manual button
			$content .= '<div class="uk-margin-medium-top no-print">' .
				'<a href="' . $this->wire()->page->url . 'manual/" class="uk-button uk-button-primary">' .
					wireIconMarkup('print') . ' ' .
					$this->_('Print Manual') .
				'</a>' .
			'</div>';
		}

		$content = '<div class="uk-margin-medium-bottom sitedocs-page-content-page">' . $content . '</div>';

		if($isView) {

			$prev = $this->filterItems($pageView->prevAll('check_access=0'))->first;
			$next = $this->filterItems($pageView->nextAll('check_access=0'))->first;

			if($prev || $next) {

				$content .= '<hr class="no-print">';

				$_dirLink = function(Page $dirPage, $dir) use ($sanitizer) {

					$icon = '<span>' . wireIconMarkup("arrow-$dir") . '</span>';
					return '<a class="uk-flex uk-flex-middle uk-flex-' . $dir . ' uk-text-' . $dir . ' sitedocs-' . ($dir === 'left' ? 'prev' : 'next') . '" href="' . $dirPage->urlViewSiteDoc . '">' .
						($dir === 'left' ? $icon : '') .
						'<span>' .
							'<span class="uk-text-meta">' . ($dir === 'left' ?
								$this->_('Previous') :
								$this->_('Next')) .
							'</span>' .
							'<br>' .
							'<span>' . $sanitizer->entities1($dirPage->getFormatted('title')) . '</span>' .
						'</span>' .
						($dir === 'right' ? $icon : '') .
					'</a>';
				};

				$content .= '<nav class="uk-grid uk-grid-small uk-child-width-1-2 uk-flex-between uk-margin-medium-top uk-margin-medium-bottom sitedocs-prevnext no-print" data-uk-grid>';
					$content .= '<div>';
					if($prev) {
						$content .= $_dirLink($prev, 'left');
					}
					$content .= '</div>';
					$content .= '<div>';
					if($next) {
						$content .= $_dirLink($next, 'right');
					}
					$content .= '</div>';
				$content .= '</nav>';
			}
		}

		if($userCanEdit) {

			$content .= '<hr class="no-print">';

			if($thisPage->modifiedUser && $thisPage->modifiedUser->id) {
				$content .= '<p class="uk-text-meta sitedocs-modified no-print">' .
					sprintf(
						$this->_('Last updated %1$s by %2$s'),
						$this->wire()->datetime->relativeTimeStr($thisPage->modified),
						$sanitizer->entities1($thisPage->modifiedUser->get('title|name'))
					) .
				"</p>";
			}

			$content .= '<div class="uk-grid uk-grid-small uk-child-width-auto no-print" data-uk-grid>' .
				'<div class="sitedocs-edit">' .
					'<a href="' . $thisPage->editUrl . '" class="uk-button uk-button-text uk-button-small">' .
						wireIconMarkup('pencil') . ' ' .
						$this->_('Edit this page') .
					'</a>' .
				'</div>' .
				($thisPage->addable() ?
					'<div class="sitedocs-add">' .
						'<a href="' .
							$this->wire()->config->urls->admin .
								'page/add/?parent_id=' . $thisPage->id .
								'&template_id=' . $this->wire()->templates->get(SiteDocs::templatePage)->id .
						'" class="uk-button uk-button-text uk-button-small">' .
							wireIconMarkup('plus-circle') . ' ' .
							$this->_('Add a child page') .
						'</a>' .
					'</div>' :
					'') .
			'</div>';
		}

		return '<div class="sitedocs-page uk-margin-top">' .
			'<div class="uk-grid" data-uk-grid>' .
				'<div class="uk-width-1-1 uk-hidden@m no-print">' .
					'<button class="uk-button uk-button-default uk-width-1-1 uk-flex uk-flex-between uk-flex-middle sitedocs-mobile-toggle" type="button" data-uk-toggle="target: #sitedocs-mobile-nav">' .
						'<span>' . $indexTitle . '</span>' .
						wireIconMarkup('bars') .
					'</button>' .
				'</div>' .
				'<div class="uk-width-1-4@m uk-visible@m no-print">' .
					'<div class="sitedocs-nav uk-sticky uk-overflow-auto" data-uk-sticky="offset: 80; media: 640; bottom: #content">' .
						$nav .
					'</div>' .
				'</div>' .
				'<div class="uk-width-1-1 uk-width-expand@m">' .
					'<div class="sitedocs-page-content">' .
						'<div class="sitedocs-page-content-main">' .
							'<h2 class="uk-h1">' . ($isView ?
								$sanitizer->entities1($pageView->title) :
								$title
							) . '</h2>' .
							$content .
						'</div>' .
					'</div>' .
				'</div>' .
				'<div class="uk-width-1-5@m uk-visible@m no-print">' .
					($onPageNav ?
						'<div class="sitedocs-page-nav uk-sticky" data-uk-sticky="offset: 80; media: 960; bottom: #content">' .
							'<ul class="uk-nav uk-nav-default" data-uk-scrollspy-nav="closest: li; scroll: true; offset: 20; overflow: true">' .
								'<li class="uk-nav-header">' . $this->_('On this page') . '</li>' .
								'<ul class="uk-nav-sub">' .
									'<li>' . $onPageNav . '</li>' .
								'</ul>' .
							'</ul>' .
						'</div>' :
						''
					) .
				'</div>' .
			'</div>' .
		'</div>' .
		'<div id="sitedocs-mobile-nav" class="uk-modal-full" data-uk-modal>' .
			'<div class="uk-modal-dialog uk-height-1-1">' .
				'<button class="uk-modal-close-full" type="button" uk-close></button>' .
				'<div class="uk-modal-body uk-height-1-1" data-uk-overflow-auto>' .
					'<div class="uk-modal-title uk-margin-bottom">' .
						$title .
					'</div>' .
					str_replace('uk-nav-default', 'uk-nav-primary', $nav) .
				'</div>' .
			'</div>' .
		'</div>';
	}

	/**
	 * Helpers
	 *
	 */

	/**
	 * Add an error message to the user's session and redirect to the module root
	 *
	 * @param string $text
	 * @param int $flags
	 * @return $this
	 *
	 */
	public function errorRedirect($text, $flags = 0) {
		$this->error($text, $flags);
		$this->wire()->session->location($this->wire()->page->url);
		return $this;
	}

	/*
	 * Get the first image from the index page
	 *
	 * @return Pageimage|null
	 *
	 */
	public function ___getIndexImage() {
		$pageIndex = $this->siteDocs->getIndexPage();
		if($pageIndex->images->count()) {
			return $pageIndex->images->first();
		}
		return null;
	}

	/**
	 * Get the site name
	 *
	 * @return string
	 *
	 */
	public function ___getSiteName() {
		return $this->siteName ?: $this->wire()->config->httpHost;
	}

	/**
	 * Filter a PageArray to only include items the current user can view
	 *
	 * @param PageArray $items
	 * @return PageArray
	 *
	 */
	private function filterItems(PageArray $items) {
		foreach($items as $item) {
			if(!$this->siteDocs->userCanView($item)) {
				$items->remove($item);
			}
		}
		return $items;
	}

	/**
	 * Build a menu item for the documentation tree
	 *
	 * @param Page $page
	 * @return array
	 *
	 */
	private function menuItem(Page $page) {

		$siteDocs = $this->siteDocs;

		$item = [
			'id' => $page->id,
			'url' => $page->urlViewSiteDoc,
			'title' => $page->getFormatted('title'),
			'items' => [],
			'hasContent' => !empty($siteDocs->getContent($page)),
		];
		foreach($this->pageChildren($page) as $child) {
			$item['items'][] = $this->menuItem($child);
		}

		return $item;
	}

	/**
	 * Load HTML content into a DOMDocument with a root wrapper
	 *
	 * @param string $content
	 * @return \DOMDocument
	 *
	 */
	private function domLoad($content) {
		$dom = new \DOMDocument();
		$prev = libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="utf-8" ?><div id="__root__">' . $content . '</div>');
		libxml_clear_errors();
		libxml_use_internal_errors($prev);
		return $dom;
	}

	/**
	 * Save the content of a DOMDocument, stripping the root wrapper
	 *
	 * @param \DOMDocument $dom
	 * @return string
	 *
	 */
	private function domSave(\DOMDocument $dom) {
		$root = $dom->getElementById('__root__');
		if(!$root) {
			return '';
		}
		$result = '';
		foreach($root->childNodes as $child) {
			$result .= $dom->saveHTML($child);
		}

		return $result;
	}

	/**
	 * Get the children of a page that the current user can view
	 *
	 * @param Page $page
	 * @return PageArray
	 *
	 */
	private function pageChildren(Page $page) {
		return $this->filterItems($page->children('check_access=0'));
	}

	/*
	 * Install / uninstall
	 *
	 */

	/**
	 * Install the module's admin page
	 *
	 * @throws WireException
	 *
	 */
	public function ___install() {
		$this->wire()->cache->deleteFor($this);
		parent::___install();
	}

	/**
	 * Uninstall the module's admin page
	 *
	 */
	public function ___uninstall() {
		$this->wire()->cache->deleteFor($this);
		parent::___uninstall();
	}
}
