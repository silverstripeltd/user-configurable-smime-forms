<?php

namespace SilverStripe\SmimeForms\Model;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\SmimeForms\Traits\CertificateAdminPermissionsTrait;

class SmimeEncryptionCertificate extends DataObject
{

    use CertificateAdminPermissionsTrait;

    /**
     * Define the database table name for this data object type.
     */
    private static string $table_name = 'SmimeEncryptionCertificate';

    /**
     * Define the singular name for this data object.
     */
    private static string $singular_name = 'Encryption Certificate';

    /**
     * Define the plural name for this data object.
     */
    private static string $plural_name = 'Encryption Certificates';

    /**
     * Define the database fields for this data object.
     */
    private static array $db = [
        'EmailAddress' => 'Varchar(80)',
    ];

    private static array $casting = [
        'EncryptionCertificate' => 'Varchar(255)',
    ];

    /**
     * Provides encryption certificate file name for use in summary fields.
     */
    public function getEncryptionCertificate(): string
    {
        return $this->EncryptionCrt->Exists() ? $this->EncryptionCrt->FileFilename : 'File not uploaded';
    }

    /**
     * Define has-one relationships
     */
    private static array $has_one = [
        'EncryptionCrt' => File::class, // The certificate to be used for encrypting email data
    ];

    /**
     * Define summary fields for use in grid field listings for this data object.
     */
    private static array $summary_fields = [
        'EmailAddress' => 'Email Address',
        'EncryptionCertificate' => 'Encryption Certificate',
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

    /**
     * @ineritDoc
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();
        $existing = SmimeEncryptionCertificate::get()->filter('EmailAddress', $this->EmailAddress)->first();

        if ($existing && $existing->ID !== $this->ID) {
            $result->addError('There is already an entry with this email address.');
        }

        return $result;
    }

}
