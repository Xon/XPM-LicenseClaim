<?php

class XPMLicenseClaim_XenProduct_DataWriter_Product extends XFCP_XPMLicenseClaim_XenProduct_DataWriter_Product
{
    protected function _getFields()
    {
        $fields = parent::_getFields();

        $fields['xenproduct_product']['site_claimable_id'] = array('type' => self::TYPE_UNKNOWN, 'default' => null);
        $fields['xenproduct_product']['external_product_id'] = array('type' => self::TYPE_UNKNOWN, 'default' => null);

        return $fields;
    }

    /**
     * Pre-save handling.
     */
    protected function _preSave()
    {
        if (XPMLicenseClaim_Globals::$siteId && XPMLicenseClaim_Globals::$productId)
        {
            if (XPMLicenseClaim_Globals::$siteId > 0)
            {
                $this->set('site_claimable_id', XPMLicenseClaim_Globals::$siteId);
            }
            else if (XPMLicenseClaim_Globals::$siteId < 0)
            {
                $this->error('This external extra ID has must be positive.', 'site_claimable_id');
                return false;
            }
            else
            {
                $this->set('site_claimable_id', null);
            }
            if (XPMLicenseClaim_Globals::$productId > 0)
            {
                $this->set('external_product_id', XPMLicenseClaim_Globals::$productId);
            }
            else if (XPMLicenseClaim_Globals::$productId < 0)
            {
                $this->error('This external Product ID has must be positive.', 'external_product_id');
                return false;
            }
            else
            {
                $this->set('external_product_id', null);
            }
        }
        if ($this->get('external_product_id') && ($this->isInsert() || $this->get('external_product_id') != $this->getExisting('external_product_id')))
        {
            $existing = $this->_db->fetchRow('
                SELECT *
                FROM xenproduct_product
                WHERE site_claimable_id = ? and external_product_id = ?
            ', array($this->get('site_claimable_id'), $this->get('external_product_id')));
            if ($existing)
            {
                $this->error('This external product ID has already been used on another product (' . htmlspecialchars($existing['product_title']) . ').', 'external_product_id');
                return false;
            }
        }
        parent::_preSave();
    }
}