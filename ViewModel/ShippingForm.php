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

namespace Fineweb\SimulateProductShipping\ViewModel;

use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Data of the postcode form used to estimate shipping on the product page.
 */
class ShippingForm implements ArgumentInterface
{
    public const ACTION_ROUTE = 'fineweb/simulate/index';
    public const BUSCA_CEP_URL = 'https://buscacepinter.correios.com.br/app/endereco/index.php';

    /**
     * @param UrlInterface $urlBuilder
     * @param JsonHexTag $jsonHexTag
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly JsonHexTag $jsonHexTag
    ) {
    }

    /**
     * Get the absolute URL of the Correios postcode lookup
     *
     * @return string
     */
    public function getBuscaCepUrl(): string
    {
        return self::BUSCA_CEP_URL;
    }

    /**
     * Serialize the widget configuration for the `text/x-magento-init` script, safe inside a `<script>` tag
     *
     * @return string
     */
    public function getInitConfig(): string
    {
        return (string) $this->jsonHexTag->serialize([
            '[data-role="fineweb-shipping-estimate"]' => [
                'Fineweb_SimulateProductShipping/js/fineweb-shipping' => [
                    'actionUrl' => $this->urlBuilder->getUrl(self::ACTION_ROUTE),
                ],
            ],
        ]);
    }
}
