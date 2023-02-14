<?php

namespace SilverStripe\SmimeForms\Extensions;

use Sheadawson\Linkable\Forms\LinkField;
use Sheadawson\Linkable\Models\Link;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Permission;

class SiteConfigExtension extends DataExtension
{

    /**
     * @var array
     */
    private static $db = [
        'EmailSigningPhrase' => 'Varchar(255)',
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'EmailSigningCertificate' => File::class,
        'EmailSigningKey' => File::class,
    ];

    /**
     * @var array
     */
    private static $owns = [
        'EmailSigningCertificate',
        'EmailSigningKey',
    ];

}
