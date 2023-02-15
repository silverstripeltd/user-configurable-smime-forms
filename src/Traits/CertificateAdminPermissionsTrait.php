<?php

namespace SilverStripe\SmimeForms\Traits;

use SilverStripe\Security\Permission;
use SilverStripe\SmimeForms\Admin\EncryptionAdmin;

trait CertificateAdminPermissionsTrait
{

    /**
     * Permissions for viewing certificates.
     *
     * @param null $member
     * @return bool
     */
    public function canView($member = null): bool
    {
        return Permission::check(EncryptionAdmin::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

    /**
     * Permissions for editing certificates.
     *
     * @param null $member
     * @return bool
     */
    public function canEdit($member = null): bool
    {
        return Permission::check(EncryptionAdmin::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

    /**
     * Permissions for creating certificates.
     *
     * @param null $member
     * @param array $context
     * @return bool
     */
    public function canCreate($member = null, array $context = []): bool
    {
        return Permission::check(EncryptionAdmin::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

}
