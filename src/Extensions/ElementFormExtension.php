<?php

namespace SilverStripe\SmimeForms\Extensions;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

class ElementFormExtension extends DataExtension
{

    private static array $db = [
        'UseEncryption' => 'Boolean(0)',
    ];

    public function updateCMSFields(FieldList $fields): FieldList
    {
        $fields->addFieldsToTab('Root.FormOptions', [
            CheckboxField::create('UseEncryption', 'Use SMIME encryption when sending submission emails'),
        ]);

        return $fields;
    }

    /**
     * Check whether form submission emails should be encrypted.
     *
     * @return bool
     */
    public function encryptEmail(): bool
    {
        return $this->owner->UseEncryption;
    }

}
