<?php namespace ProcessWire;

/**
 * Site Docs
 *
 * #pw-summary Implements internal site documentation management.
 *
 * #pw-var $siteDocs
 *
 * #pw-instantiate $siteDocs = $modules->get('SiteDocs');
 *
 * #pw-body =
 * Manages a hierarchy of internal site documentation pages (index > page > page),
 * with per-page role-based view restrictions, a print-friendly manual and simple search.
 *
 * See SiteDocs.info.php for module info, and API.md for full API documentation.
 * #pw-body
 *
 * @copyright 2026 NB Communication Ltd
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 *
 */
class SiteDocs extends WireData implements Module {

	const templateIndex = 'sitedocs-index';
	const templatePage = 'sitedocs-page';
	const fieldRoles = 'sitedocs_roles';

	/**
	 * @var string The Process module admin path
	 *
	 */
	protected $processPath = '';

	/**
	 * Init
	 *
	 */
	public function init() {

		$this->processPath = $this->wire()->cache->getFor(
			$this,
			'processPath',
			WireCache::expireNever,
			function() {
				$processSiteDocs = $this->wire()->pages->get('process=ProcessSiteDocs');
				return $processSiteDocs->id ? $processSiteDocs->path : false;
			}
		);

		if(!$this->processPath) return;

		$this->addHookProperty('Page(template=' . self::templateIndex . '|' . self::templatePage . ')::urlViewSiteDoc', function(HookEvent $event) {
			$page = $event->object;
			if(
				$this->isSiteDocsPage($page) &&
				!in_array((string) ($this->wire()->page->process ?? ''), [
					'ProcessPageAdd',
					'ProcessPageClone',
					'ProcessPageEdit',
					'ProcessPagesExportImport',
					'ProcessPageSort',
				])
			) {
				$event->return = "{$this->processPath}view/?id={$page->id}";
			}
		});

		// Add custom search results for site docs pages
		$this->addHook('ProcessPageSearchLive::findCustom', function(HookEvent $event) {

			$data = $event->arguments(0); // array — includes 'q' (query string)
			$search = $event->object;     // ProcessPageSearchLive instance
			$group = $this->_('Docs');    // heading shown in the dropdown

			// Only return these results within the site docs pages
			$result = strpos($_SERVER['HTTP_REFERER'] ?? '', $this->processPath) === false;

			$items = null;
			$query = $data['q'];
			if($query) {

				$items = $this->findCustom($query);
				if($items->count) {

					foreach($items as $item) {

						$summary = $event->wire()->sanitizer->markupToText($this->getContent($item));
						$truncateType = 'sentence';
						$maxLength = 300;

						$pos = stripos($summary, $query);
						if(stripos($item->title, $query) === false && $pos !== false && strlen($summary) > $maxLength) {
							// The subtitle should be a small excerpt from around where the query string appears in the content
							$start = max(0, $pos - 10);
							$summary = ($start ? '...' : '') . substr($summary, $start);
							$truncateType = 'word';
						}

						$search->addResult($group, $item->title, $item->urlViewSiteDoc, [
							'id' => $item->id,
							'name' => $item->name,
							'subtitle' => $item->parent->template->name === self::templatePage ? $item->parent->title : '',
							'summary' => $event->wire()->sanitizer->truncate($summary, [
								'type' => $truncateType,
								'maxLength' => $maxLength,
							]),
							'icon' => 'info-circle',
						]);
					}

				} else {
					$result = true;
				}
			}

			$event->return = $result;
		});
	}

	/**
	 * Is the given page part of the site documentation?
	 *
	 * @param Page $page
	 * @return bool
	 *
	 */
	public function isSiteDocsPage(Page $page) {
		return in_array($page->template->name, [self::templateIndex, self::templatePage]) && !$page->isTrash();
	}

	/**
	 * Access control
	 *
	 */

	/**
	 * Return the roles (PageArray) that restrict view access to $page, walking up ancestors
	 *
	 * Returns null if the page (and none of its ancestors) has any roles set, meaning
	 * the page is unrestricted for any user permitted to use the module.
	 *
	 * @param Page $page
	 * @return PageArray|null
	 *
	 */
	public function pageAllowedRoles(Page $page) {
		$p = $page;
		while($p && $p->id) {
			if($this->isSiteDocsPage($p) && $p->template->hasField(self::fieldRoles)) {
				$roles = $p->getUnformatted(self::fieldRoles);
				if($roles && count($roles)) return $roles;
			}
			$p = $p->parent;
		}
		return null;
	}

	/**
	 * Can the given (or current) user view the given documentation page?
	 *
	 * @param Page $page
	 * @param User|null $user
	 * @return bool
	 *
	 */
	public function userCanView(Page $page, ?User $user = null) {

		$user = $user ?: $this->wire()->user;

		if($user->isSuperuser()) return true;

		if($page->isUnpublished()) return false;

		$roles = $this->pageAllowedRoles($page);
		if($roles === null) return true; // unrestricted

		foreach($roles as $role) {
			if($user->hasRole($role)) return true;
		}

		return false;
	}

