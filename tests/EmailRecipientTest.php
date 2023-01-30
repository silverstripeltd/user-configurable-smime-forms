<?php

namespace SilverStripe\SmimeForms\Tests;

use DNADesign\ElementalUserForms\Model\ElementForm;
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

}
