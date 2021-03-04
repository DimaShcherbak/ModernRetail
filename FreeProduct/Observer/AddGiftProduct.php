<?php

namespace ModernRetail\FreeProduct\Observer;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\AbstractHelper;

/**
 * Class AddGiftProduct
 * @package ModernRetail\FreeProduct\Observer
 */
class AddGiftProduct extends AbstractHelper implements ObserverInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;
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
     * @var FormKey
     */
    private $formKey;
    /**
     * @var Product
     */
    private $product;
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * AddGiftProduct constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param CollectionFactory $productCollectionFactory
     * @param Cart $cart
     * @param FormKey $formKey
     * @param Product $product
     * @param ProductRepository $productRepository
     */
    public function __construct(Context $context, Session $checkoutSession,
                                CollectionFactory $productCollectionFactory,
                                Cart $cart,
                                FormKey $formKey,
                                Product $product,
                                ProductRepository $productRepository)
    {
        $this->checkoutSession = $checkoutSession;
        $this->context = $context;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->cart = $cart;
        $this->formKey = $formKey;
        $this->product = $product;
        $this->productRepository = $productRepository;
        parent::__construct($context);
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
     * @return mixed
     */
    public function getFreeProductLimit()
    {
        return $this->scopeConfig->getValue('free_product_settings/general/free_product_limit', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return array
     */
    public function getIdItemQuote()
    {
        $data = [];
        foreach ($this->cart->getQuote()->getAllVisibleItems() as $item) {
            $data[] = $item->getProduct()->getId();
        }
        return $data;
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
        $item = $observer->getEvent()->getData('quote_item');
        $product = $observer->getEvent()->getData('product');
        if ($this->getFreeProductLimit() <= $grandTotal) {
            $giftProducts = $this->getFreeProductFiltr();
            foreach ($giftProducts as $gift) {
                if (in_array($gift, $this->getIdItemQuote()) && $item->getPrice() == 0) continue;
                $item = ($item->getParentItem() ? $item->getParentItem() : $item);
                // Enter the id of the prouduct which are required to be added to avoid recurrssion
                if ($product->getId() != $gift) {
                    $params = array(
                        'product' => $gift
                    );
                    $_product = $this->productRepository->getById($gift)->setPrice(0);
                    $this->cart->addProduct($_product, $params);
                    $this->cart->save();
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
