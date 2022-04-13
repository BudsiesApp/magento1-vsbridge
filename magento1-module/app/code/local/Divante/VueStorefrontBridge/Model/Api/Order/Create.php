<?php

/**
 * Class Divante_VueStorefrontBridge_CartController
 *
 * @package     Divante
 * @category    VueStorefrontBridge
 * @copyright   Copyright (C) 2018 Divante Sp. z o.o.
 */
class Divante_VueStorefrontBridge_Model_Api_Order_Create
{
    /**
     * @var Mage_Sales_Model_Quote
     */
    private $quote;

    /**
     * @var Mage_Customer_Model_Customer
     */
    private $customer;

    /**
     * Divante_VueStorefrontBridge_Model_Api_Order constructor.
     *
     * @param array $payload
     */
    public function __construct(array $payload)
    {
        $this->quote     = $payload[0];
        $this->customer  = $payload[1];
    }

    /**
     * Only Guest Order are supported for now
     * @param $requestPayload
     *
     * @return Mage_Sales_Model_Order
     * @throws Mage_Core_Exception
     */
    public function execute($requestPayload)
    {
        if (is_null($this->quote)) {
            Mage::throwException('No quote entity passed to order create model');
        }

        $this->checkProducts($requestPayload);

        $billingAddress = (array)$requestPayload->addressInformation->billingAddress;
        $shippingAddress = (array)$requestPayload->addressInformation->shippingAddress;

        // Assign customer if exists
        if (!$this->customer) {
            $this->quote->setCustomerIsGuest(true);
        } else {
            $this->quote->setCustomerIsGuest(false);
            $this->quote->assignCustomer($this->customer);
        }

        // Add billing address to quote
        /** @var Mage_Sales_Model_Quote_Address $billingAddressData */
        $billingAddressData = $this->quote->getBillingAddress()->addData($billingAddress);
        $billingAddressData->implodeStreetAddress();
        $this->quote->setCustomerEmail($billingAddressData->getEmail());
        $this->quote->setCustomerFirstname($billingAddressData->getFirstname());
        $this->quote->setCustomerLastname($billingAddressData->getLastname());

        // Add shipping address to quote
        $shippingAddressData = $this->quote->getShippingAddress()->addData($shippingAddress);

        // NA eq to company not defined
        if ($shippingAddress['company'] == 'NA') {
            $shippingAddressData->setCompany(null);
        }

        $shippingAddressData->implodeStreetAddress();
        $shippingMethodCode = $requestPayload->addressInformation->shipping_method_code;
        $paymentMethodCode = $requestPayload->addressInformation->payment_method_code;
        $shippingMethodCarrier = $requestPayload->addressInformation->shipping_carrier_code;
        $shippingMethod = $shippingMethodCarrier  . '_' . $shippingMethodCode;

        // Collect shipping rates on quote shipping address data
        $shippingAddressData->setCollectShippingRates(true)
            ->collectShippingRates();
        // Set shipping and payment method on quote shipping address data
        $shippingAddressData->setShippingMethod($shippingMethod)->setPaymentMethod($paymentMethodCode);

        $this->quote->getPayment()->importData(
            [
                'method' => $paymentMethodCode,
                'additional_information' => (array)$requestPayload->addressInformation->payment_method_additional,
            ]
        );

        $this->quote->getShippingAddress()->setCollectShippingRates(true);
        $this->quote->collectTotals();
        $this->quote->save();

        return $this->createOrderFromQuote();
    }

    /**
     * @return Mage_Sales_Model_Order
     * @throws Exception
     */
    private function createOrderFromQuote()
    {
        /** @var Mage_Sales_Model_Service_Quote $service */
        $service = Mage::getModel('sales/service_quote', $this->quote);
        $service->submitAll();

        /** @var Mage_Sales_Model_Order $order */
        $order = $service->getOrder();

        if ($order) {
            $order->queueNewOrderEmail();
            $this->quote->save();
            Mage::getModel('sales/order')->getResource()->updateGridRecords($order->getId());
        }

        return $order;
    }

    /**
     * @param $requestPayload
     */
    private function checkProducts($requestPayload)
    {
        $clientItems = $requestPayload->products;
        $currentQuoteItems = $this->quote->getAllVisibleItems();

        foreach ($clientItems as $clientItem) {
            $serverItem = $this->findProductInQuote($clientItem, $currentQuoteItems);

            if (!$serverItem) {
                throw new DomainException('Fail to find product from order request in quote');
            }
        }

        foreach ($currentQuoteItems as $currentQuoteItem) {
            $clientItem = $this->findProductInRequest($currentQuoteItem, $clientItems);

            if (null === $clientItem) {
                $this->quote->deleteItem($currentQuoteItem);
            }
        }
    }

    /**
     * @param object $clientItem
     * @param Mage_Sales_Model_Quote_Item[] $quoteItems
     *
     * @return Mage_Sales_Model_Quote_Item|null
     */
    private function findProductInQuote($clientItem, $quoteItems)
    {
        foreach ($quoteItems as $item) {
            if ((int)$item->getId() === (int)$clientItem->server_item_id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param Mage_Sales_Model_Quote_Item $quoteItem
     * @param object[] $clientItems
     *
     * @return object|null
     */
    private function findProductInRequest($quoteItem, $clientItems)
    {
        foreach ($clientItems as $item) {
            if ((int)$item->server_item_id === (int)$quoteItem->getId()) {
                return $item;
            }
        }

        return null;
    }
}
