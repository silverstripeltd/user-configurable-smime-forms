<?php

namespace SilverStripe\SmimeForms\Extensions;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * Class ElementFormExtension
 *
 * An extension for the {@see ElementForm} and {@see UserDefinedForm} classes to provide an option for
 * encrypting form submission emails.
 *
 * @package SilverStripe\SmimeForms\Extensions
 */
class FormEmailEncryptionExtension extends DataExtension
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
        $fields->unshift(
            Wrapper::create($encryptionMessage = LiteralField::create(
                'EmailEncryptionMessage',
                '<p class="message good">'
                . 'Email encryption enabled: Ensure that any email recipients have valid encryption certificates.</p>'
            ))
        );

        $fields->addFieldsToTab('Root.FormOptions', [
            CheckboxField::create('UseEncryption', 'Enable S/MIME Encryption')
                ->setDescription('Enabling this will encrypt form submission emails. Encryption certificates'
                    . ' will need to be uploaded for each recipient.'),
        ]);

        $encryptionMessage->displayIf('UseEncryption')->isChecked();

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
