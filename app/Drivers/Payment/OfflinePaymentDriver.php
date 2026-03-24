<?php 
declare(strict_types=1);

namespace App\Drivers\Payment;

use Spatie\LaravelData\DataCollection;
use App\Contract\PaymentDriverInterface;
use App\Data\PaymentData;
use App\Data\SalesOrderData;
use App\Models\SalesOrder;

class OfflinePaymentDriver implements PaymentDriverInterface
{
    public readonly string $driver;

    public function __construct()
    {
        $this->driver = 'offline';
    }

    /** @return DataCollection<PaymentData> */
    public function getMethods() : DataCollection
    {
        return PaymentData::collect([
            PaymentData::from([
                'driver' => $this->driver,
                'method' => 'bsi-bank-transfer',
                'label' => 'Bank Transfer BSI',
                'payload' => [
                    'account_number' => '72727272',
                    'account_holder_name' => 'Muhammad Hafiz Sapta'
                ]
            ])
        ], DataCollection::class);
    }

    public function process(SalesOrderData $sales_order)
    {
       SalesOrder::where('trx_id', $sales_order->trx_id)
            ->update([
                'payment_payload' => [
                    'key' => 'value'
                ]
            ]); 
    }

    public function shouldShowPayNowButton(SalesOrderData $sales_order) : bool
    {
        return false;
    }

    public function getRedirectUrl(SalesOrderData $sales_order) : ?string
    {
        return null;
    }
}