import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { AdminUserRow, Paginated } from '@/types';

type Props = {
    users: Paginated<AdminUserRow>;
};

const dateFormatter = new Intl.DateTimeFormat('ja-JP', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatDate(value: string | null): string {
    return value ? dateFormatter.format(new Date(value)) : '-';
}

export default function AdminUsersIndex({ users }: Props) {
    return (
        <>
            <Head title="Users" />

            <div className="flex flex-col gap-4 py-4">
                <div className="flex items-end justify-between">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-xl font-medium">Users</h1>
                        <p className="text-sm text-muted-foreground">
                            {users.total === 0
                                ? 'No users yet.'
                                : `${users.from}-${users.to} of ${users.total}`}
                        </p>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">ID</th>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Email</th>
                                <th className="px-4 py-3 font-medium">
                                    Email verified
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Registered
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr
                                    key={user.id}
                                    className="border-b last:border-b-0"
                                    data-test="admin-user-row"
                                >
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {user.id}
                                    </td>
                                    <td className="px-4 py-3">{user.name}</td>
                                    <td className="px-4 py-3">{user.email}</td>
                                    <td className="px-4 py-3">
                                        {user.email_verified_at ? (
                                            <Badge variant="secondary">
                                                Verified
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                Unverified
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {formatDate(user.created_at)}
                                    </td>
                                </tr>
                            ))}
                            {users.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No users found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-muted-foreground">
                            Page {users.current_page} of {users.last_page}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!users.prev_page_url}
                                asChild={!!users.prev_page_url}
                            >
                                {users.prev_page_url ? (
                                    <Link href={users.prev_page_url}>
                                        Previous
                                    </Link>
                                ) : (
                                    <span>Previous</span>
                                )}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!users.next_page_url}
                                asChild={!!users.next_page_url}
                            >
                                {users.next_page_url ? (
                                    <Link href={users.next_page_url}>Next</Link>
                                ) : (
                                    <span>Next</span>
                                )}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
