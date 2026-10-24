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

use Fineweb\SimulateProductShipping\Api\ShippingEstimatorInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Reads the product form and a Brazilian postcode (CEP) and answers with the shipping methods available.
 *
 * Responds with a JSON object grouped by carrier title, or with {"error": {"message": "..."}}.
 */
class Index implements HttpPostActionInterface
{
    private const POSTCODE_LENGTH = 8;

    /**
     * Product form fields forwarded as the buy request (the same ones add-to-cart posts).
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
     * @param ShippingEstimatorInterface $shippingEstimator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly ShippingEstimatorInterface $shippingEstimator,
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
            $methods = $this->shippingEstimator->estimate(
                $this->getProductId(),
                $this->getBuyRequest(),
                $postcode
            );

            if ($methods === []) {
                return $this->error(__('There is no shipping method available at this time.'), 200);
            }

            return $this->json($methods);
        } catch (LocalizedException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (Throwable $e) {
            $this->logger->critical($e);

            return $this->error(__('We could not estimate shipping right now. Please try again.'), 500);
        }
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
     * @return array<string, mixed>
     */
    private function getBuyRequest(): array
    {
        $qty = $this->request->getParam('qty', 1);
        $qty = is_numeric($qty) ? (float) $qty : 0.0;
        $buyRequest = ['qty' => $qty > 0 ? $qty : 1.0];
        foreach (self::BUY_REQUEST_KEYS as $key) {
            $value = $this->request->getParam($key);
            if ($value !== null) {
                $buyRequest[$key] = $value;
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
