<?php

class XPMLicenseClaim_XenProduct_ControllerPublic_OptionalExtra extends XFCP_XPMLicenseClaim_XenProduct_ControllerPublic_OptionalExtra
{
    public function actionSave()
    {
        $fieldShown = $this->_input->filterSingle('external_extra_id_shown', XenForo_Input::BOOLEAN);
        if ($fieldShown)
        {
            XPMLicenseClaim_Globals::$siteId = $this->_input->filterSingle('site_claimable_id', XenForo_Input::UINT);
            XPMLicenseClaim_Globals::$extraId = $this->_input->filterSingle('external_extra_id', XenForo_Input::UINT);
        }

        return parent::actionSave();
    }
}