export type AnnouncementType = 'feature' | 'notice' | 'incident' | 'action_required' | 'promotion';
export type AnnouncementAudience = 'all' | 'plans' | 'tenants';
export type AnnouncementStatus = 'draft' | 'scheduled' | 'live' | 'expired' | 'resolved';

/** Comunicado tal como lo ve un usuario del cliente. */
export interface Announcement {
    id: number;
    type: AnnouncementType;
    type_label: string;
    title: string;
    body: string;
    link_url: string | null;
    link_label: string | null;
    published_at: string;
    resolved_at: string | null;
    read_at: string | null;
    dismissed_at: string | null;
}

/** Comunicado en la administración de la plataforma. */
export interface AdminAnnouncement extends Omit<Announcement, 'read_at' | 'dismissed_at' | 'published_at'> {
    audience: AnnouncementAudience;
    plan_keys: string[];
    tenants: { id: number; name: string }[];
    admins_only: boolean;
    published_at: string | null;
    expires_at: string | null;
    send_email: boolean;
    emailed_at: string | null;
    status: AnnouncementStatus;
    reads_count: number;
    author: string | null;
    created_at: string;
}

export interface AnnouncementInput {
    type: AnnouncementType;
    title: string;
    body: string;
    link_url: string | null;
    link_label: string | null;
    audience: AnnouncementAudience;
    plan_keys: string[];
    tenant_ids: number[];
    admins_only: boolean;
    published_at: string | null;
    expires_at: string | null;
    send_email: boolean;
}

export interface AnnouncementStat {
    tenant_id: number;
    tenant_name: string;
    recipients: number;
    reads: number;
}
