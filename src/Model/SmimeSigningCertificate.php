<?php

namespace SilverStripe\SmimeForms\Model;

use LeKoala\Encrypt\EncryptedDBVarchar;
use LeKoala\Encrypt\HasEncryptedFields;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\PasswordField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\SmimeForms\Admin\EncryptionAdmin;

class SmimeSigningCertificate extends DataObject
{

    use HasEncryptedFields;

    /**
     * @var string
     */
    private static $table_name = 'SmimeSigningCertificate';

    private static $singular_name = 'Signing Certificate';

    private static $plural_name = 'Signing Certificates';

    private static $url_segment = 'signing';

    /**
     * @var array
     */
    private static $db = [
        'EmailAddress' => 'Varchar(80)',
        'SigningPassword' => EncryptedDBVarchar::class,
    ];

    private static $casting = [
        'SigningCertificateFilename' => 'Varchar(255)',
        'SigningKeyFilename' => 'Varchar(255)'
    ];

    public function getSigningCertificateFilename()
    {
        return $this->SigningCertificate->exists() ? $this->SigningCertificate->Name : 'File not uploaded';
    }

    public function getSigningKeyFilename()
    {
        return $this->SigningKey->exists() ? $this->SigningKey->Name : 'File not uploaded';
    }

    /**
     * Define has-one relationships
     */
    private static array $has_one = [
        'SigningCertificate' => File::class, // The certificate to be used for signing the email
        'SigningKey' => File::class,
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'EmailAddress' => 'Email Address',
        'SigningCertificateFilename' => 'Signing Certificate',
        'SigningKeyFilename' => 'Signing Key',
    ];

    /**
     * Define ownership (e.g., for publishing)
     */
    private static array $owns = [
        'SigningCertificate',
        'SigningKey',
    ];

    /**
     * Folder in which uploaded encryption certificates will be stored
     */
    private static string $uploadFolder = 'SmimeCertificates';


    /**
     * @inheritDoc
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName('SigningPassword');

        // Show field for uploading the encryption certificate for this recipient
        $fields->add(
            UploadField::create('SigningCertificate', 'S/MIME Signing Certificate')
                ->setFolderName(self::$uploadFolder)
                ->setAllowedExtensions(['crt'])
                ->setDescription('Upload a valid <strong>.crt</strong> file for this email address. '
                    . 'This can be either a self-signed certificate or one purchased from a '
                    . 'recognised Certificate Authority.')
        );

        $fields->add(
            UploadField::create('SigningKey', 'S/MIME Signing Key')
                ->setFolderName(self::$uploadFolder)
                ->setAllowedExtensions(['key'])
                ->setDescription('Upload a valid <strong>.key</strong> file for this recipient email address.')
        );

        $fields->add(
            PasswordField::create('SigningPassword', 'Key Passphrase')
                ->setDescription('This is the passphrase entered when the <strong>.key</strong> file was created. '
                    . 'This won\'t be displayed here and is stored in an encrypted form.')
        );


        return $fields;
    }

    public function onAfterWrite(): void
    {
        parent::onAfterWrite();
        // Check if a signing certificate file has been uploaded
        $this->afterWriteForAsset($this->SigningCertificate);
        $this->afterWriteForAsset($this->SigningKey);

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

    private function afterWriteForAsset(File $asset): void
    {
        if (!$asset->exists()) {
            return;
        }

        // Set file to protected and 'publish' it
        $asset->write();
        $asset->publishFile();
        $asset->publishSingle();
        $asset->protectFile();
    }

    public function getField($field)
    {
        return $this->getEncryptedField($field);
    }

    public function setField($fieldName, $val)
    {
        return $this->setEncryptedField($fieldName, $val);
    }

}
