<?php 
declare(strict_types=1);

namespace App\Drivers\Payment;

use Spatie\LaravelData\DataCollection;
use App\Contract\PaymentDriverInterface;
use App\Data\PaymentData;
use App\Data\SalesOrderData;
use App\Data\SalesOrderItemData;
use App\Services\SalesOrderService;
use Illuminate\Support\Facades\Http;

class MootaPaymentDriver implements PaymentDriverInterface
{
    public readonly string $driver;

    public function __construct()
    {
        $this->driver = 'moota';
    }

    /** @return DataCollection<PaymentData> */
    public function getMethods() : DataCollection
    {
        return PaymentData::collect([
            PaymentData::from(config('services.moota.accounts')),
        ], DataCollection::class);
    }

    public function process(SalesOrderData $sales_order)
    {
        $response = Http::withoutVerifying()
            ->withToken(config('services.moota.access_token'))
            ->post('https://api.moota.co/api/v2/create-transaction', [
                'order_id' => $sales_order->trx_id,
                'account_id' => data_get($sales_order->payment->payload, 'account_id'),
                'customers' => [
                    'name' => $sales_order->customer->full_name,
                    'email' => $sales_order->customer->email,
                    'phone' => $sales_order->customer->phone
                ],
                'items' => $sales_order->items->toCollection()->map(function(SalesOrderItemData $item){
                    return [
                        'name' => $item->name,
                        'description' => $item->short_desc,
                        'qty' => $item->quantity,
                        'price' => $item->price
                    ];
                })->merge([
                    [
                        'name' => $sales_order->shipping->courier,
                        'description' => $sales_order->shipping->estimated_delivery,
                        'qty' => 1,
                        'price' => $sales_order->shipping_cost
                    ]
                ])->toArray(),
                'description' => '',
                'note' => '',
                'redirect_url' => route('order-confirmed', $sales_order->trx_id),
                'total' => $sales_order->total
            ]);

        return app(SalesOrderService::class)->updateShippingPayload($sales_order, [
            'moota_payload' => $response->json('data')
        ]);

    }

    public function shouldShowPayNowButton(SalesOrderData $sales_order) : bool
    {
        return true;
    }

    public function getRedirectUrl(SalesOrderData $sales_order) : ?string
    {
        return data_get($sales_order->payment->payload, 'moota_payload.payment_url');
    }
}