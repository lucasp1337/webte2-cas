/**
 * Icon library — 16-viewbox, currentColor, stroke-based.
 * Each icon is a named functional component.
 */
import { type SVGProps } from 'react';

type IconBaseProps = SVGProps<SVGSVGElement> & {
    size?: number;
    strokeWidth?: number;
};

function IconBase({ size = 14, strokeWidth = 1.5, children, ...props }: IconBaseProps & { children: React.ReactNode }) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 16 16"
            fill="none"
            stroke="currentColor"
            strokeWidth={strokeWidth}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            {...props}
        >
            {children}
        </svg>
    );
}

export type IconProps = Omit<IconBaseProps, 'children'>;

export function SunIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <circle cx="8" cy="8" r="3" />
            <path d="M8 1v1.5M8 13.5V15M3.5 3.5l1 1M11.5 11.5l1 1M1 8h1.5M13.5 8H15M3.5 12.5l1-1M11.5 4.5l1-1" />
        </IconBase>
    );
}

export function MoonIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M13 9.5A6 6 0 0 1 6.5 3a4.5 4.5 0 0 0 0 9 6 6 0 0 0 6.5-2.5z" />
        </IconBase>
    );
}

export function ArrowIcon(props: IconProps) {
    return (
        <IconBase size={12} {...props}>
            <path d="M3 8h10M9 4l4 4-4 4" />
        </IconBase>
    );
}

export function PlayIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M4 3l9 5-9 5V3z" fill="currentColor" stroke="none" />
        </IconBase>
    );
}

export function PauseIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <rect x="4" y="3" width="3" height="10" fill="currentColor" stroke="none" />
            <rect x="9" y="3" width="3" height="10" fill="currentColor" stroke="none" />
        </IconBase>
    );
}

export function ResetIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M2 8a6 6 0 1 0 1.8-4.3" />
            <path d="M2 2v3.5h3.5" />
        </IconBase>
    );
}

export function ChevronLeftIcon(props: IconProps) {
    return (
        <IconBase size={12} {...props}>
            <path d="M10 4l-4 4 4 4" />
        </IconBase>
    );
}

export function ChevronRightIcon(props: IconProps) {
    return (
        <IconBase size={12} {...props}>
            <path d="M6 4l4 4-4 4" />
        </IconBase>
    );
}

export function ChevronDownIcon(props: IconProps) {
    return (
        <IconBase size={12} {...props}>
            <path d="M4 6l4 4 4-4" />
        </IconBase>
    );
}

export function SearchIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <circle cx="7" cy="7" r="4" />
            <path d="M10 10l3 3" />
        </IconBase>
    );
}

export function DownloadIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M8 2v8M4 7l4 4 4-4" />
            <path d="M3 13h10" />
        </IconBase>
    );
}

export function ExternalIcon(props: IconProps) {
    return (
        <IconBase size={12} {...props}>
            <path d="M6 4H4v8h8v-2" />
            <path d="M8 4h4v4" />
            <path d="M7 9l5-5" />
        </IconBase>
    );
}

export function WarnIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M8 2l6 11H2L8 2z" />
            <path d="M8 6v3M8 11.5v.5" />
        </IconBase>
    );
}

export function CheckIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M3 8l3 3 7-7" />
        </IconBase>
    );
}

export function RefreshIcon(props: IconProps) {
    return (
        <IconBase size={12} {...props}>
            <path d="M2 7a6 6 0 0 1 10.5-3" />
            <path d="M12.5 1.5v3h-3" />
            <path d="M14 9a6 6 0 0 1-10.5 3" />
            <path d="M3.5 14.5v-3h3" />
        </IconBase>
    );
}

export function ChevronUpIcon(props: IconProps) {
    return (
        <IconBase size={11} {...props}>
            <path d="M3 10l5-5 5 5" />
        </IconBase>
    );
}

export function ChevronDownSmallIcon(props: IconProps) {
    return (
        <IconBase size={11} {...props}>
            <path d="M3 6l5 5 5-5" />
        </IconBase>
    );
}

export function MenuIcon(props: IconProps) {
    return (
        <IconBase {...props}>
            <path d="M2 4h12M2 8h12M2 12h12" />
        </IconBase>
    );
}

export function BackIcon(props: IconProps) {
    return (
        <IconBase size={12} {...props}>
            <path d="M13 8H3M7 4L3 8l4 4" />
        </IconBase>
    );
}

/**
 * Wedge brand mark — the "L-shaped notch" icon used next to the WEBTE2 wordmark.
 * clip-path polygon(0 0, 100% 0, 100% 60%, 60% 60%, 60% 100%, 0 100%)
 */
export function BrandWedgeIcon({ size = 14, className }: { size?: number; className?: string }) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 14 14"
            aria-hidden="true"
            className={className}
            style={{
                background: 'var(--brand-gradient)',
                clipPath: 'polygon(0 0, 100% 0, 100% 60%, 60% 60%, 60% 100%, 0 100%)',
                display: 'inline-block',
            }}
        />
    );
}
