import type { BadgeVariant } from '@/components/ui/badge';
import type { DocumentType, TenantStatus, TenantType } from '@/types';

export const CLIENT_TYPE_LABELS: Record<TenantType, { singular: string; plural: string }> = {
    company: { singular: 'Empresa', plural: 'Empresas' },
    individual: { singular: 'Persona natural', plural: 'Personas naturales' },
};

export const DOCUMENT_LABELS: Record<DocumentType, string> = {
    ruc: 'RUC',
    dni: 'DNI',
    ce: 'Carné de extranjería',
};

/** Igual que DocumentType::allowedFor() en el backend. */
export const DOCUMENTS_FOR: Record<TenantType, DocumentType[]> = {
    company: ['ruc'],
    individual: ['dni', 'ce', 'ruc'],
};

export const CLIENT_STATUS: Record<TenantStatus, { label: string; variant: BadgeVariant }> = {
    active: { label: 'Activo', variant: 'success' },
    trial: { label: 'Prueba', variant: 'info' },
    suspended: { label: 'Suspendido', variant: 'error' },
};

export const AUDIT_ACTION_LABELS: Record<string, string> = {
    'client.created': 'Cliente creado',
    'client.updated': 'Datos actualizados',
    'client.suspended': 'Cliente suspendido',
    'client.reactivated': 'Cliente reactivado',
    'impersonation.started': 'Soporte: entró al panel',
    'impersonation.stopped': 'Soporte: salió del panel',
    'impersonation.request': 'Soporte: cambio en datos',
};
