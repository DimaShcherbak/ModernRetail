<?php

namespace ModernRetail\FreeProduct\Observer;

use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\AbstractHelper;

/**
 * Class UpdateGiftProduct
 * @package ModernRetail\FreeProduct\Observer
 */
class UpdateGiftProduct extends AbstractHelper implements ObserverInterface
{
    /**
     * @var Session
     */
    protected $checkoutSession;
    /**
     * @var Context
     */
    private $context;
    /**
     * @var CollectionFactory
     */
    private $productCollectionFactory;
    /**
     * @var Cart
     */
    private $cart;
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * UpdateGiftProduct constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param CollectionFactory $productCollectionFactory
     * @param Cart $cart
     * @param ProductRepository $productRepository
     */
    public function __construct(Context $context,
                                Session $checkoutSession,
                                CollectionFactory $productCollectionFactory,
                                Cart $cart,
                                ProductRepository $productRepository)
    {
        $this->checkoutSession = $checkoutSession;
        $this->context = $context;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->cart = $cart;
        $this->productRepository = $productRepository;
        parent::__construct($context);
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
     * @return mixed
     */
    public function getFreeProductLimit()
    {
        return $this->scopeConfig->getValue('free_product_settings/general/free_product_limit', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return array
     */
    public function getFreeProductFiltr()
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToFilter('free_product', '1');
        $id = [];
        foreach ($collection->getItems() as $item) {
            $id[] = $item->getId();
        }
        return $id;
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
        foreach ($observer->getEvent()->getInfo()->getData() as $key => $value) {
            if ($quote->getItemById($key)->getPrice() == 0) {
                $quote->getItemById($key)->setQty(1);
            }
        }

        if ($this->getFreeProductLimit() <= $grandTotal) {
            $grandTotal = $this->getTotalPrice();
            if ($this->getFreeProductLimit() <= $grandTotal) {
                foreach ($quote->getAllItems() as $quoteItem) {
                    if ($quoteItem->getPrice() == 0) return;
                }
                $giftProducts = $this->getFreeProductFiltr();
                foreach ($giftProducts as $gift) {
                    // Enter the id of the prouduct which are required to be added to avoid recurrssion
                    $params = array(
                        'product' => $gift,
                        'qty' => 1
                    );
                    $_product = $this->productRepository->getById($gift)->setPrice(0)->setQty(1);
                    $this->cart->addProduct($_product, $params);
                    $giftItem = $quote->getItemByProduct($_product);
                    $giftItem->setQty(1);
                    $giftItem->setCustomPrice(0);
                    $giftItem->setOriginalCustomPrice(0);
                    $giftItem->setBasePrice(0);
                    $giftItem->setPrice(0);
                    $giftItem->getProduct()->setIsSuperMode(true);
                }
            }
        }
    }
}



