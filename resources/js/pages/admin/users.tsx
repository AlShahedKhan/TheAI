import { Head, router, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { number } from './helpers';
import type { AdminUser, Paginated } from './types';

type Props = {
    users: Paginated<AdminUser>;
};

export default function AdminUsers({ users }: Props) {
    const adjust = useForm({ credits: 0, reason: '' });

    return (
        <>
            <Head title="Users" />
            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-lg font-semibold">Users</h1>
                    <p className="text-sm text-muted-foreground">
                        Manage balances, abuse controls, and credit adjustments.
                    </p>
                </div>

                <div className="rounded-lg border bg-card">
                    <div className="divide-y">
                        {users.data.map((user) => (
                            <div
                                key={user.id}
                                className="grid gap-4 p-4 xl:grid-cols-[1fr_24rem_12rem]"
                            >
                                <div className="text-sm">
                                    <div className="font-medium">
                                        {user.name}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {user.email}
                                    </div>
                                    <div className="mt-2">
                                        {number(user.balance)} credits ·{' '}
                                        {number(user.total_transactions)} ledger
                                        rows
                                    </div>
                                    {user.is_suspended && (
                                        <div className="mt-1 text-destructive">
                                            Suspended
                                        </div>
                                    )}
                                </div>

                                <form
                                    className="grid gap-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        adjust.post(
                                            `/admin/users/${user.id}/adjust`,
                                            {
                                                preserveScroll: true,
                                                onSuccess: () => adjust.reset(),
                                            },
                                        );
                                    }}
                                >
                                    <Label>Manual credit adjustment</Label>
                                    <div className="grid grid-cols-[7rem_1fr] gap-2">
                                        <Input
                                            type="number"
                                            value={adjust.data.credits}
                                            onChange={(event) =>
                                                adjust.setData(
                                                    'credits',
                                                    Number(event.target.value),
                                                )
                                            }
                                        />
                                        <Input
                                            placeholder="Reason"
                                            value={adjust.data.reason}
                                            onChange={(event) =>
                                                adjust.setData(
                                                    'reason',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <InputError
                                        message={
                                            adjust.errors.credits ||
                                            adjust.errors.reason
                                        }
                                    />
                                    <Button
                                        variant="outline"
                                        disabled={adjust.processing}
                                    >
                                        Save adjustment
                                    </Button>
                                </form>

                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        router.patch(
                                            `/admin/users/${user.id}/suspend`,
                                            {
                                                is_suspended:
                                                    !user.is_suspended,
                                            },
                                            { preserveScroll: true },
                                        );
                                    }}
                                >
                                    <Button
                                        className="w-full"
                                        variant={
                                            user.is_suspended
                                                ? 'outline'
                                                : 'destructive'
                                        }
                                    >
                                        {user.is_suspended
                                            ? 'Restore'
                                            : 'Suspend'}
                                    </Button>
                                </form>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

AdminUsers.layout = () => ({
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Users', href: '/admin/users' },
    ],
});
