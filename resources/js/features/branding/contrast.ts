function channel(pair: string): number {
    const c = parseInt(pair, 16) / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
}

/** Contraste WCAG contra blanco (igual que ReadableBrandColor en el backend). */
export function contrastWithWhite(hex: string): number {
    const luminance = 0.2126 * channel(hex.slice(1, 3)) + 0.7152 * channel(hex.slice(3, 5)) + 0.0722 * channel(hex.slice(5, 7));
    return 1.05 / (luminance + 0.05);
}

export const MIN_CONTRAST = 4.5;
export const HEX_COLOR = /^#[0-9a-fA-F]{6}$/;
