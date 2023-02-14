<?php

namespace SilverStripe\SmimeForms\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\TextField;
use SilverStripe\SmimeForms\Extensions\SmimeEncryptionCertificate;

class EncryptionAdmin extends ModelAdmin
{

    /**
     * @var string
     */
    private static $menu_title = 'Email Encryption';

    /**
     * @var string
     */
    private static $url_segment = 'email-encryption-certificates';

    /**
     * @var array
     */
    private static $managed_models = [
        SmimeEncryptionCertificate::class,
    ];

}
