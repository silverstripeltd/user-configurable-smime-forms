<?php

namespace SilverStripe\SmimeForms\Tests;

use DNADesign\ElementalUserForms\Model\ElementForm;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SmimeForms\Extensions\UserDefinedFormControllerExtension;
use SilverStripe\SmimeForms\Model\SmimeEncryptionCertificate;
use SilverStripe\SmimeForms\Model\SmimeSigningCertificate;
use SilverStripe\UserForms\Control\UserDefinedFormController;
use SilverStripe\UserForms\Model\EditableFormField\EditableEmailField;
use SilverStripe\UserForms\Model\Recipient\EmailRecipient;

class UserDefinedFormControllerExtensionTest extends SapphireTest
{

    protected $usesDatabase = true; // phpcs:ignore

    protected static $fixture_file = 'UserDefinedFormControllerExtensionTest.yml'; // phpcs:ignore

    public function setUp(): void
    {
        parent::setUp();

        TestAssetStore::activate('SmimeCertificatesTest');

        $this->logInWithPermission('ADMIN');

        $crtFile = File::create();
        $crtFile->setFromLocalFile(sprintf('%s%s', __DIR__, '/fixtures/smime_test_recipient.crt'));
        $crtFile->write();

        $certificate = SmimeEncryptionCertificate::create();
        $certificate->EmailAddress = 'recipient@example.com';
        $certificate->EncryptionCrtID = $crtFile->ID;
        $certificate->write();

        // Set signing certificate etc for sender@example.come
        $senderCrtFile = File::create();
        $senderCrtFile->setFromLocalFile(sprintf('%s%s', __DIR__, '/fixtures/smime_test_sender.crt'));
        $senderCrtFile->write();

        $senderKeyFile = File::create();
        $senderKeyFile->setFromLocalFile(sprintf('%s%s', __DIR__, '/fixtures/smime_test_sender.key'));
        $senderKeyFile->write();

        $signingCertificate = SmimeSigningCertificate::create();
        $signingCertificate->EmailAddress = 'sender@example.com';
        $signingCertificate->SigningCertificateID = $senderCrtFile->ID;
        $signingCertificate->SigningKeyID = $senderKeyFile->ID;
        $signingCertificate->SigningPassword = 'Test123!';
        $signingCertificate->write();
    }

    protected function tearDown(): void
    {
        TestAssetStore::reset();

        parent::tearDown();
    }

    /**
     * Test the update email function and check that the SMIMEMailer is set up with expected properties.
     */
    public function testUpdateEmailWithNoEncryption(): void
    {
        // Get the form and set encryption to true
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');
        $form->UseEncryption = false;

        // Set up email and recipient
        $recipient = EmailRecipient::create();
        $recipient->EmailAddress = 'recipient@example.com';
        $recipient->EmailFrom = 'senderOne@example.com';
        $recipient->write();

        $email = Email::create();
        $email->setFrom('senderOne@example.com');

        // Mock the extension we are testing and inject it
        $mockedExtension = $this->getMockBuilder(UserDefinedFormControllerExtension::class)
            ->onlyMethods(['registerSMIMEMailer'])
            ->getMock();

        Injector::inst()->registerService($mockedExtension, UserDefinedFormControllerExtension::class);

        // Instantiate the controller which will pick up our mocked extension
        $controller = UserDefinedFormController::create($form);

        // Set up expectations for calling the mocked registerSMIMEMailer function to check the encryption file path.
        // Since we can't check on the full system path a callback is used so that we can make partial assertions.
        $mockedExtension
            ->expects($this->never())
            ->method('registerSMIMEMailer');

        $controller->updateEmail($email, $recipient);
    }

    /**
     * Test the update email function and check that the SMIMEMailer is set up with expected properties.
     */
    public function testUpdateEmailWithEncryptionOnly(): void
    {
        // Get the form and set encryption to true
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');
        $form->UseEncryption = true;

        // Set up email and recipient
        $recipient = EmailRecipient::create();
        $recipient->EmailAddress = 'recipient@example.com';
        $recipient->EmailFrom = 'senderOne@example.com';
        $recipient->write();

        $email = Email::create();
        $email->setFrom('senderOne@example.com');

        // Mock the extension we are testing and inject it
        $mockedExtension = $this->getMockBuilder(UserDefinedFormControllerExtension::class)
            ->onlyMethods(['registerSMIMEMailer'])
            ->getMock();

        Injector::inst()->registerService($mockedExtension, UserDefinedFormControllerExtension::class);

        // Instantiate the controller which will pick up our mocked extension
        $controller = UserDefinedFormController::create($form);

        // Set up expectations for calling the mocked registerSMIMEMailer function to check the encryption file path.
        // Since we can't check on the full system path a callback is used so that we can make partial assertions.
        $mockedExtension
            ->expects($this->once())
            ->method('registerSMIMEMailer')
            ->with($this->callback(function ($encryptionCertificateFilepath) {
                $this->assertStringContainsString(
                    '/SmimeCertificatesTest/.protected/c7d650c675/smime_test_recipient.crt',
                    $encryptionCertificateFilepath
                );

                return true;
            }), []);

        $controller->updateEmail($email, $recipient);
    }

