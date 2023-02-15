<?php

namespace SilverStripe\SmimeForms\Traits;

use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\SmimeForms\Admin\EncryptionAdmin;

trait CertificateAdminPermissionsTrait
{

    /**
     * Permissions for viewing certificates.
     */
    public function canView(?Member $member = null): bool
    {
        return Permission::check(EncryptionAdmin::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

    /**
     * Permissions for editing certificates.
     */
    public function canEdit(?Member $member = null): bool
    {
        return Permission::check(EncryptionAdmin::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

    /**
     * Permissions for creating certificates.
     */
    public function canCreate(?Member $member = null, array $context = []): bool
    {
        return Permission::check(EncryptionAdmin::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

}
