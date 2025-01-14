<?php

/**
 * Class Divante_VueStorefrontBridge_Model_Api_Customer_Address
 *
 * @package     Divante
 * @category    VueStorefrontBridge
 * @author      Agata Firlejczyk <afirlejczyk@divante.com>
 * @copyright   Copyright (C) 2018 Divante Sp. z o.o.
 */
class Divante_VueStorefrontBridge_Model_Api_Customer_Address
{
    protected array $fieldsBlacklist = [
        'attribute_set_id',
        'entity_type_id',
        'is_default_billing',
        'is_default_shipping',
        'entity_id',
        'customer_id',
        'created_at',
        'updated_at',
    ];

    /**
     * @param $addressData
     *
     * @return Mage_Customer_Model_Address
     */
    public function loadCustomerAddressById($addressData)
    {
        /* @var $address Mage_Customer_Model_Address */
        $address = Mage::getModel('customer/address');

        if (isset($addressData->id) && (int)($addressData->id)) {
            $address->load($addressData->id);
        }

        return $address;
    }

    /**
     * @param Mage_Customer_Model_Address  $address
     * @param array                        $addressData
     * @param Mage_Customer_Model_Customer $customer
     *
     * @throws Exception
     */
    public function saveAddress(
        Mage_Customer_Model_Address $address,
        array $addressData,
        Mage_Customer_Model_Customer $customer
    ) {
        $addressData['parent_id'] = $customer->getId();
        $addressData['customer_id'] = $customer->getId();
        $address->addData($addressData);

        if (!empty($addressData['region']['region_id'])) {
            $addressRegion = Mage::getModel('directory/region')->load($addressData['region']['region_id']);

            if (!$addressRegion->getData()) {
                throw new LogicException('Wrong address region_id was provided.');
            }

            $address->setRegionId((int)$addressRegion->getId());
            $address->setRegion($addressRegion->getDefaultName());
        } else {
            $address->setRegionId(null);
            $address->setRegion($addressData['region']['region']);
        }

        if (isset($addressData['default_billing']) && ($addressData['default_billing'] === true)) {
            $address->setIsDefaultBilling(true);
        } else {
            $address->setIsDefaultBilling(false);
        }

        if (isset($addressData['default_shipping']) && ($addressData['default_shipping'] === true)) {
            $address->setIsDefaultShipping(true);
        } else {
            $address->setIsDefaultShipping(false);
        }

        $addressErrors = $address->validate();

        if ($addressErrors !== true) {
            $message = implode('.', $addressErrors);
            throw new Mage_Core_Exception($message);
        }

        if (!$address->getIsDefaultBilling() && ($customer->getDefaultBilling() == $address->getId())) {
            $customer->setDefaultBilling(null);
        }

        if (!$address->getIsDefaultShipping() && ($customer->getDefaultShipping() == $address->getId())) {
            $customer->setDefaultShipping(null);
        }
        
        $address->save();
        /** @var Mage_Customer_Model_Resource_Customer $resourceModel */
        $resourceModel = $customer->getResource();

        if ($customer->dataHasChangedFor('default_billing')) {
            $resourceModel->saveAttribute($customer, 'default_billing');
        }

        if ($customer->dataHasChangedFor('default_shipping')) {
            $resourceModel->saveAttribute($customer, 'default_shipping');
        }
    }

    /**
     * @param Mage_Customer_Model_Address  $address
     * @param Mage_Customer_Model_Customer $customer
     *
     * @return mixed
     */
    public function prepareAddress(Mage_Customer_Model_Address $address, Mage_Customer_Model_Customer $customer)
    {
        $addressDTO = $address->getData();
        $addressDTO['id'] = $addressDTO['entity_id'];

        $addressDTO['region'] = [
            'region' => $addressDTO['region'] ?? null,
            'region_id' => $addressDTO['region_id'] ?? null
        ];
        unset($addressDTO['region_id']);

        $streetDTO = explode("\n", $addressDTO['street']);
        if (count($streetDTO) < 2) {
            $streetDTO[] = '';
        }
        $addressDTO['street'] = $streetDTO;

        if (empty($addressDTO['firstname'])) {
            $addressDTO['firstname'] = $customer->getFirstname();
        }

        if (empty($addressDTO['lastname'])) {
            $addressDTO['lastname'] = $customer->getLastname();
        }

        if (empty($addressDTO['city'])) {
            $addressDTO['city'] = '';
        }

        if (empty($addressDTO['country_id'])) {
            $addressDTO['country_id'] = $this->getDefaultCountry();
        }

        if (empty($addressDTO['postcode'])) {
            $addressDTO['postcode'] = '';
        }

        if (empty($addressDTO['telephone'])) {
            $addressDTO['telephone'] = '';
        }

        if ($address->getIsDefaultBilling()) {
            $addressDTO['default_billing'] = (bool)$address->getIsDefaultBilling();
        } elseif ($customer->getDefaultBilling() === $address->getId()) {
            $addressDTO['default_billing'] = true;
        }  else {
            $addressDTO['default_billing'] = false;
        }

        if ($address->getIsDefaultShipping()) {
            $addressDTO['default_shipping'] = (bool)$address->getIsDefaultShipping();
        } elseif ($customer->getDefaultShipping() === $address->getId()) {
            $addressDTO['default_shipping'] = true;
        } else {
            $addressDTO['default_shipping'] = false;
        }

        return $addressDTO;
    }

    /**
     * @return string
     */
    public function getDefaultCountry(): string
    {
        /** @var Mage_Core_Helper_Data $helper */
        $helper = Mage::helper('core');

        return $helper->getDefaultCountry();
    }

    public function getFieldsBlacklist(): array
    {
        return $this->fieldsBlacklist;
    }
}
