<?php
// declare(strict_types=1);

namespace App\Livewire;

use App\Contract\CartServiceInterface;
use App\Data\CartItemData;
use App\Data\ProductData;
use Livewire\Component;

class AddToCart extends Component
{
    public int $quantity;
    public string $label = 'Add To Cart';
    public string $sku;
    public float $price;
    public int $weight;
    public int $stock;


    public function mount(ProductData $product, CartServiceInterface $cart)
    {
        $this->sku = $product->sku;
        $this->price = $product->price;
        $this->weight = $product->weight;
        $this->stock = $product->stock;
        $this->quantity = $cart->getItemBySku($product->sku)?->quantity ?? 1;

        $this->validate();
    }

    public function rules() : array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', "max:{$this->stock}"]
        ];
    }

    public function addToCart(CartServiceInterface $cart)
    {
        $this->validate();

        $cart->addOrUpdate(new CartItemData(
            sku: $this->sku,
            quantity: $this->quantity,
            price: $this->price,
            weight: $this->weight
        ));

        session()->flash('success', 'Produk berhasil dimasukkan ke keranjang');

        $this->dispatch('cart-updated');
        return redirect()->route('cart');
    }

    public function render()
    {
        return view('livewire.add-to-cart');
    }
}
