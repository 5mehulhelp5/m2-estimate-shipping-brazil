<?php
/**
 * Unit test bootstrap.
 *
 * Magento generates its *Factory classes at runtime, so they do not exist as files for PHPUnit to mock.
 * The one factory this module's code depends on is declared here with the signature Magento generates.
 */

declare(strict_types=1);

namespace {
    require dirname(__DIR__, 2) . '/vendor/autoload.php';
}

namespace Magento\Quote\Model {
    if (!class_exists(QuoteFactory::class)) {
        class QuoteFactory
        {
            /**
             * @param array<string, mixed> $data
             * @return Quote
             */
            public function create(array $data = [])
            {
                throw new \LogicException('Mock ' . self::class . ' instead of using it.');
            }
        }
    }
}
