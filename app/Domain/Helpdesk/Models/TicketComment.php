<?php

namespace App\Domain\Helpdesk\Models;

use App\Domain\Helpdesk\Enums\CommentKind;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable(['ticket_id', 'user_id', 'kind', 'body'])]
class TicketComment extends Model
{
    protected function casts(): array
    {
        return ['kind' => CommentKind::class];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Files uploaded with this comment (stored on the ticket).
     *
     * @return Collection<int, Media>
     */
    public function attachments(): Collection
    {
        return $this->ticket->getMedia('attachments')
            ->filter(fn (Media $m) => $m->getCustomProperty('comment_id') === $this->getKey())
            ->values();
    }
}
