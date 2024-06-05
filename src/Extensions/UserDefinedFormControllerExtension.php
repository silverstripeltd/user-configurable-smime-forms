<?php

namespace SilverStripe\SmimeForms\Extensions;

use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use SilverStripe\SmimeForms\Model\SmimeEmail;
use SilverStripe\SmimeForms\Model\SmimeEncryptionCertificate;
use SilverStripe\SmimeForms\Model\SmimeSigningCertificate;
use SilverStripe\UserForms\Model\Recipient\EmailRecipient;

/**
 * Class UserDefinedFormControllerExtension
 *
 * An extension for {@see UserDefinedFormController} class to check whether the
 * form submission needs to be encrypted. If so, this will use the SmimeEmail
 * class to both sign and encrypt the email prior to sending it using symfony/Mailer.
 *
 * @package SilverStripe\SmimeForms\Extensions
 */
class UserDefinedFormControllerExtension extends DataExtension
{

    /**
     * Uses Injector to fully override the Email class with
     * the SMimeEmail class if a form enables email encryption.
     *
     * We use the 'updateEmailData' hook to do this as the Email
     * instance is yet to be created at this point of the process.
     *
     * Meaning once this hook is executed the next Email instance
     * that is created will be of type SMimeEmail.
     *
     * @param array $emailData
     * @param array $attachments
     * @return void
     */
    public function updateEmailData(array $emailData, array $attachments): void
    {
        // Early exit if this form should not be encrypted
        if (!$this->owner->isEncryptEmail()) {
            return;
        }

        // Otherwise, use Injector to fully override Email with SMimeEmail
        Injector::inst()->registerService(new SmimeEmail(), Email::class);
    }

    /**
     * Uses the 'updateEmail' hook to set the signing credentials
     * and encryption certificate on the given SMimeEmail instance.
     *
     * It extracts the credentials and certificates from the given
     * EmailRecipient and uses these to sign and encrypt the email.
     *
     * @param Email $email
     * @param EmailRecipient $recipient
     * @return void
     * @throws NotFoundExceptionInterface
     */
    public function updateEmail(Email $email, EmailRecipient $recipient): void
    {
        // Early exit if this form should not be encrypted
        if (!$this->owner->isEncryptEmail()) {
            return;
        }

        // If the To field is a dynamic field then allow email to be sent without encryption or warning
        $skipEncryption = $recipient->SendEmailToField()->exists();

        $encryptionFilePath = null;
        $signingCredentials = null;

        if (!$skipEncryption) {
            // Check for a recipient encryption certificate, with a matching email address, and set path to file
            $encryptionCertificateEntry = SmimeEncryptionCertificate::get()
                ->filter('EmailAddress', $recipient->EmailAddress)->first();

            if ($encryptionCertificateEntry && $encryptionCertificateEntry->EncryptionCrt->exists()) {
                $encryptionFilePath = $this->getFilePath($encryptionCertificateEntry->EncryptionCrt);
            }

            // If no encryption certificate was found then proceed but append a warning to the email.
            if (!$encryptionFilePath) {
                $subject = $email->getSubject();
                $encryptionMessage = '[UNENCRYPTED: CHECK CMS CONFIGURATION]';

                $email->setSubject(sprintf('%s %s', $subject, $encryptionMessage));
            }

            // Get the Sender Email address
            $address = $email->getFrom();
            $senderEmailAddress = $address && count($address) > 0 ? $address[0]->getAddress() : '';

            // Get the credentials for signing an Email
            $signingCredentials = $this->checkForSigningCredentials($senderEmailAddress);
        }

        // Set email signing and encryption properties
        $email->setSigningCredentials($signingCredentials);
        $email->setEncryptionFilePath($encryptionFilePath);
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

        $fileUrl = str_replace(
            '/assets/',
            '',
            $flysystem->getAsURL(
                $dbFile->Filename,
                $dbFile->Hash,
                $dbFile->Variant
            )
        );

        $protectedPath = sprintf(
            '%s/.protected/%s',
            $basePath,
            $fileUrl
        );

        if (file_exists($protectedPath)) {
            return $protectedPath;
        }

        $path = sprintf(
            '%s/%s',
            ASSETS_PATH,
            $fileUrl
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
            $flysystem->getAsURL(
                '{$tempPath}/{$dbFile->Filename}',
                $dbFile->Hash,
                $dbFile->Variant
            )
        );

        if (!file_exists($test_path)) {
            return null;
        }

        return $test_path;
    }

    /**
     * Checks for signing certificate for an email address and, if found, returns an array containing
     * the certificate path, key path and key passphrase.
     *
     * @param string $senderEmailAddress
     * @return array|null
     * @throws NotFoundExceptionInterface
     */
    private function checkForSigningCredentials(string $senderEmailAddress): array|null
    {
        $senderSigningCertificate = SmimeSigningCertificate::get()
            ->filter('EmailAddress', $senderEmailAddress)->first();

        if (!$senderSigningCertificate) {
            return null;
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
