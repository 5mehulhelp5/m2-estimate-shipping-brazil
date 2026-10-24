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

use Fineweb\SimulateProductShipping\Api\ShippingEstimatorInterface;
use Fineweb\SimulateProductShipping\Controller\Simulate\Index;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
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
     * @var ShippingEstimatorInterface&MockObject
     */
    private ShippingEstimatorInterface $estimator;

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

        $this->estimator = $this->createMock(ShippingEstimatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new Index($request, $resultFactory, $this->estimator, $this->logger);
    }

    public function testHandlesPostRequestsOnly(): void
    {
        self::assertInstanceOf(HttpPostActionInterface::class, $this->controller);
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
            'not a string' => [['postcode' => ['88010400']]],
            'not an array' => ['88010400'],
        ];
    }

    #[DataProvider('invalidPostcodeProvider')]
    public function testRejectsAnInvalidPostcodeWithAReadableError(mixed $simulate): void
    {
        $this->params['simulate'] = $simulate;
        $this->estimator->expects(self::never())->method('estimate');

        $this->controller->execute();

        self::assertSame(400, $this->response['code']);
        self::assertSame(
            ['error' => ['message' => 'Please enter a valid postcode (8 digits).']],
            $this->response['data']
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validPostcodeProvider(): array
    {
        return ['with a hyphen' => ['88010-400'], 'digits only' => ['88010400'], 'with spaces' => [' 88010 400 ']];
    }

    #[DataProvider('validPostcodeProvider')]
    public function testAcceptsThePostcodeInAnyFormatAndPassesOnlyItsDigits(string $postcode): void
    {
        $this->params['simulate'] = ['postcode' => $postcode];
        $this->estimator->expects(self::once())
            ->method('estimate')
            ->with(42, self::anything(), '88010400')
            ->willReturn([]);

        $this->controller->execute();
    }

    public function testAnswersTheEstimateGroupedByCarrier(): void
    {
        $rates = ['Flat Rate' => [['title' => 'Fixed', 'price' => 'R$ 15,00', 'message' => '']]];
        $this->estimator->method('estimate')->willReturn($rates);

        $this->controller->execute();

        self::assertSame(200, $this->response['code']);
        self::assertSame($rates, $this->response['data']);
    }

    public function testForwardsTheQuantityAndTheChosenOptionsAndNothingElse(): void
    {
        $this->params += [
            'super_attribute' => ['93' => '52'],
            'bundle_option' => ['1' => '3'],
            'bundle_option_qty' => ['1' => '2'],
            'unrelated' => 'ignored',
        ];
        $this->estimator->expects(self::once())->method('estimate')->with(
            42,
            [
                'qty' => 2.0,
                'super_attribute' => ['93' => '52'],
                'bundle_option' => ['1' => '3'],
                'bundle_option_qty' => ['1' => '2'],
            ],
            '88010400'
        )->willReturn([]);

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
        $this->estimator->expects(self::once())
            ->method('estimate')
            ->with(42, ['qty' => 1.0], '88010400')
            ->willReturn([]);

        $this->controller->execute();
    }

    public function testUsesProductZeroWhenTheIdIsNotNumeric(): void
    {
        $this->params['product'] = 'abc';
        $this->estimator->expects(self::once())
            ->method('estimate')
            ->with(0, self::anything(), '88010400')
            ->willReturn([]);

        $this->controller->execute();
    }

    public function testReportsWhenNoShippingMethodIsAvailable(): void
    {
        $this->estimator->method('estimate')->willReturn([]);

        $this->controller->execute();

        self::assertSame(200, $this->response['code']);
        self::assertSame(
            ['error' => ['message' => 'There is no shipping method available at this time.']],
            $this->response['data']
        );
    }

    public function testReportsTheReasonOfALocalizedFailure(): void
    {
        $this->estimator->method('estimate')->willThrowException(
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
        $this->estimator->method('estimate')->willThrowException($failure);
        $this->logger->expects(self::once())->method('critical')->with($failure);

        $this->controller->execute();

        self::assertSame(500, $this->response['code']);
        self::assertSame(
            ['error' => ['message' => 'We could not estimate shipping right now. Please try again.']],
            $this->response['data']
        );
    }
}
