<?php

require_once('AbstractController.php');
require_once(__DIR__.'/../helpers/JWT.php');

/**
 *
 * @package     Divante
 * @category    VueStorefrontBridge
 * @copyright   Copyright (C) 2018 Divante Sp. z o.o.
 */
class Divante_VueStorefrontBridge_CustomProductController extends Divante_VueStorefrontBridge_AbstractController
{
    /**
     * Divante_VueStorefrontBridge_WishlistController constructor.
     *
     * @param Zend_Controller_Request_Abstract  $request
     * @param Zend_Controller_Response_Abstract $response
     * @param array                             $invokeArgs
     */
    public function __construct(
        Zend_Controller_Request_Abstract $request,
        Zend_Controller_Response_Abstract $response,
        array $invokeArgs = []
    ) {
        parent::__construct($request, $response, $invokeArgs);
    }

    public function createAction()
    {
        try {
            if ($this->getRequest()->getMethod() !== 'GET' && $this->getRequest()->getMethod() !== 'OPTIONS') {
                return $this->_result(500, 'Only GET method allowed');
            }

            $type = $this->getRequest()->getParam('type');

            return $this->_result(
                200,
                [
                    'storeId' => $this->_currentStore()->getId(),
                    'type' => $type,
                    'attributeId' => random_int(100, 1000)
                ]
            );
        } catch (Exception $err) {
            return $this->_result(500, $err->getMessage());
        }
    }
}
