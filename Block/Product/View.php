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

namespace Fineweb\SimulateProductShipping\Block\Product;

use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\View\Element\Template;

/**
 * Block that renders the postcode form used to estimate shipping on the product page.
 */
class View extends Template
{
    public const FINE_ACTION_URL = 'fineweb/simulate/index';
    public const BUSCA_CEP_URL = 'https://buscacepinter.correios.com.br/app/endereco/index.php';

    /**
     * @param Template\Context $context
     * @param JsonHexTag $jsonHexTag
     * @param mixed[] $data
     */
    public function __construct(
        Template\Context $context,
        private readonly JsonHexTag $jsonHexTag,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get the controller route that calculates the estimate (relative to the store base URL)
     *
     * @return string
     */
    public function getActionUrl(): string
    {
        return self::FINE_ACTION_URL;
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
            '.simulate-container' => [
                'Fineweb_SimulateProductShipping/js/fineweb-shipping' => [
                    'actionUrl' => $this->getUrl($this->getActionUrl()),
                ],
            ],
        ]);
    }
}
