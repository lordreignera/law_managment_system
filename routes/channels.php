<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversations.{conversation}', function (User $user, Conversation $conversation) {
    return $conversation->participants()
        ->where('user_id', $user->id)
        ->exists();
});
