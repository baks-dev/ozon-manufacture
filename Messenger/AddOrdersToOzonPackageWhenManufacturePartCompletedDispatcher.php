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

namespace BaksDev\Ozon\Manufacture\Messenger;

use BaksDev\Centrifugo\Server\Publish\CentrifugoPublishInterface;
use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\Invariable\ManufacturePartInvariable;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Repository\ManufacturePartInvariable\ManufacturePartInvariableInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\ManufacturePartDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\ManufacturePartProductsDTO;
use BaksDev\Manufacture\Part\UseCase\Admin\NewEdit\Products\Orders\ManufacturePartProductOrderDTO;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Repository\CurrentOrderEvent\CurrentOrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusPackage;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryFbsOzon;
use BaksDev\Ozon\Package\Entity\Package\OzonPackage;
use BaksDev\Ozon\Package\Repository\Package\ExistOrderInOzonPackage\ExistOrderInOzonPackageInterface;
use BaksDev\Ozon\Package\Repository\Supply\ExistOpenOzonSupplyProfile\ExistOzonSupplyInterface;
use BaksDev\Ozon\Package\Repository\Supply\OpenOzonSupplyIdentifier\OpenOzonSupplyIdentifierInterface;
use BaksDev\Ozon\Package\Type\Supply\Id\OzonSupplyUid;
use BaksDev\Ozon\Package\UseCase\Package\Pack\Orders\OzonPackageOrderDTO;
use BaksDev\Ozon\Package\UseCase\Package\Pack\OzonPackageDTO;
use BaksDev\Ozon\Package\UseCase\Package\Pack\OzonPackageHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Метод добавляет заказы Ozon в ОТКРЫТУЮ поставку при ВЫПОЛНЕННОЙ производственной парии Ozon Fbs
 */
