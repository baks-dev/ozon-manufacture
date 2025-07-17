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

namespace BaksDev\Ozon\Manufacture\Repository\AllOzonOrdersInManufacture;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Services\Paginator\PaginatorInterface;
use BaksDev\Delivery\Type\Id\DeliveryUid;
use BaksDev\Manufacture\Part\Entity\Event\ManufacturePartEvent;
use BaksDev\Manufacture\Part\Entity\Invariable\ManufacturePartInvariable;
use BaksDev\Manufacture\Part\Entity\ManufacturePart;
use BaksDev\Manufacture\Part\Entity\Products\ManufacturePartProduct;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusClosed;
use BaksDev\Manufacture\Part\Type\Status\ManufacturePartStatus\ManufacturePartStatusCompleted;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Entity\Invariable\OrderInvariable;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Entity\Products\OrderProduct;
use BaksDev\Orders\Order\Entity\Products\Price\OrderPrice;
use BaksDev\Orders\Order\Entity\User\Delivery\OrderDelivery;
use BaksDev\Orders\Order\Entity\User\OrderUser;
use BaksDev\Orders\Order\Type\Status\OrderStatus;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusNew;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryDbsOzon;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryFbsOzon;
use BaksDev\Products\Category\Entity\Offers\CategoryProductOffers;
use BaksDev\Products\Category\Entity\Offers\Variation\CategoryProductVariation;
use BaksDev\Products\Category\Entity\Offers\Variation\Modification\CategoryProductModification;
use BaksDev\Products\Category\Type\Id\CategoryProductUid;
use BaksDev\Products\Product\Entity\Category\ProductCategory;
use BaksDev\Products\Product\Entity\Event\ProductEvent;
use BaksDev\Products\Product\Entity\Info\ProductInfo;
use BaksDev\Products\Product\Entity\Material\ProductMaterial;
use BaksDev\Products\Product\Entity\Offers\Image\ProductOfferImage;
use BaksDev\Products\Product\Entity\Offers\ProductOffer;
use BaksDev\Products\Product\Entity\Offers\Variation\Image\ProductVariationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\Image\ProductModificationImage;
use BaksDev\Products\Product\Entity\Offers\Variation\Modification\ProductModification;
use BaksDev\Products\Product\Entity\Offers\Variation\ProductVariation;
use BaksDev\Products\Product\Entity\Photo\ProductPhoto;
use BaksDev\Products\Product\Entity\Trans\ProductTrans;
use BaksDev\Products\Product\Forms\ProductFilter\Admin\ProductFilterDTO;
use BaksDev\Products\Stocks\Entity\Total\ProductStockTotal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Doctrine\DBAL\ArrayParameterType;

final class AllOzonOrdersInManufactureRepository implements AllOzonOrdersInManufactureInterface
{
    private ?ProductFilterDTO $filter = null;

    private ?SearchDTO $search = null;

