<?php
/**
 * Simulates all Shipping methods on the product page.
 * Copyright (C) 2020  Copyright (c) 2020 Fineweb (https://www.fineweb.com.br)
 *
 * This file is part of Fineweb/SimulateProductShipping.
 *
 * Fineweb/SimulateProductShipping is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Fineweb\SimulateProductShipping\Model;

use Fineweb\SimulateProductShipping\Api\ShippingEstimatorInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Collects the shipping rates of a quote that holds only the product, for a Brazilian postcode.
 *
 * No quote is saved and no customer is needed, so it works for guests.
 */
class ShippingEstimator implements ShippingEstimatorInterface
{
    private const COUNTRY_ID = 'BR';

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param QuoteFactory $quoteFactory
     * @param TotalsCollector $totalsCollector
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly QuoteFactory $quoteFactory,
        private readonly TotalsCollector $totalsCollector,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    /**
     * @inheritdoc
     */
    public function estimate(int $productId, array $buyRequest, string $postcode): array
    {
        $store = $this->storeManager->getStore();
        $product = $this->productRepository->getById($productId, false, $store->getId());
        if (!$product instanceof Product) {
            throw new LocalizedException(__('The product could not be loaded.'));
        }

        $quote = $this->quoteFactory->create();
        $quote->setStoreId((int) $store->getId());

        $item = $quote->addProduct($product, new DataObject(['product' => $productId] + $buyRequest));
        if (is_string($item)) {
            throw new LocalizedException(__($item));
        }

        $address = $quote->getShippingAddress();
        $address->setCountryId(self::COUNTRY_ID);
        $address->setPostcode($postcode);
        $address->setCollectShippingRates(true);
        $this->totalsCollector->collectAddressTotals($quote, $address);

        $methods = [];
        foreach ($address->getGroupedAllShippingRates() as $rates) {
            foreach ($rates as $rate) {
                $methods[(string) $rate->getCarrierTitle()][] = [
                    'title' => (string) $rate->getMethodTitle(),
                    'price' => $this->priceCurrency->convertAndFormat(
                        (float) $rate->getPrice(),
                        false,
                        PriceCurrencyInterface::DEFAULT_PRECISION,
                        $store->getId()
                    ),
                    'message' => (string) $rate->getErrorMessage(),
                ];
            }
        }

        return $methods;
    }
}
