<?php

namespace NetWerkstatt\FolderPerPage\Tests;


use Page;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use NetWerkstatt\FolderPerPage\RootFolder;
use SilverStripe\Dev\SapphireTest;



/**
 * Tests for RootFolder extension
 */
class RootFolderTest extends SapphireTest
{

    protected static $fixture_file = 'RootFolderTest.yml';

    /**
     * Check if a folder is generated and saved when a page is saved.
     */
    public function testCreateFolder()
    {
        $page = Page::create();
        $this->assertEquals(0, $page->RootFolderID, 'a new Page should not have a folder yet');

        $page->Title = 'Create Page Test';

        $page->write();

        $this->assertNotEquals(0, $page->RootFolderID, 'a page should have a folder after saving');

        $folder = $page->RootFolder();

        $this->assertEquals($page->URLSegment, $folder->Name, 'Page URLSegment and Folder Title should be the same');
        $path = $root = Config::inst()->get(RootFolder::class, 'folder_root') . '/'
                . $page->URLSegment . '/';

        $this->assertEquals(
            'create-page-test',
            $folder->getTitle(),
            'folder should be named "create-page-test"'
        );

        $parentFolder = $folder->Parent();
        $this->assertEquals(
            Config::inst()->get(RootFolder::class, 'folder_root'),
            $parentFolder->getTitle(),
            'parent folder should be folder_root config'
        );
    }

    /**
     * Checks if the folder will be updated when saving a page and changing the URLSegment
     */
    public function testUpdateFolder()
    {
        $page1 = $this->objFromFixture('Page', 'page1');
        $folder = $page1->RootFolder();

        $this->assertEquals($page1->URLSegment, $folder->getTitle(), 'Page URLSegment and Folder Title should be the same');

        $page1->URLSegment = ('updatedpage');
        $page1->write();

        $folder = $page1->RootFolder(); //reload folder after saving
        $this->assertEquals('updatedpage', $folder->getTitle(), 'Folder name should be updated after saving a page');
        $this->assertEquals(
            $page1->URLSegment,
            $folder->Name,
            'Page URLSegment and Folder Title should be the same, even after updating'
        );
    }

    /**
     * Checks if no folder is created for ignored page types, e.g. VirtualPage or ErrorPage
     */
    public function testIgnoredPageTypes()
    {
        $ignoredPageTypes = Config::inst()->get(RootFolder::class, 'ignored_classes');

        foreach ($ignoredPageTypes as $type) {
            $page = $type::create();

            $page->write();
            $this->assertEquals(
                0,
                $page->RootFolderID,
                'Ignored page type ' . $type . ' should not have a RootFolderID'
            );
        }
    }

    /**
     * Check if subpage's folder is a subfolder of parent page
     */
    public function testHierarchy()
    {
        $parent = $this->objFromFixture('Page', 'parentpage');
        $child = $this->objFromFixture('Page', 'subpage');

        //test if fixtures are set up properly
        $this->assertEquals($parent->ID, $child->ParentID, 'subpage should be a child of parentpage');

        $this->assertEquals(
            $parent->RootFolderID,
            $child->RootFolder()->ParentID,
            'rootfolder2 should be a child of rootfolder 1'
        );

        $newPage = Page::create();
        $newPage->ParentID = $parent->ID;
        $newPage->Title = 'Hierarchy Test';
        $newPage->urlSegment = 'hierarchy-test';
        $newPage->write();

        $this->assertEquals(
            $parent->RootFolderID,
            $newPage->RootFolder()->ParentID,
            'new folder should be a child of page1 folder'
        );
    }

    /**
     * Checks if getRootFolderName() works properly
     */
    public function testGetRootFolderName()
    {
        $parent = $this->objFromFixture('Page', 'parentpage');
        $child = $this->objFromFixture('Page', 'subpage');

        //test if fixtures are set up properly
        $this->assertEquals($parent->ID, $child->ParentID, 'subpage should be a child of parentpage');

        $this->assertStringEndsWith(
            $child->RootFolder()->Name . '/',
            $child->getRootFolderName(),
            'FolderName should be at the end of getRootFolderName()'
        );

        $root = Config::inst()->get(RootFolder::class, 'folder_root');
        $this->assertStringStartsWith(
            $root . '/' . $parent->RootFolder()->Name,
            $child->getRootFolderName(),
            'Parents FolderName should be at the beginning of getRootFolderName()'
        );

        $this->assertStringStartsWith(
            ASSETS_DIR,
            $child->getRootFolderName(false),
            'ASSETS_DIR should be at the beginning of getRootFolderName(false)'
        );
    }

    /**
     * Check if a duplicated page has a new folder applied
     */
    public function testDuplicatePage()
    {
        $page = $this->objFromFixture('Page', 'page1');

//        $this->assertFileExists($page->RootFolder()->getFullPath(),
//            'root folder of original page should exist on file system');

        $duplicatedPage = $page->duplicate(true);

        //we have to re-load the duplicated page, cause the RootFolder is set in onAfterWrite and the current
        //object is not aware of it

        $duplicatedPage = Page::get()->byID($duplicatedPage->ID);

        $this->assertNotEquals(
            $page->URLSegment,
            $duplicatedPage->URLSegment,
            'The duplicated page must not have the same urlsegment'
        );
        $this->assertNotEquals(
            $page->RootFolderID,
            $duplicatedPage->RootFolderID,
            'The duplicated page must not have the same root folder'
        );

//        $this->assertFileExists($page->RootFolder()->getFullPath(),
//            'root folder of original page should still exist on file system after duplication');
//        $this->assertFileExists($duplicatedPage->RootFolder()->getFullPath(),
//            'root folder of duplicated page should exist on file system');

    }

}
