<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\SaleStatus;
use App\Models\Machine;
use App\Models\Sale;
use App\Models\User;
use App\Services\Access\MachineAccessManager;
use Illuminate\Support\Carbon;

/**
 * Test yardimcilari.
 *
 * Pest'in `$this->foo` dinamik ozellikleri statik analizde tiplenemedigi icin
 * paylasilan kurulum burada acik fonksiyonlar olarak tanimlanir.
 */
function superAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

function accessManager(): MachineAccessManager
{
    return app(MachineAccessManager::class);
}

function makeSale(
    Machine $machine,
    string $soldAt,
    int $slotNo,
    int $priceMinor,
    string $product,
    SaleStatus $status = SaleStatus::Success,
): Sale {
    return Sale::query()->create([
        'machine_id' => $machine->id,
        'slot_no' => $slotNo,
        'product_name_snapshot' => $product,
        'product_key' => Sale::normalizeProductKey($product),
        'price_minor' => $priceMinor,
        'status' => $status,
        'sold_at' => Carbon::parse($soldAt),
        'local_id' => null,
    ]);
}
