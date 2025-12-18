import { useEffect, useMemo, useState } from 'react';
import { Helmet } from 'react-helmet-async';
import {
    Activity,
    AlertTriangle,
    ArrowUpRight,
    CalendarClock,
    Factory,
    Layers3,
    ShieldCheck,
    TrendingUp,
} from 'lucide-react';

import { KpiCard } from '@/components/analytics/kpi-card';
import { MiniChart } from '@/components/analytics/mini-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface PartForecastRow {
    id: string;
    name: string;
    usageSummary: string;
    safetyStock: number;
    reorderPoint: number;
    leadTime: number;
    leadTimeVariance: number;
    orderBy: string;
    recommendedQty: number;
    supplierMix: string;
    risk: 'low' | 'medium' | 'high';
    rationale: string;
}

interface SupplierOption {
    id: string;
    label: string;
    leadTimeDelta: number;
    variabilityMultiplier: number;
    costDeltaPct: number;
    notes: string;
}

interface ScenarioPreset {
    id: string;
    label: string;
    baseline: {
        avgDailyDemand: number;
        leadTimeDays: number;
        leadTimeVariance: number;
        safetyStock: number;
        reorderQty: number;
        orderByDate: string;
        serviceLevel: number;
        unitCost: number;
    };
    supplierOptions: SupplierOption[];
}

const PART_FORECASTS: PartForecastRow[] = [
    {
        id: 'bracket-12a',
        name: 'Bracket 12A \u2013 anodized',
        usageSummary: '45 units/day (6 week avg)',
        safetyStock: 320,
        reorderPoint: 540,
        leadTime: 18,
        leadTimeVariance: 4.2,
        orderBy: '2025-01-05',
        recommendedQty: 900,
        supplierMix: 'NovaForge 70% / LumaSteel 30%',
        risk: 'medium',
        rationale: 'Lead time spike after Supplier B outage; holding buffer for QA rework windows.',
    },
    {
        id: 'spindle-44c',
        name: 'Spindle 44C \u2013 hardened steel',
        usageSummary: '12 units/week (PdM driven)',
        safetyStock: 80,
        reorderPoint: 140,
        leadTime: 26,
        leadTimeVariance: 6.1,
        orderBy: '2025-01-18',
        recommendedQty: 210,
        supplierMix: 'Orion Machining 60% / Backup Pool 40%',
        risk: 'high',
        rationale: 'Certificate renewal pending; recommend dual sourcing to reduce stop-ship exposure.',
    },
    {
        id: 'seal-kit-7f',
        name: 'Seal kit 7F \u2013 nitrile',
        usageSummary: '210 units/month (field returns)',
        safetyStock: 150,
        reorderPoint: 310,
        leadTime: 12,
        leadTimeVariance: 2.1,
        orderBy: '2024-12-29',
        recommendedQty: 500,
        supplierMix: 'FlowSure 100%',
        risk: 'low',
        rationale: 'Stable demand with low variance; safe to defer order until after holiday shutdown.',
    },
];

