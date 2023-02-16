<?php

namespace SilverStripe\SmimeForms\Model;

use LeKoala\Encrypt\EncryptedDBVarchar;
use LeKoala\Encrypt\HasEncryptedFields;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\PasswordField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Permission;
use SilverStripe\SmimeForms\Admin\EncryptionAdmin;

class SmimeSigningCertificate extends DataObject
{

    use HasEncryptedFields;

    /**
     * Define the database table name for this data object type.
     */
    private static string $table_name = 'SmimeSigningCertificate';

    /**
     * Define the singular name for this data object.
     */
    private static string $singular_name = 'Signing Certificate';

    /**
     * Define the plural name for this data object.
     */
    private static string $plural_name = 'Signing Certificates';

    /**
     * Define the database fields for this data object.
     */
    private static array $db = [
        'EmailAddress' => 'Varchar(80)',
        'SigningPassword' => EncryptedDBVarchar::class,
    ];

    private static array $casting = [
        'SigningCertificateFilename' => 'Varchar(255)',
        'SigningKeyFilename' => 'Varchar(255)',
    ];

    /**
     * Provides signing certificate file name for use in summary fields.
     */
    public function getSigningCertificateFilename(): string
    {
        return $this->SigningCertificate->exists() ? $this->SigningCertificate->Name : 'File not uploaded';
    }

    /**
     * Provides signing key file name for use in summary fields.
     */
    public function getSigningKeyFilename(): string
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
     * Define summary fields for use in grid field listings for this data object.
     */
    private static array $summary_fields = [
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
    public function getCMSFields(): FieldList
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

    /**
     * @inheritDoc
     * @throws ValidationException
     */
    public function onAfterWrite(): void
    {
        parent::onAfterWrite();

        // Check if a signing certificate file has been uploaded
        $this->afterWriteForAsset($this->SigningCertificate());
        $this->afterWriteForAsset($this->SigningKey());
    }

    /**
     * @inheritDoc
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();
        $existing = SmimeSigningCertificate::get()->filter('EmailAddress', $this->EmailAddress)->first();

        if ($existing && $existing->ID !== $this->ID) {
            $result->addError('There is already an entry with this email address.');
        }

        return $result;
    }

    /**
     * Make asset protected and publish it.
     *
     * @throws ValidationException
     */
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

    /**
     * @inheritDoc
     */
    public function getField($field): ?string
    {
        return $this->getEncryptedField($field);
    }

    /**
     * @inheritDoc
     */
    public function setField($fieldName, $val): SmimeSigningCertificate
    {
        return $this->setEncryptedField($fieldName, $val);
    }

    /**
     * Permissions for viewing certificates.
     *
     * @inheritDoc
     */
    public function canView($member = null): bool
    {
        return Permission::check(EncryptionAdmin::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

    /**
     * Permissions for editing certificates.
     *
     * @inheritDoc
     */
    public function canEdit($member = null): bool
    {
        return Permission::check(EncryptionAdmin::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

    /**
     * Permissions for creating certificates.
     *
     * @inheritDoc
     */
    public function canCreate($member = null, $context = []): bool
    {
        return Permission::check(EncryptionAdmin::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

}
