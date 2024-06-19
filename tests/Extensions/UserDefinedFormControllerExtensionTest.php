<?php

namespace SilverStripe\SmimeForms\Tests\Extensions;

use DNADesign\ElementalUserForms\Model\ElementForm;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Control\Email\Email;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\ValidationException;
use SilverStripe\SmimeForms\Model\SmimeEmail;
use SilverStripe\SmimeForms\Model\SmimeEncryptionCertificate;
use SilverStripe\SmimeForms\Model\SmimeSigningCertificate;
use SilverStripe\UserForms\Control\UserDefinedFormController;
use SilverStripe\UserForms\Model\Recipient\EmailRecipient;
use Throwable;

class UserDefinedFormControllerExtensionTest extends FunctionalTest
{

    /**
     * @inheritDoc
     */
    protected $usesDatabase = true; // phpcs:ignore

    /**
     * @inheritDoc
     */
    protected static $fixture_file = '../fixtures/UserDefinedFormControllerExtensionTest.yml'; // phpcs:ignore

    /**
     * The encryption certificate for encrypting emails.
     *
     * @var File
     */
    private File $encryptionCertificate;

    /**
     * The signing certificate for digitally signing emails.
     *
     * @var File
     */
    private File $signingCertificate;

    /**
     * The signing private key for digitally signing emails.
     *
     * @var File
     */
    private File $signingKey;

    /**
     * Loads all the signing and encryption certificates/keys into the local file system
     * and sets up the SmimeEncryptionCertificate and SmimeSigningCertificate instances.
     *
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        // Activate the TestAssetStore for loading certificates and keys
        TestAssetStore::activate('SmimeCertificatesTest');

        // Bypass CMS admin permissions
        $this->logInWithPermission('ADMIN');

        // Load the encryption certificate
        $this->encryptionCertificate = $this->getAndLoadLocalFile('smime_test_recipient.crt');

        // Load the signing certificate
        $this->signingCertificate = $this->getAndLoadLocalFile('smime_test_sender_certificate.pem');

        // Load the signing private key
        $this->signingKey = $this->getAndLoadLocalFile('smime_test_sender_privatekey.pem');
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        TestAssetStore::reset();

        parent::tearDown();
    }

    /**
     * Checks the default Email class persists if the email
     * encryption is disabled on a UserDefinedForm.
     *
     * @return void
     */
    public function testUpdateEmailDataWithNoFormEncryption(): void
    {
        // Get the ElementForm instance
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');

        // Set up the controller and call the 'updateEmail' hook
        $controller = UserDefinedFormController::create($form);
        $controller->updateEmailData([], []);

        // Check creating an Email instance does not resolve to an instance of SMimeEmail
        $email = Email::create();
        $this->assertNotInstanceOf(SmimeEmail::class, $email);
    }

    /**
     * Check creating an Email instance returns an SMimeEmail instance
     * when email encryption is enabled on a UserDefinedForm.
     *
     * @return void
     */
    public function testUpdateEmailDataWithFormEncryption(): void
    {
        // Get the ElementForm instance
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');

        // Enable email encryption for this form
        $form->UseEncryption = true;

        // Set up the controller and call the 'updateEmail' hook
        $controller = UserDefinedFormController::create($form);
        $controller->updateEmailData([], []);

        // Check creating an Email instance resolves to an instance of SMimeEmail
        $email = Email::create();
        $this->assertInstanceOf(SmimeEmail::class, $email);
    }

    /**
     * Checks the SMimeEmail instance is neither signed nor encrypted when a
     * recipient does not have a valid set of signing and encryption credentials.
     *
     * @return void
     */
    public function testUpdateEmailWithNoRecipientSigningAndEncryption(): void
    {
        // Get the ElementForm instance
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');

        // Enable email encryption for this form
        $form->UseEncryption = true;

        // Get the EmailRecipient
        $recipient = $this->objFromFixture(EmailRecipient::class, 'recipient_standard');

        // Mock an SmimeEmail instance from the EmailRecipient
        $email = $this->mockSmimeEmailFromRecipient($recipient);

        // Set up the controller and call the 'updateEmail' hook
        $controller = UserDefinedFormController::create($form);
        $controller->updateEmail($email, $recipient);

        // Send the email
        try {
            $email->send();
        } catch (Throwable $e) {
            var_dump($e->getMessage());
        }

        // Check the SMimeEmail instance according to this recipient
        // has no associated signing and encryption credentials
        $this->assertNull($email->getSigningCredentials());
        $this->assertNull($email->getEncryptionFilePath());

        // Check the email is neither signed nor encrypted
        $body = $email->getBody();
        $type = $body->getMediaType();
        $mediaSubType = $body->getMediaSubtype();
        $this->assertEquals('multipart', $type);
        $this->assertEquals('alternative', $mediaSubType);
    }

