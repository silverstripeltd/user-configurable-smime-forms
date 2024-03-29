<?php

namespace SilverStripe\SmimeForms\Extensions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\Email\Mailer;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use SilverStripe\SMIME\Control\SMIMEMailer;
use SilverStripe\SmimeForms\Model\SmimeEncryptionCertificate;
use SilverStripe\SmimeForms\Model\SmimeSigningCertificate;
use SilverStripe\UserForms\Model\Recipient\EmailRecipient;

/**
 * Class UserDefinedFormControllerExtension
 *
 * An extension for {@see UserDefinedFormController} class to check whether the form submission needs
 * to be encrypted. If so, it will replace the standard Mailer with a {@see SMIMEMailer}.
 *
 * @package SilverStripe\SmimeForms\Extensions
 */
class UserDefinedFormControllerExtension extends DataExtension
{

    /**
     * Called as an extension hook from {@see UserDefinedFormController}.
     *
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function updateEmail(Email $email, EmailRecipient $recipient): void
    {
        // Check form configuration to see if the email should be encrypted
        if (!$this->owner->encryptEmail()) {
            return;
        }

        // If the To field is a dynamic field then allow email to be sent without encryption or warning
        $skipEncryption = $recipient->SendEmailToField()->exists();

        $pathToFile = null;
        $signingCredentials = [];

        if (!$skipEncryption) {
            // Check for a recipient encryption certificate, with a matching email address, and set path to file
            $encryptionCertificateEntry = SmimeEncryptionCertificate::get()
                ->filter('EmailAddress', $recipient->EmailAddress)->first();

            if ($encryptionCertificateEntry && $encryptionCertificateEntry->EncryptionCrt->exists()) {
                $pathToFile = $this->getFilePath($encryptionCertificateEntry->EncryptionCrt);
            }

            // If no encryption certificate was found then proceed but append a warning to the email.
            if (!$pathToFile) {
                $subject = $email->getSubject();
                $encryptionMessage = '[UNENCRYPTED: CHECK CMS CONFIGURATION]';

                $email->setSubject(sprintf('%s %s', $subject, $encryptionMessage));
            }

            $senderEmailAddress = array_key_first($email->getFrom());

            $signingCredentials = $this->checkForSigningCredentials($senderEmailAddress);
        }

        // Re-registering SmimeMailer for each recipient with appropriate encryption/signing details
        $this->registerSMIMEMailer(
            $pathToFile,
            $signingCredentials
        );
    }

    /**
     * Uses injection to replace the standard Mailer class, used for handling the sending of the email,
     * with an SMIMEMailer instance with encryption/signature properties.
     */
    public function registerSMIMEMailer(?string $pathToEncryptionFile, array $signingCredentials): void
    {
        Injector::inst()->registerService(
            SMIMEMailer::create(
                $pathToEncryptionFile,
                $signingCredentials['certificate'] ?? null,
                $signingCredentials['key'] ?? null,
                $signingCredentials['passphrase'] ?? null,
            ),
            Mailer::class
        );
    }

    /**
     * Get the actual location of the File or Image asset.
     * Note: No actual built in asset store function seems to be available to do this.
     *
     * @param File $record
     * @param string $tempPath Default: ''
     * @return string|null
     * @throws NotFoundExceptionInterface
     */
    public function getFilePath(File $record, string $tempPath = ''): ?string
    {
        /** @var FlysystemAssetStore $flysystem */
        $flysystem = Injector::inst()->get(AssetStore::class);

        // Used for test purposes so that we can verify the path exists
        $basePath = $flysystem instanceof TestAssetStore ? $flysystem::base_path() : ASSETS_PATH;

        $dbFile = $record->File;

        $protectedPath = sprintf(
            '%s/.protected/%s',
            $basePath,
            $flysystem->getMetadata(
                $dbFile->Filename,
                $dbFile->Hash,
                $dbFile->Variant
            )['path']
        );

        if (file_exists($protectedPath)) {
            return $protectedPath;
        }

        $path = sprintf(
            '%s/%s',
            ASSETS_PATH,
            $flysystem->getMetadata(
                $dbFile->Filename,
                $dbFile->Hash,
                $dbFile->Variant
            )['path']
        );

        if (file_exists($path)) {
            return $path;
        }

        if (!$tempPath) {
            return null;
        }

        $test_path = sprintf(
            '%s/%s',
            ASSETS_PATH,
            $flysystem->getMetadata(
                '{$tempPath}/{$dbFile->Filename}',
                $dbFile->Hash,
                $dbFile->Variant
            )['path']
        );

        if (!file_exists($test_path)) {
            return null;
        }

        return $test_path;
    }

    /**
     * Checks for signing certificate for an email address and, if found, returns an array containing
     * the certificate path, key path and kay passphrase.
     *
     * @throws NotFoundExceptionInterface
     */
    private function checkForSigningCredentials(string $senderEmailAddress): array
    {
        $senderSigningCertificate = SmimeSigningCertificate::get()
            ->filter('EmailAddress', $senderEmailAddress)->first();

        if (!$senderSigningCertificate) {
            return [];
        }

        $certificatePath = $senderSigningCertificate->SigningCertificate->exists() ?
            $this->getFilePath($senderSigningCertificate->SigningCertificate)
            : null;

        $keyPath = $senderSigningCertificate->SigningKey->exists() ?
            $this->getFilePath($senderSigningCertificate->SigningKey)
            : null;

        return [
            'certificate' => $certificatePath,
            'key' => $keyPath,
            'passphrase' => $senderSigningCertificate->SigningPassword,
        ];
    }

}
