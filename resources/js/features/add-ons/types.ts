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
