<?php
declare(strict_types=1);

namespace App\Drivers\Shipping;

use App\Data\CartData;
use App\Data\RegionData;
use App\Data\ShippingData;
use App\Data\ShippingServiceData;
use Spatie\LaravelData\DataCollection;
use App\Contract\ShippingDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException; // Tambahan untuk error koneksi
use Illuminate\Support\Facades\Log; // Tambahan untuk pencatatan log

class ApiKurirShippingDriver implements ShippingDriverInterface
{
    public readonly string $driver;

    public function __construct()
    {
        $this->driver = 'apikurir';
    }

    /** @return DataCollection<ShippingServiceData> */
    public function getServices() : DataCollection
    {
        return ShippingServiceData::collect([
            [
                'driver' => $this->driver,
                'code' => 'grab-sameday',
                'courier' => 'Grab',
                'service' => 'Same Day'
            ],
            [
                'driver' => $this->driver,
                'code' => 'jne-reguler',
                'courier' => 'JNE',
                'service' => 'Reguler'
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
        try {
            // PERBAIKAN: Tambahkan timeout(10) agar maksimal menunggu 10 detik
            $response = Http::timeout(10)->withBasicAuth(
                config('shipping.api_kurir.username'),
                config('shipping.api_kurir.password')
            )->post('https://sandbox.apikurir.id/shipments/v1/open-api/rates', [
                'isUseInsurance' => true,
                'isPickup' => true,
                'weight' => $cart->total_weight,
                'packagePrice' => $cart->total,
                'origin' => [
                    'postalCode' => $origin->postal_code
                ],
                'destination' => [
                    'postalCode' => $destination->postal_code
                ],
                'logistics' => [$shipping_service->courier],
                'services' => [$shipping_service->service]
            ]);

            // Opsional: Cek jika API merespons dengan error (misal 401 Unauthorized atau 500)
            if ($response->failed()) {
                Log::warning("API Kurir Error: " . $response->body());
                return null;
            }

            $data = $response->collect('data')->flatten(1)->values()->first();
            
            if (empty($data)){
                return null;
            }

            // Tambahkan spasi pada durasi agar lebih enak dibaca (misal "1-2 HARI")
            $est = data_get($data, 'minDuration') . '-' . data_get($data, 'maxDuration') . ' ' . data_get($data, 'durationType');
            
            return new ShippingData(
                $this->driver,
                $shipping_service->courier,
                $shipping_service->service,
                $est,
                data_get($data, 'price'),
                data_get($data, 'weight'),
                $origin,
                $destination,
                data_get($data, 'logoUrl')
            );

        } catch (ConnectionException $e) {
            // PERBAIKAN: Tangkap error jika API timeout atau tidak bisa diakses
            Log::error('Koneksi ke API Kurir gagal/timeout: ' . $e->getMessage());
            
            // Kembalikan null agar website tidak error dan user tetap bisa checkout dengan kurir lain
            return null; 
        }
    }
}