	/**
	 * Search
	 *
	 */

	/**
	 * Search documentation titles/bodies, filtered by the current user's view access
	 *
	 * @param string $query
	 * @param array $contentFields The fields to search within (default: 'body')
	 * @return PageArray
	 *
	 */
	public function ___findCustom($query, array $contentFields = ['body']) {

		$pages = $this->wire()->pages;

		$selectors = [
			'template' => self::templatePage,
			'check_access' => 0, // needed as no users have view access on template, will filter manually below
			'include' => 'hidden',
		];

		$items = $pages->newPageArray();

		// Title — "contains all words" is a superset of exact/phrase matches, so a single
		// query preserves full recall while cutting query count. Match quality (exact vs
		// phrase vs scattered words) is restored below via an in-PHP re-rank instead of
		// extra queries.
		foreach($pages->find(array_merge($selectors, [
			'title~=' => $query,
		])) as $item) {
			if(!$items->has($item)) {
				$items->add($item);
			}
		}

		// Additional fields — contains all words
		foreach($contentFields as $f) {
			foreach($pages->find(array_merge($selectors, [
				$f . '~=' => $query,
			])) as $item) {
				if(!$items->has($item)) {
					$items->add($item);
				}
			}
		}

		foreach($items as $item) {
			if(!$this->userCanView($item)) {
				$items->remove($item);
			}
		}

		// Re-rank so exact/phrase title matches float above scattered-word or body-only
		// matches, since a single '~=' query no longer distinguishes match quality via
		// query order. PHP 8's usort() is stable, so items within the same rank keep
		// their original (title-then-body, insertion) order.
		$itemsArray = $items->getArray();
		usort($itemsArray, function(Page $a, Page $b) use ($query) {
			$rank = function(Page $item) use ($query) {
				$title = (string) $item->title;
				if(strcasecmp($title, $query) === 0) return 0; // exact title match
				if(stripos($title, $query) !== false) return 1; // title contains phrase
				return 2; // scattered words in title, or body-only match
			};
			return $rank($a) <=> $rank($b);
		});

		$result = $pages->newPageArray();
		$result->import($itemsArray);

		return $result;
	}

	/**
	 * Helpers
	 *
	 */

	/**
	 * Get the content of a documentation page
	 *
	 * @param Page $page
	 * @return string
	 *
	 */
	public function ___getContent(Page $page) {
		return $page->get('body');
	}

	/**
	 * Get the Site Docs index page
	 *
	 * @return Page|NullPage
	 *
	 */
	public function getIndexPage() {
		return $this->wire()->pages->get('template=' . self::templateIndex . ', include=hidden');
	}

	/**
	 * Install / uninstall
	 *
	 */

