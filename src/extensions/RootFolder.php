<?php

namespace NetWerkstatt\FolderPerPage\Extensions;

use SilverStripe\Assets\Folder;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\Core\Config\Config;
use SilverStripe\ErrorPage\ErrorPage;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\Hierarchy\Hierarchy;
use SilverStripe\View\Parsers\URLSegmentFilter;
use Translatable;

/**
 * Adds a RootFolder to a Page for using it as default upload folder for all related stuff.
 *
 *
 * @package FolderPerPage
 * @author Werner Krauß
 */
class RootFolder extends DataExtension
{
    private static $has_one = [
        'RootFolder' => Folder::class,
    ];

    private static $owns = [
        'RootFolder'
    ];

    /**
     * @var array exclude this page types and class names
     */
    private static $ignored_classes = [VirtualPage::class, ErrorPage::class];

    /**
     * @var bool should folders be created for translated objects?
     */
    private static $create_folder_for_translations = false;

    /**
     * @var string default root for all folders; may be overwritten in config of decorated class
     */
    private static $folder_root = 'Articles';

    /**
     * create folder and set relation
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function onBeforeWrite()
    {
        $this->checkFolder();
    }

    /**
     * Creates a folder for a page as a subfolder of the parent page
     * You can exclude page types by setting $ignored_classes in config
     *
     * Doesn't create folders for translated pages by default.
     *
     * @TODO doesn't check if page is moved to another parent
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function checkFolder()
    {
        $ignoredPageTypes = Config::inst()->get(self::class, 'ignored_classes');

        foreach ($ignoredPageTypes as $pagetype) {
            if (is_a($this->owner, $pagetype)) {
                return;
            }
        }

        if (class_exists('Translatable')
            && $this->owner->Locale !== Translatable::default_locale()
            && !Config::inst()->get(self::class, 'create_folder_for_translations')
        ) {
            return;
        }

        if (!$this->owner->RootFolderID) {
            $this->createRootFolder();
        } else {
            $this->updateRootFolder();
        }
    }

    /**
     * Does the work of creating a new RootFolder, saves the relation in the extended DataObject
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function createRootFolder()
    {
        //get path to parent folder
        $parent = $this->owner->hasExtension(Hierarchy::class)
            ? $this->owner->getParent()
            : null;
        if (is_a($parent, 'Page') && $parentFolder = $parent->RootFolder()) {
            $folderRoot = $parent->getRootFolderName();
        } else {
            //fallback to classes folder_root which is defined in your config.yml
            $folderRoot = $this->getFolderRoot() . '/';
        }

        if ($folderRoot === '/') {
            $folderRoot = $this->getFolderRoot() . '/';
        }

        $folder = Folder::find_or_make($folderRoot . $this->getOrCreateURLSegment());
        $folder->Title = $this->owner->Title;
        $folder->setTitle($this->owner->URLSegment);
        $folder->write();

        $this->owner->RootFolderID = $folder->ID;
    }

    /**
     * Returns the folder root for the current root folder, e.g. 'Articles',
     * if a config $folder_root is defined in the decorated class.
     *
     * Falls back to global config
     */
    public function getFolderRoot()
    {
        return $this->owner::config()->get('folder_root')
            ?: Config::inst()->get(self::class, 'folder_root');
    }

    /**
     * code taken from SiteTree::onBeforeWrite()
     *
     * we need $URLSegment already created and checked before there
     *
     * @return mixed
     */
    private function getOrCreateURLSegment()
    {
        // If there is no URLSegment set, generate one from Title
        if ((!$this->owner->URLSegment || $this->owner->URLSegment === 'new-page') && $this->owner->Title) {
            $this->owner->URLSegment = $this->owner->generateURLSegment($this->owner->Title);
        } else {
            if (!$this->owner->isInDB || $this->owner->isChanged('URLSegment', 2)) {
                // Do a strict check on change level, to avoid double encoding caused by
                // bogus changes through forceChange()
                $filter = URLSegmentFilter::create();
                $this->owner->URLSegment = $filter->filter($this->owner->URLSegment);
                // If after sanitising there is no URLSegment, give it a reasonable default
                if (!$this->owner->URLSegment) {
                    $this->owner->URLSegment = "page-$this->owner->ID";
                }
            }
        }

        // Ensure that this object has a non-conflicting URLSegment value.
        $count = 2;
        while (!$this->owner->validURLSegment()) {
            $this->owner->URLSegment = preg_replace('/-[0-9]+$/', null, $this->owner->URLSegment) . '-' . $count;
            $count++;
        }

        return $this->owner->URLSegment;
    }

    /**
     * Does the work of updating the folder if the URLSegment or ParentID is changed.
     * if both it does two writes...
     *
     * @todo: rethink moving subfolders as it may timeout on real large trees
     */
    protected function updateRootFolder()
    {
        $rootFolder = $this->owner->RootFolder();
        if ($this->owner->URLSegment && $this->owner->isChanged('URLSegment')) {
            $rootFolder->setTitle($this->owner->URLSegment);
            $rootFolder->write();
        }

        if ($this->owner->ParentID > 0 && $this->owner->isChanged('ParentID')) {
            $oldParentID = $rootFolder->ParentID;
            $newParentID = $this->owner->Parent()->RootFolderID;
            if ($oldParentID !== $newParentID && $newParentID !== $rootFolder->ID) {
                $rootFolder->ParentID = $newParentID;
                $rootFolder->write();
            }
        }
    }

    /**
     * check updates and rename folder if needed
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function onAfterWrite()
    {
        $this->checkFolder();
    }

    /**
     * reset $RootFolderID on a duplicated page
     * @param $originalOrClone
     */
    public function onBeforeDuplicate($originalOrClone)
    {
        if ($this->owner->ID == 0) {
            $this->owner->RootFolderID = 0;
        }
    }

    /**
     * Helper function to return the name of the RootFolder for setting in @link UploadField
     * or @link GridFieldBulkUpload
     * By default relative to /assets/
     *
     * @param bool $relativeToAssetsDir
     * @return mixed|string
     */
    public function getRootFolderName($relativeToAssetsDir = true)
    {
        if ($this->owner->RootFolderID) {
            return $relativeToAssetsDir
                ? $this->owner->RootFolder()->getFilename()
                : implode('/', [ASSETS_DIR, $this->owner->RootFolder()->getFilename()]);
        }

        //use folder root as fallback for now
        return $this->getFolderRoot();
    }
}
