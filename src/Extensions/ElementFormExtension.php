<?php

namespace SilverStripe\SmimeForms\Extensions;

use DNADesign\ElementalUserForms\Model\ElementForm;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

/**
 * Class ElementFormExtension
 *
 * An extension for the {@see ElementForm} class to provide an option for encrypting form submission emails.
 *
 * @package SilverStripe\SmimeForms\Extensions
 */
class ElementFormExtension extends DataExtension
{

    /**
     * Additional database fields to add
     */
    private static array $db = [
        'UseEncryption' => 'Boolean(0)',
    ];

    /**
     * @inheritDoc
     */
    public function updateCMSFields(FieldList $fields): FieldList
    {
        $fields->addFieldsToTab('Root.FormOptions', [
            CheckboxField::create('UseEncryption', 'Use S/MIME encryption when sending form submission emails'),
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