	/**
	 * Install fields, templates, permission, and sample pages, then the module's admin page
	 *
	 * @throws WireException
	 *
	 */
	public function ___install() {

		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$pages = $this->wire()->pages;
		$templates = $this->wire()->templates;

		// Remove caches for this module if they exist
		$this->wire()->cache->deleteFor($this);

		// Install fields
		// Create the body/images/site_docs_roles fields if they don't already exist

		$fieldRolesName = self::fieldRoles;

		if(!$fields->get('body')) {
			$f = new Field();
			$f->type = $modules->get('FieldtypeTextarea');
			$f->name = 'body';
			$f->label = 'Body';
			$f->icon = 'pencil';
			$f->rows = 15;
			if($modules->isInstalled('InputfieldTinyMCE')) {
				$f->inputfieldClass = 'InputfieldTinyMCE';
			} else if($modules->isInstalled('InputfieldCKEditor')) {
				$f->inputfieldClass = 'InputfieldCKEditor';
			} else {
				$f->inputfieldClass = 'InputfieldTextarea';
			}
			$f->save();
			$this->message('Created field: body');
		} else if(!($fields->get('body')->type instanceof FieldtypeTextarea)) {
			throw new WireException('Field "body" exists but is not of type Textarea.');
		}

		if(!$fields->get('images')) {
			$f = new Field();
			$f->type = $modules->get('FieldtypeImage');
			$f->name = 'images';
			$f->label = 'Images';
			$f->icon = 'picture-o';
			$f->maxFiles = 0;
			$f->extensions = 'jpg jpeg gif png';
			$f->save();
			$this->message('Created field: images');
		} else if(!($fields->get('images')->type instanceof FieldtypeImage)) {
			throw new WireException('Field "images" exists but is not of type Image.');
		}

		if(!$fields->get($fieldRolesName)) {
			$f = new Field();
			$f->type = $modules->get('FieldtypePage');
			$f->name = $fieldRolesName;
			$f->label = 'Visible to roles';
			$f->description = 'If no roles are selected here, view access is inherited from the parent page(s). Leave all levels empty to make a page unrestricted for any Site Docs user.';
			$f->notes = 'The superuser will always have access regardless of the roles selected.';
			$f->icon = 'user';
			$f->collapsed = 2;
			$f->parent_id = $this->wire()->config->rolesPageID;
			$f->labelFieldName = 'name';
			$f->inputfield = 'InputfieldCheckboxes';
			$f->derefAsPage = FieldtypePage::derefAsPageArray;
			$f->findPagesSelect = 'name!=guest, template=' . $this->wire()->templates->get('role')->id;
			$f->save();
			$this->message('Created field: ' . $fieldRolesName);
		} else if(!($fields->get($fieldRolesName)->type instanceof FieldtypePage)) {
			throw new WireException(sprintf('Field "%s" exists but is not of type Page.', $fieldRolesName));
		}

		// Install Templates
		// Create the sitedocs-index/page templates if they don't already exist

		$templateIndexName = self::templateIndex;
		$templatePageName = self::templatePage;

		$specs = [
			$templateIndexName => [
				'fields' => ['title', 'body', 'images'],
				'settings' => [
					'label' => 'Site Docs Index',
					'icon' => 'question-circle',
					'noParents' => -1,
				],
			],
			$templatePageName => [
				'fields' => ['title', 'body', 'images', $fieldRolesName],
				'settings' => [
					'label' => 'Site Docs Page',
					'icon' => 'info-circle',
				],
			],
		];

		foreach($specs as $name => $spec) {
			if($templates->get($name)) continue;

			$fg = new Fieldgroup();
			$fg->name = $name;
			foreach($spec['fields'] as $fieldName) {
				$field = $fields->get($fieldName);
				if($field) $fg->add($field);
			}
			$fg->save();

			$t = new Template();
			$t->name = $name;
			$t->fieldgroup = $fg;
			$t->useRoles = 1;
			$t->set('roles', []);
			$t->set('editRoles', []);
			$t->set('createRoles', []);
			$t->set('addRoles', []);
			foreach(($spec['settings'] ?? []) as $key => $value) {
				$t->$key = $value;
			}
			$t->save();

			$this->message(sprintf('Created template: %s', $name));
		}

		$templateIndex = $templates->get($templateIndexName);
		if(!$templateIndex) throw new WireException(sprintf('Template "%s" could not be found.', $templateIndexName));
		$templatePage = $templates->get($templatePageName);
		if(!$templatePage) throw new WireException(sprintf('Template "%s" could not be found.', $templatePageName));

		if($templateIndex) {
			$templateIndex->childTemplates = [$templatePage->id];
			$templateIndex->save();
		}

		if($templatePage) {
			$templatePage->parentTemplates = [$templateIndex->id, $templatePage->id];
			$templatePage->childTemplates = [$templatePage->id];
			$templatePage->save();
		}

		// Install Pages
		// Create the index page and sample content pages if the index page doesn't already exist

		$labelCreated = 'Created page: %s';

		$pageIndex = $pages->get("template={$templateIndexName}, include=hidden");
		if(!$pageIndex->id) {
			$pageIndex = $pages->new([
				'template' => $templateIndexName,
				'parent' => $pages->get('/'),
				'name' => 'sitedocs',
				'title' => 'Docs',
				'status' => Page::statusHidden,
			]);
			$this->message(sprintf($labelCreated, $pageIndex->url));
		}

		$nameGettingStarted = 'getting-started';
		$pageGettingStarted = $pages->get("template={$templatePageName}, name={$nameGettingStarted}, include=hidden");
		if(!$pageGettingStarted->id) {
			$pageGettingStarted = $pages->new([
				'template' => $templatePageName,
				'parent' => $pageIndex,
				'name' => $nameGettingStarted,
				'title' => 'Getting Started',
			]);
			$this->message(sprintf($labelCreated, $pageGettingStarted->url));
		}

		$nameHowToCreateAPage = 'how-to-create-a-page';
		$pageHowToCreateAPage = $pages->get("template={$templatePageName}, name={$nameHowToCreateAPage}, include=hidden");
		if(!$pageHowToCreateAPage->id) {
			$pageHowToCreateAPage = $pages->new([
				'template' => $templatePageName,
				'parent' => $pageGettingStarted,
				'name' => $nameHowToCreateAPage,
				'title' => 'How to Create a Page',
				'body' => '<p>This is a placeholder page — replace this content with real documentation.</p>',
			]);
			$this->message(sprintf($labelCreated, $pageHowToCreateAPage->url));
		}
	}

	/**
	 * Uninstall the ProcessSiteDocs Module
	 *
	 */
	public function ___uninstall() {
		try {
			// Remove caches for this module if they exist
			$this->wire()->cache->deleteFor($this);
			// Uninstall the ProcessSiteDocs module
			$this->wire()->modules->uninstall('ProcessSiteDocs');
		} catch(\Exception $e) {
			$this->error($e->getMessage());
		}
	}
}
