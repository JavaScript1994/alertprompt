import { LogOut, ShieldCheck, UserRound } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuthUser, useLogout } from '@/hooks/useAuth';
import UserAvatar from '@/features/profile/UserAvatar';

export default function ProfileMenu() {
    const { data: user } = useAuthUser();
    const logout = useLogout();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    className="rounded-full outline-none focus-visible:ring-[3px] focus-visible:ring-ring"
                    aria-label="Menú de perfil"
                >
                    <UserAvatar name={user?.name} photoUrl={user?.photo_url} />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64 p-0">
                <DropdownMenuLabel className="flex items-center gap-3 px-4 py-4 font-normal">
                    <UserAvatar name={user?.name} photoUrl={user?.photo_url} className="size-11" fallbackClassName="text-sm" />
                    <div className="min-w-0">
                        <p className="truncate font-semibold text-foreground">{user?.name}</p>
                        <p className="truncate text-xs text-muted-foreground">{user?.email}</p>
                        {user && user.roles.length > 0 && (
                            <p className="text-xs text-muted-foreground">
                                {user.roles.map((role) => role.label).join(' · ')}
                            </p>
                        )}
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator className="mx-0 my-0" />
                <div className="p-2">
                    <DropdownMenuItem asChild>
                        <Link to="/settings/profile">
                            <UserRound />
                            Mi perfil
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link to="/settings/security">
                            <ShieldCheck />
                            Seguridad
                            {user && !user.mfa.enabled && <span className="ml-auto size-2 rounded-full bg-warning" aria-label="Pendiente" />}
                        </Link>
                    </DropdownMenuItem>
                </div>
                <DropdownMenuSeparator className="mx-0 my-0" />
                <div className="p-4">
                    <Button
                        variant="outlineprimary"
                        className="w-full"
                        onClick={() => logout.mutate()}
                        loading={logout.isPending}
                    >
                        {!logout.isPending && <LogOut />}
                        Cerrar sesión
                    </Button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
