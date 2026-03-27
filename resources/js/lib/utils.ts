import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function humanReadableDate(date: string | Date): string {
    const d = typeof date === 'string' ? new Date(date) : date;
    const now = new Date();
    const diffMs = now.getTime() - d.getTime();
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSecs < 60) {
        return 'Just Now';
    }

    if (diffMins < 60) {
        return `${diffMins} ${diffMins === 1 ? 'Minute' : 'Minutes'} Ago`;
    }

    if (diffHours < 24) {
        return `${diffHours} ${diffHours === 1 ? 'Hour' : 'Hours'} Ago`;
    }

    if (diffDays === 1) {
        return 'Yesterday';
    }

    if (diffDays < 30) {
        return `${diffDays} ${diffDays === 1 ? 'Day' : 'Days'} Ago`;
    }

    if (diffDays < 365) {
        return d.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
        });
    }

    return d.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
