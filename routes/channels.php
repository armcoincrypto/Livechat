<?php
/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;


// Срабатывает для всех администраторов
Broadcast::channel('admin.events', function ($user) {
    return Auth::check() && $user->can('allow_admin');
}, ['guards' => ['web']]);

Broadcast::channel('reverbAdminNotification', function ($user) {
    return Auth::check();
});


Broadcast::channel('order.public_id.chat.{order_id}', function ($user, int $order_id) {
    return Auth::check() && Task::where('public_id', '=', $order_id)->exists();
});

Broadcast::channel('order.id.chat.{id}', function ($user, $id) {
    return Auth::check() && $user->id === Task::find($id)->first()->id_user;
});

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('order.user.{id_user}', function ($user, $id_user) {
    return (int) $user->id === (int) $id_user;
}, ['guards' => ['web']]);

Broadcast::channel('order.chat.notification.{id_user}', function ($user, $id_user) {
    return (int) $user->id === (int) $id_user;
});

Broadcast::channel('order.chat.messages.{public_id}', function (User $user, $public_id) {
    return $user->id === Task::where('public_id', $public_id)->first()->id_user;
});
