<?php

namespace App\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class SalesPaymentData extends Data
{
    public function __construct(
        public string $driver,
        public string $method,
        public string $label,
        public array $payload = [],
        public Carbon|null $paid_at
    ) {}
}
