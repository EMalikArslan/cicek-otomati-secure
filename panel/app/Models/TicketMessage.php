<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property string $body
 * @property array<array-key, mixed>|null $attachments
 * @property bool $is_internal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ticket $ticket
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMessage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMessage whereAttachments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMessage whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMessage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMessage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMessage whereIsInternal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMessage whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMessage whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMessage whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['ticket_id', 'user_id', 'body', 'attachments', 'is_internal'])]
class TicketMessage extends Model
{
    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_internal' => 'boolean',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Ic notlar yalnizca super admine gorunur. */
    public function visibleTo(User $user): bool
    {
        return ! $this->is_internal || $user->isSuperAdmin();
    }
}
