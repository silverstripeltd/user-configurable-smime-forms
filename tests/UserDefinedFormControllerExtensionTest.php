<?php

namespace SilverStripe\SmimeForms\Tests;

use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SmimeForms\Model\SmimeEncryptionCertificate;

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
    }

    protected function tearDown(): void
    {
        TestAssetStore::reset();

        parent::tearDown();
    }

    /**
     * Test the update email function and check that the SMIMEMailer is set up with expected properties.
     * TODO: Here I want to test that registerSMIMEMailer is called with expected arguments but the mock
     * doesn't work correctly and need to figure out why.
     */
    /*public function testupdateEmail(): void
    {
        // Get the form and set encryption to true
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');
        $form->UseEncryption = true;

        // Set up email and recipient
        $recipient = EmailRecipient::create();
        $recipient->EmailAddress = 'recipient@example.com';
        $recipient->write();

        $email = Email::create();
        $email->setFrom('sender@example.com');

        // Set up the controller, and mock the registerSMIMEMailer method so we can assert the arguments used for
        // setting up the SMIMEMailer.
        $mockedController = $this->getMockBuilder(UserDefinedFormController::class)
            ->setConstructorArgs([$form])
            ->setMethods(['registerSMIMEMailer'])
            ->getMock();

        // Set up expectations for mocked object
        $mockedController->expects($this->once())->method('registerSMIMEMailer')->with($this->anything());

        // Call the updateEmail function
        $mockedController->updateEmail($email, $recipient);
    }*/

}
