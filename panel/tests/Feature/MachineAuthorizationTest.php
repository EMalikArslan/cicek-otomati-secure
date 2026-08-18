<?php

declare(strict_types=1);

use App\Enums\MachinePermission;
use App\Enums\MachineRole;
use App\Models\Machine;
use App\Models\MachineUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

use function Tests\accessManager;
use function Tests\superAdmin;

// ---- Erisim izolasyonu (IDOR) -------------------------------------------

it('yetkisi olmayan kullanici otomata erisemez', function (): void {
    $machine = Machine::factory()->create();
    $outsider = User::factory()->create();

    expect($outsider->hasAccessToMachine($machine))->toBeFalse()
        ->and($outsider->can('view', $machine))->toBeFalse();
});

it('kullanici yalnizca yetkili oldugu otomatlari listeler', function (): void {
    $mine = Machine::factory()->create();
    $theirs = Machine::factory()->create();
    $user = User::factory()->create();

    accessManager()->grant(superAdmin(), $mine, $user, MachineRole::Operator);

    $visible = Machine::query()->visibleTo($user->fresh())->pluck('id');

    expect($visible)->toContain($mine->id)
        ->and($visible)->not->toContain($theirs->id);
});

it('super admin tum otomatlari gorur', function (): void {
    Machine::factory()->count(3)->create();

    expect(Machine::query()->visibleTo(superAdmin())->count())->toBe(3);
});

// ---- Sayfa bazli izinler -------------------------------------------------

it('izleyici rolu gozu uzaktan acamaz ama analizi gorebilir', function (): void {
    $machine = Machine::factory()->create();
    $viewer = User::factory()->create();

    accessManager()->grant(superAdmin(), $machine, $viewer, MachineRole::Viewer);
    $viewer = $viewer->fresh();

    expect($viewer->can('viewAnalytics', $machine))->toBeTrue()
        ->and($viewer->can('openSlot', $machine))->toBeFalse()
        ->and($viewer->can('openLid', $machine))->toBeFalse()
        ->and($viewer->can('restock', $machine))->toBeFalse();
});

it('operator rolu kapak kontrol sayfasina varsayilan olarak erisemez', function (): void {
    $machine = Machine::factory()->create();
    $operator = User::factory()->create();

    accessManager()->grant(superAdmin(), $machine, $operator, MachineRole::Operator);
    $operator = $operator->fresh();

    expect($operator->can('openSlot', $machine))->toBeTrue()
        ->and($operator->can('viewLidControl', $machine))->toBeFalse();
});

it('super admin tek tek izin acip kapatabilir', function (): void {
    $admin = superAdmin();
    $machine = Machine::factory()->create();
    $user = User::factory()->create();

    $access = accessManager()->grant($admin, $machine, $user, MachineRole::Viewer);
    expect($user->fresh()->can('openLid', $machine))->toBeFalse();

    accessManager()->updatePermissions($admin, $access, [
        MachinePermission::LidsOpen->value => true,
    ]);

    expect($user->fresh()->can('openLid', $machine))->toBeTrue();
});

// ---- Yetki verme kisiti --------------------------------------------------

it('super admin olmayan kullanici yetki veremez', function (): void {
    $machine = Machine::factory()->create();
    $owner = User::factory()->create();
    $target = User::factory()->create();

    accessManager()->grant(superAdmin(), $machine, $owner, MachineRole::Owner);
    accessManager()->grant($owner->fresh(), $machine, $target, MachineRole::Viewer);
})->throws(AuthorizationException::class);

it('otomat sahibi bile baskasina yetki veremez', function (): void {
    $machine = Machine::factory()->create();
    $owner = User::factory()->create();

    accessManager()->grant(superAdmin(), $machine, $owner, MachineRole::Owner);
    $owner = $owner->fresh();

    // Sahip tum sayfa izinlerine sahip, ama yetki dagitamaz.
    expect($owner->can('openSlot', $machine))->toBeTrue()
        ->and($owner->can('create', MachineUser::class))->toBeFalse();
});

it('yetki kaldirildiginda erisim biter', function (): void {
    $admin = superAdmin();
    $machine = Machine::factory()->create();
    $user = User::factory()->create();

    $access = accessManager()->grant($admin, $machine, $user, MachineRole::Operator);
    expect($user->fresh()->hasAccessToMachine($machine))->toBeTrue();

    accessManager()->revoke($admin, $access);

    expect($user->fresh()->hasAccessToMachine($machine))->toBeFalse();
});

// ---- Hesap durumu --------------------------------------------------------

it('askiya alinmis kullanici yetkisi dursa bile erisemez', function (): void {
    $machine = Machine::factory()->create();
    $user = User::factory()->create();

    accessManager()->grant(superAdmin(), $machine, $user, MachineRole::Owner);
    $user->update(['status' => 'suspended']);

    expect($user->fresh()->canOnMachine($machine, MachinePermission::SlotsOpen))->toBeFalse();
});

// ---- Izin semasi ---------------------------------------------------------

it('bilinmeyen izin anahtarlari kaydedilmez', function (): void {
    $machine = Machine::factory()->create();
    $user = User::factory()->create();

    $access = accessManager()->grant(superAdmin(), $machine, $user, MachineRole::Viewer, [
        'slots.view' => true,
        'gizli.arka.kapi' => true,
    ]);

    expect($access->permissions)->not->toHaveKey('gizli.arka.kapi')
        ->and($access->permissions['slots.view'])->toBeTrue();
});

it('fiziksel etki doguran izinler step-up dogrulama ister', function (): void {
    expect(MachinePermission::SlotsOpen->requiresStepUp())->toBeTrue()
        ->and(MachinePermission::LidsOpen->requiresStepUp())->toBeTrue()
        ->and(MachinePermission::AnalyticsView->requiresStepUp())->toBeFalse();
});
