<?php

/**
 * Adds a RootFolder to a Page for using it as default upload folder for all related stuff.
 *
 *
 * @package FolderPerPage
 * @author Werner Krauß
 */
class RootFolder extends DataExtension
{
	private static $has_one = array(
		'RootFolder' => 'Folder',
	);

	/**
	 * @var array exclude this page types and class names
	 */
	private static $ignored_classes = array('VirtualPage', 'ErrorPage');

	/**
	 * @var bool should folders be created for translated objects?
	 */
	private static $create_folder_for_translations = false;

	/**
	 * @var string default root for all folders; may be overwritten in config of decorated class
	 */
	private static $folder_root = 'Articles';

	function onAfterWrite()
	{
		if ($this->owner->ID) {
			$this->checkFolder();
		}
	}

	/**
	 * Creates a folder for a page as a subfolder of the parent page
	 * You can exclude page types by setting $ignored_classes in config
	 *
	 * Doesn't create folders for translated pages by default.
	 *
	 * @TODO doesn't check if page is moved to another parent
	 */
	function checkFolder()
	{
		$ignoredPageTypes = Config::inst()->get($this->class, 'ignored_classes');

		foreach ($ignoredPageTypes as $pagetype) {
			if (is_a($this->owner, $pagetype)) {
				return;
			}
		}

		if (class_exists('Translatable')
			&& $this->owner->Locale !== Translatable::default_locale()
			&& !Config::inst()->get($this->class, 'create_folder_for_translations')
		) {
			return;
		}


		if (!$this->owner->RootFolderID) {
			//get path to parent folder
			$parent = $this->owner->getParent();
			if (is_a($parent, 'Page') && $parentFolder = $parent->RootFolder()) {
				$folderRoot = $parentFolder->getRelativePath();
				$folderRoot = str_replace(ASSETS_DIR . '/', '', $folderRoot);
			} else {
				$folderRoot = $this->getFolderRoot() . '/';
			}

			if ($folderRoot == '/') {
				$folderRoot = getFolderRoot() . '/';
			}

			$folder = Folder::find_or_make($folderRoot . $this->owner->URLSegment);
			$folder->Title = $this->owner->Title;
			$folder->setName($this->owner->URLSegment);
			$folder->write();

			$this->owner->RootFolderID = $folder->ID;
		} else {
			if ($this->owner->isChanged('URLSegment')) {
//				$this->owner->RootFolder()->Title = $this->owner->Title;
				$this->owner->RootFolder()->setName($this->owner->URLSegment);
				$this->owner->RootFolder()->write();
			}
		}

	}

	/**
	 * Returns the folder root for the current root folder, e.g. 'Articles',
	 * if a config $folder_root is defined in the decorated class.
	 *
	 * Falls back to global config
	 */
	public function getFolderRoot()
	{
		return ($this->owner->config()->get('folder_root'))
			? $this->owner->config()->get('folder_root')
			: Config::inst()->get($this->class, 'folder_root');
	}
}