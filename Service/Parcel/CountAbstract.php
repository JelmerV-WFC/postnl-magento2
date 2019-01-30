<?php
/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\PostNL\Service\Parcel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Sales\Api\Data\ShipmentItemInterface;
use Magento\Quote\Model\ResourceModel\Quote\Item as QuoteItem;
use Magento\Sales\Api\Data\OrderItemInterface;
use TIG\PostNL\Config\Provider\ProductType as PostNLType;
use TIG\PostNL\Service\Options\ProductDictionary;
use TIG\PostNL\Config\Provider\ShippingOptions;

abstract class CountAbstract
{
    const ATTRIBUTE_PARCEL_COUNT = 'postnl_parcel_count';
    const WEIGHT_PER_PARCEL      = 20000;

    // @codingStandardsIgnoreLine
    protected $productDictionary;

    // @codingStandardsIgnoreLine
    protected $shippingOptions;

    // @codingStandardsIgnoreLine
    protected $products;

    /**
     * @param ProductDictionary $productDictionary
     * @param ShippingOptions $shippingOptions
     */
    public function __construct(
        ProductDictionary $productDictionary,
        ShippingOptions $shippingOptions
    ) {
        $this->productDictionary = $productDictionary;
        $this->shippingOptions   = $shippingOptions;
    }

    /**
     * @param int $weight
     * @param ShipmentItemInterface[]|OrderItemInterface[]|QuoteItem[] $items
     *
     * @return int|\Magento\Framework\Api\AttributeInterface|null
     */
    // @codingStandardsIgnoreLine
    protected function calculate($weight, $items)
    {
        $this->products = $this->getProducts($items);
        if (empty($this->products)) {
            return $this->getBasedOnWeight($weight);
        }

        /**
         * We start counting at zero and first add up all items which contain a parcel count. Afterwards we
         * add the rest of the parcels calculated by weight.
         */
        $parcelCount = 0;
        foreach ($items as $item) {
            $parcelCount += $this->getParcelCount($item);
        }
        $parcelCount += $this->getBasedOnWeight($weight);

        return $parcelCount < 1 ? 1 : $parcelCount;
    }

    /**
     * @param $weight
     * @param $parcelCount
     *
     * @return float|int
     */
    // @codingStandardsIgnoreLine
    protected function getBasedOnWeight($weight)
    {
        $remainingParcelCount = ceil($weight / self::WEIGHT_PER_PARCEL);
        $weightCount = $remainingParcelCount < 1 ? 1 : $remainingParcelCount;
        return $weightCount;
    }

    /**
     * @param ShipmentItemInterface|OrderItemInterface|QuoteItem $item
     *
     * @return mixed
     */
    // @codingStandardsIgnoreLine
    protected function getParcelCount($item)
    {
        if (!isset($this->products[$item->getProductId()])) {
            return 0;
        }

        /** @var ProductInterface $product */
        $product = $this->products[$item->getProductId()];
        $productParcelCount = $product->getCustomAttribute(self::ATTRIBUTE_PARCEL_COUNT);

        /** If Parcel Count isn't set, it's value will be null. Which can't be multiplied. */
        if ($productParcelCount) {
            return ($productParcelCount->getValue() * $this->getQty($item));
        }

        return 0;
    }

    /**
     * Parcel count is only needed if a specific product type is found within the items.
     *
     * @param $items
     *
     * @return ProductInterface[]
     */
    // @codingStandardsIgnoreLine
    protected function getProducts($items)
    {
        return $this->productDictionary->get(
            $items,
            [PostNLType::PRODUCT_TYPE_EXTRA_AT_HOME, PostNLType::PRODUCT_TYPE_REGULAR]
        );
    }

    /**
     * @param $items
     *
     * @return int
     */
    // @codingStandardsIgnoreLine
    protected function getWeight($items)
    {
        $weight = 0;
        /** @var QuoteItem $item */
        foreach ($items as $item) {
            /** @noinspection PhpUndefinedMethodInspection */
            $weight += $item->getWeight();
        }

        return $weight;
    }

    /**
     * @param ShipmentItemInterface|OrderItemInterface|QuoteItem $item
     *
     * @return mixed
     */
    // @codingStandardsIgnoreLine
    protected function getQty($item)
    {
        return $item->getQty() ?: $item->getQtyOrdered();
    }
}
