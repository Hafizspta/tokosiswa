<?php
declare(strict_types=1);

namespace App\Drivers\Shipping;

use App\Data\CartData;
use App\Data\RegionData;
use App\Data\ShippingData;
use App\Data\ShippingServiceData;
use Spatie\LaravelData\DataCollection;
use App\Contract\ShippingDriverInterface;


class RajaOngkirShippingDriver implements ShippingDriverInterface
{
    public readonly string $driver;

    public function __construct()
    {
        $this->driver = 'rajaongkir';
    }

    /** @return DataCollection<ShippingServiceData> */
    public function getServices() : DataCollection
    {
        return ShippingServiceData::collect([
            [
                'driver' => $this->driver,
                'code' => 'jne-reguler',
                'courier' => 'jne', 
                'service' => 'REG'  
            ],
            [
                'driver' => $this->driver,
                'code' => 'pos-kilat',
                'courier' => 'pos',
                'service' => 'Paket Kilat Khusus'
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
        $weight = $cart->total_weight > 0 ? $cart->total_weight : 1000;

        try {
            if (!$origin->rajaongkir_id || !$destination->rajaongkir_id) {
                return null;
            }

            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withHeaders([
                    'key' => config('shipping.rajaongkir.api_key')
                ])
                ->asForm()
                ->post('https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost', [
                    'origin' => $origin->rajaongkir_id, 
                    'destination' => $destination->rajaongkir_id,
                    'weight' => $weight,
                    'courier' => strtolower($shipping_service->courier)
                ]);

            if ($response->failed()) {
                return null;
            }

            $results = $response->json('data');
            
            if (empty($results)) {
                return null;
            }

            $availableServices = $results;

            $targetService = $shipping_service->service;
            $acceptedServices = [$targetService];
            
            if ($targetService === 'REG') {
                $acceptedServices[] = 'CTC';
            }
            if ($targetService === 'Paket Kilat Khusus') {
                $acceptedServices[] = 'Pos Reguler';
            }

            $selectedCost = collect($availableServices)->filter(function ($item) use ($acceptedServices) {
                return in_array($item['service'], $acceptedServices);
            })->first();

            if (!$selectedCost) {
                return null; 
            }

            $est = str_ireplace('day', 'HARI', $selectedCost['etd']); 
            $price = $selectedCost['cost'];

            return new ShippingData(
                $this->driver,
                strtoupper($shipping_service->courier),
                $shipping_service->service,
                $est,
                $price,
                (string) $weight,
                $origin,
                $destination,
                null 
            );

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('RajaOngkir Error: ' . $e->getMessage());
            return null; 
        }
    }
}


