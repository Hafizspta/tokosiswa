<?php

namespace App\Livewire;

use App\Data\CartData;
use Livewire\Component;
use App\Data\RegionData;
use App\Data\ShippingData;
use Illuminate\Support\Number;
use Illuminate\Support\Collection;
use App\Services\RegionQueryService;
use Illuminate\Support\Facades\Gate;
use App\Contract\CartServiceInterface;
use App\Data\CheckoutData;
use App\Data\CustomerData;
use App\Rules\ValidPaymentMethodHash;
use App\Rules\ValidShippingHash;
use App\Services\CheckoutService;
use App\Services\PaymentMethodQueryService;
use Spatie\LaravelData\DataCollection;
use App\Services\ShippingMethodService;

class Checkout extends Component
{
    public array $data = [
        'full_name' => null,
        'email' => null,
        'phone' => null,
        'address_line' => null,
        'destination_region_code' => null,
        'shipping_hash' => null,
        'payment_method_hash' => null
    ];

    public array $region_selector = [
        'keyword' => null,
        'region_selected' => null
    ]; 

    public array $shipping_selector = [
        'shipping_method' => null
    ];

    public array $payment_method_selector = [
        'payment_method_selected' => null
    ];

    public array $sumaries = [
        'sub_total' => 0,
        'sub_total_formatted' => '-',
        'shipping_total' => 0,
        'shipping_total_formatted' => '-',
        'grand_total' => 0,
        'grand_total_formatted' => '-'
    ];

    public function mount()
    {
        if(!Gate::inspect('is_stock_available')->allowed()){
            return redirect()->route('cart');
        }

        if($this->cart->total_quantity <= 0) {
            return redirect()->route('cart');
        }

        $this->calculateTotal();
    }

    public function rules()
    {
        return [
            'data.full_name'                => ['required', 'min:3', 'max:255'],
            'data.email'                    => ['required', 'email', 'max:255'],
            'data.phone'                    => ['required', 'min:7', 'max:14' ],
            'data.address_line'            => ['required', 'min:8', 'max:255'],
            'data.destination_region_code'  => ['required', 'exists:regions,code'],
            'data.shipping_hash'            => ['required', new ValidShippingHash()],
            'data.payment_method_hash'      => ['required', new ValidPaymentMethodHash]

        ];
    }

    protected function messages() 
    {
        return [
            'data.full_name' => 'Mohon isi nama lengkap Anda.',
            'data.email' => 'Email wajib diisi.',
            'data.phone' => 'Nomor telepon/WA wajib diisi.',
            'data.address_line' => 'Alamat pengiriman harus diisi lengkap (Jalan/Lorong, No. Rumah, RT/RW).',
            'data.destination_region_code' => 'Silakan pilih Kecamatan/Kota tujuan.',
            'data.shipping_hash' => 'Silakan pilih kurir pengiriman terlebih dahulu.',
            'data.payment_method_hash' => 'Metode pembayaran belum dipilih.'
        ];
    }


    public function calculateTotal()
    {
        data_set($this->sumaries, 'sub_total', $this->cart->total);
        data_set($this->sumaries, 'sub_total_formatted', $this->cart->total_formatted);

        $shipping_cost = $this->shippingMethod?->cost ?? 0;
        data_set($this->sumaries, 'shipping_total', $shipping_cost);
        data_set($this->sumaries, 'shipping_total_formatted', Number::currency($shipping_cost));

        $grand_total = $this->cart->total + $shipping_cost;
        data_set($this->sumaries, 'grand_total', $grand_total);
        data_set($this->sumaries, 'grand_total_formatted', Number::currency($grand_total));
    }

    public function getCartProperty(CartServiceInterface $cart) : CartData
    {
        return $cart->all();
    }

    public function getRegionsProperty(RegionQueryService $query_service) : DataCollection
    {

        if(!data_get($this->region_selector, 'keyword')){
            $data = [];
            return new DataCollection(RegionData::class, []);
        }

        return $query_service->searchRegionByName(
            data_get($this->region_selector, 'keyword')
        );        
    }

    public function getRegionProperty(RegionQueryService $query_service) : ?RegionData
    {
        $region_selected = data_get($this->region_selector, 'region_selected');

        if(!$region_selected){
            return null;
        }

        return $query_service->searchRegionByCode($region_selected);
    }

    public function updatedRegionSelectorRegionSelected($value)
    {
        data_set($this->data, 'destination_region_code', $value);
    }

    /** @return DataCollection<ShippingData> */
    public function getShippingMethodsProperty(
        RegionQueryService $region_query,
        ShippingMethodService $shipping_service
    ) : DataCollection|Collection
    {
        if(!data_get($this->data, 'destination_region_code')){
            return new DataCollection(ShippingData::class, []);
        }

        $origin_code = config('shipping.shipping_origin_code');
        $origin_region = $region_query->searchRegionByCode($origin_code);
        $destination_region = $region_query->searchRegionByCode(data_get($this->data, 'destination_region_code'));

        if (!$origin_region || !$destination_region) {
            return new DataCollection(ShippingData::class, []);
        }

        return $shipping_service->getShippingMethods(
            $origin_region,
            $destination_region,
            $this->cart
        )->toCollection()->groupBy('service');
    }

    public function getShippingMethodProperty(
    ShippingMethodService $shipping_service
    ) : ?ShippingData
    {
        if(
            empty(data_get($this->data, 'shipping_hash')) ||
            empty(data_get($this->data, 'destination_region_code'))
        ){
            return null;
        }

        $data = $shipping_service->getShippingMethod(
            data_get($this->data, 'shipping_hash')
        );

        if($data == null){
            $this->addError('shipping_hash', "Shipping Cost Missing");
            return null; // <-- Perbaikan di sini: kembalikan null, bukan redirect()
        }

        return $data;
    }

    public function updatedShippingSelectorShippingMethod($value)
    {
        data_set($this->data, 'shipping_hash', $value);
        $this->calculateTotal();
    }

    public function getPaymentMethodsProperty(
        PaymentMethodQueryService $query_service
    ) : DataCollection
    {
        return $query_service->getPaymentMethods();
    }

    public function updatedPaymentMethodSelectorPaymentMethodSelected($value)
    {
        data_set($this->data, 'payment_method_hash', $value);
    }

    public function placeAnOrder(
        CartServiceInterface $cart
    )
    {
        $validated = $this->validate();
        $shipping_method = app(ShippingMethodService::class)->getShippingMethod(
            data_get($validated, 'data.shipping_hash')
        );

        $payment_method = app(PaymentMethodQueryService::class)->getPaymentMethodByHash(
            data_get($validated, 'data.payment_method_hash')
        );

        $checkout = CheckoutData::from([
            'customer' => CustomerData::from(data_get($validated, 'data')),
            'address_line' => data_get($validated, 'data.address_line'),
            'origin' => $shipping_method->origin,
            'destination' => $shipping_method->destination,
            'cart' => $this->cart,
            'shipping' => $shipping_method,
            'payment' => $payment_method
        ]);

        $service = app(CheckoutService::class);
        $sales_order = $service->makeAnOrder($checkout);
        $cart->clear();

        return redirect()->route('order-confirmed', $sales_order->trx_id);

    }

    public function render()
    {
        return view('livewire.checkout',[
            'cart' => $this->cart
        ]);
    }
}