#[AsMessageHandler(priority: 10)]
final readonly class AddOrdersToOzonPackageWhenManufacturePartCompletedDispatcher
{
    public function __construct(
        #[Target('ozonManufactureLogger')] private LoggerInterface $logger,
        private CentrifugoPublishInterface $CentrifugoPublish,
        private DeduplicatorInterface $Deduplicator,
        private ManufacturePartInvariableInterface $ManufacturePartInvariableRepository,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEventRepository,
        private CurrentOrderEventInterface $CurrentOrderEventRepository,
        private ExistOzonSupplyInterface $ExistOzonSupplyRepository,
        private ExistOrderInOzonPackageInterface $ExistOrderPackageRepository,
        private OpenOzonSupplyIdentifierInterface $OpenOzonSupplyIdentifierRepository,
        private OzonPackageHandler $OzonPackageHandler,
    ) {}

    public function __invoke(ManufacturePartMessage $message): bool
    {
        $Deduplicator = $this->Deduplicator
            ->namespace('ozon-manufacture')
            ->deduplication([$message->getId(), self::class]);

        if($Deduplicator->isExecuted())
        {
            return false;
        }

        /** Активное событие производственной партии */
        $ManufacturePartEvent = $this->ManufacturePartCurrentEventRepository
            ->fromPart($message->getId())
            ->find();

        if(false === ($ManufacturePartEvent instanceof ManufacturePartEvent))
        {
            $this->logger->critical(
                'ozon-manufacture: ManufacturePartEvent не определено',
                [$message, self::class.':'.__LINE__]
            );

            return false;
        }

        if(
            false === $ManufacturePartEvent->equalsManufacturePartStatus(ManufacturePartStatusCompleted::class)
            ||
            false === $ManufacturePartEvent->equalsManufacturePartComplete(TypeDeliveryFbsOzon::class)
        )
        {
            return false;
        }

        /** Активное событие в ManufacturePartInvariable */
        $ManufacturePartInvariable = $this->ManufacturePartInvariableRepository
            ->forPart($message->getId())
            ->find();

        if(false === ($ManufacturePartInvariable instanceof ManufacturePartInvariable))
        {
            return false;
        }

        /** Проверяем, что имеется открытая поставка OzonSupply со статусом NEW */
        $ExistNewSupply = $this->ExistOzonSupplyRepository
            ->forProfile($ManufacturePartInvariable->getProfile())
            ->isExistNewOzonSupply();

        if(false === $ExistNewSupply)
        {
            return false;
        }

        $ManufacturePartDTO = new ManufacturePartDTO();
        $ManufacturePartEvent->getDto($ManufacturePartDTO);

        $UserProfileUid = $ManufacturePartInvariable->getProfile();

        /** Если коллекция продукции в производственной партии пустая - завершаем работу */
        if(true === $ManufacturePartDTO->getProduct()->isEmpty())
        {
            $this->logger->warning(
                'ozon-manufacture: продукция в производственной партии не найдена',
                [ManufacturePartDTO::class, self::class.':'.__LINE__]
            );

            return true;
        }

        /**
         * Продукты в производственной партии
         *
         * Добавляем все заказы Ozon FBS со статусом «На упаковке» в ОТКРЫТУЮ системную поставку OzonSupply
         * @var ManufacturePartProductsDTO $ManufacturePartProductsDTO
         */
        foreach($ManufacturePartDTO->getProduct() as $ManufacturePartProductsDTO)
        {
            /** Если коллекция заказов, закрепленных за продуктом из производственной партии пустая - пропускаем */
            if(true === $ManufacturePartProductsDTO->getOrd()->isEmpty())
            {
                $this->logger->critical(
                    'ozon-manufacture: заказы в производственной партии не найдены',
                    [ManufacturePartProductsDTO::class, self::class.':'.__LINE__]
                );

                continue;
            }

            /**
             * Идентификатор ОТКРЫТОЙ поставки OzonSupply
             * поставка открывается @see OpenOzonSupplyWhenManufacturePartCompletedDispatcher
             */
            $OzonSupplyUid = $this
                ->OpenOzonSupplyIdentifierRepository
                ->forProfile($UserProfileUid)
                ->find();

            if(false === ($OzonSupplyUid instanceof OzonSupplyUid))
            {
                $this->logger->critical(
                    sprintf('ozon-manufacture: Открытая поставка не найдена: OzonSupplyUid %s ', $OzonSupplyUid),
                    [$message, self::class.':'.__LINE__]
                );

                return false;
            }

            /** Создаем упаковку на заказы одного продукта */
            $inPartOzonPackageDTO = new OzonPackageDTO($UserProfileUid)
                ->setSupply($OzonSupplyUid); // идентификатор открытой поставки

            /**
             * Заказы на продукт
             * @var ManufacturePartProductOrderDTO $ManufacturePartProductOrderDTO
             */
            foreach($ManufacturePartProductsDTO->getOrd() as $ManufacturePartProductOrderDTO)
            {

                /** Активное событие заказа */
                $OrderEvent = $this->CurrentOrderEventRepository
                    ->forOrder($ManufacturePartProductOrderDTO->getOrd())
                    ->find();

                if(false === ($OrderEvent instanceof OrderEvent))
                {
                    $this->logger->critical(
                        'ozon-manufacture: не найдено активное событие заказа',
                        [self::class.':'.__LINE__, $ManufacturePartProductOrderDTO->getOrd()]
                    );

                    continue;
                }

                /** Заказ на упаковке и тип заказа Ozon FBS */
                if(
                    false === $OrderEvent->isStatusEquals(OrderStatusPackage::class)
                    ||
                    false === $OrderEvent->isDeliveryTypeEquals(TypeDeliveryFbsOzon::TYPE)
                )
                {
                    continue;
                }

                /** На каждый продукт из заказа создаем упаковку */
                foreach($OrderEvent->getProduct() as $ordProduct)
                {

                    /** Пропускаем уже добавленную упаковку в поставку */
                    if(
                        true === $this->ExistOrderPackageRepository
                            ->forOrder($OrderEvent->getMain())
                            ->forOrderProduct($ordProduct->getId())
                            ->isExist()
                    )
                    {
                        continue;
                    }

                    /** Соответствие продукту из заказа продукту из производственной партии */
                    $isProduct = $ordProduct->getProduct()->equals($ManufacturePartProductsDTO->getProduct())
                        && ((is_null($ordProduct->getOffer()) === true && is_null($ManufacturePartProductsDTO->getOffer()) === true) || $ordProduct->getOffer()?->equals($ManufacturePartProductsDTO->getOffer()))
                        && ((is_null($ordProduct->getVariation()) === true && is_null($ManufacturePartProductsDTO->getVariation()) === true) || $ordProduct->getVariation()?->equals($ManufacturePartProductsDTO->getVariation()))
                        && ((is_null($ordProduct->getModification()) === true && is_null($ManufacturePartProductsDTO->getModification()) === true) || $ordProduct->getModification()?->equals($ManufacturePartProductsDTO->getModification()));

                    if(true === $isProduct)
                    {

                        /** Добавляем заказ в упаковку */
                        $OzonPackageOrderDTO = new OzonPackageOrderDTO()
                            ->setId($OrderEvent->getMain()) // идентификатор заказа
                            ->setProduct($ordProduct->getId()) // идентификатор продукта из заказа
                            ->setSort(time()); // сортировка по умолчанию

                        $inPartOzonPackageDTO->addOrd($OzonPackageOrderDTO);
                    }
                    else
                    {
                        /** Создаем НОВУЮ упаковку на заказы одного продукта, если продукт вне текущей поставки */
                        $outPartOzonPackageDTO = new OzonPackageDTO($UserProfileUid)
                            ->setSupply($OzonSupplyUid)
                            ->setOutPart(); // вне текущей поставки

                        /** Добавляем заказ в упаковку со статусом NEW */
                        $OzonPackageOrderDTO = new OzonPackageOrderDTO()
                            ->setId($OrderEvent->getMain()) // идентификатор заказа
                            ->setProduct($ordProduct->getId()) // идентификатор продукта из заказа
                            ->setSort(time()); // сортировка по умолчанию

                        $outPartOzonPackageDTO->addOrd($OzonPackageOrderDTO);

                        $ozonPackage = $this->OzonPackageHandler->handle($outPartOzonPackageDTO);

                        if(false === ($ozonPackage instanceof OzonPackage))
                        {
                            $this->logger->critical(
                                sprintf('ozon-manufacture: Ошибка %s при сохранении упаковки', $ozonPackage),
                                [$message, self::class.':'.__LINE__]
                            );

                            return false;
                        }

                        $this->logger->info(
                            'Добавили OzonPackage упаковку в поставку OzonSupply',
                            [
                                $ozonPackage, $OzonSupplyUid,
                                self::class.':'.__LINE__
                            ]
                        );
                    }
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
                continue;
            }

            $ozonPackage = $this->OzonPackageHandler->handle($inPartOzonPackageDTO);

            if(false === ($ozonPackage instanceof OzonPackage))
            {
                $this->logger->critical(
                    sprintf('ozon-manufacture: Ошибка %s при сохранении упаковки', $ozonPackage),
                    [$message, self::class.':'.__LINE__]
                );

                return false;
            }

            $this->logger->info(
                'Добавили OzonPackage упаковку в поставку OzonSupply',
                [
                    $ozonPackage, $OzonSupplyUid,
                    self::class.':'.__LINE__
                ]
            );
        }

        $Deduplicator->save();

        return true;
    }
}