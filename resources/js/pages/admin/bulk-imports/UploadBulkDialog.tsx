import { Download, ShieldCheck, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useAllClients, useAttestationText, useUploadBulkImport } from '@/hooks/useBulkImports';
import { apiErrorMessage } from '@/lib/format';

export function downloadBulkTemplate(): void {
    const csv = [
        'name,phone,email,distrito,consent_channels,consent_granted_at,consent_source,consent_evidence_text,consent_ip',
        'Ana Torres,+51987654321,ana@example.com,Miraflores,whatsapp|sms,2026-05-10,formulario_web,"Acepto recibir ofertas por WhatsApp y SMS",190.12.4.8',
        'Carlos Ramos,+51911223344,,San Isidro,,,,,',
    ].join('\n');

    const url = URL.createObjectURL(new Blob(['﻿', csv], { type: 'text/csv;charset=utf-8;' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = 'plantilla_carga_masiva.csv';
    link.click();
    URL.revokeObjectURL(url);
}

export default function UploadBulkDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
    const { data: clients } = useAllClients();
    const { data: attestation } = useAttestationText();
    const upload = useUploadBulkImport();
    const [clientId, setClientId] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [source, setSource] = useState('');
    const [accepted, setAccepted] = useState(false);

    const close = () => {
        setClientId('');
        setFile(null);
        setSource('');
        setAccepted(false);
        upload.reset();
        onClose();
    };

    const canSubmit = clientId !== '' && file !== null && source.trim().length >= 15 && accepted;

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        if (!canSubmit || !file) return;
        upload.mutate({ client_id: Number(clientId), file, declared_source: source.trim() }, { onSuccess: close });
    };

    return (
        <Dialog open={open} onOpenChange={(isOpen) => !isOpen && close()}>
            <DialogContent className="max-h-[90vh] max-w-xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Nueva carga masiva</DialogTitle>
                    <DialogDescription>
                        Los contactos se importan al cliente elegido. El consentimiento solo se registra en las filas con
                        evidencia completa.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={onSubmit} className="space-y-4" noValidate>
                    {upload.isError && (
                        <Alert variant="error">
                            <XCircle />
                            <AlertTitle>
                                {apiErrorMessage(upload.error, ['file', 'client_id', 'declared_source', 'attestation'], 'No se pudo subir la base.')}
                            </AlertTitle>
                        </Alert>
                    )}

                    <Field label="Cliente" htmlFor="bulk-client">
                        <Select value={clientId} onValueChange={(value) => value && setClientId(value)}>
                            <SelectTrigger id="bulk-client" className="w-full">
                                <SelectValue placeholder="Elige el cliente" />
                            </SelectTrigger>
                            <SelectContent>
                                {clients?.map((client) => (
                                    <SelectItem key={client.id} value={String(client.id)}>
                                        {client.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field
                        label="Archivo CSV"
                        htmlFor="bulk-file"
                        hint={
                            <button type="button" onClick={downloadBulkTemplate} className="inline-flex items-center gap-1 text-primary hover:underline dark:text-brand-200">
                                <Download className="size-3.5" />
                                Descargar plantilla con las columnas de consentimiento
                            </button>
                        }
                    >
                        <Input id="bulk-file" type="file" accept=".csv,text/csv" onChange={(event) => setFile(event.target.files?.[0] ?? null)} />
                    </Field>

                    <Field
                        label="¿De dónde salió esta base?"
                        htmlFor="bulk-source"
                        hint="Ej.: formulario de suscripción del sitio web del cliente, campaña de verano 2026."
                    >
                        <Textarea id="bulk-source" rows={3} value={source} onChange={(event) => setSource(event.target.value)} />
                    </Field>

                    <Alert variant="warning">
                        <ShieldCheck />
                        <AlertTitle>Ley N° 32323</AlertTitle>
                        <AlertDescription>
                            No se pueden cargar bases compradas ni de terceros para enviar publicidad. Quien se dio de baja
                            no vuelve a recibir marketing aunque aparezca en el archivo.
                        </AlertDescription>
                    </Alert>

                    <div className="flex items-start gap-3 rounded-lg border p-4">
                        <Checkbox id="bulk-attestation" checked={accepted} onCheckedChange={(checked) => setAccepted(checked === true)} className="mt-0.5" />
                        <Label htmlFor="bulk-attestation" className="cursor-pointer text-sm leading-relaxed font-normal">
                            {attestation ?? 'Cargando declaración…'}
                        </Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={!canSubmit} loading={upload.isPending}>
                            Subir e importar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
