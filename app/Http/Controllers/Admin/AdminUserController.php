<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 管理画面の会員管理。今は一覧のみ。
 */
class AdminUserController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): Response
    {
        $users = User::query()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->through(fn (User $user) => [
                // パスワードや 2要素認証の情報は画面に渡さない。
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
        ]);
    }
}
