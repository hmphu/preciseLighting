<?php
/**
 * Copyright (c) 2018 MageWorkshop. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace MageWorkshop\CustomerPermissions\Helper;

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order\Config;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session;
use Magento\Framework\Session\SessionManager;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Customer\Model\CustomerFactory;
use Magento\Store\Model\StoreManagerInterface;

class VerifiedHelper extends RulesHelper
{
    const XML_PATH_MAGEWORKSHOP_CUSTOMER_PERMISSIONS_GENERAL_VERIFIED_BUYERS = 'mageworkshop_detailedreview/mageworkshop_customerpermissions/verified_buyers';
    const XML_PATH_MAGEWORKSHOP_CUSTOMER_PERMISSIONS_GENERAL_GROUPS          = 'mageworkshop_detailedreview/mageworkshop_customerpermissions/groups';
    const XML_PATH_MAGEWORKSHOP_CUSTOMER_PERMISSIONS_GENERAL_VERIFIED_BUYERS_ICON = 'mageworkshop_detailedreview/mageworkshop_customerpermissions/verified_buyers_icon';

    /** @var CollectionFactory $orderCollectionFactory */
    protected $orderCollectionFactory;

    /** @var Config $orderConfig */
    protected $orderConfig;

    protected static $orderedProductsByEmail = [];

    /**
     * VerifiedHelper constructor.
     * @param CustomerRepositoryInterface $customerRepositoryInterface
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CustomerFactory $customerFactory
     * @param Context $context
     * @param Session $customerSession
     * @param SessionManager $sessionManager
     * @param StoreManagerInterface $storeManagerInterface
     * @param CollectionFactory $collectionFactory
     * @param Config $orderConfig
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepositoryInterface,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CustomerFactory $customerFactory,
        Context $context,
        Session $customerSession,
        SessionManager $sessionManager,
        StoreManagerInterface $storeManagerInterface,
        CollectionFactory $collectionFactory,
        Config $orderConfig
    ) {
        $this->orderCollectionFactory = $collectionFactory;
        $this->orderConfig            = $orderConfig;
        parent::__construct($customerRepositoryInterface, $searchCriteriaBuilder,
            $customerFactory, $context, $customerSession, $sessionManager, $storeManagerInterface);
    }


    /**
     * Gets all ordered products and returns true
     * if customer wants to post review on already bought product
     *
     * @param $email
     * @param $productId
     * @return bool
     */
    public function getOrderedProductsByCustomerEmail($email, $productId)
    {
        if (!isset(self::$orderedProductsByEmail[$email])) {
            $productIds = array();
            $salesOrderCollection = $this->orderCollectionFactory->create();
            $salesOrderCollection
                ->addFieldToFilter('customer_email', array('eq' => $email))
                ->addFieldToFilter('state', array('in' => $this->orderConfig->getVisibleOnFrontStatuses()));
            /** @var \Magento\Sales\Model\Order $order */
            foreach ($salesOrderCollection as $order) {
                /** @var \Magento\Sales\Model\Order\Item $item */
                foreach ($order->getAllVisibleItems() as $item) {
                    $productIds[] = $item->getProductId();
                }
            }

            self::$orderedProductsByEmail[$email] = array_unique($productIds);
        }

        return in_array($productId, self::$orderedProductsByEmail[$email]);
    }
   
    /**
     *
     * Checking if customer is logged in and if customer ever bought current product
     * @param $productId
     * @return bool
     */
    public function allowToPostReviewForCurrentUser($productId)
    {
        if (!$this->scopeConfig->getValue(self::XML_PATH_MAGEWORKSHOP_CUSTOMER_PERMISSIONS_GENERAL_VERIFIED_BUYERS)) {
            return true;
        } else {
            return ($this->getCustomerEmail() && $this->getOrderedProductsByCustomerEmail($this->getCustomerEmail(), $productId));
        }
    }

    /**
     * @param $productId
     * @return bool
     */
    public function showVerifiedBuyersIcon($productId)
    {
        if (!$this->scopeConfig->getValue(self::XML_PATH_MAGEWORKSHOP_CUSTOMER_PERMISSIONS_GENERAL_VERIFIED_BUYERS_ICON)) {
            return true;
        } else {
            return ($this->getCustomerEmail() && $this->getOrderedProductsByCustomerEmail($this->getCustomerEmail(), $productId));
        }
    }

    /**
     * @return array
     */
    public function getAllowedGroups()
    {
        $result = array();
        $allowedGroups = $this->scopeConfig->getValue(self::XML_PATH_MAGEWORKSHOP_CUSTOMER_PERMISSIONS_GENERAL_GROUPS);
        if (isset($allowedGroups)) {
            $result = explode(',', $allowedGroups);
        }
        return $result;
    }

    /**
     * @return bool
     */
    public function isAutoApproveAvailable()
    {
        $groups        = $this->getAllowedGroups();
        $customerGroup = $this->customerSession->getCustomerGroupId();
        return in_array($customerGroup, $groups);
    }
}

