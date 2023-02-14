<?php

namespace SilverStripe\SmimeForms\Extensions;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Forms\CompositeValidator;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\DataObject;

class SmimeEncryptionCertificate extends DataObject
{

    /**
     * @var string
     */
    private static $table_name = 'SmimeEncryptionCertificate';

    /**
     * @var array
     */
    private static $db = [
        'EmailAddress' => 'Varchar(80)',
    ];

    /**
     * Define has-one relationships
     */
    private static array $has_one = [
        'EncryptionCrt' => File::class, // The certificate to be used for encrypting email data
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'EmailAddress' => 'EmailAddress',
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
     * @inheritDoc
     */
    public function updateCMSFields(FieldList $fields): FieldList
    {
        // Show field for uploading the encryption certificate for this recipient
        $fields->add(
            UploadField::create('EncryptionCrt', 'S/MIME Encryption Certificate')
                ->setFolderName(self::$uploadFolder)
                ->setAllowedExtensions(['crt'])
                ->setDescription('Upload a valid <pre>.crt</pre> file for this recipient email address. '
                    . 'This can be either a self-signed certificate or one purchased from a '
                    . 'recognised Certificate Authority.')
        );

        return $fields;
    }

    public function onAfterWrite(): void
    {
        parent::onAfterWrite();

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

    public function validate() {
        $result = parent::validate();
        $existing = SmimeEncryptionCertificate::get()->filter('EmailAddress', $this->EmailAddress)->first();

        if ($existing) {
            $result->addError('There is already an entry with this email address.');
        }

        return $result;
    }

}
