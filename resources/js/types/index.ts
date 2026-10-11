import type { MfaStatus } from '@/features/mfa/types';

export type TenantType = 'company' | 'individual';
export type TenantStatus = 'active' | 'trial' | 'suspended';
export type DocumentType = 'ruc' | 'dni' | 'ce';

export interface Tenant {
    id: number;
    name: string;
    /** Subdominio del login de la empresa; null en la plataforma. */
    slug: string | null;
    login_url: string | null;
    type: TenantType;
    document_type: DocumentType | null;
    document_number: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    address: string | null;
    plan: string;
    plan_name: string;
    status: TenantStatus;
    /** true solo para AlertPrompt: su usuario ve el panel de administración. */
    is_platform: boolean;
    timezone: string;
    /** Módulos contratados (config/modules.php). La plataforma tiene todos. */
    modules: ModuleKey[];
}

export type ModuleKey = 'whatsapp' | 'sms' | 'email' | 'csv_import' | 'scheduling' | 'reports' | 'branding';

export interface ModuleSummary {
    key: ModuleKey;
    label: string;
    description: string;
    clients_count: number;
    included_in_plans: string[];
}

export interface AssignableRole {
    id: number;
    name: string;
    label: string;
    description: string | null;
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

/** Datos personales de un usuario del panel (no de un contacto). */
export interface PersonalData {
    first_name: string | null;
    last_name: string | null;
    job_title: string | null;
    /** YYYY-MM-DD */
    birth_date: string | null;
    phone: string | null;
    mobile: string | null;
    /** Ruta de la API (disco privado); null sin foto. */
    photo_url: string | null;
}

export interface User extends PersonalData {
    id: number;
    name: string;
    email: string;
    last_login_at: string | null;
    roles: UserRoleSummary[];
    permissions: PermissionName[];
    tenant: Tenant;
    /** Modo soporte: el panel muestra los datos de este cliente. */
    impersonating: Tenant | null;
    mfa: MfaStatus;
}

/** Cliente visto desde el panel de la plataforma. */
export interface Client extends Tenant {
    users_count: number;
    contacts_count: number;
    campaigns_count: number;
    created_at: string;
}

export interface TenantUser extends PersonalData {
    id: number;
    name: string;
    email: string;
    /** Correo nuevo esperando confirmación desde esa dirección. */
    pending_email: string | null;
    roles: { id: number; name: string; label: string }[];
    email_verified_at: string | null;
    mfa_enabled: boolean;
    last_login_at: string | null;
    deactivated_at: string | null;
    created_at: string;
}

export interface AuditLogEntry {
    id: number;
    action: string;
    metadata: Record<string, unknown>;
    user: { id: number; name: string; email: string } | null;
    ip: string | null;
    created_at: string;
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

export type ChannelAccountStatus = 'pending' | 'active' | 'disabled';
export type QualityRating = 'GREEN' | 'YELLOW' | 'RED' | 'UNKNOWN';
export type AccountChannel = 'whatsapp' | 'sms';

export interface ChannelAccount {
    id: number;
    channel: AccountChannel;
    provider: string;
    display_name: string | null;
    sender: string;
    status: ChannelAccountStatus;
    quality_rating: QualityRating | null;
    messaging_tier: string | null;
    activated_at: string | null;
    /** Solo en /api/admin/*. */
    notes?: string | null;
    credential_hints?: Record<string, string>;
    webhook_url?: string;
}

export interface ChannelAccountSummary {
    channel: AccountChannel;
    account: ChannelAccount | null;
    shared_sender_allowed: boolean;
    can_send: boolean;
}

export interface AdminChannelAccountSlot {
    channel: AccountChannel;
    providers: string[];
    account: ChannelAccount | null;
}

export type AlertSeverity = 'info' | 'warning' | 'critical';

export interface AppAlert {
    id: number;
    type: string;
    severity: AlertSeverity;
    title: string;
    message: string;
    details: Record<string, unknown>;
    occurrences: number;
    campaign: { id: number; name: string } | null;
    /** Solo en /api/admin/*. */
    tenant?: { id: number; name: string } | null;
    resolved_at: string | null;
    resolved_by: string | null;
    created_at: string;
    updated_at: string;
}

export interface DeliveryCounts {
    attempted: number;
    sent: number;
    delivered: number;
    read: number;
    failed: number;
    skipped: number;
    delivery_rate: number;
}

export interface ReportSummary {
    range: { from: string; to: string };
    totals: DeliveryCounts;
    daily: (DeliveryCounts & { day: string })[];
    by_channel: (DeliveryCounts & { channel: TemplateChannel })[];
    by_category: (DeliveryCounts & { category: string })[];
    campaigns: (DeliveryCounts & {
        id: number;
        name: string;
        channel: TemplateChannel;
        status: string;
        category: string;
        tenant_name: string;
    })[];
}

export interface AdminReportSummary extends ReportSummary {
    by_tenant: (DeliveryCounts & { tenant_id: number; tenant_name: string; tenant_type: TenantType })[];
    clients: { active: number; suspended: number };
    open_alerts: number;
}

export interface BulkImport {
    id: number;
    tenant: { id: number; name: string } | null;
    uploaded_by: string | null;
    original_filename: string;
    declared_source: string;
    attestation_text: string;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    totals: {
        rows: number;
        created: number;
        updated: number;
        consents_recorded: number;
        without_consent: number;
        consents_blocked: number;
        invalid: number;
    };
    errors?: { line: number; message: string }[];
    errors_count: number;
    started_at: string | null;
    finished_at: string | null;
    created_at: string;
}

export type MembershipStatus = 'scheduled' | 'active' | 'expired' | 'cancelled';

export interface Membership {
    id: number;
    plan: string;
    plan_name: string;
    status: MembershipStatus;
    billing_cycle: 'monthly' | 'yearly';
    price: string;
    currency: string;
    starts_at: string;
    ends_at: string;
    quotas: Partial<Record<TemplateChannel, number | null>>;
    contract_reference: string | null;
    notes?: string | null;
    created_by?: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    created_at: string;
}

export type ChannelUsage = Record<TemplateChannel, { used: number; quota: number | null }>;

export interface Plan {
    id: number;
    key: string;
    name: string;
    description: string | null;
    /** Mensual sin IGV; null = a medida. */
    monthly_price: string | null;
    quotas: Record<TemplateChannel, number | null>;
    /** Módulos con los que nace un cliente del plan. */
    modules: ModuleKey[];
    is_public: boolean;
    /** Inactivo: no se ofrece en altas nuevas; quien lo tiene lo conserva. */
    is_active: boolean;
    /** Solo en /api/admin/plans. */
    clients_count?: number;
    sort: number;
}

export interface MembershipOverview {
    current: Membership | null;
    next: Membership | null;
    usage: ChannelUsage;
    quotas_enforced: boolean;
    history: Membership[];
    plans: Plan[];
    current_plan: string | null;
    pending_request: PlanChangeRequest | null;
    last_decision: PlanChangeRequest | null;
}

export type PlanChangeStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';

export interface PlanChangeRequest {
    id: number;
    current_plan: string | null;
    current_plan_name: string | null;
    requested_plan: string;
    requested_plan_name: string;
    status: PlanChangeStatus;
    comment: string | null;
    requested_by: string | null;
    decided_by: string | null;
    decided_at: string | null;
    decision_note: string | null;
    effective_from: string | null;
    /** Solo en /api/admin/*. */
    tenant?: { id: number; name: string | null };
    created_at: string;
}

export type InvoiceStatus = 'issued' | 'paid' | 'void';

export interface PaymentRecord {
    id: number;
    invoice_id: number;
    invoice_code?: string;
    amount: string;
    method: 'transfer' | 'yape' | 'plin' | 'cash' | 'card';
    method_label: string;
    reference: string | null;
    status: string;
    paid_at: string;
    notes?: string | null;
}

export interface Invoice {
    id: number;
    code: string;
    document_type: 'factura' | 'boleta';
    is_electronic: boolean;
    sunat_status: 'not_sent' | 'pending' | 'accepted' | 'rejected';
    issue_date: string;
    due_date: string;
    currency: string;
    subtotal: string;
    igv: string;
    total: string;
    paid: string;
    balance: string;
    description: string;
    customer: { name: string; document_type: string | null; document_number: string | null; address: string | null };
    status: InvoiceStatus;
    is_overdue: boolean;
    pdf_url: string | null;
    paid_at: string | null;
    voided_at: string | null;
    void_reason: string | null;
    tenant?: { id: number; name: string | null };
    payments?: PaymentRecord[];
}

export interface BillingSummary {
    balance: string;
    overdue_count: number;
    transfer_instructions: string;
    cards_enabled: boolean;
}

export interface SavedPaymentMethod {
    id: number;
    provider: string;
    brand: string | null;
    last4: string | null;
    exp_month: number | null;
    exp_year: number | null;
    is_default: boolean;
}
