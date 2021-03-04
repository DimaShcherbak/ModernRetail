<?php

namespace ModernRetail\FreeProduct\Observer;

use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\AbstractHelper;

/**
 * Class DelGiftProduct
 * @package Dimas\GiftProduct\Observer
 */
class DelGiftProduct extends AbstractHelper implements ObserverInterface
{
    /**
     * @var ProductRepository
     */
    protected $_productRepository;
    /**
     * @var Cart
     */
    protected $_cart;
    /**
     * @var Session
     */
    protected $checkoutSession;
    /**
     * @var Context
     */
    private $context;

    /**
     * DelGiftProduct constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param ProductRepository $productRepository
     * @param Cart $cart
     */
    public function __construct(Context $context,
                                Session $checkoutSession,
                                ProductRepository $productRepository,
                                Cart $cart)
    {
        $this->_productRepository = $productRepository;
        $this->_cart = $cart;
        $this->checkoutSession = $checkoutSession;
        $this->context = $context;
        parent::__construct($context);
    }

    /**
     * @return mixed
     */
    public function getFreeProductLimit()
    {
        return $this->scopeConfig->getValue('free_product_settings/general/free_product_limit', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return float|int
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getTotalPrice()
    {
        $items = $this->checkoutSession->getQuote()->getAllVisibleItems();
        $totalPrise = [];
        foreach ($items as $item) {
            $totalPrise[] = $item->getPrice() * $item->getQty();
        }
        return array_sum($totalPrise);
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $grandTotal = $this->getTotalPrice();
        $quote = $this->checkoutSession->getQuote();
        if ($this->getFreeProductLimit() > $grandTotal) {
            foreach ($quote->getAllVisibleItems() as $item) {
                if ($item->getPrice() == 0) {
                    $item->delete();
                }
            }
        }
    }
}

