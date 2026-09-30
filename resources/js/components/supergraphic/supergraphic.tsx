import supergraphic01 from '../../../images/brand/supergraphic/supergraphic-01.svg';
import supergraphic02 from '../../../images/brand/supergraphic/supergraphic-02.svg';
import supergraphic03 from '../../../images/brand/supergraphic/supergraphic-03.svg';
import supergraphic04 from '../../../images/brand/supergraphic/supergraphic-04.svg';
import supergraphic05 from '../../../images/brand/supergraphic/supergraphic-05.svg';
import supergraphic06 from '../../../images/brand/supergraphic/supergraphic-06.svg';
import supergraphic07 from '../../../images/brand/supergraphic/supergraphic-07.svg';
import supergraphic08 from '../../../images/brand/supergraphic/supergraphic-08.webp';
import { cn } from '@/lib/utils';

const supergraphicUrls = {
    '01': supergraphic01,
    '02': supergraphic02,
    '03': supergraphic03,
    '04': supergraphic04,
    '05': supergraphic05,
    '06': supergraphic06,
    '07': supergraphic07,
    '08': supergraphic08,
} as const;

export type SupergraphicVariant = keyof typeof supergraphicUrls;

type SupergraphicProps = {
    variant: SupergraphicVariant;
    className?: string;
    loading?: 'lazy' | 'eager';
};

/**
 * Decorative brand-kit supergraphic (ICSA 2026). Always hidden from
 * assistive tech; position and size it through `className`.
 */
export function Supergraphic({
    variant,
    className,
    loading = 'lazy',
}: SupergraphicProps) {
    return (
        <img
            src={supergraphicUrls[variant]}
            alt=""
            aria-hidden="true"
            loading={loading}
            decoding="async"
            draggable={false}
            className={cn('pointer-events-none select-none', className)}
        />
    );
}
