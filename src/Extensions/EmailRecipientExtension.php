<?php

namespace SilverStripe\SmimeForms\Extensions;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

/**
 * Extension to allow the addition of an SMIME encryption certificate to a user form email recipient.
 */
class EmailRecipientExtension extends DataExtension
{

    private static array $has_one = [
        'EncryptionCrt' => File::class, // The certificate to be used for encrypting email data
    ];

    /**
     * @var array
     */
    private static array $owns = [
        'EncryptionCrt',
    ];

    /**
     * {@inheritDoc}
     */
    public function updateCMSFields(FieldList $fields): FieldList
    {
        $form = $this->getOwner()->Form();

        // Check if this form should be encrypted
        if ($form && !$form->encryptEmail()) {
            return $fields;
        }

        // Show field for uploading the encryption certificate for this recipient
        $fields->addFieldToTab(
            'Root.Encryption',
            UploadField::create('EncryptionCrt', 'Certificate for SMIME encryption')
                ->setFolderName('smime')
                ->setAllowedExtensions(['crt'])
        );

        return $fields;
    }

    public function onAfterWrite(): void
    {
        parent::onAfterWrite();

        // Check if form should send encrypted emails
        $form = $this->getOwner()->Form();

        if ($form && !$form->encryptEmail()) {
            return;
        }

        // Check if an encryption certificate file has been uploaded
        $encryptionCertificate = $this->owner->EncryptionCrt;

        if (!$encryptionCertificate) {
            return;
        }

        // Set file to protected and 'publish' it
        $encryptionCertificate->write();
        $encryptionCertificate->publishFile();
        $encryptionCertificate->publishSingle();
        $encryptionCertificate->protectFile();
    }

}
