<?php

namespace SilverStripe\SmimeForms\Extensions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\Email\Mailer;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\SMIME\Control\SMIMEMailer;
use SilverStripe\UserForms\Model\Recipient\EmailRecipient;

class UserDefinedFormControllerExtension extends DataExtension
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function updateEmail(Email $email, EmailRecipient $recipient)
    {
        // Check form configuration to see if the email should be encrypted
        if (!$this->owner->encryptEmail()) {
            return;
        }

        $pathToFile = $this->getFilePath($recipient->EncryptionCrt);

        // If no encryption certificate is found then don't proceed
        if (!$pathToFile) {
            throw new Exception('Encryption certificate is not found');
        }

        Injector::inst()->registerService(
            SMIMEMailer::create(
                $pathToFile,
                Environment::getEnv('SS_SMIME_SIGN_CERT'),
                Environment::getEnv('SS_SMIME_SIGN_KEY'),
                Environment::getEnv('SS_SMIME_SIGN_PASS'),
            ),
            Mailer::class
        );
    }

    /**
     * Get the actual location of the File or Image asset.
     *
     * @param File $record
     * @param string $tempPath Default: ''
     * @return string|null
     * @throws NotFoundExceptionInterface
     */
    public function getFilePath(File $record, string $tempPath = ''): ?string
    {
        if ($record->exists()) {
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

            if ($tempPath) {
                $test_path = sprintf(
                    '%s/%s',
                    ASSETS_PATH,
                    $flysystem->getMetadata(
                        '{$tempPath}/{$dbFile->Filename}',
                        $dbFile->Hash,
                        $dbFile->Variant
                    )['path']
                );

                if (file_exists($test_path)) {
                    return $test_path;
                }
            }
        }

        return null;
    }

}