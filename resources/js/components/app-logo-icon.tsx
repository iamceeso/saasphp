import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import type { ComponentPropsWithoutRef } from 'react';

type AppLogoIconProps = ComponentPropsWithoutRef<'img'>;

export default function AppLogoIcon({ className, alt, ...props }: AppLogoIconProps) {
    const { site } = usePage<SharedData>().props;
    const logo = site.logo?.trim() || '/logos/logo.png';
    const src = logo.startsWith('http://') || logo.startsWith('https://') || logo.startsWith('/')
        ? logo
        : `/${logo}`;

    return (
        <img
            src={src}
            alt={alt ?? site.name}
            className={className ?? 'h-full w-full object-contain'}
            onError={(event) => {
                event.currentTarget.src = '/logos/logo.png';
            }}
            {...props}
        />
    );
}
