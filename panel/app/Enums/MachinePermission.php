<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Otomat bazli granuler izinler.
 *
 * `machine_user.permissions` JSON'unda anahtar olarak bu enum degerleri tutulur.
 * Izin verme/alma yalnizca super admin tarafindan yapilabilir (MachineUserPolicy).
 *
 * "Her sayfa super admin tarafinda kullaniciya acilip kapatilabilmeli" isteri
 * `page()` esleme fonksiyonu uzerinden karsilanir: bir sayfaya erisim, o sayfanin
 * birincil izninin verilmis olmasina baglidir.
 */
enum MachinePermission: string
{
    // Ozet ve Analiz
    case AnalyticsView = 'analytics.view';
    case AnalyticsExport = 'analytics.export';

    // Goz Kontrol
    case SlotsView = 'slots.view';
    case SlotsOpen = 'slots.open';
    case SlotsToggle = 'slots.toggle';
    case SlotsPriceEdit = 'slots.price.edit';

    // Kapak Kontrol
    case LidsOpen = 'lids.open';

    // Akilli Dolum
    case RestockManage = 'restock.manage';

    // Ayarlar ve Oneriler
    case SettingsManage = 'settings.manage';
    case NotificationsManage = 'notifications.manage';
    case RecommendationsView = 'recommendations.view';

    // Destek
    case TicketsCreate = 'tickets.create';

    public function label(): string
    {
        return match ($this) {
            self::AnalyticsView => 'Ozet ve analizi goruntule',
            self::AnalyticsExport => 'Analiz verisini disa aktar',
            self::SlotsView => 'Gozleri goruntule',
            self::SlotsOpen => 'Gozu uzaktan ac',
            self::SlotsToggle => 'Gozu satisa ac/kapat',
            self::SlotsPriceEdit => 'Fiyat duzenle',
            self::LidsOpen => 'Kapak kontrol (test/hizli dolum)',
            self::RestockManage => 'Akilli dolum yap',
            self::SettingsManage => 'Otomat ayarlarini yonet',
            self::NotificationsManage => 'Bildirim tercihlerini yonet',
            self::RecommendationsView => 'Akilli onerileri gor',
            self::TicketsCreate => 'Talep/geri bildirim olustur',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::AnalyticsView, self::AnalyticsExport => 'Ozet ve Analiz',
            self::SlotsView, self::SlotsOpen, self::SlotsToggle, self::SlotsPriceEdit => 'Goz Kontrol',
            self::LidsOpen => 'Kapak Kontrol',
            self::RestockManage => 'Akilli Dolum',
            self::SettingsManage, self::NotificationsManage, self::RecommendationsView => 'Ayarlar ve Oneriler',
            self::TicketsCreate => 'Destek',
        };
    }

    /**
     * Fiziksel etki doguran izinler: 2FA zorunlu + son 15 dakikada
     * dogrulama yoksa sifre yeniden sorulur (step-up auth).
     */
    public function requiresStepUp(): bool
    {
        return match ($this) {
            self::SlotsOpen, self::LidsOpen => true,
            default => false,
        };
    }

    /** Bu iznin actigi panel sayfasinin rota adi (yoksa null). */
    public function page(): ?string
    {
        return match ($this) {
            self::AnalyticsView => 'machines.analytics',
            self::SlotsView => 'machines.slots',
            self::LidsOpen => 'machines.lids',
            self::RestockManage => 'machines.restock',
            self::SettingsManage => 'machines.settings',
            default => null,
        };
    }

    /** @return array<string, list<self>> Gruplanmis izin listesi (izin matrisi ekrani icin). */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::cases() as $permission) {
            $grouped[$permission->group()][] = $permission;
        }

        return $grouped;
    }

    /**
     * Bir rolun varsayilan izin seti. Super admin bunu ekrandan degistirebilir;
     * burasi yalnizca "yeni kullanici ekle" akisindaki baslangic noktasidir.
     *
     * @return array<string, bool>
     */
    public static function defaultsForRole(MachineRole $role): array
    {
        $all = fn (bool $value): array => array_reduce(
            self::cases(),
            function (array $carry, self $permission) use ($value): array {
                $carry[$permission->value] = $value;

                return $carry;
            },
            []
        );

        return match ($role) {
            MachineRole::Owner => $all(true),
            MachineRole::Operator => [
                ...$all(false),
                self::AnalyticsView->value => true,
                self::SlotsView->value => true,
                self::SlotsOpen->value => true,
                self::SlotsToggle->value => true,
                self::SlotsPriceEdit->value => true,
                self::RestockManage->value => true,
                self::RecommendationsView->value => true,
                self::TicketsCreate->value => true,
            ],
            MachineRole::Viewer => [
                ...$all(false),
                self::AnalyticsView->value => true,
                self::SlotsView->value => true,
                self::TicketsCreate->value => true,
            ],
        };
    }
}
