import type { ModuleKey } from '@/types';

export type AddOnStatus = 'available' | 'coming_soon';

/** Estado de un servicio para el cliente que lo mira. */
export type ClientAddOnState = 'contracted' | 'included' | 'pending' | 'available' | 'coming_soon';

export interface ClientAddOn {
    id: number;
    key: string;
    name: string;
    description: string | null;
    status: AddOnStatus;
    module: ModuleKey | null;
    monthly_price: string | null;
    state: ClientAddOnState;
    interested: boolean;
    contract: { id: number; monthly_price: string; starts_at: string } | null;
    pending_request: { id: number; comment: string | null; requested_by: string | null; created_at: string } | null;
    last_decision: { status: 'approved' | 'rejected'; decision_note: string | null; decided_at: string | null } | null;
}

/** Servicio del catálogo en Administración. */
export interface AddOn {
    id: number;
    key: string;
    name: string;
    description: string | null;
    status: AddOnStatus;
    module: ModuleKey | null;
    monthly_price: string | null;
    is_active: boolean;
    sort: number;
    active_contracts_count?: number;
    interests_count?: number;
}

export interface AddOnInput {
    name: string;
    description: string | null;
    status: AddOnStatus;
    module: ModuleKey | null;
    monthly_price: string | null;
}

export type AddOnRequestStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';

export interface AddOnRequest {
    id: number;
    add_on: { id: number; key: string | null; name: string | null; monthly_price: string | null };
    status: AddOnRequestStatus;
    comment: string | null;
    requested_by: string | null;
    decided_by: string | null;
    decided_at: string | null;
    decision_note: string | null;
    tenant: { id: number; name: string | null };
    created_at: string;
}

/** Contrato de un servicio en la ficha del cliente. */
export interface TenantAddOn {
    id: number;
    add_on: { id: number; key: string | null; name: string | null; module: ModuleKey | null };
    status: 'active' | 'cancelled';
    monthly_price: string;
    starts_at: string;
    cancelled_at: string | null;
    cancel_reason: string | null;
    activated_by: string | null;
    cancelled_by: string | null;
}

export interface AddOnInterest {
    id: number;
    tenant: { id: number; name: string | null; plan: string | null };
    user: string | null;
    created_at: string;
}
