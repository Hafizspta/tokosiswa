<?php
declare(strict_types=1);

namespace App\Drivers\Shipping;

use App\Data\CartData;
use App\Data\RegionData;
use App\Data\ShippingData;
use App\Data\ShippingServiceData;
use Spatie\LaravelData\DataCollection;
use App\Contract\ShippingDriverInterface;

class OfflineShippingDriver implements ShippingDriverInterface
{
    public readonly string $driver;

    public function __construct()
    {
        $this->driver = 'offline';
    }

    /** @return DataCollection<ShippingServiceData> */
    public function getServices() : DataCollection
    {
        return ShippingServiceData::collect([
            [
                'driver' => $this->driver,
                'code' => 'offline-flat-01',
                'courier' => 'Internal Kurir',
                'service' => 'Instant'
            ],
            [
                'driver' => $this->driver,
                'code' => 'offline-flat-02',
                'courier' => 'Internal Kurir',
                'service' => 'Instant'
            ]
        ], DataCollection::class);
    }

    public function getRate(
        RegionData $origin,
        RegionData $destination,
        CartData $cart,
        ShippingServiceData $shipping_service
    ) : ?ShippingData
    {
        $data = null;

        switch($shipping_service->code){
            case 'offline-flat-01':
                $data = ShippingData::from([
                    'driver' => $this->driver,
                    'courier' => $shipping_service->courier,
                    'service' => $shipping_service->service,
                    'estimated_delivery' => "20-30 menit",
                    'cost' => 10000,
                    'weight' => $cart->total_weight,
                    'origin' => $origin,
                    'destination' => $destination
                ]);
                break;
                case 'offline-flat-02':
                $data = ShippingData::from([
                    'driver' => $this->driver,
                    'courier' => $shipping_service->courier,
                    'service' => $shipping_service->service,
                    'estimated_delivery' => "10-15 menit",
                    'cost' => 15000,
                    'weight' => $cart->total_weight,
                    'origin' => $origin,
                    'destination' => $destination
                ]);
                break;
        }
        return $data;
    }
}