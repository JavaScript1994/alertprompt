import { useState } from 'react';
import PageHeader from '@/components/shared/PageHeader';
import ReportView, { ExportButton, RangePicker } from '@/components/shared/ReportView';
import { useCan } from '@/hooks/usePermissions';
import { lastDays, useReport } from '@/hooks/useReports';

export default function Reports() {
    const [days, setDays] = useState(30);
    const range = lastDays(days);
    const { data, isLoading } = useReport(range);
    const canExport = useCan()('reports.export');

    return (
        <div>
            <PageHeader
                title="Reportes"
                description="Cómo se entregaron tus mensajes."
                actions={
                    <>
                        <RangePicker days={days} onChange={setDays} />
                        {canExport && <ExportButton href={`/api/reports/export?from=${range.from}&to=${range.to}`} />}
                    </>
                }
            />
            <ReportView report={data} isLoading={isLoading} />
        </div>
    );
}
