<?php

namespace SilverStripe\SmimeForms\Model;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Forms\CompositeValidator;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\SmimeForms\Admin\EncryptionAdmin;

class SmimeEncryptionCertificate extends DataObject
{

    /**
     * @var string
     */
    private static $table_name = 'SmimeEncryptionCertificate';

    private static $singular_name = 'Encryption Certificate';

    private static $plural_name = 'Encryption Certificates';

    private static $url_segment = 'encryption';
    /**
     * @var array
     */
    private static $db = [
        'EmailAddress' => 'Varchar(80)',
    ];

    private static $casting = [
        'EncryptionCertificate' => 'Varchar(255)'
    ];

    public function getEncryptionCertificate()
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
     * @var array
     */
    private static $summary_fields = [
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

    public function validate() {
        $result = parent::validate();
        $existing = SmimeEncryptionCertificate::get()->filter('EmailAddress', $this->EmailAddress)->first();

        if ($existing && $existing->ID !== $this->ID) {
            $result->addError('There is already an entry with this email address.');
        }

        return $result;
    }

    public function canView($member = null)
    {
        return Permission::check(EncryptionAdmin::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

    public function canEdit($member = null)
    {
        return Permission::check(EncryptionAdmin::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check(EncryptionAdmin::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

}