    /**
     * Test the update email function and check that the SMIMEMailer is set up with expected properties.
     */
    public function testUpdateEmailWithEncryptionAndSigning(): void
    {
        // Get the form and set encryption to true
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');
        $form->UseEncryption = true;

        // Set up email and recipient
        $recipient = EmailRecipient::create();
        $recipient->EmailAddress = 'recipient@example.com';
        $recipient->EmailFrom = 'sender@example.com';
        $recipient->write();

        $email = Email::create();
        $email->setFrom('sender@example.com');

        // Mock the extension we are testing and inject it
        $mockedExtension = $this->getMockBuilder(UserDefinedFormControllerExtension::class)
            ->onlyMethods(['registerSMIMEMailer'])
            ->getMock();

        Injector::inst()->registerService($mockedExtension, UserDefinedFormControllerExtension::class);

        // Instantiate the controller which will pick up our mocked extension
        $controller = UserDefinedFormController::create($form);

        // Set up expectations for calling the mocked registerSMIMEMailer function
        // to check file paths and expected signing credentials.
        // Since we can't check on the full system path a callback is used for each argument so
        // that we can make partial assertions.
        $mockedExtension
            ->expects($this->once())
            ->method('registerSMIMEMailer')
            ->with(
                $this->callback(function ($encryptionFilePath) {
                    $this->assertStringContainsString(
                        '.protected/c7d650c675/smime_test_recipient.crt',
                        $encryptionFilePath
                    );

                    return true;
                }),
                $this->callback(function ($signingCertificate) {
                    $this->assertStringContainsString(
                        '.protected/3c5b7c74b6/smime_test_sender.crt',
                        $signingCertificate['certificate']
                    );

                    $this->assertStringContainsString(
                        '.protected/53869f05b4/smime_test_sender.key',
                        $signingCertificate['key']
                    );

                    $this->assertEquals(
                        'Test123!',
                        $signingCertificate['passphrase']
                    );

                    return true;
                })
            );

        $controller->updateEmail($email, $recipient);
    }

    /**
     * When email recipients are missing an encryption certificate the subject
     * is appended with a warning to configure CMS.
     */
    public function testUpdateEmailSubjectWhenEmailWithoutEncryptionCertificate(): void
    {
        // Get the form and set encryption to true
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');
        $form->UseEncryption = true;

        $emailField = $this->objFromFixture(EditableEmailField::class, 'email-field1');

        $email = Email::create();
        $email->setFrom('sender@example.com');
        $email->setSubject('Registration Form');

        $recipient = EmailRecipient::create();
        $recipient->EmailAddress = 'other-recipient@example.com';
        $recipient->EmailFrom = 'sender@example.com';
        $recipient->write();

        $controller = UserDefinedFormController::create($form);
        $controller->updateEmail($email, $recipient);

        // An email recipient without encryption certificate will have updated subject
        $this->assertEquals('Registration Form [UNENCRYPTED: CHECK CMS CONFIGURATION]', $email->getSubject());

    }

    /**
     * When recipients of forms are dynamic (from an input field) then
     * encryption should be skipped.
     */
    public function testUpdateEmailWithDynamicRecipient(): void
    {
        // Get the form and set encryption to true
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');
        $form->UseEncryption = true;

        $emailField = $this->objFromFixture(EditableEmailField::class, 'email-field1');

        // Set up email and dynamic recipient (email taken from email field in form)
        $recipient = EmailRecipient::create();
        $recipient->EmailAddress = 'dynamic-recipient@example.com';
        $recipient->SendEmailToFieldID = $emailField->ID;
        $recipient->EmailFrom = 'sender@example.com';
        $recipient->write();

        $email = Email::create();
        $email->setFrom('sender@example.com');
        $email->setSubject('Registration Form');

        // Mock the extension we are testing and inject it
        $mockedExtension = $this->getMockBuilder(UserDefinedFormControllerExtension::class)
            ->onlyMethods(['registerSMIMEMailer'])
            ->getMock();

        Injector::inst()->registerService($mockedExtension, UserDefinedFormControllerExtension::class);

        // Instantiate the controller which will pick up our mocked extension
        $controller = UserDefinedFormController::create($form);

        // Set up expectations for calling the mocked registerSMIMEMailer function
        // to check file paths and expected signing credentials.
        // Since we can't check on the full system path a callback is used for each argument so
        // that we can make partial assertions.
        $mockedExtension
            ->expects($this->once())
            ->method('registerSMIMEMailer')
            ->with(
                $this->callback(function ($encryptionFilePath) {
                    $this->assertNull($encryptionFilePath);

                    return true;
                }),
                $this->callback(function ($signingCertificate) {
                    $this->assertEquals([], $signingCertificate);

                    return true;
                })
            );

        $controller->updateEmail($email, $recipient);

        $this->assertEquals('Registration Form', $email->getSubject()); // No warning and no encryption
    }

}
