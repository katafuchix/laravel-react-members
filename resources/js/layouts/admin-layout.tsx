import { Link, router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, logout } from '@/routes/admin';
import { index as usersIndex } from '@/routes/admin/users';

const navItems = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Users', href: usersIndex() },
];

/**
 * 管理画面用の簡易レイアウト。
 * 会員用のサイドバーは auth.user（会員）を前提にしているため、管理画面では使わない。
 */
export default function AdminLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { auth } = usePage().props;
    const { url } = usePage();

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="border-b">
                <div className="mx-auto flex h-14 w-full max-w-6xl items-center gap-6 px-4">
                    <Link
                        href={dashboard()}
                        className="flex items-center gap-2 font-medium"
                    >
                        <AppLogoIcon className="size-6 fill-current text-[var(--foreground)] dark:text-white" />
                        <span>Admin</span>
                    </Link>

                    <nav className="flex items-center gap-1 text-sm">
                        {navItems.map((item) => {
                            const active = url.startsWith(item.href.url);

                            return (
                                <Link
                                    key={item.title}
                                    href={item.href}
                                    className={cn(
                                        'rounded-md px-3 py-1.5 transition-colors hover:bg-accent',
                                        active
                                            ? 'bg-accent font-medium'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {item.title}
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="ml-auto flex items-center gap-3 text-sm">
                        <span className="text-muted-foreground">
                            {auth.admin?.name}
                        </span>
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={logout()}
                                as="button"
                                onClick={() => router.flushAll()}
                                data-test="admin-logout-button"
                            >
                                <LogOut />
                                Log out
                            </Link>
                        </Button>
                    </div>
                </div>
            </header>

            <main className="mx-auto w-full max-w-6xl flex-1 p-4">
                {children}
            </main>
        </div>
    );
}
