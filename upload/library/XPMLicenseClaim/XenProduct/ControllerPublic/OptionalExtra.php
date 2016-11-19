<?php

class XPMLicenseClaim_XenProduct_ControllerPublic_OptionalExtra extends XFCP_XPMLicenseClaim_XenProduct_ControllerPublic_OptionalExtra
{
    public function actionSave()
    {
        $fieldShown = $this->_input->filterSingle('xenmods_extra_id_shown', XenForo_Input::BOOLEAN);
        $xenmodsExtraId = $this->_input->filterSingle('xenmods_extra_id', XenForo_Input::UINT);

        if (!$fieldShown)
        {
            return parent::actionSave();
        }

        if (XenForo_Application::isRegistered('session'))
        {
            /** @var $session XenForo_Session */
            $session = XenForo_Application::get('session');
            $session->set('xmExtraId', $xenmodsExtraId);
        }

        return parent::actionSave();
    }
}