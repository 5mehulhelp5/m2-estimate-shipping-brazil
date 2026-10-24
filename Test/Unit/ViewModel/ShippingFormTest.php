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

namespace Fineweb\SimulateProductShipping\Test\Unit\ViewModel;

use Fineweb\SimulateProductShipping\ViewModel\ShippingForm;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use PHPUnit\Framework\TestCase;

class ShippingFormTest extends TestCase
{
    /**
     * @var ShippingForm
     */
    private ShippingForm $viewModel;

    protected function setUp(): void
    {
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            static fn (string $route): string => 'https://shop.test/' . $route . '/'
        );

        $this->viewModel = new ShippingForm($urlBuilder, new JsonHexTag());
    }

    public function testIsAViewModel(): void
    {
        self::assertInstanceOf(ArgumentInterface::class, $this->viewModel);
    }

    public function testPostcodeLookupLinkIsSecure(): void
    {
        self::assertStringStartsWith('https://', $this->viewModel->getBuscaCepUrl());
    }

    public function testInitConfigPointsTheWidgetAtTheEstimateController(): void
    {
        $config = json_decode($this->viewModel->getInitConfig(), true);

        self::assertSame(
            [
                '[data-role="fineweb-shipping-estimate"]' => [
                    'Fineweb_SimulateProductShipping/js/fineweb-shipping' => [
                        'actionUrl' => 'https://shop.test/fineweb/simulate/index/',
                    ],
                ],
            ],
            $config
        );
    }

    public function testInitConfigCannotCloseTheScriptTag(): void
    {
        self::assertStringNotContainsString('<', $this->viewModel->getInitConfig());
    }
}
