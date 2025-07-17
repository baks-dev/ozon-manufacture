<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 *
 */

declare(strict_types=1);

namespace BaksDev\Ozon\Manufacture\Messenger\CacheClear;

use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Сбрасывает кэш модуля orders-order
 */
#[AsMessageHandler(priority: -100)]
final readonly class OzonOrdersOrderCacheClearHandler
{
    public function __construct(
        #[Target('ozonManufactureLogger')] private LoggerInterface $logger,
        private AppCacheInterface $cache,
    ) {}

    public function __invoke(OrderMessage $message): void
    {
        $cache = $this->cache->init('ozon-manufacture');

        if(false === $cache->clear())
        {
            $this->logger->warning(
                'Ошибка очистки кэша модуля ozon-manufacture',
                [
                    ManufacturePart::class,
                    self::class.':'.__LINE__
                ]
            );
        }
    }
}