<?php

namespace SilverStripe\SmimeForms\Model;

use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Injector\Injector;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Crypto\SMimeEncrypter;
use Symfony\Component\Mime\Crypto\SMimeSigner;

/**
 * Used to override Silverstripe's Email class to enable digital signing
 * and encryption of emails before sending them with Symfony's Mailer class.
 */
class SmimeEmail extends Email
{

    /**
     * An array containing a set of credentials for digitially signing an email.
     *
     * These include:
     * - signing certificate
     * - signing private key
     * - signing passphrase
     *
     * @var array|null
     */
    private array|null $signingCredentials = null;

    /**
     * The path (or array of paths) of the file(s) containing the certificate(s).
     *
     * @var string|array|null
     */
    private string|array|null $encryptionFilePath = null;

    /**
     * Overrides Silverstripe's 'Email::send()' function to
     * enable the digital signing and encryption of emails.
     *
     * @see Email::send()
     * @throws NotFoundExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ReflectionException
     * @return void
     */
    public function send(): void
    {
        // Render the contents of the email body
        $this->invokeUpdateHtmlAndTextWithRenderedTemplates();

        // Sign email using SMimeSigner
        $this->signEmail();

        // Encrypt email using SMimeEncrypter
        $this->encryptEmail();

        // Send the email using Symfony's Mailer class
        Injector::inst()->get(MailerInterface::class)->send($this);
    }

    /**
     * Signs this Email instance using the SMimeSigner.
     *
     * @see SMimeSigner
     * @return void
     */
    public function signEmail(): void
    {
        // Get set of signing credentials
        $signingCredentials = $this->getSigningCredentials();

        if (!$signingCredentials) {
            return;
        }

        // Extract credentials into variables
        $certificate = array_key_exists('certificate', $signingCredentials) ? $signingCredentials['certificate'] : null;
        $key = array_key_exists('key', $signingCredentials) ? $signingCredentials['key'] : null;
        $passPhrase = array_key_exists('passphrase', $signingCredentials) ? $signingCredentials['passphrase'] : null;

        // Early exit if the 'certificate' or 'private key' is not set
        if (!$certificate || !$key) {
            return;
        }

        // Create the SMimeSigner instance
        $signer = new SMimeSigner(
            $certificate,
            $key,
            $passPhrase
        );

        // Sign the email using the SMimeSigner
        $signedMessage = $signer->sign($this);

        // Get the signed body from the signed message
        $signedBody = $signedMessage->getBody();

        // Update the body of the current email instance
        $this->setBody($signedBody);
    }

    /**
     * Encrypts this Email instance using the SMimeEncrypter.
     *
     * @see SMimeEncrypter
     * @return void
     */
    public function encryptEmail(): void
    {
        // Get the encryption certificate file path
        $encryptionFilePath = $this->getEncryptionFilePath();

        // Early exit if the encryption certificate does not exist
        if (!$encryptionFilePath) {
            return;
        }

        // Create the SMimeEncrypter instance
        $encrypter = new SMimeEncrypter($encryptionFilePath);

        // Encrypt the email using the SMimeEncrypter
        $encryptedMessage = $encrypter->encrypt($this);

        // Get the encrypted body
        $encryptedBody = $encryptedMessage->getBody();

        // Update the body of the current email instance
        $this->setBody($encryptedBody);
    }

    /**
     * Calls 'Email::updateHtmlAndTextWithRenderedTemplates()' in order to render
     * the contents of the email body. Since it is a private function, we use
     * 'ReflectionClass' to invoke this method.
     *
     * @throws ReflectionException
     * @return void
     */
    public function invokeUpdateHtmlAndTextWithRenderedTemplates(): void
    {
        // Create a ReflectionClass instance for the current object
        $reflection = new ReflectionClass(Email::class);

        // Get the private method
        $method = $reflection->getMethod('updateHtmlAndTextWithRenderedTemplates');

        // Set the method accessible
        $method->setAccessible(true);

        // Invoke the private method on the current object
        $method->invoke($this);
    }

    /**
     * Sets the set of credentials used for digitally signing emails.
     *
     * @param array|null $signingCredentials
     * @return void
     */
    public function setSigningCredentials(array|null $signingCredentials): void
    {
        $this->signingCredentials = $signingCredentials;
    }

    /**
     * Sets the encryption certificate file path for encrypting emails.
     *
     * @param string|array|null $encryptionFilePath
     * @return void
     */
    public function setEncryptionFilePath(string|array|null $encryptionFilePath): void
    {
        $this->encryptionFilePath = $encryptionFilePath;
    }

    /**
     * Returns an array containing the set of credentials for digitally signing emails.
     *
     * @return array|null
     */
    public function getSigningCredentials(): array|null
    {
        return $this->signingCredentials;
    }

    /**
     * Returns the file path of the encryption certificate.
     *
     * @return string|array|null
     */
    public function getEncryptionFilePath(): string|array|null
    {
        return $this->encryptionFilePath;
    }

}
