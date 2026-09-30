import { cn } from '@/lib/utils';

export type KeyVisualComposition = 'corners' | 'stage';

export type KeyVisualScrim = 'left-bottom' | 'center' | 'none';

type RingGroup = {
    cx: number;
    cy: number;
    radii: number[];
};

type Layout = {
    width: number;
    height: number;
    groups: RingGroup[];
};

type CompositionConfig = {
    backgroundClassName: string;
    ringColor: string;
    fillOpacity: number;
    strokeOpacity: number;
    landscape: Layout;
    portrait: Layout;
};

const compositions: Record<KeyVisualComposition, CompositionConfig> = {
    corners: {
        backgroundClassName: 'bg-kv-corners',
        ringColor: '#ffffff',
        fillOpacity: 0.07,
        strokeOpacity: 0.22,
        landscape: {
            width: 1600,
            height: 900,
            groups: [
                { cx: 0, cy: 900, radii: [260, 420, 580, 740] },
                { cx: 1600, cy: 0, radii: [220, 360, 500] },
            ],
        },
        portrait: {
            width: 900,
            height: 1600,
            groups: [
                { cx: 0, cy: 1600, radii: [300, 480, 660, 840] },
                { cx: 900, cy: 0, radii: [240, 400, 560] },
            ],
        },
    },
    stage: {
        backgroundClassName: 'bg-kv-stage',
        ringColor: '#ffffff',
        fillOpacity: 0.06,
        strokeOpacity: 0.2,
        landscape: {
            width: 1600,
            height: 900,
            groups: [{ cx: 800, cy: 1150, radii: [320, 500, 680, 860, 1040] }],
        },
        portrait: {
            width: 900,
            height: 1600,
            groups: [{ cx: 450, cy: 1850, radii: [320, 520, 720, 920] }],
        },
    },
};

const scrimClassNames: Record<KeyVisualScrim, string | null> = {
    'left-bottom':
        'bg-[radial-gradient(ellipse_90%_75%_at_0%_100%,rgb(2_41_191/0.6),transparent_75%)]',
    center: 'bg-[radial-gradient(ellipse_at_center,rgb(2_41_191/0.45),transparent_70%)]',
    none: null,
};

function Rings({
    layout,
    config,
    animated,
    className,
}: {
    layout: Layout;
    config: CompositionConfig;
    animated: boolean;
    className: string;
}) {
    return (
        <svg
            viewBox={`0 0 ${layout.width} ${layout.height}`}
            preserveAspectRatio="xMidYMid slice"
            className={cn('absolute inset-0 h-full w-full', className)}
        >
            {layout.groups.map((group) =>
                group.radii.map((radius, index) => (
                    <circle
                        key={`${group.cx}-${group.cy}-${radius}`}
                        cx={group.cx}
                        cy={group.cy}
                        r={radius}
                        fill={config.ringColor}
                        fillOpacity={config.fillOpacity}
                        stroke={config.ringColor}
                        strokeOpacity={config.strokeOpacity}
                        strokeWidth={1.5}
                        vectorEffect="non-scaling-stroke"
                        className={cn(
                            animated && 'motion-safe:animate-kv-ripple',
                        )}
                        style={
                            animated
                                ? {
                                      transformBox: 'fill-box',
                                      transformOrigin: 'center',
                                      animationDelay: `${index * -2.5}s`,
                                  }
                                : undefined
                        }
                    />
                )),
            )}
        </svg>
    );
}

type KeyVisualProps = {
    composition: KeyVisualComposition;
    scrim?: KeyVisualScrim;
    /** Slow ambient motion: the gradient drifts and the rings pulse outward. Off under reduced motion. */
    animated?: boolean;
    className?: string;
};

/**
 * Decorative background rebuilt from the ICSA 2026 Key Visual: a brand
 * gradient plus large translucent concentric rings. Renders as an
 * `absolute -z-10` layer, so the parent needs `relative isolate`.
 */
export function KeyVisual({
    composition,
    scrim = 'none',
    animated = false,
    className,
}: KeyVisualProps) {
    const config = compositions[composition];
    const scrimClassName = scrimClassNames[scrim];

    return (
        <div
            aria-hidden="true"
            className={cn(
                'pointer-events-none absolute inset-0 -z-10 overflow-hidden select-none',
                config.backgroundClassName,
                animated && 'motion-safe:animate-kv-drift',
                className,
            )}
        >
            <Rings
                layout={config.landscape}
                config={config}
                animated={animated}
                className="hidden sm:block"
            />
            <Rings
                layout={config.portrait}
                config={config}
                animated={animated}
                className="sm:hidden"
            />
            {scrimClassName && (
                <div className={cn('absolute inset-0', scrimClassName)} />
            )}
        </div>
    );
}
