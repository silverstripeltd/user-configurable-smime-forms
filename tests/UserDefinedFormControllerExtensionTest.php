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

 /*   public function testCheckEncryptionToggle(): void
    {
        // Get the form and set encryption to true
        $form = $this->objFromFixture(ElementForm::class, 'registration_form');
        $form->UseEncryption = true;

        // Set up the controller, passing in the 'owner' form
        $controller = UserDefinedFormController::create($form);

        $recipient = EmailRecipient::create();
        $recipient->EmailAddress = 'recipient@example.com';
        $recipient->write();

        $email = Email::create();
        $email->setFrom('sender@example.com');

        $mock = $this->getMockBuilder(SMIMEMailer::class)
            ->setMethods([
                'setEncryptingCerts',
                'setSigningCert',
                'setSigningKey',
            ])
        ->getMock();

        // Check that the SMIMEMailer has been
        $mock->expects($this->once())->method('setEncryptingCerts');

        // Call the update email function
        $controller->updateEmail($email, $recipient);

    }*/

}