const SCENARIO_PRESETS: ScenarioPreset[] = [
    {
        id: 'bracket-12a',
        label: 'Bracket 12A',
        baseline: {
            avgDailyDemand: 45,
            leadTimeDays: 18,
            leadTimeVariance: 4.2,
            safetyStock: 320,
            reorderQty: 900,
            orderByDate: '2025-01-05',
            serviceLevel: 95,
            unitCost: 37,
        },
        supplierOptions: [
            {
                id: 'novaforge',
                label: 'NovaForge (incumbent)',
                leadTimeDelta: 0,
                variabilityMultiplier: 1,
                costDeltaPct: 0,
                notes: '+0.4d variance from holiday changeover.',
            },
            {
                id: 'lumasteel',
                label: 'LumaSteel (faster turn)',
                leadTimeDelta: -3,
                variabilityMultiplier: 0.9,
                costDeltaPct: 4,
                notes: 'Adds 4% premium but chops variance by 10%.',
            },
            {
                id: 'dual-source',
                label: 'Split \u2013 Nova 50% / Luma 50%',
                leadTimeDelta: -1,
                variabilityMultiplier: 0.92,
                costDeltaPct: 2,
                notes: 'Heat-treat load balancing keeps rush capacity available.',
            },
        ],
    },
    {
        id: 'spindle-44c',
        label: 'Spindle 44C',
        baseline: {
            avgDailyDemand: 2,
            leadTimeDays: 26,
            leadTimeVariance: 6.1,
            safetyStock: 80,
            reorderQty: 210,
            orderByDate: '2025-01-18',
            serviceLevel: 97,
            unitCost: 420,
        },
        supplierOptions: [
            {
                id: 'orion',
                label: 'Orion Machining (certified)',
                leadTimeDelta: 0,
                variabilityMultiplier: 1,
                costDeltaPct: 0,
                notes: 'Certificate renewal overdue; expect QA hold if delayed.',
            },
            {
                id: 'hyperion',
                label: 'Hyperion (expedite slot)',
                leadTimeDelta: -6,
                variabilityMultiplier: 0.85,
                costDeltaPct: 6,
                notes: 'Premium expedite lane with on-site QA sign-off.',
            },
        ],
    },
];

const INVENTORY_TRAJECTORY = [
    { label: 'Dec 16', inventory: 1480, threshold: 500, demand: 310 },
    { label: 'Dec 23', inventory: 1210, threshold: 500, demand: 330 },
    { label: 'Dec 30', inventory: 980, threshold: 500, demand: 350 },
    { label: 'Jan 06', inventory: 720, threshold: 510, demand: 360 },
    { label: 'Jan 13', inventory: 460, threshold: 520, demand: 365 },
    { label: 'Jan 20', inventory: 180, threshold: 530, demand: 370 },
];

const KPI_DEFINITIONS = [
    {
        label: 'Projected run-out window',
        value: '18 days',
        description: 'Includes supplier variance and PdM pull-ins.',
        icon: Activity,
        footnote: 'Lead time variance adds 3.2 days vs last month.',
    },
    {
        label: 'Recommended order quantity',
        value: '1,450 units',
        description: 'Covers 6 weeks of demand plus 15% buffer.',
        icon: Layers3,
        footnote: 'Buffer driven by Supplier B outage on 11/28.',
    },
    {
        label: 'Next order-by date',
        value: '05 Jan 2025',
        description: 'Keeps cycle service level at 95%.',
        icon: CalendarClock,
        footnote: 'Moves up 4 days if Supplier C is chosen.',
    },
];

