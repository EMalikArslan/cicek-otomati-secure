<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Cihaz kimlik bilgisi.
 *
 * - `secret_hash`   : MQTT parolasi. Broker'in HTTP auth hook'u bunu dogrular;
 *                     duz metin hicbir yerde saklanmaz.
 * - `command_secret`: Komut imzalama (HMAC-SHA256) anahtari. Sunucunun imza
 *                     uretebilmesi icin geri donusturulebilir olmali, bu yuzden
 *                     hash degil `encrypted` cast ile saklanir.
 *
 * @property int $id
 * @property int $machine_id
 * @property string $mqtt_username
 * @property string $secret_hash
 * @property string $command_secret
 * @property string|null $cert_fingerprint
 * @property Carbon|null $last_auth_at
 * @property Carbon|null $rotated_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential whereCertFingerprint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential whereCommandSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential whereLastAuthAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential whereMachineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential whereMqttUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential whereRotatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential whereSecretHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeviceCredential whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'machine_id', 'mqtt_username', 'secret_hash', 'command_secret',
    'cert_fingerprint', 'last_auth_at', 'rotated_at', 'revoked_at',
])]
#[Hidden(['secret_hash', 'command_secret'])]
class DeviceCredential extends Model
{
    protected function casts(): array
    {
        return [
            'command_secret' => 'encrypted',
            'last_auth_at' => 'datetime',
            'rotated_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function verifySecret(string $plain): bool
    {
        return ! $this->isRevoked() && Hash::check($plain, $this->secret_hash);
    }
}
