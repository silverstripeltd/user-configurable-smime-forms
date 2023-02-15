<?php

namespace SilverStripe\SmimeForms\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\SmimeForms\Model\SmimeEncryptionCertificate;
use SilverStripe\SmimeForms\Model\SmimeSigningCertificate;

/**
 * class EncryptionAdmin
 *
 * This class sets ups a model admin for configuring recipient and sender certificates used for email encryption and
 * digital signing of emails.
 *
 * Only members with a PERMISSION_SMIME_ENCRYPTION_ADMIN permission will be able to view this model admin and manage
 * these certificates.
 */
class EncryptionAdmin extends ModelAdmin implements PermissionProvider
{

    /**
     * Permission name for allowing access to certificate administration.
     */
    public const PERMISSION_SMIME_ENCRYPTION_ADMIN = 'PERMISSION_SMIME_ENCRYPTION_ADMIN';

    /**
     * Define the menu title for this ModelAdmin.
     */
    private static string $menu_title = 'S/MIME Certificates';

    /**
     * Define the url segment for this ModelAdmin.
     */
    private static string $url_segment = 'smime-certificates';

    /**
     * Define the icon used in the left hand menu for this ModelAdmin.
     */
    private static string $menu_icon_class = 'font-icon-user-lock';

    /**
     * This ModelAdmin manages certificates for encryption and signing.
     */
    private static array $managed_models = [
        SmimeEncryptionCertificate::class,
        SmimeSigningCertificate::class,
    ];

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

}
