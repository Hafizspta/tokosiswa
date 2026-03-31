<?php
declare(strict_types=0);

namespace App\Data;

use App\Models\Region;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

class RegionData extends Data
{
    #[Computed]
    public string $label;

    public function __construct(
        public string $code,
        public string $province,
        public string $city,
        public string $district,
        public string $sub_district,
        public ?string $postal_code, 
        public string $country = 'indonesia',
        public ?int $rajaongkir_id = null
    ) {
        $this->label = "$sub_district, $district, $city, $province, $postal_code";
    }

    public static function fromModel(Region $region) : self
    {
        $province = '';
        $city = '';
        $district = '';
        $sub_district = '';

        if ($region->type === 'village') {
            $province = $region->parent?->parent?->parent?->name ?? '';
            $city = $region->parent?->parent?->name ?? '';
            $district = $region->parent?->name ?? '';
            $sub_district = $region->name;
        } elseif ($region->type === 'district') {
            $province = $region->parent?->parent?->name ?? '';
            $city = $region->parent?->name ?? '';
            $district = $region->name;
            $sub_district = '-';
        } elseif ($region->type === 'regency') {
            $province = $region->parent?->name ?? '';
            $city = $region->name;
            $district = '-';
            $sub_district = '-';
        } else {
            $province = $region->name;
            $city = '-';
            $district = '-';
            $sub_district = '-';
        }

        return new self(
            code: $region->code,
            province: $province,
            city: $city,
            district: $district,
            sub_district: $sub_district,
            postal_code: $region->postal_code ?? '-',
            rajaongkir_id: $region->rajaongkir_id ?? null
        );
    }
}