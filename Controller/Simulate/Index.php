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

namespace Fineweb\SimulateProductShipping\Controller\Simulate;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Estimates the shipping methods available for a product and a Brazilian postcode (CEP).
 *
 * Responds with a JSON object grouped by carrier title, or with {"error": {"message": "..."}}.
 */
class Index implements HttpPostActionInterface
{
    private const COUNTRY_ID = 'BR';
    private const POSTCODE_LENGTH = 8;

    /**
     * Product form fields forwarded to the quote as the buy request (the same ones add-to-cart posts).
     */
    private const BUY_REQUEST_KEYS = [
        'super_attribute',
        'super_group',
        'bundle_option',
        'bundle_option_qty',
        'options',
    ];

    /**
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param ProductRepositoryInterface $productRepository
     * @param QuoteFactory $quoteFactory
     * @param TotalsCollector $totalsCollector
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly QuoteFactory $quoteFactory,
        private readonly TotalsCollector $totalsCollector,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Estimate the shipping methods for the posted product and postcode
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $postcode = $this->getPostcode();
        if ($postcode === null) {
            return $this->error(__('Please enter a valid postcode (8 digits).'), 400);
        }

        try {
            return $this->json($this->estimate($postcode));
        } catch (LocalizedException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (Throwable $e) {
            $this->logger->critical($e);

            return $this->error(__('We could not estimate shipping right now. Please try again.'), 500);
        }
    }

    /**
     * Build the estimate for the posted product
     *
     * @param string $postcode
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function estimate(string $postcode): array
    {
        $store = $this->storeManager->getStore();
        $product = $this->productRepository->getById($this->getProductId(), false, $store->getId());
        if (!$product instanceof Product) {
            throw new LocalizedException(__('The product could not be loaded.'));
        }

        $quote = $this->quoteFactory->create();
        $quote->setStoreId((int) $store->getId());

        $item = $quote->addProduct($product, $this->getBuyRequest());
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

        if ($methods === []) {
            return ['error' => ['message' => (string) __('There is no shipping method available at this time.')]];
        }

        return $methods;
    }

    /**
     * Read the postcode from the form, keeping only its digits
     *
     * @return string|null Null when it does not have exactly 8 digits
     */
    private function getPostcode(): ?string
    {
        $simulate = $this->request->getParam('simulate');
        $postcode = is_array($simulate) ? $simulate['postcode'] ?? '' : '';
        $digits = preg_replace('/\D/', '', is_string($postcode) ? $postcode : '');

        return strlen((string) $digits) === self::POSTCODE_LENGTH ? $digits : null;
    }

    /**
     * Read the posted product id
     *
     * @return int
     */
    private function getProductId(): int
    {
        $productId = $this->request->getParam('product');

        return is_numeric($productId) ? (int) $productId : 0;
    }

    /**
     * Build the buy request (quantity and product options) from the posted product form
     *
     * @return DataObject
     */
    private function getBuyRequest(): DataObject
    {
        $qty = $this->request->getParam('qty', 1);
        $qty = is_numeric($qty) ? (float) $qty : 0.0;
        $buyRequest = new DataObject(['product' => $this->getProductId(), 'qty' => $qty > 0 ? $qty : 1.0]);
        foreach (self::BUY_REQUEST_KEYS as $key) {
            $value = $this->request->getParam($key);
            if ($value !== null) {
                $buyRequest->setData($key, $value);
            }
        }

        return $buyRequest;
    }

    /**
     * Build a JSON error response
     *
     * @param Phrase|string $message
     * @param int $httpCode
     * @return Json
     */
    private function error(Phrase|string $message, int $httpCode): Json
    {
        return $this->json(['error' => ['message' => (string) $message]], $httpCode);
    }

    /**
     * Build a JSON response
     *
     * @param mixed[] $data
     * @param int $httpCode
     * @return Json
     */
    private function json(array $data, int $httpCode = 200): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setHttpResponseCode($httpCode);
        $result->setData($data);

        return $result;
    }
}
