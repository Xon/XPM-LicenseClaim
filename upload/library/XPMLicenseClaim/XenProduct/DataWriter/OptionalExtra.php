<?php

class XPMLicenseClaim_XenProduct_DataWriter_OptionalExtra extends XFCP_XPMLicenseClaim_XenProduct_DataWriter_OptionalExtra
{
    protected function _getFields()
    {
        $fields = parent::_getFields();

        $fields['xenproduct_optional_extra']['site_claimable_id'] = array('type' => self::TYPE_UNKNOWN, 'default' => null);
        $fields['xenproduct_optional_extra']['external_extra_id'] = array('type' => self::TYPE_UNKNOWN, 'default' => null);

        return $fields;
    }

    /**
     * Pre-save handling.
     */
    protected function _preSave()
    {
        if (XPMLicenseClaim_Globals::$siteId && XPMLicenseClaim_Globals::$extraId)
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
            if (XPMLicenseClaim_Globals::$extraId > 0)
            {
                $this->set('external_extra_id', XPMLicenseClaim_Globals::$extraId);
            }
            else if (XPMLicenseClaim_Globals::$extraId < 0)
            {
                $this->error('This external extra ID has must be positive.', 'external_extra_id');
                return false;
            }
            else
            {
                $this->set('external_extra_id', null);
            }
        }
        if ($this->get('external_extra_id') && ($this->isInsert() || $this->get('external_extra_id') != $this->getExisting('external_extra_id')))
        {
            $existing = $this->_db->fetchRow('
                SELECT *
                FROM xenproduct_optional_extra
                WHERE site_claimable_id = ? and external_extra_id = ?
            ', array($this->get('site_claimable_id'), $this->get('external_extra_id')));
            if ($existing)
            {
                $this->error('This external extra ID has already been used on another optional extra (' . htmlspecialchars($existing['extra_title']) . ').', 'external_extra_id');
                return false;
            }
        }
        parent::_preSave();
    }
}