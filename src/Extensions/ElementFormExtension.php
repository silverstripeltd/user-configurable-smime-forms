<?php

namespace SilverStripe\SmimeForms\Extensions;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\DataExtension;

class ElementFormExtension extends DataExtension
{

    private static array $db = [
        'UseEncryption' => 'Boolean(0)',
    ];

    public function updateCMSFields($tabbedFields)
    {
        $tabbedFields->addFieldsToTab('Root.FormOptions', [
            CheckboxField::create('UseEncryption', 'Use SMIME encryption when sending submission emails'),
        ]);
    }

    public function encryptEmail()
    {
        return $this->owner->UseEncryption;
    }

}
