<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->createSuperAdmin();
    }

    /**
     * Super admin hesabi.
     *
     * Parola config/etm.php uzerinden .env'den okunur; tanimli degilse
     * rastgele uretilip bir kez ekrana yazilir. Kodda sabit parola bulunmaz.
     */
    private function createSuperAdmin(): void
    {
        $email = (string) config('etm.super_admin.email');
        $password = (string) config('etm.super_admin.password', '');
        $generated = false;

        if ($password === '') {
            $password = bin2hex(random_bytes(12));
            $generated = true;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->command->line("Super admin zaten mevcut: {$email}");

            return;
        }

        User::query()->create([
            'name' => (string) config('etm.super_admin.name'),
            'email' => $email,
            'password' => Hash::make($password),
            'is_super_admin' => true,
            'status' => 'active',
            'locale' => 'tr',
            'email_verified_at' => now(),
        ]);

        $this->command->info("Super admin olusturuldu: {$email}");

        if ($generated) {
            $this->command->warn("Uretilen parola (bir kez gosterilir): {$password}");
            $this->command->warn('Ilk giristen sonra degistirin ve 2FA kurun.');
        }
    }
}
