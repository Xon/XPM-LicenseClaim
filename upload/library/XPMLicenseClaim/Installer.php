<?php

class XPMLicenseClaim_XenProduct_Installer
{
    protected static $_db = null;

    protected static function _canBeInstalled(&$error)
    {
        if (XenForo_Application::isRegistered('addOns'))
        {
            $addOns = XenForo_Application::get('addOns');
            if (empty($addOns['XenProduct']))
            {
                $error = 'This add-on requires Xen Product Manager to be installed first.';

                return false;
            }
        }

        if (XenForo_Application::$versionId < 1050070)
        {
            $error = 'This add-on requires XenForo 1.5.0 or higher.';
            return false;
        }
        return true;
    }

    public static function install($existingAddOn, array $addOnData, SimpleXMLElement $xml)
    {
        $version = isset($existingAddOn['version_id']) ? $existingAddOn['version_id'] : 0;
        if (!self::_canBeInstalled($error))
        {
            throw new XenForo_Exception($error, true);
        }

        if (!$version)
        {
            self::_runQuery("
                CREATE TABLE IF NOT EXISTS xenproduct_xenmods_log (
                    xenmods_log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT(10) UNSIGNED NOT NULL,
                    product_id INT(10) UNSIGNED NOT NULL,
                    xenmods_product_id INT(10) NOT NULL,
                    cart_key VARCHAR(250) NOT NULL,
                    item_id INT(10) NOT NULL,
                    email VARCHAR(250) NOT NULL,
                    log_date INT(10) UNSIGNED NOT NULL,
                    PRIMARY KEY (xenmods_log_id),
                    UNIQUE KEY cart_key_item_id_email(cart_key, item_id, email)
                ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
            ");
        }

        if (!$version || $version < 1000170)
        {
            self::_runQuery("
                ALTER TABLE xenproduct_product
                ADD xenmods_product_id INT(10) DEFAULT NULL,
                ADD UNIQUE INDEX xenmods_product_id(xenmods_product_id)
            ");

            self::_runQuery("
                ALTER TABLE xenproduct_optional_extra
                ADD xenmods_extra_id INT(10) DEFAULT NULL,
                ADD UNIQUE INDEX xenmods_extra_id(xenmods_extra_id)
            ");
        }
        
        if (!$version || $version < 1000300)
        {
            self::_runQuery("
                alter table xenproduct_product CHANGE xenmods_product_id xenmods_product_id INT(10) DEFAULT NULL;
            ");
            self::_runQuery("
                update xenproduct_product set xenmods_product_id = null where xenmods_product_id = 0;
            ");

            self::_runQuery("
                alter table xenproduct_optional_extra CHANGE xenmods_extra_id xenmods_extra_id INT(10) DEFAULT NULL;
            ");
            self::_runQuery("
                update xenproduct_optional_extra set xenmods_extra_id = null where xenmods_extra_id = 0;
            ");
        }
    }

    public static function uninstall()
    {
        self::_runQuery("
            ALTER TABLE xenproduct_product
            DROP xenmods_product_id
        ");
    }

    protected static function _runQuery($sql)
    {
        $db = self::_getDb();

        try
        {
            $db->query($sql);
        }
        catch (Zend_Db_Exception $e) {}
    }

    /**
     * @return Zend_Db_Adapter_Abstract
     */
    protected static function _getDb()
    {
        if (!self::$_db)
        {
            self::$_db = XenForo_Application::getDb();
        }

        return self::$_db;
    }
}