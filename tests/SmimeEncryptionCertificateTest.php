<?php

namespace SilverStripe\SmimeForms\Tests;

use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ValidationException;
use SilverStripe\SmimeForms\Model\SmimeEncryptionCertificate;

class SmimeEncryptionCertificateTest extends SapphireTest
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
     * Email address for an encryption certificate should be unique.
     */
    public function testValidation(): void
    {
        $certificate = SmimeEncryptionCertificate::create();
        $certificate->EmailAddress = 'recipient@example.com';
        $certificate->write();

        // Check written to database
        $certificates = SmimeEncryptionCertificate::get();
        $this->assertCount(1, $certificates);

        // Expect an exception for the next write where we try to create certificate with duplicate email address
        $this->expectException(ValidationException::class);
        $this->expectErrorMessage('There is already an entry with this email address.');

        $certificate = SmimeEncryptionCertificate::create();
        $certificate->EmailAddress = 'recipient@example.com';
        $certificate->write();

        // Check not written to database
        $certificates = SmimeEncryptionCertificate::get();
        $this->assertCount(1, $certificates);

        $certificate = SmimeEncryptionCertificate::create();
        $certificate->EmailAddress = 'recipient2@example.com';
        $certificate->write();

        // Check written to database
        $certificates = SmimeEncryptionCertificate::get();
        $this->assertCount(2, $certificates);
    }

    public function testWritesProtectedFiles(): void
    {
        TestAssetStore::activate('CertificatesAssetsTest');
        $this->logInWithPermission('ADMIN');

        $crtFile = File::create();
        $crtFile->setFromLocalFile(sprintf('%s%s', __DIR__, '/fixtures/smime_test_recipient.crt'));
        $crtFile->write();

        $certificate = SmimeEncryptionCertificate::create();
        $certificate->EmailAddress = 'recipient@example.com';
        $certificate->EncryptionCrtID = $crtFile->ID;
        $certificate->write();

        $file = File::get_by_id($crtFile->ID);

        $this->assertEquals('protected', $file->getVisibility());
    }

}
