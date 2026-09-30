import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';
import icsaLogoWhite from '../../images/brand/logo/icsa-2026-white.webp';
import icsaLogo from '../../images/brand/logo/icsa-2026.webp';

/**
 * Official ICSA 2026 lockup; collapses to the circle mark in an icon-only sidebar.
 */
export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <span className="flex group-data-[collapsible=icon]:hidden">
                <img
                    src={icsaLogo}
                    alt={name}
                    className="h-10 w-auto dark:hidden"
                />
                <img
                    src={icsaLogoWhite}
                    alt={name}
                    className="hidden h-10 w-auto dark:block"
                />
            </span>
            <AppLogoIcon
                alt={name}
                className="hidden size-8 shrink-0 group-data-[collapsible=icon]:block"
            />
        </>
    );
}
