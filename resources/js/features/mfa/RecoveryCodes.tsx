import { Check, Copy, Download } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

/** Códigos de recuperación recién generados: se muestran una sola vez. */
export default function RecoveryCodes({ codes, email }: { codes: string[]; email: string }) {
    const [copied, setCopied] = useState(false);
    const text = codes.join('\n');

    const copy = async () => {
        await navigator.clipboard.writeText(text);
        setCopied(true);
    };

    const download = () => {
        const content = [
            'AlertPrompt — códigos de recuperación',
            `Usuario: ${email}`,
            `Generados: ${new Date().toLocaleString('es-PE')}`,
            '',
            'Cada código sirve una sola vez para entrar si pierdes tu app de autenticación.',
            'Guárdalos en un lugar seguro (gestor de contraseñas o impresos).',
            '',
            ...codes,
            '',
        ].join('\n');
        const url = URL.createObjectURL(new Blob([content], { type: 'text/plain;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = 'alertprompt-codigos-de-recuperacion.txt';
        link.click();
        URL.revokeObjectURL(url);
    };

    return (
        <div className="space-y-3">
            <ul className="grid grid-cols-2 gap-x-3 gap-y-2 rounded-lg border bg-muted/40 p-4 font-mono text-[0.8rem] tracking-wide whitespace-nowrap text-foreground">
                {codes.map((code) => (
                    <li key={code}>{code}</li>
                ))}
            </ul>
            <div className="flex flex-wrap gap-2">
                <Button type="button" variant="outline" size="sm" onClick={download}>
                    <Download />
                    Descargar .txt
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={copy}>
                    {copied ? <Check /> : <Copy />}
                    {copied ? 'Copiados' : 'Copiar'}
                </Button>
            </div>
            <p className="text-xs text-muted-foreground">
                No volverás a ver estos códigos. Cada uno sirve una sola vez si pierdes tu app de autenticación.
            </p>
        </div>
    );
}
