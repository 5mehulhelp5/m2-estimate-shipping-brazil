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

namespace Fineweb\SimulateProductShipping\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * Estimates the shipping methods available for a product and a Brazilian postcode (CEP).
 */
interface ShippingEstimatorInterface
{
    /**
     * Estimate the shipping rates of a product, with the options and quantity of its add-to-cart form.
     *
     * @param int $productId
     * @param mixed[] $buyRequest Quantity (qty) and chosen options, as the add-to-cart form posts them
     * @param string $postcode The 8 digits of the postcode
     * @return array<string, array<int, array{title: string, price: string, message: string}>> Rates grouped by
     *         carrier title, empty when no carrier offers a rate
     * @throws LocalizedException When the product cannot be estimated
     */
    public function estimate(int $productId, array $buyRequest, string $postcode): array;
}
