<?php

namespace SilverStripe\SmimeForms\Tests;

use DNADesign\ElementalUserForms\Model\ElementForm;
use SilverStripe\Assets\File;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\UserForms\Model\Recipient\EmailRecipient;
use SilverStripe\UserForms\Model\UserDefinedForm;
use function PHPUnit\Framework\assertContainsEquals;
use function PHPUnit\Framework\assertNotContains;

class EmailRecipientTest extends SapphireTest
{

    protected static $fixture_file = 'EmailRecipientTest.yml';

    /**
     * Test that if a form has been checked to use encryption, then adding an email recipient provides
     * the EncryptionCrt field.
     */
    public function testEncryptionCheckbox(): void
    {
        $form = $this->objFromFixture(ElementForm::class, 'encryptedForm');
        $recipient = EmailRecipient::create();
        $recipient->Form = $form;
        $fields = $recipient->getCMSFields();

        assertContainsEquals('EncryptionCrt', $fields->dataFieldNames());

        $form = $this->objFromFixture(ElementForm::class, 'unencryptedForm');
        $recipient = EmailRecipient::create();
        $recipient->Form = $form;
        $fields = $recipient->getCMSFields();

        assertNotContains('EncryptionCrt', $fields->dataFieldNames());
    }

    /**
     * Check that when a certificate is attached to a recipient then it is set as
     * a protected file (i.e., cannot be accessed publicly)
     */
    public function testUploadedCertificateIsProtected(): void
    {
        // Create the certificate file
        $file = File::create();
        $file->setFromLocalFile(__DIR__ . '/fixtures/smime_test_recipient.crt');
        $file->write();

        // Create recipient
        $form = $this->objFromFixture(ElementForm::class, 'encryptedForm');
        $recipient = EmailRecipient::create();
        $recipient->Form = $form;
        $recipient->EncryptionCrt = $file;
        $recipient->EmailAddress = 'recipient@example.com';
        $recipient->EmailFrom = 'admin@example.com';
        $recipient->write();

        self::assertEquals('protected', $file->getVisibility());
    }

}
