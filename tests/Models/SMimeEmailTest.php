<?php

namespace SilverStripe\SmimeForms\Tests\Models;

use DNADesign\ElementalUserForms\Model\ElementForm;
use ReflectionException;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\ValidationException;
use SilverStripe\SmimeForms\Model\SmimeEmail;
use SilverStripe\SmimeForms\Model\SmimeEncryptionCertificate;
use SilverStripe\SmimeForms\Model\SmimeSigningCertificate;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;
use SilverStripe\UserForms\Model\Submission\SubmittedFormField;
use SilverStripe\View\ViewableData;
use Throwable;

class SMimeEmailTest extends FunctionalTest
{

    /**
     * @inheritDoc
     */
    protected $usesDatabase = true; // phpcs:ignore

    /**
     * @inheritDoc
     */
    protected static $fixture_file = '../fixtures/SMimeEmailTest.yml';

    /**
     * Loads all the signing and encryption certificates/keys into the local file system
     * and sets up the SmimeEncryptionCertificate and SmimeSigningCertificate instances.
     *
     * @inheritDoc
     * @throws ValidationException
     */
    public function setUp(): void
    {
        parent::setUp();

        // Activate the TestAssetStore for loading certificates and keys
        TestAssetStore::activate('SmimeCertificatesTest');

        // Bypass CMS admin permissions
        $this->logInWithPermission('ADMIN');

        // Load the encryption certificate
        $encryptionCertificateFile = $this->getAndLoadLocalFile('smime_test_recipient.crt');

        // Load the signing certificate
        $signingCertificateFile = $this->getAndLoadLocalFile('smime_test_sender_certificate.pem');

        // Load the signing private key
        $signingKeyFile = $this->getAndLoadLocalFile('smime_test_sender_privatekey.pem');

        // Mock the SmimeSigningCertificate instance
        $signingCertificate = SmimeSigningCertificate::create();
        $signingCertificate->EmailAddress = 'sender@example.com';
        $signingCertificate->SigningCertificateID = $signingCertificateFile->ID;
        $signingCertificate->SigningKeyID = $signingKeyFile->ID;
        $signingCertificate->SigningPassword = 'Test123!';
        $signingCertificate->write();

        // Mock the SmimeEncryptionCertificate instance
        $certificate = SmimeEncryptionCertificate::create();
        $certificate->EmailAddress = 'recipient@example.com';
        $certificate->EncryptionCrtID = $encryptionCertificateFile->ID;
        $certificate->write();
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
     * Checks email is digitally signed when sent to a recipient.
     *
     * @return void
     * @throws ValidationException
     */
    public function testSendSignedMail(): void
    {
        // Mock SmimeEmail instance
        $email = $this->mockSmimeEmail(
            'sender@example.com',
            'recipient@example.com',
            'RegistrationTest [UNENCRYPTED: CHECK CMS CONFIGURATION]',
        );

        // Mock set of submitted data associated to this email
        $this->mockSubmittedFormData($email);

        // Set the signing certificate credentials
        $signingCredentials = $this->mockSigningCredentials();
        $email->setSigningCredentials($signingCredentials);

        // Send the email
        try {
            $email->send();
        } catch (Throwable $e) {
            var_dump($e->getMessage());
        }

        // Check email is digitally signed
        $body = $email->getBody();
        $type = $body->getMediaType();
        $mediaSubType = $body->getMediaSubtype();
        $this->assertEquals('multipart', $type);
        $this->assertEquals('signed', $mediaSubType);

        // Check email was sent successfully
        $this->assertEmailSent('recipient@example.com');
    }

    /**
     * Checks email is encrypted when sent to a recipient.
     *
     * @return void
     * @throws ValidationException
     */
    public function testSendEncryptedMail(): void
    {
        // Mock SmimeEmail instance
        $email = $this->mockSmimeEmail(
            'sender@example.com',
            'recipient@example.com',
            'RegistrationEncryptionTest',
        );

        // Mock set of submitted data associated to this email
        $this->mockSubmittedFormData($email);

        // Set the encryption certificate
        $encryptionFilePath = $this->mockEncryptionFilePath();
        $email->setEncryptionFilePath($encryptionFilePath);

        // Send the email
        try {
            $email->send();
        } catch (Throwable $e) {
            var_dump($e->getMessage());
        }

        // Check email is encrypted
        $body = $email->getBody();
        $type = $body->getMediaType();
        $mediaSubType = $body->getMediaSubtype();
        $this->assertEquals('application', $type);
        $this->assertEquals('pkcs7-mime', $mediaSubType);

        // Check email was sent successfully
        $this->assertEmailSent('recipient@example.com');
    }

    /**
     * Checks email is both signed and encrypted when sent to a recipient.
     *
     * @throws ValidationException
     */
    public function testSendSignedAndEncryptedMail(): void
    {
        // Mock SmimeEmail instance
        $email = $this->mockSmimeEmail(
            'sender@example.com',
            'recipient@example.com',
            'RegistrationEncryptionTest',
        );

        // Mock set of submitted data associated to this email
        $this->mockSubmittedFormData($email);

        // Set the signing certificate credentials
        $signingCredentials = $this->mockSigningCredentials();
        $email->setSigningCredentials($signingCredentials);

        // Set the encryption certificate
        $encryptionFilePath = $this->mockEncryptionFilePath();
        $email->setEncryptionFilePath($encryptionFilePath);

        // Send the email
        try {
            $email->send();
        } catch (Throwable $e) {
            var_dump($e->getMessage());
        }

        // Check email is both signed and encrypted
        $body = $email->getBody();
        $type = $body->getMediaType();
        $mediaSubType = $body->getMediaSubtype();
        $this->assertEquals('application', $type);
        $this->assertEquals('pkcs7-mime', $mediaSubType);

        // Check email was sent successfully
        $this->assertEmailSent('recipient@example.com');
    }

    /**
     * Checks the signatures of an Email are correct prior to and post signing.
     *
     * @return void
     * @throws ReflectionException|ValidationException
     */
    public function testSignEmail(): void
    {
        // Mock SmimeEmail instance
        $email = $this->mockSmimeEmail(
            'sender@example.com',
            'recipient@example.com',
            'RegistrationTest [UNENCRYPTED: CHECK CMS CONFIGURATION]',
        );

        // Mock set of submitted data associated to this email
        $this->mockSubmittedFormData($email);
        $email->invokeUpdateHtmlAndTextWithRenderedTemplates();

        // Set the signing certificate credentials
        $signingCredentials = $this->mockSigningCredentials();
        $email->setSigningCredentials($signingCredentials);

        // Check the un-signed state of email
        $body = $email->getBody();
        $type = $body->getMediaType();
        $mediaSubType = $body->getMediaSubtype();
        $this->assertEquals('multipart', $type);
        $this->assertEquals('alternative', $mediaSubType);

        // Run encryption function
        $email->signEmail();

        // Check email has correct signed signatures
        $body = $email->getBody();
        $type = $body->getMediaType();
        $mediaSubType = $body->getMediaSubtype();
        $this->assertEquals('multipart', $type);
        $this->assertEquals('signed', $mediaSubType);
    }

    /**
     * Checks the signatures of an Email are correct prior to and post encryption.
     *
     * @return void
     * @throws ReflectionException|ValidationException
     */
    public function testEncryptEmail(): void
    {
        // Mock SmimeEmail instance
        $email = $this->mockSmimeEmail(
            'sender@example.com',
            'recipient@example.com',
            'RegistrationEncryptionTest',
        );

        // Mock set of submitted data associated to this email
        $this->mockSubmittedFormData($email);
        $email->invokeUpdateHtmlAndTextWithRenderedTemplates();

        // Set the encryption file path
        $encryptionFilePath = $this->mockEncryptionFilePath();
        $email->setEncryptionFilePath($encryptionFilePath);

        // Check unencrypted state of email
        $body = $email->getBody();
        $type = $body->getMediaType();
        $mediaSubType = $body->getMediaSubtype();
        $this->assertEquals('multipart', $type);
        $this->assertEquals('alternative', $mediaSubType);

        // Run encryption function
        $email->encryptEmail();

        // Check email has correct encryption signatures
        $body = $email->getBody();
        $type = $body->getMediaType();
        $mediaSubType = $body->getMediaSubtype();
        $this->assertEquals('application', $type);
        $this->assertEquals('pkcs7-mime', $mediaSubType);
    }

    /**
     * Checks if the email data is correctly rendered on the email templates.
     * This is essential in ensuring that the content exists prior to any form
     * of digital signing or encryption.
     *
     * @return void
     * @throws ReflectionException|ValidationException
     */
    public function testInvokeUpdateHtmlAndTextWithRenderedTemplates(): void
    {
        // Mock SmimeEmail instance
        $email = $this->mockSmimeEmail(
            'vatest@example.com',
            'mailcatcher@example.com',
            'RegistrationTest [UNENCRYPTED: CHECK CMS CONFIGURATION]'
        );

        // Mock set of submitted data associated to this email
        $this->mockSubmittedFormData($email);

        // Check the data on the email template hasn't been rendered before this function is invoked
        $this->assertNull($email->getTextBody());
        $this->assertNull($email->getHtmlBody());

        // Now trigger the render function
        $email->invokeUpdateHtmlAndTextWithRenderedTemplates();

        // Check the data on the email exists and has been rendered, ready for signing and encryption
        $this->assertNotNull($email->getTextBody());
        $this->assertNotNull($email->getHtmlBody());
    }

    /**
     * Checks the signing credentials such as the signing certificate
     * and private key are correctly set for an SMimeEmail instance.
     *
     * @return void
     */
    public function testSetAndGetSigningCredentials(): void
    {
        // Mock SmimeEmail instance
        $email = $this->mockSmimeEmail(
            'vatest@example.com',
            'mailcatcher@example.com',
            'RegistrationTest [UNENCRYPTED: CHECK CMS CONFIGURATION]'
        );

        // Mock the signing certificate credentials
        $expectedCredentials = $this->mockSigningCredentials();

        // Run test function
        $email->setSigningCredentials($expectedCredentials);

        // Check output is not null and matches expected set of credentials
        $actual = $email->getSigningCredentials();
        $this->assertNotNull($actual);
        $this->assertEquals($expectedCredentials, $actual);
    }

    /**
     * Checks the encryption file path is correctly set for an SMimeEmail instance.
     *
     * @return void
     */
    public function testSetAndGetEncryptionFilePath(): void
    {
        // Mock SmimeEmail instance
        $email = $this->mockSmimeEmail(
            'vatest@example.com',
            'mailcatcher@example.com',
            'RegistrationTest [UNENCRYPTED: CHECK CMS CONFIGURATION]'
        );

        // Mock the encryption file path
        $encryptionFilePath = $this->mockEncryptionFilePath();

        // Run test function
        $email->setEncryptionFilePath($encryptionFilePath);

        // Check output is not null and matches expected file path
        $actual = $email->getEncryptionFilePath();
        $this->assertNotNull($actual);
        $this->assertEquals($encryptionFilePath, $actual);
    }

    /**
     * Returns a mock SMimeEmail instance.
     *
     * @param string $from
     * @param string $to
     * @param string $subject
     * @return SmimeEmail
     */
    private function mockSmimeEmail(string $from, string $to, string $subject): SmimeEmail
    {
        // Mock SmimeEmail instance
        $email = new SmimeEmail($from, $to, $subject);
        $email->setHTMLTemplate('/email/SubmittedFormEmail');
        $email->setPlainTemplate('/email/SubmittedFormEmailPlain');

        return $email;
    }

    /**
     * Returns an array containing a mock set of signing credentials.
     *
     * @return array
     */
    private function mockSigningCredentials(): array
    {
        $signingCertificate = SmimeSigningCertificate::get()->first();
        $signingCertificateFile = File::get()->byID($signingCertificate->SigningCertificateID);
        $signingCertificateFilePath = TestAssetStore::getLocalPath($signingCertificateFile);
        $signingKeyFile = File::get()->byID($signingCertificate->SigningKeyID);
        $signingKeyFilePath = TestAssetStore::getLocalPath($signingKeyFile);

        return [
            'certificate' => $signingCertificateFilePath,
            'key' => $signingKeyFilePath,
            'passphrase' => 'Test123!',
        ];
    }

    /**
     * Returns a string containing a mock set encryption file path.
     *
     * @return string
     */
    private function mockEncryptionFilePath(): string
    {
        $encryptionCertificate = SmimeEncryptionCertificate::get()->first();
        $encryptionFile = File::get()->byID($encryptionCertificate->EncryptionCrtID);

        return TestAssetStore::getLocalPath($encryptionFile);
    }

    /**
     * Mocks SubmittedForm data associated to the given Email instance.
     *
     * @param SmimeEmail $email
     * @return void
     * @throws ValidationException
     */
    private function mockSubmittedFormData(SmimeEmail &$email): void
    {
        // Get ElementForm instance
        $elementForm = $this->objFromFixture(ElementForm::class, 'registration_form');
        $form = $elementForm->Form();

        // Mock SubmittedForm instance
        $submittedForm = new SubmittedForm();
        $submittedForm->ParentID = $form->ID;
        $submittedForm->write();

        // Mock SubmittedFormField instances
        $textSubmittedField = $this->mockSubmittedFormField(
            $submittedForm,
            'TextFieldOne',
            'Sample Text Field',
            'Sample Text Value'
        );

        $emailSubmittedField = $this->mockSubmittedFormField(
            $submittedForm,
            'EmailFieldOne',
            'Sample Email Field',
            'sample@example.com'
        );

        // Mock Fields set
        $fields = new ArrayList();
        $fields->push($textSubmittedField);
        $fields->push($emailSubmittedField);

        // Set dynamic data
        $dynamicData = [
            'Sender' => null,
            'HideFormData' => false,
            'SubmittedForm' => $submittedForm,
            'Fields' => $fields,
            'Body' => '',
        ];

        // Set the dynamic data for the ViewableData
        $viewableData = new ViewableData();
        $viewableData->setDynamicData('Sender', $dynamicData['Sender']);
        $viewableData->setDynamicData('HideFormData', $dynamicData['HideFormData']);
        $viewableData->setDynamicData('SubmittedForm', $dynamicData['SubmittedForm']);
        $viewableData->setDynamicData('Fields', $dynamicData['Fields']);
        $viewableData->setDynamicData('Body', $dynamicData['Body']);

        // Set ViewableData for the Email
        $email->setData($viewableData);
    }

    /**
     * Mocks a SubmittedFormField instance associated to the given SubmittedForm instance.
     *
     * @param SubmittedForm $submittedForm
     * @param string $name
     * @param string $title
     * @param string $value
     * @return SubmittedFormField
     * @throws ValidationException
     */
    private function mockSubmittedFormField(
        SubmittedForm $submittedForm,
        string $name,
        string $title,
        string $value
    ): SubmittedFormField {
        $field = new SubmittedFormField();
        $field->ParentID = $submittedForm->ID;
        $field->Name = $name;
        $field->Title = $title;
        $field->Value = $value;
        $field->write();

        return $field;
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

}
