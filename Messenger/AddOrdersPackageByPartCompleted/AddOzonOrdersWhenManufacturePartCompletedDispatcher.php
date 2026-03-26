<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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
 */

declare(strict_types=1);

namespace BaksDev\Ozon\Manufacture\Messenger\AddOrdersPackageByPartCompleted;


use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\Orders\ManufacturePartProductOrderDTO;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusPackage;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryFbsOzon;
use BaksDev\Ozon\Package\Entity\Package\OzonPackage;
use BaksDev\Ozon\Package\UseCase\Package\Pack\Orders\OzonPackageOrderDTO;
use BaksDev\Ozon\Package\UseCase\Package\Pack\OzonPackageDTO;
use BaksDev\Ozon\Package\UseCase\Package\Pack\OzonPackageHandler;
use BaksDev\Wildberries\Package\Repository\Package\ExistOrderPackage\ExistOrderPackageInterface;
use BaksDev\Wildberries\Package\UseCase\Package\Pack\WbPackageHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[Autoconfigure(shared: false)]
#[AsMessageHandler(priority: 0)]
final readonly class AddOzonOrdersWhenManufacturePartCompletedDispatcher
{
    public function __construct(
        #[Target('wildberriesManufactureLogger')] private LoggerInterface $logger,
        private CentrifugoPublishInterface $CentrifugoPublish,
        private CurrentOrderEventInterface $CurrentOrderEventRepository,
        private ExistOrderPackageInterface $ExistOrderPackageRepository,
        private OzonPackageHandler $OzonPackageHandler,
        private MessageDispatchInterface $messageDispatch
    ) {}

    public function __invoke(AddOzonOrdersWhenManufacturePartCompletedMessage $message): void
    {

        /** Создаем упаковку на заказы одного продукта */
        $inPartOzonPackageDTO = new OzonPackageDTO($message->getProfile())
            ->setSupply($message->getSupply()); // идентификатор открытой поставки

        foreach($message->getOrders() as $order)
        {
            /** Активное событие заказа */
            $OrderEvent = $this->CurrentOrderEventRepository
                ->forOrder($order)
                ->find();

            if(false === ($OrderEvent instanceof OrderEvent))
            {
                $this->logger->critical(
                    'ozon-manufacture: не найдено активное событие заказа',
                    [self::class.':'.__LINE__, $order],
                );

                continue;
            }

            /** Пропускаем, если тип заказа не Ozon FBS */
            if(false === $OrderEvent->isDeliveryTypeEquals(TypeDeliveryFbsOzon::TYPE))
            {
                continue;
            }

            /** Пропускаем и пробуем позже, если заказ не на упаковке */
            if(false === $OrderEvent->isStatusEquals(OrderStatusPackage::class))
            {
                $this->messageDispatch->dispatch(
                    message: $message,
                    stamps: [new MessageDelay('5 seconds')],
                    transport: 'orders-order-low',
                );

                return;
            }

            /** Не добавляем заказ в упаковку, если он уже в поставке */
            if(true === $this->ExistOrderPackageRepository->forOrder($OrderEvent->getMain())->isExist())
            {
                continue;
            }

            /** На каждый продукт из заказа создаем упаковку */
            foreach($OrderEvent->getProduct() as $ordProduct)
            {
                /** Добавляем заказ в упаковку */
                $OzonPackageOrderDTO = new OzonPackageOrderDTO()
                    ->setId($OrderEvent->getMain()) // идентификатор заказа
                    ->setProduct($ordProduct->getId()) // идентификатор продукта из заказа
                    ->setSort(time()); // сортировка по умолчанию

                $inPartOzonPackageDTO->addOrd($OzonPackageOrderDTO);
            }

            /** Скрываем у всех заказ */
            $this->CentrifugoPublish
                ->addData(['identifier' => $OrderEvent->getMain()]) // ID заказа
                ->addData(['profile' => false])
                ->send('remove');
        }

        /** Если упаковка пуста - приступаем к следующему продукту */
        if($inPartOzonPackageDTO->getOrd()->isEmpty())
        {
            return;
        }

        $ozonPackage = $this->OzonPackageHandler->handle($inPartOzonPackageDTO);

        if(false === ($ozonPackage instanceof OzonPackage))
        {
            $this->logger->critical(
                sprintf('ozon-manufacture: Ошибка %s при сохранении упаковки', $ozonPackage),
                [$message, self::class.':'.__LINE__],
            );

            return;
        }

        $this->logger->info(
            'Добавили OzonPackage упаковку в поставку OzonSupply',
            [$ozonPackage, $message->getSupply(), self::class.':'.__LINE__],
        );


    }
}
