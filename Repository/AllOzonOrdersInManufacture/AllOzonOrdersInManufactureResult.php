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
 *
 */

declare(strict_types=1);

namespace BaksDev\Ozon\Manufacture\Repository\AllOzonOrdersInManufacture;

use BaksDev\Products\Product\Type\Event\ProductEventUid;
use BaksDev\Products\Product\Type\Offers\Id\ProductOfferUid;
use BaksDev\Products\Product\Type\Offers\Variation\Id\ProductVariationUid;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\Id\ProductModificationUid;
use DateTimeImmutable;

final readonly class AllOzonOrdersInManufactureResult
{
    public function __construct(
        private string $order_data,

        private string $product,
        private ?string $offer,
        private ?string $variation,
        private ?string $modification,

        private int $order_total,

        private ?string $card_article,
        private string $product_name,

        private ?string $product_offer_value,
        private ?string $product_offer_postfix,
        private ?string $product_offer_reference,

        private ?string $product_variation_value,
        private ?string $product_variation_postfix,
        private ?string $product_variation_reference,

        private ?string $product_modification_value,
        private ?string $product_modification_postfix,
        private ?string $product_modification_reference,

        private string $product_image,
        private string $product_image_ext,
        private bool $product_image_cdn,
        private string $product_article,

        private string|false|null $exist_manufacture,
    ) {}

    public function getOrderData(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->order_data);
    }

    public function getProduct(): ProductEventUid
    {
        return new ProductEventUid($this->product);
    }

    public function getOffer(): ?ProductOfferUid
    {
        return (null !== $this->offer) ? new ProductOfferUid($this->offer) : null;
    }

    public function getVariation(): ?ProductVariationUid
    {
        return (null !== $this->variation) ? new ProductVariationUid($this->variation) : null;
    }

    public function getModification(): ?ProductModificationUid
    {
        return (null !== $this->modification) ? new ProductModificationUid($this->modification) : null;
    }

    public function getOrderTotal(): int
    {
        return $this->order_total;
    }

    public function getCardArticle(): ?string
    {
        return $this->card_article;
    }

    public function getProductName(): string
    {
        return $this->product_name;
    }

    public function getProductOfferValue(): ?string
    {
        return $this->product_offer_value;
    }

    public function getProductOfferPostfix(): ?string
    {
        return $this->product_offer_postfix;
    }

    public function getProductOfferReference(): ?string
    {
        return $this->product_offer_reference;
    }

    public function getProductVariationValue(): ?string
    {
        return $this->product_variation_value;
    }

    public function getProductVariationPostfix(): ?string
    {
        return $this->product_variation_postfix;
    }

    public function getProductVariationReference(): ?string
    {
        return $this->product_variation_reference;
    }

    public function getProductModificationValue(): ?string
    {
        return $this->product_modification_value;
    }

    public function getProductModificationPostfix(): ?string
    {
        return $this->product_modification_postfix;
    }

    public function getProductModificationReference(): ?string
    {
        return $this->product_modification_reference;
    }

    public function getProductImage(): string
    {
        return $this->product_image;
    }

    public function getProductImageExt(): string
    {
        return $this->product_image_ext;
    }

    public function isProductImageCdn(): bool
    {
        return $this->product_image_cdn;
    }

    public function getProductArticle(): string
    {
        return $this->product_article;
    }

    public function getExistManufacture(): string|false
    {
        return (false === empty($this->exist_manufacture)) ? $this->exist_manufacture : false;
    }
}