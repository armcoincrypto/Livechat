<?php

namespace App\Http\Controllers\Administrator\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SessionResource;
use App\Models\PermissionsGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Выводим все группы пользователей
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $roles = Role::orderBy('id')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'attributes' => [
                    'name' => $role->name,
                    'title' => $role->title,
                    'users' => $role->users->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'attributes' => [
                                'avatar' => \Str::upper(\Str::substr($user->name, 0, 1)),
                                'name' => $user->name,
                                'email' => $user->email,
                            ]
                        ];
                    })
                ]
            ];
        });
        return response()->json([
            'data' => $roles,
            'total' => count($roles),
        ]);
    }

    /**
     * Обработка и создание новой группы
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|unique:roles|max:100',
            'name' => 'required|unique:roles|max:10|regex:/^[a-z ]+$/u',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $role = Role::create([
            'name' => Str::lower(security_xss($request->name)),
            'title' => security_xss($request->title),
        ]);

        return response()->json([
            'status' => 0,
            'message' => $role->name .' успешно добавлен'
        ]);
    }

    /**
     * Форма редактирования группы
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        $role = Role::findOrFail($id);
        $permissions = PermissionsGroup::with('permissions')->get();

        return response()->json([
            'id' => $role->id,
            'attributes' => [
                'title' => $role->title,
                'permissions' => $permissions,
                'hasPermissions' => $role->getAllPermissions()->pluck('name', 'id')->toArray()
            ]
        ]);
    }

    /**
     * Обработка и обновление роли
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 1,
                'message' => $validator->messages()->first()
            ]);
        }

        $input = $request->except(['permissions']);
        $permissions = $request['permissions'];
        $role->fill($input)->save();

        foreach ($permissions as $key => $value) {
            $p = Permission::where('id', '=', $key)->firstOrFail();
            if($value == 0) {
                $role->revokePermissionTo($p);
            } else {
                $role->givePermissionTo($p);
            }
        }


        return response()->json([
            'status' => 0,
            'message' => 'Роль: '. $role->title . ' успешно обновлен',
            'data' => new SessionResource(auth()->user())
        ]);
    }

    /**
     * Удаление роли
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id, Request $request)
    {
        if($request->has('only') and $request->get('only') == 'user')
        {
            $user = User::find($id);

            // Проверяем сколько пользователей привязаны к ролям
            if($user->id == auth()->user()->id and $user->roles()->count() == 1)
            {
                return response()->json([
                    'status' => 1,
                    'message' => 'Вы не можете отвязать эту группу'
                ]);
            }


            $user->roles()->detach((int) $request->get('role_id'));
            return response()->json([
                'status' => 0,
                'message' => $user->name .' успешно отвязан от роли'
            ]);
        }

        $role = Role::findOrFail($id);
        $oldItem = $role;
        if($role->users()->count() > 0) {
            return response()->json([
                'status' => 1,
                'message' => 'Вы не можете удалить эту роль, к ней привязаны пользователи'
            ]);
        }

        $role->delete();

        return response()->json([
            'status' => 0,
            'message' => $oldItem->title.' успешно удален'
        ]);
    }
}
