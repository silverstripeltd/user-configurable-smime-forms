<?php

namespace SilverStripe\SmimeForms\Extensions;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CompositeValidator;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\DataExtension;

/**
 * Class EmailRecipientExtension
 *
 * An extension for {@see EmailRecipient} to allow the addition of an S/MIME encryption certificate
 * to a user form email recipient.
 *
 * @package SilverStripe\SmimeForms\Extensions
 */
class EmailRecipientExtension extends DataExtension
{

}
