<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 管理者のログイン・ログアウト（ガード: admin）。
 */
class AdminSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('admin/login');
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->to($this->redirectUrlAfterLogin($request));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        // セッション全体を破棄すると、同じブラウザで会員としてログイン中の状態も消えるため、
        // 管理者のログアウトではトークンの再生成だけを行う。
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * ログイン後の遷移先。
     *
     * 「ログイン後に戻る URL」（url.intended）は会員側と共有のセッションに入るため、
     * 会員側の画面を開いたときの URL が残っていることがある。
     * そのまま使うと管理者のログイン後に会員側の画面へ飛んでしまうので、
     * /admin 配下の URL のときだけ使い、それ以外は管理画面のダッシュボードにする。
     */
    private function redirectUrlAfterLogin(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && $this->isAdminUrl($intended)) {
            return $intended;
        }

        return route('admin.dashboard');
    }

    private function isAdminUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        return $path === '/admin' || str_starts_with($path, '/admin/');
    }
}
