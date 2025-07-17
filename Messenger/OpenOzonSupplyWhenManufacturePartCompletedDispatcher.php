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

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\Invariable\ManufacturePartInvariable;
use BaksDev\Manufacture\Part\Messenger\ManufacturePartMessage;
use BaksDev\Manufacture\Part\Repository\ManufacturePartCurrentEvent\ManufacturePartCurrentEventInterface;
use BaksDev\Manufacture\Part\Repository\ManufacturePartInvariable\ManufacturePartInvariableInterface;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryFbsOzon;
use BaksDev\Ozon\Package\Entity\Supply\OzonSupply;
use BaksDev\Ozon\Package\Repository\Supply\ExistOpenOzonSupplyProfile\ExistOzonSupplyInterface;
use BaksDev\Ozon\Package\UseCase\Supply\New\OzonSupplyNewDTO;
use BaksDev\Ozon\Package\UseCase\Supply\New\OzonSupplyNewHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Открывает новую поставку OzonSupply: - выполняется завершающий этап производства; - заказ имеет доставку Ozon FBS
 */
#[AsMessageHandler(priority: 80)]
final readonly class OpenOzonSupplyWhenManufacturePartCompletedDispatcher
{
    public function __construct(
        #[Target('ozonManufactureLogger')] private LoggerInterface $Logger,
        private DeduplicatorInterface $Deduplicator,
        private OzonSupplyNewHandler $OzonSupplyNewHandler,
        private ManufacturePartCurrentEventInterface $ManufacturePartCurrentEventRepository,
        private ManufacturePartInvariableInterface $ManufacturePartInvariableRepository,
        private ExistOzonSupplyInterface $ExistOpenOzonSupplyRepository,
    ) {}

    public function __invoke(ManufacturePartMessage $message): void
    {
        $Deduplicator = $this->Deduplicator
            ->namespace('ozon-manufacture')
            ->deduplication([$message, self::class]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $ManufacturePartEvent = $this->ManufacturePartCurrentEventRepository
            ->fromPart($message->getId())
            ->find();

        if(false === ($ManufacturePartEvent instanceof ManufacturePartEvent))
        {
            $this->Logger->critical(
                'manufacture-part: ManufacturePartEvent не определено',
                [$message, self::class.':'.__LINE__]
            );

            return;
        }

        /**
         * Обрабатываем, если:
         *
         * - ManufacturePartStatus -> ManufacturePartStatusCompleted
         * - DeliveryUid -> TypeDeliveryFbsOzon
         */

        if(false === $ManufacturePartEvent->equalsManufacturePartStatus(ManufacturePartStatusCompleted::class))
        {
            return;
        }

        if(false === $ManufacturePartEvent->equalsManufacturePartComplete(TypeDeliveryFbsOzon::class))
        {
            return;
        }

        $ManufacturePartInvariable = $this->ManufacturePartInvariableRepository
            ->forPart($message->getId())
            ->find();

        if(false === ($ManufacturePartInvariable instanceof ManufacturePartInvariable))
        {
            return;
        }

        /**
         * Не открываем новую поставку, если поставка OzonSupply уже открыта
         */
        $ExistNewOrOpenOzonSupply = $this->ExistOpenOzonSupplyRepository
            ->forProfile($ManufacturePartInvariable->getProfile())
            ->isExistNewOrOpenOzonSupply();

        if(true === $ExistNewOrOpenOzonSupply)
        {
            $Deduplicator->save();
            return;
        }

        /**
         * Открываем новую поставку на указанный профиль
         */
        $profile = $ManufacturePartInvariable->getProfile();

        $OzonSupplyNewDTO = new OzonSupplyNewDTO($profile);
        $OzonSupply = $this->OzonSupplyNewHandler->handle($OzonSupplyNewDTO);

        if(false === ($OzonSupply instanceof OzonSupply))
        {
            $this->Logger->critical(
                'ozon-manufacture: Ошибка при открытии новой поставки при завершающем этапе производства Ozon FBS',
                [$OzonSupply, self::class.':'.__LINE__]
            );

            return;
        }

        $Deduplicator->save();
    }
}