export function ForecastingInventoryInsightsPage() {
    const [selectedPresetId, setSelectedPresetId] = useState<string>(SCENARIO_PRESETS[0]?.id ?? '');
    const [serviceLevel, setServiceLevel] = useState<number>(SCENARIO_PRESETS[0]?.baseline.serviceLevel ?? 95);
    const [selectedSupplierId, setSelectedSupplierId] = useState<string>(SCENARIO_PRESETS[0]?.supplierOptions[0]?.id ?? '');
    const [demandInput, setDemandInput] = useState<string>(String(SCENARIO_PRESETS[0]?.baseline.avgDailyDemand ?? 0));

    const selectedPreset = useMemo(() => {
        return SCENARIO_PRESETS.find((preset) => preset.id === selectedPresetId) ?? SCENARIO_PRESETS[0];
    }, [selectedPresetId]);

    useEffect(() => {
        if (!selectedPreset) {
            return;
        }
        setSelectedSupplierId(selectedPreset.supplierOptions[0]?.id ?? '');
        setServiceLevel(selectedPreset.baseline.serviceLevel);
        setDemandInput(String(selectedPreset.baseline.avgDailyDemand));
    }, [selectedPreset]);

    const supplierChoice = useMemo(() => {
        if (!selectedPreset) {
            return undefined;
        }
        return selectedPreset.supplierOptions.find((option) => option.id === selectedSupplierId) ?? selectedPreset.supplierOptions[0];
    }, [selectedPreset, selectedSupplierId]);

    const scenarioResult = useMemo(() => {
        if (!selectedPreset || !supplierChoice) {
            return null;
        }

        const parsedDemand = Number(demandInput) > 0 ? Number(demandInput) : selectedPreset.baseline.avgDailyDemand;
        const serviceDelta = serviceLevel - selectedPreset.baseline.serviceLevel;
        const demandRatio = parsedDemand / selectedPreset.baseline.avgDailyDemand;
        const variabilityMultiplier = supplierChoice.variabilityMultiplier;
        const adjustedSafetyStock = Math.round(
            selectedPreset.baseline.safetyStock * (1 + serviceDelta * 0.015) * variabilityMultiplier,
        );
        const adjustedReorderQty = Math.round(
            selectedPreset.baseline.reorderQty * demandRatio * (1 + serviceDelta * 0.01) * variabilityMultiplier,
        );
        const adjustedLeadTime = Math.max(1, Math.round(selectedPreset.baseline.leadTimeDays + supplierChoice.leadTimeDelta));
        const projectedRunOutDays = Math.max(1, Math.round((adjustedSafetyStock + adjustedReorderQty) / parsedDemand));
        const cashImpact = adjustedReorderQty * selectedPreset.baseline.unitCost;
        const orderByShiftDays = Math.round(serviceDelta * 0.2) - supplierChoice.leadTimeDelta;
        const plannedOrderDate = shiftDateString(selectedPreset.baseline.orderByDate, -orderByShiftDays);

        return {
            adjustedSafetyStock,
            adjustedReorderQty,
            adjustedLeadTime,
            projectedRunOutDays,
            cashImpact,
            orderByDate: plannedOrderDate,
            explanation: `Targeting ${serviceLevel}% service with ${supplierChoice.label} keeps ${projectedRunOutDays} days of coverage while absorbing ${supplierChoice.notes}`,
        };
    }, [demandInput, selectedPreset, serviceLevel, supplierChoice]);

    return (
        <div className="flex flex-1 flex-col gap-8">
            <Helmet>
                <title>Forecasting & Inventory Insights</title>
            </Helmet>

            <section className="rounded-3xl border border-white/10 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 p-6 text-slate-50 shadow-2xl">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="space-y-3">
                        <p className="text-xs uppercase tracking-[0.3em] text-slate-400">AI forecasting cockpit</p>
                        <h1 className="text-3xl font-semibold leading-tight">
                            Lead time-aware recommendations, ready for approval.
                        </h1>
                        <p className="max-w-2xl text-sm text-slate-300">
                            Blend demand history, supplier reliability, and digital twin context to see precisely when stocks dip below safety levels.
                            Every card shows why the AI picked a move so buyers can approve with confidence.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <div className="rounded-2xl bg-white/10 px-4 py-3 text-sm text-slate-200">
                            <p className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Service level</p>
                            <p className="text-2xl font-semibold">95%</p>
                            <p className="text-xs text-slate-300">Auto-adjusted for supplier variance</p>
                        </div>
                        <div className="rounded-2xl bg-white/10 px-4 py-3 text-sm text-slate-200">
                            <p className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Stockout risk</p>
                            <p className="text-2xl font-semibold text-amber-300">Low \u2193</p>
                            <p className="text-xs text-slate-300">Scenario modeling enabled</p>
                        </div>
                    </div>
                </div>
            </section>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {KPI_DEFINITIONS.map((kpi) => (
                    <KpiCard
                        key={kpi.label}
                        label={kpi.label}
                        value={kpi.value}
                        description={kpi.description}
                        icon={kpi.icon}
                        footnote={kpi.footnote}
                    />
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                <MiniChart
                    title="Projected inventory vs threshold"
                    description="Simulated coverage window using supplier variance + PdM signals."
                    data={INVENTORY_TRAJECTORY}
                    series={[
                        { key: 'inventory', label: 'Projected inventory', color: '#38bdf8' },
                        { key: 'threshold', label: 'Safety stock floor', color: '#fbbf24' },
                    ]}
                    valueFormatter={(value, key) =>
                        key === 'inventory' ? `${value.toLocaleString()} units` : `${value.toLocaleString()} units`
                    }
                />
                <Card className="h-full">
                    <CardHeader>
                        <CardTitle className="text-base font-semibold">Recommended interventions</CardTitle>
                        <CardDescription className="text-sm">
                            AI highlights the clearest moves with rationale pulled from deliveries, QA notes, and ERP reservations.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {RECOMMENDATIONS.map((item) => (
                            <div key={item.title} className="rounded-2xl border bg-card/30 p-4">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-sm font-semibold text-foreground">{item.title}</p>
                                        <p className="text-xs text-muted-foreground">{item.subtitle}</p>
                                    </div>
                                    <Badge variant={item.badgeVariant}>{item.badgeLabel}</Badge>
                                </div>
                                <p className="mt-3 text-sm text-muted-foreground">{item.rationale}</p>
                                <button
                                    type="button"
                                    className="mt-3 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                >
                                    Queue action <ArrowUpRight className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <Card className="xl:col-span-2">
                    <CardHeader>
                        <CardTitle className="text-base font-semibold">Critical parts watchlist</CardTitle>
                        <CardDescription className="text-sm">Lead time aware reorder recommendations with transparent reasoning.</CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead className="text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="pb-3 font-medium">Part</th>
                                    <th className="pb-3 font-medium">Usage</th>
                                    <th className="pb-3 font-medium">Safety stock</th>
                                    <th className="pb-3 font-medium">Reorder point</th>
                                    <th className="pb-3 font-medium">Lead time</th>
                                    <th className="pb-3 font-medium">Order by</th>
                                    <th className="pb-3 font-medium">Supplier mix</th>
                                    <th className="pb-3 font-medium">Risk</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/60">
                                {PART_FORECASTS.map((part) => (
                                    <tr key={part.id}>
                                        <td className="py-3">
                                            <p className="font-medium text-foreground">{part.name}</p>
                                            <p className="text-xs text-muted-foreground">{part.rationale}</p>
                                        </td>
                                        <td className="py-3 text-muted-foreground">{part.usageSummary}</td>
                                        <td className="py-3 text-muted-foreground">{part.safetyStock.toLocaleString()} units</td>
                                        <td className="py-3 text-muted-foreground">{part.reorderPoint.toLocaleString()} units</td>
                                        <td className="py-3 text-muted-foreground">
                                            {part.leadTime}d \u00B1 {part.leadTimeVariance.toFixed(1)}d
                                        </td>
                                        <td className="py-3 text-muted-foreground">{formatDate(part.orderBy)}</td>
                                        <td className="py-3 text-muted-foreground">{part.supplierMix}</td>
                                        <td className="py-3">
                                            <RiskBadge level={part.risk} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base font-semibold">Scenario sandbox</CardTitle>
                        <CardDescription className="text-sm">
                            Run what-if checks by tweaking demand, service level, and supplier mix before handing off to procurement.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="scenario-part">Part / assembly</Label>
                            <Select value={selectedPreset.id} onValueChange={setSelectedPresetId}>
                                <SelectTrigger id="scenario-part">
                                    <SelectValue placeholder="Select part" />
                                </SelectTrigger>
                                <SelectContent>
                                    {SCENARIO_PRESETS.map((preset) => (
                                        <SelectItem key={preset.id} value={preset.id}>
                                            {preset.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="scenario-demand">Avg daily demand</Label>
                            <Input
                                id="scenario-demand"
                                value={demandInput}
                                onChange={(event) => setDemandInput(event.target.value)}
                                inputMode="decimal"
                                placeholder="e.g. 45"
                            />
                            <p className="text-xs text-muted-foreground">Baseline: {selectedPreset?.baseline.avgDailyDemand} units/day.</p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="scenario-service">Target service level ({serviceLevel}%)</Label>
                            <input
                                id="scenario-service"
                                type="range"
                                min={90}
                                max={99}
                                step={1}
                                value={serviceLevel}
                                onChange={(event) => setServiceLevel(Number(event.target.value))}
                                className="h-1 w-full cursor-pointer appearance-none rounded-full bg-muted"
                            />
                            <p className="text-xs text-muted-foreground">Increasing service level grows safety stock non-linearly.</p>
                        </div>

                        <div className="space-y-2">
                            <Label>Supplier mix</Label>
                            <Select value={supplierChoice?.id} onValueChange={setSelectedSupplierId}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select supplier mix" />
                                </SelectTrigger>
                                <SelectContent>
                                    {selectedPreset?.supplierOptions.map((option) => (
                                        <SelectItem key={option.id} value={option.id}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {supplierChoice ? (
                                <p className="text-xs text-muted-foreground">{supplierChoice.notes}</p>
                            ) : null}
                        </div>

                        {scenarioResult ? (
                            <div className="rounded-2xl border bg-muted/40 p-4 text-sm">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p className="text-xs uppercase tracking-[0.3em] text-muted-foreground">Recommended order</p>
                                        <p className="text-2xl font-semibold text-foreground">
                                            {scenarioResult.adjustedReorderQty.toLocaleString()} units
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Order by {formatDate(scenarioResult.orderByDate)} to keep {scenarioResult.projectedRunOutDays} days of coverage.
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-xs uppercase tracking-[0.3em] text-muted-foreground">Cash impact</p>
                                        <p className="text-lg font-semibold text-emerald-600">
                                            {formatCurrency(scenarioResult.cashImpact)}
                                        </p>
                                        <p className="text-xs text-muted-foreground">Includes supplier premium deltas.</p>
                                    </div>
                                </div>
                                <dl className="mt-4 grid grid-cols-2 gap-4 text-xs text-muted-foreground">
                                    <div>
                                        <dt>Safety stock</dt>
                                        <dd className="text-sm font-semibold text-foreground">
                                            {scenarioResult.adjustedSafetyStock.toLocaleString()} units
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Lead time</dt>
                                        <dd className="text-sm font-semibold text-foreground">
                                            {scenarioResult.adjustedLeadTime} days
                                        </dd>
                                    </div>
                                </dl>
                                <p className="mt-4 text-xs text-muted-foreground">{scenarioResult.explanation}</p>
                                <Button className="mt-4 w-full" variant="default">
                                    Draft replenishment task
                                </Button>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

const RECOMMENDATIONS = [
    {
        title: 'Pull in Bracket 12A order by 4 days',
        subtitle: 'Supplier variance up 12% week-over-week',
        badgeLabel: 'Time sensitive',
        badgeVariant: 'destructive' as const,
        rationale: 'Supplier B ran a furnace calibration that added 3.2 days to actual lead time. Pull the PO window forward to maintain 95% service.',
    },
    {
        title: 'Split Spindle 44C to Hyperion 40%',
        subtitle: 'QA backlog still unresolved for Orion',
        badgeLabel: 'Dual source',
        badgeVariant: 'default' as const,
        rationale: 'Hyperion can absorb the expedite at +6% cost but trims variance from 6.1d to 3.2d, avoiding a maintenance shutdown.',
    },
    {
        title: 'Hold Seal kit 7F order until Jan 08',
        subtitle: 'Demand compression expected post campaign',
        badgeLabel: 'Defer spend',
        badgeVariant: 'secondary' as const,
        rationale: 'Telematics show failure rate dropping 18%; deferring frees $18k cash while staying 2.4 weeks above safety stock.',
    },
];

function RiskBadge({ level }: { level: PartForecastRow['risk'] }) {
    const intent =
        level === 'high' ? 'bg-rose-100 text-rose-700' : level === 'medium' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700';

    return <Badge className={`${intent} font-medium capitalize`}>{level} risk</Badge>;
}

function shiftDateString(input: string, deltaDays: number) {
    const date = new Date(input);
    if (Number.isNaN(date.getTime())) {
        return input;
    }
    date.setDate(date.getDate() + deltaDays);
    return date.toISOString().slice(0, 10);
}

function formatDate(input: string | null | undefined) {
    if (!input) {
        return '—';
    }
    const date = new Date(input);
    if (Number.isNaN(date.getTime())) {
        return input;
    }
    return new Intl.DateTimeFormat('en-US', { month: 'short', day: '2-digit', year: 'numeric' }).format(date);
}

function formatCurrency(value: number) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(value);
}
