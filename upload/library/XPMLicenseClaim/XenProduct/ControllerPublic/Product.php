<?php

class XPMLicenseClaim_XenProduct_ControllerPublic_Product extends XFCP_XPMLicenseClaim_XenProduct_ControllerPublic_Product
{
    public function actionSave()
    {
        $fieldShown = $this->_input->filterSingle('external_product_id_shown', XenForo_Input::BOOLEAN);
        if ($fieldShown)
        {
            XPMLicenseClaim_Globals::$siteId = $this->_input->filterSingle('site_claimable_id', XenForo_Input::UINT);
            XPMLicenseClaim_Globals::$productId = $this->_input->filterSingle('external_product_id', XenForo_Input::UINT);
        }

        return parent::actionSave();
    }

    public function actionClaimExternalLicense()
    {
        $this->_assertRegistrationRequired();

        $productId = $this->_input->filterSingle('product_id', 'uint');
        $productHelper = $this->_getProductHelper();
        list ($product, $version) = $productHelper->assertProductValidAndViewable($productId);
        $db = XenForo_Application::getDb();

        if ($this->isConfirmedPost())
        {
            $cartKey = $this->_input->filterSingle('cart_key', XenForo_Input::STRING);
            $email = $this->_input->filterSingle('email', XenForo_Input::STRING);

            $products = $db->fetchAll('
                SELECT *
                FROM xenproduct_external_licence
                WHERE cart_key = ?
                AND email = ?
                AND site_claimable_id = ?
                AND product_id = ?
            ', array($cartKey, $email, $product['site_claimable_id'], $product['external_product_id']));

            if (!$products)
            {
                return $this->responseError('No licenses could be found for this product with the cart key and email address provided.');
            }

            $valid = false;
            foreach ($products AS $key => $externalProduct)
            {
                $claimed = $db->fetchRow('
                    SELECT *
                    FROM xenproduct_claim_log
                    WHERE site_claimable_id = ?
                    AND product_id = ?
                    AND cart_key = ?
                    AND item_id = ?
                    AND email = ?
                ', array(
                    $externalProduct['site_claimable_id'],
                    $externalProduct['product_id'],
                    $externalProduct['cart_key'],
                    $externalProduct['item_id'],
                    $externalProduct['email'])
                );
                if ($claimed)
                {
                    unset($products[$key]);
                    continue;
                }
                else
                {
                    $valid = true;
                }
            }

            if ($valid === false)
            {
                return $this->responseError('All licenses for this order have already been claimed.');
            }

            $visitor = XenForo_Visitor::getInstance();

            $cartModel = $this->_getCartModel();

            foreach ($products AS $item)
            {
                $cart = $cartModel->getCartByUserId($visitor->user_id, 'active');
                if ($cart)
                {
                    $cartModel->emptyCart($cart['cart_id']);
                }

                /** @var $cartWriter XenProduct_DataWriter_Cart */
                $cartWriter = XenForo_DataWriter::create('XenProduct_DataWriter_Cart');

                $cart = array(
                    'user_id' => $visitor->user_id,
                    'username' => $visitor->username,
                    'cart_currency' => XenForo_Application::getOptions()->xenproductCurrencies
                );

                $cartWriter->bulkSet($cart);
                $cartWriter->save();

                $cart = $cartWriter->getMergedData();

                $cartItemData = array(
                    'cart_id' => $cart['cart_id'],
                    'product_id' => $product['product_id'],
                    'unit_price' => $product['price'],
                    'item_optional_extras' => array()
                );


                if ($optionalExtras = @unserialize($item['license_optional_extras']))
                {
                    $extras = $db->fetchAll('
                        SELECT *
                        FROM xenproduct_optional_extra
                        WHERE site_claimable_id = ? and external_extra_id IN(' . $db->quote(array_keys($optionalExtras)) . ')
                    ', array($product['site_claimable_id']));
                    if ($extras)
                    {
                        $cartItemData['item_optional_extras'] = $this->_getOptionalExtraModel()->getOptionalExtras(array(
                            'extra_id' => XenForo_Application::arrayColumn($extras, 'extra_id')
                        ));
                    }
                }

                $cartItemWriter = XenForo_DataWriter::create('XenProduct_DataWriter_CartItem');
                $cartItemWriter->bulkSet($cartItemData);
                $cartItemWriter->save();

                $cartTotals = $cartModel->updateCartTotals($cart['cart_id']);
                $cart = array_merge($cart, $cartTotals);

                if ($item['expiry_date'])
                {
                    $expiryDate = $item['expiry_date'] + (XenForo_Application::getOptions()->XPMLicenseClaim_XenProductExtension * 86400);
                }
                else
                {
                    $expiryDate = 0;
                }

                if ($licenses = $cartModel->convertCartToLicenses($cart, $expiryDate, $item['purchase_date']))
                {
                    $db->insert('xenproduct_claim_log', array(
                        'user_id' => $visitor->user_id,
                        'product_id' => $product['product_id'],
                        'site_claimable_id' => $item['site_claimable_id'],
                        'external_product_id' => $item['product_id'],
                        'cart_key' => $item['cart_key'],
                        'item_id' => $item['item_id'],
                        'email' => $item['email'],
                        'log_date' => time()
                    ));

                    $license = reset($licenses);

                    $licenseDw = XenForo_DataWriter::create('XenProduct_DataWriter_License', XenForo_DataWriter::ERROR_SILENT);
                    $licenseDw->setExistingData($license['license_id'], true);
                    $licenseDw->set('license_alias', $item['license_alias']);
                    $licenseDw->set('license_url', $item['license_url']);
                    $licenseDw->save();
                }
            }

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('products/license'),
                'All licenses claimed successfully'
            );
        }
        else
        {
            $site = $db->fetchRow('
                SELECT *
                FROM xenproduct_site_claimable
                WHERE enabled = 1 and site_claimable_id = ?
            ', array($product['site_claimable_id']));
            if (empty($site))
            {
                return $this->responseRedirect(
                    XenForo_ControllerResponse_Redirect::SUCCESS,
                    XenForo_Link::buildPublicLink('products', $product),
                    'License are unable to be claimed'
                );
            }

            $viewParams = array(
                'product' => $product,
                'site' => $site,
            );
            return $this->responseView('XPMLicenseClaim_XenProduct_ViewPublic_Product_Claim', 'xenproduct_licence_claim', $viewParams);
        }
    }

    public function actionDetails()
    {
        $response = parent::actionDetails();
        if ($response instanceof XenForo_ControllerResponse_View && 
            !empty($response->params['product']) &&
            !empty($response->params['product']['site_claimable_id']))
        {

            $db = XenForo_Application::getDb();
            $site = $db->fetchRow('
                SELECT *
                FROM xenproduct_site_claimable
                WHERE enabled = 1 and site_claimable_id = ?
            ', array($response->params['product']['site_claimable_id']));

            if ($site)
            {
                $response->params['site'] = $site;
            }
            else
            {
                $response->params['product']['external_product_id'] = false;
            }
        }
        return $response;
    }

    protected function _getAddEditResponse(array $product = array(), array $version = array())
    {
        $response = parent::_getAddEditResponse($product, $version);
        if ($response instanceof XenForo_ControllerResponse_View && 
            !empty($response->params['product']) &&
            !empty($response->params['product']['site_claimable_id']))
        {
            $db = XenForo_Application::getDb();
            $sites = $db->fetchAll('
                SELECT *
                FROM xenproduct_site_claimable
                WHERE enabled = 1 or site_claimable_id = ?
            ', array($response->params['product']['site_claimable_id']));

            $response->params['sites'] = empty($sites) ? array() : $sites;
        }
        return $response;
    }
}