    /**
     * Checks the SMimeEmail instance is signed but not encrypted when a recipient
     * has a valid set of signing credentials but an invalid encryption certificate.
     *
     * @return void
     * @throws ValidationException
     */
    public function testUpdateEmailWithRecipientSigningCredentialsOnly(): void
    {
        // Get the ElementForm instance
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');

        // Enable email encryption for this form
        $form->UseEncryption = true;

        // Get the EmailRecipient
        $recipient = $this->objFromFixture(EmailRecipient::class, 'recipient_standard');

        // Load signing credentials for the sender
        $this->loadSigningCredentialsForRecipient($recipient->EmailFrom);

        // Mock an SmimeEmail instance from the EmailRecipient
        $email = $this->mockSmimeEmailFromRecipient($recipient);

        // Set up the controller and call the 'updateEmail' hook
        $controller = UserDefinedFormController::create($form);
        $controller->updateEmail($email, $recipient);

        // Send the email
        try {
            $email->send();
        } catch (Throwable $e) {
            var_dump($e->getMessage());
        }

        // Check there is a valid set of signing credentials
        $signingCredentials = $email->getSigningCredentials();
        $this->assertNotNull($signingCredentials);
        $this->assertStringContainsString('smime_test_sender_certificate.pem', $signingCredentials['certificate']);
        $this->assertStringContainsString('smime_test_sender_privatekey.pem', $signingCredentials['key']);
        $this->assertStringContainsString('Test123!', $signingCredentials['passphrase']);

        // Check the encryption certificate is null
        $encryptionFile = $email->getEncryptionFilePath();
        $this->assertNull($encryptionFile);

        // Check the email is signed but not encrypted
        $body = $email->getBody();
        $type = $body->getMediaType();
        $mediaSubType = $body->getMediaSubtype();
        $subject = $email->getSubject();
        $this->assertStringContainsString('[UNENCRYPTED: CHECK CMS CONFIGURATION]', $subject);
        $this->assertEquals('multipart', $type);
        $this->assertEquals('signed', $mediaSubType);
    }

    /**
     * Checks the SMimeEmail instance is encrypted but not signed when a recipient
     * has a valid encryption certificate but an invalid set of signing credentials.
     *
     * @return void
     * @throws ValidationException
     */
    public function testUpdateEmailWithRecipientEncryptionOnly(): void
    {
        // Get the ElementForm instance
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');

        // Enable email encryption for this form
        $form->UseEncryption = true;

        // Get the EmailRecipient
        $recipient = $this->objFromFixture(EmailRecipient::class, 'recipient_standard');

        // Load encryption certificate for recipient
        $this->loadEncryptionCertificateForRecipient($recipient->EmailAddress);

        // Mock an SmimeEmail instance from the EmailRecipient
        $email = $this->mockSmimeEmailFromRecipient($recipient);

        // Set up the controller and call the 'updateEmail' hook
        $controller = UserDefinedFormController::create($form);
        $controller->updateEmail($email, $recipient);

        // Send the email
        try {
            $email->send();
        } catch (Throwable $e) {
            var_dump($e->getMessage());
        }

        // Check the signing credentials is null
        $signingCredentials = $email->getSigningCredentials();
        $this->assertNull($signingCredentials);

        // Check the encryption certificate exists
        $encryptionFile = $email->getEncryptionFilePath();
        $this->assertNotNull($encryptionFile);
        $this->assertStringContainsString('smime_test_recipient.crt', $encryptionFile);

        // Check the email is signed but not encrypted
        $body = $email->getBody();
        $type = $body->getMediaType();
        $mediaSubType = $body->getMediaSubtype();
        $subject = $email->getSubject();
        $this->assertStringNotContainsString('[UNENCRYPTED: CHECK CMS CONFIGURATION]', $subject);
        $this->assertEquals('application', $type);
        $this->assertEquals('pkcs7-mime', $mediaSubType);
    }

