<?php

class XenProductXenMods_DataWriter_OptionalExtra extends XFCP_XenProductXenMods_DataWriter_OptionalExtra
{
	protected function _getFields()
	{
		$fields = parent::_getFields();

		$fields['xenproduct_optional_extra']['xenmods_extra_id'] = array('type' => self::TYPE_UINT, 'default' => 0);

		return $fields;
	}

	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
		if (XenForo_Application::isRegistered('session'))
		{
			/** @var $session XenForo_Session */
			$session = XenForo_Application::get('session');
			if ($session->isRegistered('xmExtraId'))
			{
				$this->set('xenmods_extra_id', $session->get('xmExtraId'));
				$session->remove('xmExtraId');
			}
		}
		if ($this->isInsert() || $this->get('xenmods_extra_id') != $this->getExisting('xenmods_extra_id'))
		{
			$existing = $this->_db->fetchRow('
				SELECT *
				FROM xenproduct_optional_extra
				WHERE xenmods_extra_id = ?
			', $this->get('xenmods_extra_id'));
			if ($existing)
			{
				$this->error('This XenMods extra ID has already been used on another optional extra (' . htmlspecialchars($existing['extra_title']) . ').', 'xenmods_extra_id');
				return false;
			}
		}
		parent::_preSave();
	}
}