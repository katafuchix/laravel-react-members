import { Head, Link, usePage } from '@inertiajs/react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index as usersIndex } from '@/routes/admin/users';
import type { AdminDashboardStats } from '@/types';

type Props = {
    stats: AdminDashboardStats;
};

export default function AdminDashboard({ stats }: Props) {
    const { auth } = usePage().props;

    const items = [
        { title: 'Total users', value: stats.total },
        { title: 'Verified', value: stats.verified },
        { title: 'Unverified', value: stats.unverified },
        {
            title: 'Registered in last 7 days',
            value: stats.registeredLast7Days,
        },
    ];

    return (
        <>
            <Head title="Admin dashboard" />

            <div className="flex flex-col gap-6 py-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-medium">Admin dashboard</h1>
                    <p className="text-sm text-muted-foreground">
                        Logged in as {auth.admin?.name} ({auth.admin?.email})
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {items.map((item) => (
                        <Card key={item.title}>
                            <CardHeader>
                                <CardDescription>{item.title}</CardDescription>
                                <CardTitle className="text-3xl">
                                    {item.value}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Users</CardTitle>
                        <CardDescription>
                            View registered users.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Link
                            href={usersIndex()}
                            className="text-sm underline underline-offset-4"
                        >
                            Go to users list
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