    private UserProfileUid|false $profile = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
        private readonly PaginatorInterface $paginator,
        private readonly UserProfileTokenStorageInterface $UserProfileTokenStorage
    ) {}

    public function search(SearchDTO $search): self
    {
        $this->search = $search;
        return $this;
    }

    public function filter(ProductFilterDTO $filter): self
    {
        $this->filter = $filter;
        return $this;
    }

    public function profile(UserProfile|UserProfileUid|string $profile): static
    {
        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    public function findPaginator(DeliveryUid|false $part): PaginatorInterface
    {
        $dbal = $this->DBALQueryBuilder
            ->createQueryBuilder(self::class)
            ->bindLocal();

        $dbal
            ->from(OrderInvariable::class, 'invariable');

        $dbal->join(
            'invariable',
            Order::class,
            'orders',
            'orders.id = invariable.main',
        );

        $dbal->join(
            'invariable',
            OrderEvent::class,
            'event',
            'event.id = orders.event AND event.status = :status',
        )
            ->setParameter(
                'status',
                OrderStatusNew::class,
                OrderStatus::TYPE,
            );

        $dbal->leftJoin(
            'orders',
            OrderUser::class,
            'order_user',
            'order_user.event = orders.event',
        );

        /** Находим сначала самые старые заказы с соответствующими типами заказа для Ozon*/
        $dbal
            ->addSelect('MIN(order_delivery.delivery_date) AS order_data')
            ->join(
                'order_user',
                OrderDelivery::class,
                'order_delivery',
                '
                    order_delivery.usr = order_user.id AND 
                    order_delivery.delivery IN (:delivery)
                ',
            )
            ->setParameter(
                key: 'delivery',
                value: [TypeDeliveryFbsOzon::TYPE, TypeDeliveryDbsOzon::TYPE],
                type: ArrayParameterType::STRING,
            );

        $dbal
            ->addSelect('order_product.product')
            ->addSelect('order_product.offer')
            ->addSelect('order_product.variation')
            ->addSelect('order_product.modification')
            ->leftJoin(
                'orders',
                OrderProduct::class,
                'order_product',
                'order_product.event = orders.event',
            );

        $dbal
            ->addSelect('SUM(order_product_price.total) AS order_total')
            ->join(
                'order_product',
                OrderPrice::class,
                'order_product_price',
                '
                    order_product_price.product = order_product.id 
                    AND order_product_price.total != order_product_price.access
                    ',
            );

        $dbal
            ->leftJoin(
                'order_product',
                ProductEvent::class,
                'product_event',
                'product_event.id = order_product.product',
            );

        if(true === $this->filter?->getMaterials())
        {
            $dbal->andWhereNotExists(ProductMaterial::class, 'tmp', 'tmp.event = order_product.product');
        }

        // @TODO нет статистики
        //        $dbal
        //            ->addSelect('wb_orders_statistics.analog')
        //            ->addSelect('wb_orders_statistics.alarm')
        //            ->leftJoin(
        //                'product_event',
        //                WbOrdersStatistics::class,
        //                'wb_orders_statistics',
        //                'wb_orders_statistics.product = product_event.main',
        //            );

        $dbal
            ->addSelect('product_info.article AS card_article')
            ->leftJoin(
                'order_product',
                ProductInfo::class,
                'product_info',
                'product_info.product = product_event.main AND product_info.profile = invariable.profile',
            );

        if($this->filter?->getCategory() instanceof CategoryProductUid)
        {
            $dbal
                ->join(
                    'order_product',
                    ProductCategory::class,
                    'product_category',
                    '
                    product_category.event = product_event.id AND
                    product_category.category = :category AND
                    product_category.root = true
                ')
                ->setParameter(
                    key: 'category',
                    value: $this->filter->getCategory(),
                    type: CategoryProductUid::TYPE,
                );
        }

        $dbal
            ->addSelect('product_trans.name AS product_name')
            ->leftJoin(
                'product_event',
                ProductTrans::class,
                'product_trans',
                'product_trans.event = product_event.id AND product_trans.local = :local',
            );

        /**
         * OFFERS
         */
        $dbal
            ->addSelect('product_offer.value AS product_offer_value')
            ->addSelect('product_offer.postfix AS product_offer_postfix')
            ->leftJoin(
                'product_event',
                ProductOffer::class,
                'product_offer',
                'product_offer.id = order_product.offer AND product_offer.event = product_event.id',
            );

        if($this->filter?->getOffer())
        {
            $dbal
                ->andWhere('product_offer.value = :offer')
                ->setParameter(
                    key: 'offer',
                    value: $this->filter->getOffer(),
                );
        }

        /** Тип торгового предложения */
        $dbal
            ->addSelect('category_offer.reference AS product_offer_reference')
            ->leftJoin(
                'product_offer',
                CategoryProductOffers::class,
                'category_offer',
                'category_offer.id = product_offer.category_offer',
            );

        /**
         * VARIATION
         */
        $dbal
            ->addSelect('product_variation.value AS product_variation_value')
            ->addSelect('product_variation.postfix AS product_variation_postfix')
            ->leftJoin(
                'product_offer',
                ProductVariation::class,
                'product_variation',
                'product_variation.id = order_product.variation AND product_variation.offer = product_offer.id',
            );

        /** ФИЛЬТР по множественным вариантам */
        if($this->filter?->getVariation())
        {
            $dbal
                ->andWhere('product_variation.value = :variation')
                ->setParameter(
                    key: 'variation',
                    value: $this->filter->getVariation(),
                );
        }

        /** Тип множественного варианта */
        $dbal
            ->addSelect('category_variation.reference AS product_variation_reference')
            ->leftJoin(
                'product_variation',
                CategoryProductVariation::class,
                'category_variation',
                'category_variation.id = product_variation.category_variation',
            );

        /**
         * MODIFICATION
         */
        $dbal
            ->addSelect('product_modification.value AS product_modification_value')
            ->addSelect('product_modification.postfix AS product_modification_postfix')
            ->leftJoin(
                'product_variation',
                ProductModification::class,
                'product_modification',
                'product_modification.id = order_product.modification AND product_modification.variation = product_variation.id',
            );

        /** ФИЛЬТР по модификациям множественного варианта */
        if($this->filter?->getModification())
        {
            $dbal
                ->andWhere('product_modification.value = :modification')
                ->setParameter(
                    key: 'modification',
                    value: $this->filter->getModification(),
                );
        }

        $dbal
            ->addSelect('category_modification.reference AS product_modification_reference')
            ->leftJoin(
                'product_modification',
                CategoryProductModification::class,
                'category_modification',
                'category_modification.id = product_modification.category_modification',
            );

        /** Фото продукта */
        $dbal->leftJoin(
            'product_event',
            ProductPhoto::class,
            'product_photo',
            'product_photo.event = product_event.id AND product_photo.root = true',
        );

        $dbal->leftJoin(
            'product_offer',
            ProductOfferImage::class,
            'product_offer_image',
            'product_offer_image.offer = product_offer.id AND product_offer_image.root = true',
        );

        $dbal->leftJoin(
            'product_variation',
            ProductVariationImage::class,
            'product_variation_image',
            'product_variation_image.variation = product_variation.id AND product_variation_image.root = true',
        );

        $dbal->leftJoin(
            'product_modification',
            ProductModificationImage::class,
            'product_modification_image',
            'product_modification_image.modification = product_modification.id AND product_modification_image.root = true',
        );

        $dbal->addSelect(
            "
			CASE
			    WHEN product_modification_image.name IS NOT NULL 
			   THEN CONCAT ( '/upload/".$dbal->table(ProductModificationImage::class)."' , '/', product_modification_image.name)

			   WHEN product_variation_image.name IS NOT NULL 
			   THEN CONCAT ( '/upload/".$dbal->table(ProductVariationImage::class)."' , '/', product_variation_image.name)
			   
			   WHEN product_offer_image.name IS NOT NULL 
			   THEN CONCAT ( '/upload/".$dbal->table(ProductOfferImage::class)."' , '/', product_offer_image.name)
			   
			   WHEN product_photo.name IS NOT NULL 
			   THEN CONCAT ( '/upload/".$dbal->table(ProductPhoto::class)."' , '/', product_photo.name)
					
			   ELSE NULL
			END AS product_image
		",
        );

        /** Флаг загрузки файла CDN */
        $dbal->addSelect('
			CASE
				WHEN product_modification_image.name IS NOT NULL 
			   THEN product_modification_image.ext
			   
			   WHEN product_variation_image.name IS NOT NULL 
			   THEN product_variation_image.ext
					
			   WHEN product_offer_image.name IS NOT NULL 
			   THEN product_offer_image.ext
					
			   WHEN product_photo.name IS NOT NULL 
			   THEN product_photo.ext
					
			   ELSE NULL
			END AS product_image_ext
		');

        /** Флаг загрузки файла CDN */
        $dbal->addSelect('
			CASE
			   WHEN product_modification_image.name IS NOT NULL 
			   THEN product_modification_image.cdn
			   
				WHEN product_variation_image.name IS NOT NULL 
			   THEN product_variation_image.cdn
					
			   WHEN product_offer_image.name IS NOT NULL 
			   THEN product_offer_image.cdn
					
			   WHEN product_photo.name IS NOT NULL 
			   THEN product_photo.cdn
					
			   ELSE NULL
			END AS product_image_cdn
		');

        /** Артикул продукта */
        $dbal->addSelect('
            COALESCE(
                product_modification.article, 
                product_variation.article, 
                product_offer.article, 
                product_info.article
            ) AS product_article
		');

        $dbal->allGroupByExclude();


        /** Открытая производственная партия */
        if($part)
        {
            /** Только товары, которых нет в производстве */

            $dbalExist = $this->DBALQueryBuilder->createQueryBuilder(self::class);

            $dbalExist->from(ManufacturePartProduct::class, 'exist_product');

            $dbalExist
                ->join(
                    'exist_product',
                    ManufacturePart::class,
                    'exist_part',
                    'exist_part.event = exist_product.event',
                );

            $dbalExist
                ->select('exist_part_invariable.number')
                ->leftJoin(
                    'exist_part',
                    ManufacturePartInvariable::class,
                    'exist_part_invariable',
                    'exist_part_invariable.main = exist_part.id',
                );

            $dbalExist->andWhere('exist_product.product = order_product.product');

            // $dbalExist->andWhere('(order_product.offer IS NULL OR exist_product.offer = order_product.offer)');
            $dbalExist->andWhere('(CASE 
                WHEN order_product.offer IS NOT NULL 
                THEN exist_product.offer = order_product.offer
                ELSE exist_product.offer IS NULL
            END)');

            // $dbalExist->andWhere('(order_product.variation IS NULL OR exist_product.variation = order_product.variation)');
            $dbalExist->andWhere('(CASE 
                WHEN order_product.variation IS NOT NULL 
                THEN exist_product.variation = order_product.variation
                ELSE exist_product.variation IS NULL
            END)');

            // $dbalExist->andWhere('(order_product.variation IS NULL OR exist_product.variation = order_product.variation)');
            $dbalExist->andWhere('(CASE 
                WHEN order_product.modification IS NOT NULL 
                THEN exist_product.modification = order_product.modification
                ELSE exist_product.modification IS NULL
            END)');

            /**
             * Только продукция в процессе производства
             * Только продукция на указанный завершающий этап
             */
            $dbalExist
                ->join('exist_part',
                    ManufacturePartEvent::class,
                    'exist_product_event',
                    '
                exist_product_event.id = exist_part.event AND
                exist_product_event.complete = :complete AND
                exist_product_event.status NOT IN (:status_part)
            ');

            $dbal->setParameter(
                key: 'complete',
                value: $part,
                type: DeliveryUid::TYPE,
            );

            $dbal->setParameter(
                key: 'status_part',
                value: [
                    ManufacturePartStatusClosed::STATUS,
                    ManufacturePartStatusCompleted::STATUS,
                ],
                type: ArrayParameterType::STRING,
            );

            $dbalExist->setMaxResults(1);

            $dbal->addSelect('(SELECT ('.$dbalExist->getSQL().')) AS exist_manufacture');
            $dbal->addOrderBy('exist_manufacture', 'DESC');
        }
        else
        {
            $dbal->addSelect('FALSE AS exist_manufacture');
        }

        $dbal
            ->where('invariable.profile = :profile OR invariable.profile IS NULL')
            ->setParameter(
                'profile',
                $this->profile ?: $this->UserProfileTokenStorage->getProfile(),
                UserProfileUid::TYPE,
            );

        $dbal->addOrderBy('order_data');

        /** Сверх наличия на складе */
        $dbal
            ->addSelect('(SUM(stock.total) - SUM(stock.reserve)) AS stock_available')
            ->leftJoin(
                'product_modification',
                ProductStockTotal::class,
                'stock',
                '
                    stock.profile = invariable.profile 
                    AND
                    stock.product = product_event.main
                    AND
                        CASE
                            WHEN product_offer.const IS NOT NULL THEN stock.offer = product_offer.const
                            ELSE stock.offer IS NULL
                        END
                    AND
                        CASE
                            WHEN product_variation.const IS NOT NULL THEN stock.variation = product_variation.const
                            ELSE stock.variation IS NULL
                        END
                    AND
                        CASE
                            WHEN product_modification.const IS NOT NULL THEN stock.modification = product_modification.const
                            ELSE stock.modification IS NULL
                        END
                    AND
                        (stock.total - stock.reserve) > 0
                        '
            );


        if($this->search && $this->search->getQuery())
        {
            $dbal
                ->createSearchQueryBuilder($this->search, true)
                ->addSearchLike('product_modification.article')
                ->addSearchLike('product_variation.article')
                ->addSearchLike('product_offer.article')
                ->addSearchLike('product_info.article')
                ->addSearchLike('product_trans.name');
        }

        return $this->paginator->fetchAllAssociative($dbal);
    }
}
