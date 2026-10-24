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

namespace Fineweb\SimulateProductShipping\Test\Unit\Model;

use Fineweb\SimulateProductShipping\Api\ShippingEstimatorInterface;
use Fineweb\SimulateProductShipping\Model\ShippingEstimator;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ShippingEstimatorTest extends TestCase
{
    /**
     * @var ProductRepositoryInterface&MockObject
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var Quote&MockObject
     */
    private Quote $quote;

    /**
     * @var Address&MockObject
     */
    private Address $address;

    /**
     * @var TotalsCollector&MockObject
     */
    private TotalsCollector $totalsCollector;

    /**
     * @var ShippingEstimator
     */
    private ShippingEstimator $estimator;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);

        $this->address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getGroupedAllShippingRates'])
            ->getMock();
        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addProduct', 'getShippingAddress', 'setStoreId'])
            ->getMock();
        $this->quote->method('getShippingAddress')->willReturn($this->address);
        $quoteFactory = $this->createMock(QuoteFactory::class);
        $quoteFactory->method('create')->willReturn($this->quote);

        $this->totalsCollector = $this->createMock(TotalsCollector::class);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('convertAndFormat')->willReturnCallback(
            static fn (float $amount): string => 'R$ ' . number_format($amount, 2, ',', '.')
        );

        $this->estimator = new ShippingEstimator(
            $this->productRepository,
            $quoteFactory,
            $this->totalsCollector,
            $storeManager,
            $priceCurrency
        );
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $groupedRates
     */
    private function givenShippingRates(array $groupedRates): void
    {
        $grouped = [];
        foreach ($groupedRates as $code => $rates) {
            foreach ($rates as $rate) {
                $grouped[$code][] = new DataObject($rate);
            }
        }
        $this->address->method('getGroupedAllShippingRates')->willReturn($grouped);
        $this->productRepository->method('getById')->willReturn($this->createMock(Product::class));
        $this->quote->method('addProduct')->willReturn(new DataObject());
    }

    public function testImplementsTheApiInterface(): void
    {
        self::assertInstanceOf(ShippingEstimatorInterface::class, $this->estimator);
    }

    public function testGroupsEveryCarrierInsteadOfOnlyTheLastOne(): void
    {
        $this->givenShippingRates([
            'flatrate' => [
                ['carrier_title' => 'Flat Rate', 'method_title' => 'Fixed', 'price' => 5, 'error_message' => null],
            ],
            'tablerate' => [
                ['carrier_title' => 'Best Way', 'method_title' => 'Table Rate', 'price' => 1234.56,
                    'error_message' => null],
                ['carrier_title' => 'Best Way', 'method_title' => 'Express', 'price' => 99,
                    'error_message' => 'Slow'],
            ],
        ]);

        self::assertSame([
            'Flat Rate' => [
                ['title' => 'Fixed', 'price' => 'R$ 5,00', 'message' => ''],
            ],
            'Best Way' => [
                ['title' => 'Table Rate', 'price' => 'R$ 1.234,56', 'message' => ''],
                ['title' => 'Express', 'price' => 'R$ 99,00', 'message' => 'Slow'],
            ],
        ], $this->estimator->estimate(42, ['qty' => 1.0], '88010400'));
    }

    public function testReturnsAnEmptyListWhenNoCarrierOffersARate(): void
    {
        $this->givenShippingRates([]);

        self::assertSame([], $this->estimator->estimate(42, ['qty' => 1.0], '88010400'));
    }

    public function testEstimatesForBrazilWithThePostcodeAndCollectsTheTotals(): void
    {
        $this->givenShippingRates([]);
        $this->totalsCollector->expects(self::once())
            ->method('collectAddressTotals')
            ->with($this->quote, $this->address);

        $this->estimator->estimate(42, ['qty' => 1.0], '88010400');

        self::assertSame('BR', $this->address->getCountryId());
        self::assertSame('88010400', $this->address->getPostcode());
        self::assertTrue($this->address->getCollectShippingRates());
    }

    public function testLoadsTheProductForTheCurrentStore(): void
    {
        $this->address->method('getGroupedAllShippingRates')->willReturn([]);
        $this->productRepository->expects(self::once())
            ->method('getById')
            ->with(42, false, 1)
            ->willReturn($this->createMock(Product::class));
        $this->quote->method('addProduct')->willReturn(new DataObject());
        $this->quote->expects(self::once())->method('setStoreId')->with(1);

        $this->estimator->estimate(42, ['qty' => 1.0], '88010400');
    }

    public function testForwardsTheProductQuantityAndOptionsToTheQuote(): void
    {
        $this->address->method('getGroupedAllShippingRates')->willReturn([]);
        $this->productRepository->method('getById')->willReturn($this->createMock(Product::class));
        $this->quote->expects(self::once())->method('addProduct')->with(
            self::isInstanceOf(Product::class),
            self::callback(static function (DataObject $buyRequest): bool {
                return $buyRequest->getData() === [
                    'product' => 42,
                    'qty' => 2.0,
                    'super_attribute' => ['93' => '52'],
                    'bundle_option' => ['1' => '3'],
                ];
            })
        )->willReturn(new DataObject());

        $this->estimator->estimate(
            42,
            ['qty' => 2.0, 'super_attribute' => ['93' => '52'], 'bundle_option' => ['1' => '3']],
            '88010400'
        );
    }

    public function testReportsTheReasonWhenTheProductCannotBeAddedToTheQuote(): void
    {
        $this->productRepository->method('getById')->willReturn($this->createMock(Product::class));
        $this->quote->method('addProduct')->willReturn('Please specify the product\'s option(s).');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Please specify the product\'s option(s).');

        $this->estimator->estimate(42, ['qty' => 1.0], '88010400');
    }

    public function testRejectsAProductThatIsNotACatalogProductModel(): void
    {
        $this->productRepository->method('getById')->willReturn($this->createMock(ProductInterface::class));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The product could not be loaded.');

        $this->estimator->estimate(42, ['qty' => 1.0], '88010400');
    }

    public function testLetsAMissingProductPropagate(): void
    {
        $this->productRepository->method('getById')->willThrowException(
            new NoSuchEntityException(__('The product that was requested doesn\'t exist.'))
        );

        $this->expectException(NoSuchEntityException::class);

        $this->estimator->estimate(99999, ['qty' => 1.0], '88010400');
    }
}
