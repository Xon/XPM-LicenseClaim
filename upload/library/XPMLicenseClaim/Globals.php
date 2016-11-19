<?php

// This class is used to encapsulate global state between layers without using $GLOBAL[] or
// relying on the consumer being loaded correctly by the dynamic class autoloader
class XPMLicenseClaim_Globals
{
    public $siteId = null;
    public $extraId = null;
    public $productId = null;

    private function __construct() {}
}