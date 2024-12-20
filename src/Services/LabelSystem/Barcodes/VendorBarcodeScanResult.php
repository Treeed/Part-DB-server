<?php

namespace App\Services\LabelSystem\Barcodes;

class VendorBarcodeScanResult
{
    public function __construct(
        public readonly string  $vendor,
        public readonly ?string $manufacturer_part_number = null,
        public readonly ?string $vendor_part_number = null,
        public readonly ?string $date_code = null,
        public readonly ?string $quantity = null
    )
    {
    }
}