    /**
     * Checks the SMimeEmail instance is both digitally signed and encrypted.
     *
     * @return void
     * @throws ValidationException
     */
    public function testUpdateEmailWithRecipientSigningAndEncryption(): void
    {
        // Get the ElementForm instance
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');

        // Enable email encryption for this form
        $form->UseEncryption = true;

        // Get the EmailRecipient
        $recipient = $this->objFromFixture(EmailRecipient::class, 'recipient_standard');

        // Load signing credentials for the sender
        $this->loadSigningCredentialsForRecipient($recipient->EmailFrom);

        // Load encryption certificate for recipient
        $this->loadEncryptionCertificateForRecipient($recipient->EmailAddress);

        // Mock an SmimeEmail instance from the EmailRecipient
        $email = $this->mockSmimeEmailFromRecipient($recipient);

        // Set up the controller and call the 'updateEmail' hook
        $controller = UserDefinedFormController::create($form);
        $controller->updateEmail($email, $recipient);

        // Send the email
        try {
            $email->send();
        } catch (Throwable $e) {
            var_dump($e->getMessage());
        }

        // Check there is a valid set of signing credentials
        $signingCredentials = $email->getSigningCredentials();
        $this->assertNotNull($signingCredentials);
        $this->assertStringContainsString('smime_test_sender_certificate.pem', $signingCredentials['certificate']);
        $this->assertStringContainsString('smime_test_sender_privatekey.pem', $signingCredentials['key']);
        $this->assertStringContainsString('Test123!', $signingCredentials['passphrase']);

        // Check the encryption certificate exists
        $encryptionFile = $email->getEncryptionFilePath();
        $this->assertNotNull($encryptionFile);
        $this->assertStringContainsString('smime_test_recipient.crt', $encryptionFile);

        // Check the email is signed and encrypted
        $body = $email->getBody();
        $type = $body->getMediaType();
        $mediaSubType = $body->getMediaSubtype();
        $subject = $email->getSubject();
        $this->assertStringNotContainsString('[UNENCRYPTED: CHECK CMS CONFIGURATION]', $subject);
        $this->assertEquals('application', $type);
        $this->assertEquals('pkcs7-mime', $mediaSubType);
    }

    /**
     * When recipients of forms are dynamic (from an input field)
     * then signing and encryption should be skipped.
     *
     * @throws ValidationException
     */
    public function testUpdateEmailWithDynamicRecipient(): void
    {
        // Get the ElementForm instance
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');

        // Enable email encryption for this form
        $form->UseEncryption = true;

        // Get the EmailRecipient
        $recipient = $this->objFromFixture(EmailRecipient::class, 'recipient_dynamic');

        // Load signing credentials for the sender
        $this->loadSigningCredentialsForRecipient($recipient->EmailFrom);

        // Load encryption certificate for recipient
        $this->loadEncryptionCertificateForRecipient($recipient->EmailAddress);

        // Mock an SmimeEmail instance from the EmailRecipient
        $email = $this->mockSmimeEmailFromRecipient($recipient);

        // Set up the controller and call the 'updateEmail' hook
        $controller = UserDefinedFormController::create($form);
        $controller->updateEmail($email, $recipient);

        // Send the email
        try {
            $email->send();
        } catch (Throwable $e) {
            var_dump($e->getMessage());
        }

        // Check the signing credentials is null
        $signingCredentials = $email->getSigningCredentials();
        $this->assertNull($signingCredentials);

        // Check the encryption file path is null
        $encryptionFile = $email->getEncryptionFilePath();
        $this->assertNull($encryptionFile);

        // Check the email is neither signed nor encrypted
        $body = $email->getBody();
        $type = $body->getMediaType();
        $mediaSubType = $body->getMediaSubtype();
        $this->assertEquals('multipart', $type);
        $this->assertEquals('alternative', $mediaSubType);
    }

    /**
     * Mocks an SmimeEmail instance based on the given EmailRecipient.
     *
     * @param EmailRecipient $recipient
     * @return SmimeEmail
     */
    private function mockSmimeEmailFromRecipient(EmailRecipient $recipient): SmimeEmail
    {
        $email = new SmimeEmail(
            $recipient->EmailFrom,
            $recipient->EmailAddress,
            $recipient->EmailSubject
        );

        $email->setHTMLTemplate('email/SubmittedFormEmail');
        $email->setPlainTemplate('email/SubmittedFormEmailPlain');

        return $email;
    }

    /**
     * Returns a File instance based on the given file
     * name and loads it into the local file system.
     *
     * @param string $fileName
     * @return File
     * @throws ValidationException
     */
    private function getAndLoadLocalFile(string $fileName): File
    {
        $file = File::create();
        $file->setFromLocalFile(sprintf('%s/fixtures/%s', dirname(__DIR__), $fileName));
        $file->write();

        return $file;
    }

    /**
     * Writes a SmimeEncryptionCertificate instance based on
     * the given encryption certificate and email address.
     *
     * @param string $emailAddress
     * @return void
     * @throws ValidationException
     */
    private function loadEncryptionCertificateForRecipient(string $emailAddress): void
    {
        $certificate = SmimeEncryptionCertificate::create();
        $certificate->EmailAddress = $emailAddress;
        $certificate->EncryptionCrtID = $this->encryptionCertificate->ID;
        $certificate->write();
    }

    /**
     * Writes a SmimeSigningCertificate instance based on the
     * given signing certificate, private key and email address.
     *
     * @param string $emailAddress
     * @return void
     * @throws ValidationException
     */
    private function loadSigningCredentialsForRecipient(string $emailAddress): void
    {
        $signingCertificate = SmimeSigningCertificate::create();
        $signingCertificate->EmailAddress = $emailAddress;
        $signingCertificate->SigningCertificateID = $this->signingCertificate->ID;
        $signingCertificate->SigningKeyID = $this->signingKey->ID;
        $signingCertificate->SigningPassword = 'Test123!';
        $signingCertificate->write();
    }

}
