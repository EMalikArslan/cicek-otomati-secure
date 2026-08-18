<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ticket_no
 * @property int $user_id
 * @property int|null $machine_id
 * @property string $subject
 * @property string $body
 * @property TicketPriority $priority
 * @property TicketStatus $status
 * @property int|null $assigned_to_id
 * @property Carbon|null $first_response_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $assignedTo
 * @property-read Machine|null $machine
 * @property-read Collection<int, TicketMessage> $messages
 * @property-read int|null $messages_count
 * @property-read User|null $user
 *
 * @method static Builder<static>|Ticket newModelQuery()
 * @method static Builder<static>|Ticket newQuery()
 * @method static Builder<static>|Ticket open()
 * @method static Builder<static>|Ticket query()
 * @method static Builder<static>|Ticket queueOrder()
 * @method static Builder<static>|Ticket whereAssignedToId($value)
 * @method static Builder<static>|Ticket whereBody($value)
 * @method static Builder<static>|Ticket whereClosedAt($value)
 * @method static Builder<static>|Ticket whereCreatedAt($value)
 * @method static Builder<static>|Ticket whereFirstResponseAt($value)
 * @method static Builder<static>|Ticket whereId($value)
 * @method static Builder<static>|Ticket whereMachineId($value)
 * @method static Builder<static>|Ticket wherePriority($value)
 * @method static Builder<static>|Ticket whereResolvedAt($value)
 * @method static Builder<static>|Ticket whereStatus($value)
 * @method static Builder<static>|Ticket whereSubject($value)
 * @method static Builder<static>|Ticket whereTicketNo($value)
 * @method static Builder<static>|Ticket whereUpdatedAt($value)
 * @method static Builder<static>|Ticket whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'user_id', 'machine_id', 'subject', 'body', 'priority', 'status',
    'assigned_to_id', 'first_response_at', 'resolved_at', 'closed_at',
])]
class Ticket extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            $ticket->ticket_no ??= static::nextTicketNumber();
        });
    }

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ticket_no';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /** @return HasMany<TicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->oldest();
    }

    /** @param  Builder<static>  $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            TicketStatus::Resolved->value,
            TicketStatus::Closed->value,
        ]);
    }

    /**
     * Super admin kuyrugu: once aciliyet, sonra bekleme suresi.
     *
     * @param  Builder<static>  $query
     */
    public function scopeQueueOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->oldest();
    }

    /** SLA asildi mi? (hedeflenen ilk yanit suresi gecti ve hala yanit yok) */
    public function breachedSla(): bool
    {
        if ($this->first_response_at !== null) {
            return false;
        }

        return $this->created_at->addHours($this->priority->firstResponseHours())->isPast();
    }

    public function slaDeadline(): Carbon
    {
        return $this->created_at->copy()->addHours($this->priority->firstResponseHours());
    }

    protected static function nextTicketNumber(): string
    {
        $year = now()->year;
        $count = static::withoutGlobalScopes()
            ->where('ticket_no', 'like', "ETM-{$year}-%")
            ->count();

        return sprintf('ETM-%d-%04d', $year, $count + 1);
    }
}
