export type TenantType = 'company' | 'individual';
export type TenantStatus = 'active' | 'trial' | 'suspended';

export interface Tenant {
    id: number;
    name: string;
    type: TenantType;
    plan: string;
    status: TenantStatus;
    /** true solo para AlertPrompt: su usuario ve el panel de administración. */
    is_platform: boolean;
}

export interface UserRoleSummary {
    name: string;
    label: string;
}

/** Nombre de permiso de config/permissions.php, p. ej. "campaigns.dispatch". */
export type PermissionName = string;

export type RoleScope = 'platform' | 'client';

export interface Role {
    id: number;
    /** Clave estable que usa el código; no cambia al renombrar. */
    name: string;
    label: string;
    description: string | null;
    scope: RoleScope;
    is_system: boolean;
    /** Rol de dueño: siempre tiene todos los permisos y no se edita. */
    is_locked: boolean;
    users_count: number;
    permissions_count: number;
    permissions?: PermissionName[];
}

export interface PermissionTreeItem {
    name: PermissionName;
    label: string;
}

export interface PermissionTreeModule {
    key: string;
    label: string;
    permissions: PermissionTreeItem[];
}

export interface PermissionTreeSection {
    key: string;
    label: string;
    scope: RoleScope;
    modules: PermissionTreeModule[];
}

export interface User {
    id: number;
    name: string;
    email: string;
    roles: UserRoleSummary[];
    permissions: PermissionName[];
    tenant: Tenant;
}

export interface Contact {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    attributes: Record<string, unknown>;
    created_at: string;
}

export interface PaginationLinks {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    links: PaginationLinks;
    meta: PaginationMeta;
}

export interface Consent {
    id: number;
    channel: TemplateChannel;
    granted_at: string;
    revoked_at: string | null;
    is_active: boolean;
    source: string;
    evidence_text: string;
}

export type TemplateChannel = 'whatsapp' | 'sms' | 'email';
export type TemplateCategory = 'marketing' | 'utility' | 'authentication';
export type TemplateStatus = 'draft' | 'pending_approval' | 'approved' | 'rejected' | 'disabled';

export interface Template {
    id: number;
    channel: TemplateChannel;
    category: TemplateCategory;
    name: string;
    body: string;
    variables: string[];
    provider_template_id: string | null;
    status: TemplateStatus;
    requires_consent: boolean;
    created_at: string;
}

export interface TemplatePreview {
    variables: string[];
    missing: string[];
    rendered: string;
}

export type CampaignStatus = 'draft' | 'scheduled' | 'running' | 'paused' | 'completed' | 'cancelled';

export interface CampaignStats {
    pending: number;
    queued: number;
    sent: number;
    delivered: number;
    read: number;
    failed: number;
    skipped: number;
    total: number;
}

export interface CampaignRecipientContact {
    contact_id: number;
    name: string;
    phone: string | null;
    email: string | null;
}

export interface Campaign {
    id: number;
    name: string;
    channel: TemplateChannel;
    status: CampaignStatus;
    template: Template;
    stats: CampaignStats;
    scheduled_at: string | null;
    created_at: string;
    /** Solo viene cargado en el detalle (GET /api/campaigns/{id}). */
    recipients?: CampaignRecipientContact[];
}
