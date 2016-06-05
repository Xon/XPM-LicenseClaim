<?php

class XenProductXenMods_Listener
{
	public static function extendProductController($class, array &$extend)
	{
		$extend[] = 'XenProductXenMods_ControllerPublic_Product';
	}

	public static function extendProductDataWriter($class, array &$extend)
	{
		$extend[] = 'XenProductXenMods_DataWriter_Product';
	}

	public static function extendOptionalExtraController($class, array &$extend)
	{
		$extend[] = 'XenProductXenMods_ControllerPublic_OptionalExtra';
	}

	public static function extendOptionalExtraDataWriter($class, array &$extend)
	{
		$extend[] = 'XenProductXenMods_DataWriter_OptionalExtra';
	}
}