<?php

namespace App\Domain\Helpdesk\Enums;

enum CommentKind: string
{
    /** Visible to the requester. */
    case Reply = 'reply';

    /** Staff only. */
    case Note = 'note';

    /** Status, priority or assignment change (visible to the requester). */
    case Event = 'event';
}
