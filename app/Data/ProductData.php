<?php
declare(strict_types=1);

namespace App\Data;

use App\Models\Product;
use Illuminate\Support\Number;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class ProductData extends Data
{
    #[Computed]
    public string $price_formatted;

    public function __construct(
        public string $name,
        public string $short_desc,
        public string $sku,
        public string $slug,
        public string|Optional|null $description,
        public int $stock,
        public float $price,
        public int $weight,
        public string $cover_url,
        public Optional|array $gallery = new Optional()
    ) {
        $this->price_formatted = Number::currency($price);
    }

    public static function fromModel(Product $product, bool $with_gallery = false) : self
    {
        return new self(
            $product->name,
            $product->tags()->where('type', 'categories')->pluck('name')->implode(', '),
            $product->sku,
            $product->slug,
            $product->description,
            $product->stock,
            floatval($product->price),
            $product->weight,
            $product->getFirstMediaUrl('cover'),
            gallery: $with_gallery ? $product->getMedia('gallery')->map(fn($record) => $record->getUrl())->toArray() : new Optional()
        );
    }
}
