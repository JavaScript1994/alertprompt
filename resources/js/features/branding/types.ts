/** GET /api/branding: marca del login según el subdominio (público). */
export interface PublicBranding {
    /** Subdominio que no es de ninguna empresa. */
    unknown: boolean;
    /** Empresa del subdominio; null en el dominio general. */
    tenant: { name: string; slug: string } | null;
    /** El plan incluye la marca: hay logo/color/título propios. */
    branded: boolean;
    logo_url: string | null;
    color: string | null;
    title: string | null;
}

export interface BrandingSettings {
    name: string;
    slug: string | null;
    login_url: string | null;
    logo_url: string | null;
    color: string | null;
    title: string | null;
    default_color: string;
}
