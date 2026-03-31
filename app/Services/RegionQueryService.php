<?php
declare(strict_types=1);

namespace App\Services;

use App\Data\RegionData;
use App\Models\Region;
use Spatie\LaravelData\DataCollection;

class RegionQueryService{

    public function searchRegionByName(string $keyword, int $limit = 10): DataCollection
    {
        $regions = Region::select('villages.*')
            ->from('regions as villages')
            ->leftJoin('regions as districts', 'villages.parent_code', '=', 'districts.code')
            ->leftJoin('regions as regencies', 'districts.parent_code', '=', 'regencies.code')
            ->leftJoin('regions as provinces', 'regencies.parent_code', '=', 'provinces.code')
            ->where('villages.type', 'village')
            ->where(function ($query) use ($keyword) {
                $query->where('villages.name', 'LIKE', "%{$keyword}%")
                      ->orWhere('villages.postal_code', 'LIKE', "%{$keyword}%")
                      ->orWhere('districts.name', 'LIKE', "%{$keyword}%")
                      ->orWhere('regencies.name', 'LIKE', "%{$keyword}%")
                      ->orWhere('provinces.name', 'LIKE', "%{$keyword}%");
            })
            ->with(['parent.parent.parent'])
            ->limit($limit)
            ->get();

        return new DataCollection(RegionData::class, $regions);
    }

    public function searchRegionByCode(String $code) : RegionData
    {
       return RegionData::fromModel(
            Region::where('code', $code)->first()
       ); 
    }
}