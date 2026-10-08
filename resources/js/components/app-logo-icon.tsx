import type { SVGAttributes } from 'react';

// Two forward chevrons: freight in motion.
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 44 40" xmlns="http://www.w3.org/2000/svg">
            <path d="M5 7h8l12 13-12 13H5l12-13z" />
            <path d="M19 7h8l12 13-12 13h-8l12-13z" />
        </svg>
    );
}
