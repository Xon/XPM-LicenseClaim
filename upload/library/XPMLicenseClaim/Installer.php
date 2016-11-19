<?php

class XPMLicenseClaim_Installer
{
    protected static $_db = null;

    protected static function _canBeInstalled(&$error)
    {
        if (!SV_Utils_AddOn::addOnIsActive('XenProduct'))
        {
            $error = 'This add-on requires Xen Product Manager to be installed first.';
            return false;
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

        $db = XenForo_Application::getDb();
        $db->query("
            CREATE TABLE IF NOT EXISTS `xenproduct_external_licence` (
              `external_licence_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `site_claimable_id` INT UNSIGNED NOT NULL,
              `cart_id` int(10) unsigned NOT NULL,
              `product_id` int(10) unsigned NOT NULL,
              `item_id` int(10) unsigned NOT NULL,
              `license_alias` varchar(100) NOT NULL,
              `license_url` text NOT NULL,
              `expiry_date` int(10) unsigned NOT NULL,
              `purchase_date` int(10) unsigned NOT NULL,
              `license_optional_extras` mediumblob NOT NULL,
              `cart_key` varchar(50) NOT NULL,
              `user_id` int(10) unsigned NOT NULL,
              `username` varchar(50) NOT NULL,
              `email` varchar(120) NOT NULL,
              PRIMARY KEY (external_licence_id),
              KEY cart_key_item_id_email(site_claimable_id, cart_key, item_id, email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");

        $db->query("
            CREATE TABLE IF NOT EXISTS xenproduct_site_claimable (
                site_claimable_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                label VARCHAR(250) NOT NULL,
                enabled tinyint(1) default 1,
                PRIMARY KEY (site_claimable_id),
                UNIQUE KEY label(label)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
        ");
        $db->query("
            CREATE TABLE IF NOT EXISTS xenproduct_claim_log (
                site_claimable_id INT UNSIGNED NOT NULL,
                claim_log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(10) UNSIGNED NOT NULL,
                product_id INT(10) UNSIGNED NOT NULL,
                external_product_id INT(10) NOT NULL,
                cart_key VARCHAR(250) NOT NULL,
                item_id INT(10) NOT NULL,
                email VARCHAR(250) NOT NULL,
                log_date INT(10) UNSIGNED NOT NULL,
                PRIMARY KEY (claim_log_id),
                UNIQUE KEY cart_key_item_id_email(site_claimable_id, cart_key, item_id, email)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
        ");
        SV_Utils_Install::addColumn('xenproduct_product', 'site_claimable_id', 'INT UNSIGNED DEFAULT NULL');
        SV_Utils_Install::addColumn('xenproduct_product', 'external_product_id', 'INT(10) DEFAULT NULL');
        SV_Utils_Install::addIndex('xenproduct_product', 'claimable', array('site_claimable_id', 'external_product_id'));

        SV_Utils_Install::addColumn('xenproduct_optional_extra', 'site_claimable_id', 'INT UNSIGNED DEFAULT NULL');
        SV_Utils_Install::addColumn('xenproduct_optional_extra', 'external_extra_id', 'INT(10) DEFAULT NULL');
        SV_Utils_Install::addIndex('xenproduct_optional_extra', 'claimable', array('site_claimable_id', 'external_extra_id'));

        if (SV_Utils_AddOn::addOnIsActive('XenProductXenMods'))
        {
            // migrate data
            $db->query("
                insert ignore into xenproduct_site_claimable (site_claimable_id, label) values (1, 'XenMods')
            ");
            $db->query("
                insert ignore into xenproduct_claim_log (site_claimable_id, claim_log_id, user_id, product_id, external_product_id, cart_key, item_id, email, log_date)
                select 1, xenmods_log_id, user_id, product_id, xenmods_product_id, cart_key, item_id, email, log_date
                from xenproduct_xenmods_log
            ");

            $db->query("
                update xenproduct_product
                set site_claimable_id = 1, external_product_id = xenmods_product_id
                where xenmods_product_id is not null and external_product_id is null

            ");
            $db->query("
                update xenproduct_optional_extra
                set site_claimable_id = 1, external_extra_id = xenmods_extra_id
                where xenmods_extra_id is not null and external_extra_id is null
            ");

            $db->query("
                insert ignore xenproduct_external_licence (site_claimable_id, cart_id, product_id, item_id, license_alias, license_url, expiry_date, purchase_date, license_optional_extras, cart_key, user_id, username, email)
                select 1, cart_id, product_id, item_id, license_alias, license_url, expiry_date, purchase_date, license_optional_extras, cart_key, user_id, username, email
                from avforums_xenmods
            ");

            SV_Utils_AddOn::removeOldAddOns(array('XenProductXenMods'), true);
            // ensure cleanup happens (leave the old licence table around)
            SV_Utils_Install::dropColumn('xenproduct_product', 'xenmods_product_id');
            SV_Utils_Install::dropColumn('xenproduct_optional_extra', 'xenmods_extra_id');
        }
    }

    public static function uninstall()
    {
        $db = XenForo_Application::getDb();
        $db->query("drop table if exists xenproduct_site_claimable");
        $db->query("drop table if exists xenproduct_claim_log");

        SV_Utils_Install::dropColumn('xenproduct_product', 'site_claimable_id');
        SV_Utils_Install::dropColumn('xenproduct_product', 'external_product_id');
        SV_Utils_Install::dropColumn('xenproduct_optional_extra', 'site_claimable_id');
        SV_Utils_Install::dropColumn('xenproduct_optional_extra', 'external_extra_id');
    }
}