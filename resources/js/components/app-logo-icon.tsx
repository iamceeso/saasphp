import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

export default function AppLogoIcon() {
    const { site } = usePage<SharedData>().props;
    const logo = site.logo?.trim() || '/logos/logo.png';
    const src = logo.startsWith('http://') || logo.startsWith('https://') || logo.startsWith('/')
        ? logo
        : `/${logo}`;

    return (
        <img
            src={src}
            alt={site.name}
            className="h-full w-full object-contain"
            onError={(event) => {
                event.currentTarget.src = '/logos/logo.png';
            }}
        />
    );
}
