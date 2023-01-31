<?php

namespace SilverStripe\SmimeForms\Extensions;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\UserForms\Model\Recipient\EmailRecipient;

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

    /**
     * Define has-one relationships
     */
    private static array $has_one = [
        'EncryptionCrt' => File::class, // The certificate to be used for encrypting email data
    ];

    /**
     * Define ownership (e.g., for publishing)
     */
    private static array $owns = [
        'EncryptionCrt',
    ];

    /**
     * Folder in which uploaded encryption certificates will be stored
     */
    private static string $uploadFolder = 'SmimeCertificates';

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
        $fields->insertAfter(
            'EmailBody',
            UploadField::create('EncryptionCrt', 'S/MIME Encryption Certificate')
                ->setFolderName(self::$uploadFolder)
                ->setAllowedExtensions(['crt'])
                ->setDescription('Upload a valid .crt file for this recipient email address. '
                    . 'This can be either a self-signed certificate or one purchased from a '
                    . 'recognised Certificate Authority.')
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
