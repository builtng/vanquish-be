<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('staff.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id || $user->isStaff() || $user->isCounsellor();
});

Broadcast::channel('messages.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id || $user->isStaff() || $user->isCounsellor();
});

Broadcast::channel('messages.staff_group', function ($user) {
    return $user->isStaff() || $user->isCounsellor();
});
