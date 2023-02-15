<?php

namespace SilverStripe\SmimeForms\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\TextField;
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
     * @var string
     */
    private static $menu_title = 'S/MIME Certificates';

    /**
     * @var string
     */
    private static $url_segment = 'smime-certificates';

    /**
     * @var string
     */
    private static $menu_icon_class = 'font-icon-user-lock';

    /**
     * @var array
     */
    private static $managed_models = [
        SmimeEncryptionCertificate::class,
        SmimeSigningCertificate::class,
    ];

    public const PERMISSION_SMIME_ENCRYPTION_ADMIN = 'PERMISSION_SMIME_ENCRYPTION_ADMIN';

    public function canView($member = null)
    {
        return Permission::check(self::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

    public function canCreate($member = null)
    {
        return Permission::check(self::PERMISSION_SMIME_ENCRYPTION_ADMIN);
    }

    /**
     * @return array
     */
    public function providePermissions(): array
    {
        return [
            self::PERMISSION_SMIME_ENCRYPTION_ADMIN => 'Manage S/MIME encryption certificates',
        ];
    }

}
