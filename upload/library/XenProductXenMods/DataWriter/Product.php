<?php

class XenProductXenMods_DataWriter_Product extends XFCP_XenProductXenMods_DataWriter_Product
{
	protected function _getFields()
	{
		$fields = parent::_getFields();

		$fields['xenproduct_product']['xenmods_product_id'] = array('type' => self::TYPE_UINT, 'default' => 0);

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
			if ($session->isRegistered('xmProductId'))
			{
				$this->set('xenmods_product_id', $session->get('xmProductId'));
				$session->remove('xmProductId');
			}
		}
		if ($this->isInsert() || $this->get('xenmods_product_id') != $this->getExisting('xenmods_product_id'))
		{
			$existing = $this->_db->fetchRow('
				SELECT *
				FROM xenproduct_product
				WHERE xenmods_product_id = ?
			', $this->get('xenmods_product_id'));
			if ($existing)
			{
				$this->error('This XenMods product ID has already been used on another product (' . htmlspecialchars($existing['product_title']) . ').', 'xenmods_product_id');
				return false;
			}
		}
		parent::_preSave();
	}
}