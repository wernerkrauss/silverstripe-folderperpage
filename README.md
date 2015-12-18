silverstripe-folderperpage
==========================

[![Build Status](https://travis-ci.org/wernerkrauss/silverstripe-folderperpage.svg?branch=master)](https://travis-ci.org/wernerkrauss/silverstripe-folderperpage)

Extension that creates a folder per page or dataobject

## Requirements

* [`Silverstripe 3.1.* framework`](https://github.com/silverstripe/silverstripe-framework)
* [`Silverstripe 3.1.* CMS`](https://github.com/silverstripe/cms)

## Installation

Download and install manually or use composer.

Make sure you add the extension to the classes you want to decorate. Add this to your config.yml to add it to all
Pages:

```yml
Page:
  extensions:
    ['RootFolder']
```
## Configuration

You can exclude several page types. Just use the RootFolder.ignored_classes config value. The module's config.yml has
some default values to get you started:

```yml
RootFolder:
  ignored_classes:
    ['VirtualPage', 'ErrorPage']
  create_folder_for_translations: false
  folder_root: 'Articles'
```

Of course every decorated class can have a seperate folder root, e.g. "Articles" for all pages and "News" for a
news data object. Just add the folder_root config to your object.

## Update your forms

You can use ```$this->getRootFoldername()``` to set the upload folder.

Given you have a has_one relation "Avatar" => "Image" you can add the folder to the UploadField:

```php
function getCMSFields() {
    /*...*/
    $pic = UploadField::create('Avatar', 'Your Avatar');
    $fields->addFieldToTab('Root.Main', $pic);

    $pic->setFolderName($this->getRootFolderName());
    /*...*/
}
```

## TODO
* ~~make it work for other dataobjects~~
* ~~unit tests~~
* add function that automatically updates all UploadFields and BulkUploads to use this folder in a form
* add support for subsites module, e.g. a master root per subsite
* task for updating / checking all existing pages
* ~~check if page is moved~~
  * should it move the rootfolder automatically or by switch? May be a timeout problem with very large trees
