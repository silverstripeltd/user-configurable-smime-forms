<?php

namespace SilverStripe\SmimeForms\Model;

use LeKoala\Encrypt\EncryptedDBVarchar;
use LeKoala\Encrypt\HasEncryptedFields;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Forms\CompositeValidator;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\PasswordField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
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

    /**
     * Define has-one relationships
     */
    private static array $has_one = [
        'SigningCertificate' => File::class, // The certificate to be used for signing the email
        'SigningKey' => File::class,
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


        $certificateKeyPassword = PasswordField::create('SigningPassword', 'Key Passphrase')
            ->setDescription('This is the passphrase entered when the <strong>.key</strong> file was created. '
                . 'This is stored in an encrypted form and, once entered, this field will be disabled.');

        // If a value is already set then show a read only field.
        // Note, we also need to rename the field otherwise the value will get handled by getEncryptedField
        if ($this->SigningPassword !== null) {
            $certificateKeyPassword = ReadonlyField::create('SigningPasswordReadOnly', 'Key Passphrase')
                ->setValue('********')
                ->performReadonlyTransformation();
        }

        // Show fields for uploading the signing certificate and key for the email address, along with signing password.
        $fields->addFieldsToTab(
            'Root.Main',
            [
                UploadField::create('SigningCertificate', 'Certificate File')
                    ->setFolderName(self::$uploadFolder)
                    ->setAllowedExtensions(['crt'])
                    ->setDescription('Upload a valid <strong>.crt</strong> file for this email address. '
                        . 'This can be either a self-signed certificate or one purchased from a '
                        . 'recognised Certificate Authority.'),
                UploadField::create('SigningKey', 'Certificate Private Key')
                    ->setFolderName(self::$uploadFolder)
                    ->setAllowedExtensions(['key'])
                    ->setDescription('Upload a valid <strong>.key</strong> file for this recipient email address.'),
                $certificateKeyPassword,
            ]
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
    public function getField($field): mixed
    {
        return $this->getEncryptedField($field);
    }

    /**
     * @inheritDoc
     */
    public function getCMSCompositeValidator(): CompositeValidator
    {
        $compositeValidator = parent::getCMSCompositeValidator();

        $compositeValidator->addValidator(RequiredFields::create([
            'EmailAddress',
        ]));

        return $compositeValidator;
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

    /**
     * Permissions for deleting certificates.
     *
     * @inheritDoc
     */
    public function canDelete($member = null, $context = []): bool
    {
        return Permission::check(EncryptionAdmin::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

}
