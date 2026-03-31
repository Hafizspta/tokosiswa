<?php

namespace App\Console\Commands;

use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncRajaOngkirRegions extends Command
{
    // Nama perintah yang akan Anda ketik di terminal
    protected $signature = 'rajaongkir:sync-regions';

    protected $description = 'Sinkronisasi ID RajaOngkir/Komerce dengan sistem antrean aman (Anti-Banned)';

    public function handle()
    {
        $this->info('Memulai persiapan data wilayah...');
        $apiKey = config('shipping.rajaongkir.api_key');

        // PERBAIKAN 1: Hanya cari wilayah (Desa/Kecamatan) yang ID-nya MASIH KOSONG
        $regions = Region::whereIn('type', ['district', 'village'])
            ->whereNull('rajaongkir_id')
            ->where(function ($query) {
                // Fokuskan sinkronisasi ke wilayah Aceh (11) dan Sumut (12) dulu untuk efisiensi waktu
                $query->where('code', 'LIKE', '11%')
                      ->orWhere('code', 'LIKE', '12%');
            })
            ->get();

        $total = $regions->count();

        if ($total === 0) {
            $this->info('Semua data wilayah Aceh dan Sumut sudah memiliki ID RajaOngkir. Sistem aman!');
            return Command::SUCCESS;
        }

        $this->warn("Ditemukan {$total} wilayah yang belum memiliki ID. Memulai sinkronisasi lambat (1 detik/request)...");

        // Membuat progress bar agar terlihat profesional di terminal
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($regions as $region) {
            try {
                $response = Http::timeout(15)
                    ->withHeaders(['key' => $apiKey])
                    ->get('https://rajaongkir.komerce.id/api/v1/destination/domestic-destination', [
                        'search' => $region->name
                    ]);

                // PERBAIKAN 2: Jika kuota habis, HENTIKAN paksa agar tidak kena blokir permanen
                if ($response->status() === 429) {
                    $this->newLine(2);
                    $this->error('⚠️ BAHAYA: Limit API Harian Habis (Error 429)! Proses dihentikan otomatis untuk mencegah banned.');
                    $this->line('Silakan lanjutkan kembali besok saat kuota di-reset.');
                    break; 
                }

                if ($response->successful()) {
                    $data = $response->json('data');
                    
                    if (!empty($data)) {
                        // Simpan ID yang paling pertama (paling relevan)
                        $region->update(['rajaongkir_id' => $data[0]['id']]);
                    }
                }

                // PERBAIKAN 3: Jeda 1 detik agar sistem bernapas dan tidak dianggap serangan DDoS/Spam
                sleep(1);

            } catch (\Exception $e) {
                Log::error("Gagal sinkronisasi wilayah {$region->name}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Sesi sinkronisasi selesai!');
        
        return Command::SUCCESS;
    }
}