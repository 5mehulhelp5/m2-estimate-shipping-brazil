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

namespace Fineweb\SimulateProductShipping\Test\Unit\Controller\Simulate;

use Fineweb\SimulateProductShipping\Controller\Simulate\Index;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Quote\Model\QuoteFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class IndexTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $params = [];

    /**
     * @var array{data: mixed, code: int}
     */
    private array $response = ['data' => null, 'code' => 200];

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
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $logger;

    /**
     * @var Index
     */
    private Index $controller;

    protected function setUp(): void
    {
        $this->params = [
            'product' => '42',
            'qty' => '2',
            'simulate' => ['postcode' => '88010-400'],
        ];

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => $this->params[$key] ?? $default
        );

        $json = $this->createMock(Json::class);
        $json->method('setData')->willReturnCallback(function (mixed $data) use ($json): Json {
            $this->response['data'] = $data;

            return $json;
        });
        $json->method('setHttpResponseCode')->willReturnCallback(function (int $code) use ($json): Json {
            $this->response['code'] = $code;

            return $json;
        });
        $resultFactory = $this->createMock(ResultFactory::class);
        $resultFactory->method('create')->with(ResultFactory::TYPE_JSON)->willReturn($json);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

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

        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $priceCurrency->method('convertAndFormat')->willReturnCallback(
            static fn (float $amount): string => 'R$ ' . number_format($amount, 2, ',', '.')
        );

        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new Index(
            $request,
            $resultFactory,
            $this->productRepository,
            $quoteFactory,
            $this->totalsCollector,
            $storeManager,
            $priceCurrency,
            $this->logger
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

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidPostcodeProvider(): array
    {
        return [
            'missing' => [null],
            'too short' => [['postcode' => '8801']],
            'too long' => [['postcode' => '880104000']],
            'letters' => [['postcode' => 'abcdefgh']],
            'not an array' => ['88010400'],
        ];
    }

    #[DataProvider('invalidPostcodeProvider')]
    public function testRejectsAnInvalidPostcodeWithAReadableError(mixed $simulate): void
    {
        $this->params['simulate'] = $simulate;
        $this->productRepository->expects(self::never())->method('getById');

        $this->controller->execute();

        self::assertSame(400, $this->response['code']);
        self::assertSame(
            ['error' => ['message' => 'Please enter a valid postcode (8 digits).']],
            $this->response['data']
        );
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

        $this->controller->execute();

        self::assertSame(200, $this->response['code']);
        self::assertSame([
            'Flat Rate' => [
                ['title' => 'Fixed', 'price' => 'R$ 5,00', 'message' => ''],
            ],
            'Best Way' => [
                ['title' => 'Table Rate', 'price' => 'R$ 1.234,56', 'message' => ''],
                ['title' => 'Express', 'price' => 'R$ 99,00', 'message' => 'Slow'],
            ],
        ], $this->response['data']);
    }

    public function testEstimatesForBrazilWithTheDigitsOfThePostcodeAndCollectsTotals(): void
    {
        $this->givenShippingRates([
            'flatrate' => [
                ['carrier_title' => 'Flat Rate', 'method_title' => 'Fixed', 'price' => 5, 'error_message' => null],
            ],
        ]);
        $this->totalsCollector->expects(self::once())
            ->method('collectAddressTotals')
            ->with($this->quote, $this->address);

        $this->controller->execute();

        self::assertSame('BR', $this->address->getCountryId());
        self::assertSame('88010400', $this->address->getPostcode());
        self::assertTrue($this->address->getCollectShippingRates());
    }

    public function testForwardsTheQuantityAndTheChosenOptionsToTheQuote(): void
    {
        $this->params += [
            'super_attribute' => ['93' => '52'],
            'bundle_option' => ['1' => '3'],
            'bundle_option_qty' => ['1' => '2'],
            'unrelated' => 'ignored',
        ];
        $this->address->method('getGroupedAllShippingRates')->willReturn([]);
        $this->productRepository->method('getById')
            ->with(42, false, 1)
            ->willReturn($this->createMock(Product::class));
        $this->quote->expects(self::once())->method('addProduct')->with(
            self::isInstanceOf(Product::class),
            self::callback(static function (DataObject $buyRequest): bool {
                return $buyRequest->getData() === [
                    'product' => 42,
                    'qty' => 2.0,
                    'super_attribute' => ['93' => '52'],
                    'bundle_option' => ['1' => '3'],
                    'bundle_option_qty' => ['1' => '2'],
                ];
            })
        )->willReturn(new DataObject());

        $this->controller->execute();
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidQuantityProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-3'],
            'not a number' => ['abc'],
        ];
    }

    #[DataProvider('invalidQuantityProvider')]
    public function testFallsBackToOneWhenTheQuantityIsInvalid(mixed $qty): void
    {
        $this->params['qty'] = $qty;
        $this->address->method('getGroupedAllShippingRates')->willReturn([]);
        $this->productRepository->method('getById')->willReturn($this->createMock(Product::class));
        $this->quote->expects(self::once())->method('addProduct')->with(
            self::anything(),
            self::callback(static fn (DataObject $buyRequest): bool => $buyRequest->getData('qty') === 1.0)
        )->willReturn(new DataObject());

        $this->controller->execute();
    }

    public function testReportsWhenNoShippingMethodIsAvailable(): void
    {
        $this->givenShippingRates([]);

        $this->controller->execute();

        self::assertSame(
            ['error' => ['message' => 'There is no shipping method available at this time.']],
            $this->response['data']
        );
    }

    public function testReportsTheReasonWhenTheProductCannotBeAddedToTheQuote(): void
    {
        $this->productRepository->method('getById')->willReturn($this->createMock(Product::class));
        $this->quote->method('addProduct')->willReturn('Please specify the product\'s option(s).');

        $this->controller->execute();

        self::assertSame(400, $this->response['code']);
        self::assertSame(
            ['error' => ['message' => 'Please specify the product\'s option(s).']],
            $this->response['data']
        );
    }

    public function testReportsAMissingProduct(): void
    {
        $this->productRepository->method('getById')->willThrowException(
            new NoSuchEntityException(__('The product that was requested doesn\'t exist.'))
        );

        $this->controller->execute();

        self::assertSame(400, $this->response['code']);
        self::assertSame(
            ['error' => ['message' => 'The product that was requested doesn\'t exist.']],
            $this->response['data']
        );
    }

    public function testLogsUnexpectedFailuresWithoutLeakingTheirMessage(): void
    {
        $failure = new RuntimeException('SQLSTATE[HY000]: connection refused');
        $this->productRepository->method('getById')->willThrowException($failure);
        $this->logger->expects(self::once())->method('critical')->with($failure);

        $this->controller->execute();

        self::assertSame(500, $this->response['code']);
        self::assertSame(
            ['error' => ['message' => 'We could not estimate shipping right now. Please try again.']],
            $this->response['data']
        );
    }
}
