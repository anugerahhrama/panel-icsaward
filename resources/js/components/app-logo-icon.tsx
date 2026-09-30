import type { ImgHTMLAttributes } from 'react';
import icsaMark from '../../images/brand/logo/icsa-mark.webp';

/**
 * Official ICS Award 2026 circle mark (full-colour, transparent background).
 */
export default function AppLogoIcon({
    alt = '',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src={icsaMark} alt={alt} {...props} />;
}
