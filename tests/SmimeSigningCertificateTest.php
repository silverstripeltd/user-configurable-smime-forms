<?php

namespace SilverStripe\SmimeForms\Tests;

use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\ValidationException;
use SilverStripe\SmimeForms\Model\SmimeEncryptionCertificate;
use SilverStripe\SmimeForms\Model\SmimeSigningCertificate;

class SmimeSigningCertificateTest extends SapphireTest
{

    protected $usesDatabase = true; // phpcs:ignore

    public function setUp(): void
    {
        parent::setUp();

        TestAssetStore::activate('SmimeCertificatesTest');
    }

    protected function tearDown(): void
    {
        TestAssetStore::reset();

        parent::tearDown();
    }

    /**
     * Email address for a signing certificate should be unique.
     */
    public function testValidation(): void
    {
        $certificate = SmimeSigningCertificate::create();
        $certificate->EmailAddress = 'sender@example.com';
        $certificate->write();

        // Check written to database
        $certificates = SmimeSigningCertificate::get();
        $this->assertCount(1, $certificates);

        // Expect an exception for the next write where we try to create certificate with duplicate email address
        $this->expectException(ValidationException::class);
        $this->expectErrorMessage('There is already an entry with this email address.');

        $certificate = SmimeSigningCertificate::create();
        $certificate->EmailAddress = 'sender@example.com';
        $certificate->write();

        // Check not written to database
        $certificates = SmimeSigningCertificate::get();
        $this->assertCount(1, $certificates);

        $certificate = SmimeSigningCertificate::create();
        $certificate->EmailAddress = 'anothersender@example.com';
        $certificate->write();

        // Check written to database
        $certificates = SmimeSigningCertificate::get();
        $this->assertCount(2, $certificates);
    }

    public function testWritesProtectedFiles(): void
    {
        TestAssetStore::activate('CertificatesAssetsTest');
        $this->logInWithPermission('ADMIN');

        $crtFile = File::create();
        $crtFile->setFromLocalFile(sprintf('%s%s', __DIR__, '/fixtures/smime_test_sender.crt'));
        $crtFile->write();

        $keyFile = File::create();
        $keyFile->setFromLocalFile(sprintf('%s%s', __DIR__, '/fixtures/smime_test_sender.key'));
        $keyFile->write();

        $certificate = SmimeEncryptionCertificate::create();
        $certificate->EmailAddress = 'sender@example.com';
        $certificate->SigningCertificateID = $crtFile->ID;
        $certificate->SigningKeyID = $keyFile->ID;
        $certificate->write();

        $this->assertEquals('protected', $crtFile->getVisibility());
        $this->assertEquals('protected', $keyFile->getVisibility());
    }

    public function testKeyPassphraseIsEncrypted(): void
    {
        $signingPassword = 'Test123!';

        $certificate = SmimeSigningCertificate::create();
        $certificate->EmailAddress = 'sender@example.com';
        $certificate->SigningPassword = $signingPassword;
        $certificate->write();

        // Do a direct query here so that we can get the value as it is
        // stored in the database without it being decrypted
        $sqlQuery = new SQLSelect();
        $sqlQuery->setFrom('SmimeSigningCertificate');
        $sqlQuery->addWhere(['ID = ?' => $certificate->ID]);
        $result = $sqlQuery->execute();
        $signingPasswordAsStored = $result->first()['SigningPassword'];

        // Check that stored encrypted value is not the same as set value
        $this->assertNotEquals($signingPassword, $signingPasswordAsStored);

        // Check that when we retrieve the value it is decrypted as expected
        $this->assertEquals($signingPassword, $certificate->SigningPassword);
    }

}
