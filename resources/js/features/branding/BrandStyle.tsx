/**
 * Aplica el color de la empresa a las pantallas públicas: sobreescribe
 * --tenant-accent (app.css), que usan botones, enlaces y acentos del login.
 * El color ya viene validado por el backend (#RRGGBB).
 */
export default function BrandStyle({ color }: { color: string | null | undefined }) {
    if (!color || !/^#[0-9a-fA-F]{6}$/.test(color)) return null;

    return <style>{`:root { --tenant-accent: ${color}; }`}</style>;
}
