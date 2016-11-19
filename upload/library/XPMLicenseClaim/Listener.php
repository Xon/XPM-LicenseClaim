<?php

class XPMLicenseClaim_Listener
{
    public static function load_class($class, array &$extend)
    {
        $extend[] = 'XPMLicenseClaim_'.$class;
    }
}