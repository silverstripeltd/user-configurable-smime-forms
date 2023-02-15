<?php

namespace SilverStripe\SmimeForms\Extensions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;
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
 * An extension for {@see UserDefinedForm} class to check whether the form submission needs
 * to be encrypted. If so, it will replace the standard Mailer with a {@see SMIMEMailer}.
 *
 * @package SilverStripe\SmimeForms\Extensions
 */
class UserDefinedFormControllerExtension extends DataExtension
{

    /**
     * Called as an extension hook from {@see UserDefinedForm}.
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

        $pathToFile = null;

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

        Injector::inst()->registerService(
            SMIMEMailer::create(
                $pathToFile,
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
        $dbFile = $record->File;

        $protectedPath = sprintf(
            '%s/.protected/%s',
            ASSETS_PATH,
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

        $certificatePath = $senderSigningCertificate->SigningCertificate ?
            $this->getFilePath($senderSigningCertificate->SigningCertificate)
            : null;

        $keyPath = $senderSigningCertificate->SigningKey ?
            $this->getFilePath($senderSigningCertificate->SigningKey)
            : null;

        return [
            'certificate' => $certificatePath,
            'key' => $keyPath,
            'passphrase' => $senderSigningCertificate->SigningPassword,
        ];
    }

}
