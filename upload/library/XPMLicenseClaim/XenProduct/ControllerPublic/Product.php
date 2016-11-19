<?php

class XPMLicenseClaim_XenProduct_ControllerPublic_Product extends XFCP_XPMLicenseClaim_XenProduct_ControllerPublic_Product
{
    public function actionSave()
    {
        $fieldShown = $this->_input->filterSingle('xenmods_product_id_shown', XenForo_Input::BOOLEAN);
        $xenmodsProductId = $this->_input->filterSingle('xenmods_product_id', XenForo_Input::UINT);

        if (!$fieldShown)
        {
            return parent::actionSave();
        }

        if (XenForo_Application::isRegistered('session'))
        {
            /** @var $session XenForo_Session */
            $session = XenForo_Application::get('session');
            $session->set('xmProductId', $xenmodsProductId);
        }

        return parent::actionSave();
    }

    public function actionClaimXenmodsLicense()
    {
        $this->_assertRegistrationRequired();

        $productId = $this->_input->filterSingle('product_id', 'uint');
        $productHelper = $this->_getProductHelper();
        list ($product, $version) = $productHelper->assertProductValidAndViewable($productId);

        if ($this->isConfirmedPost())
        {
            $cartKey = $this->_input->filterSingle('cart_key', XenForo_Input::STRING);
            $email = $this->_input->filterSingle('email', XenForo_Input::STRING);

            $db = XenForo_Application::getDb();

            $products = $db->fetchAll('
                SELECT *
                FROM avforums_xenmods
                WHERE cart_key = ?
                AND email = ?
                AND product_id = ?
            ', array($cartKey, $email, $product['xenmods_product_id']));

            if (!$products)
            {
                return $this->responseError('No licenses could be found for this product with the cart key and email address provided.');
            }

            $valid = false;
            foreach ($products AS $key => $item)
            {
                $claimed = $db->fetchRow('
                    SELECT *
                    FROM xenproduct_xenmods_log
                    WHERE xenmods_product_id = ?
                    AND cart_key = ?
                    AND item_id = ?
                    AND email = ?
                ', array(
                    $item['product_id'],
                    $item['cart_key'],
                    $item['item_id'],
                    $item['email'])
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
                        WHERE xenmods_extra_id IN(' . $db->quote(array_keys($optionalExtras)) . ')
                    ');
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
                    $db->insert('xenproduct_xenmods_log', array(
                        'user_id' => $visitor->user_id,
                        'product_id' => $product['product_id'],
                        'xenmods_product_id' => $item['product_id'],
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
            $viewParams = array(
                'product' => $product
            );
            return $this->responseView('XPMLicenseClaim_XenProduct_ViewPublic_Product_Claim', 'xenproduct_xenmods_claim', $viewParams);
        }
    }
}