import { Helmet } from 'react-helmet-async';
import { Link, Navigate } from 'react-router-dom';
import { ArrowRight, CheckCircle2, Mail, Sparkles, Lock, Book, HardHat } from 'lucide-react';

import { Branding } from '@/config/branding';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/contexts/auth-context';

const HIGHLIGHTS = [
    {
        title: 'RFQ to invoice workflows',
        description: 'Coordinate sourcing, quotes, purchase orders, receiving, and invoice matching in one workspace.',
    },
    {
        title: 'Supplier discovery + risk',
        description: 'Centralize supplier profiles, risk signals, and digital twin assets with real-time visibility.',
    },
    {
        title: 'AI-assisted sourcing',
        description: 'Accelerate supplier shortlists, pricing analysis, and award decisions with trusted AI guidance.',
    },
];

const LEARN_MORE_LINKS = [
    {
        title: 'Security',
        description: 'Review platform security and compliance commitments.',
        href: 'https://elementssupplyai.com/security',
    },
    {
        title: 'Privacy',
        description: 'Understand how data is protected and processed.',
        href: 'https://elementssupplyai.com/privacy',
    },
];

export function LandingPage() {
    const { isAuthenticated, isLoading, requiresEmailVerification } = useAuth();

    if (isLoading) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-slate-950 text-white">
                <div className="flex items-center gap-3 text-sm text-slate-200">
                    <Sparkles className="h-4 w-4 animate-pulse text-brand-primary" />
                    <span>Loading workspace…</span>
                </div>
            </div>
        );
    }

    if (isAuthenticated) {
        if (requiresEmailVerification) {
            return <Navigate to="/verify-email" replace />;
        }

        return <Navigate to="/app" replace />;
    }

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100" style={{backgroundImage:`url('/3d-rendering-big-red-coronavirus-cell-left-with-black-background.jpg')`, backgroundSize:'cover', backgroundPosition:'center'}}>
            <Helmet>
                <title>Welcome • {Branding.name}</title>
            </Helmet>
            <div className="relative overflow-hidden">
                <div className="pointer-events-none absolute inset-0">
                    <div className="absolute -left-24 top-0 h-80 w-80 rounded-full bg-brand-primary/20 blur-3xl" />
                    <div className="absolute right-0 top-32 h-96 w-96 rounded-full bg-cyan-400/10 blur-3xl" />
                    <div className="absolute bottom-0 left-1/3 h-72 w-72 rounded-full bg-emerald-400/10 blur-3xl" />
                </div>

                <div className="relative mx-auto flex min-h-screen w-full max-w-8xl flex-col px-5 pb-8 pt-6 sm:px-8">
                    <header className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 backdrop-blur">
                        <div className="flex items-center gap-3">
                            <img src={Branding.logo.whiteText} alt={Branding.name} className="h-8" />
                        </div>
                        <nav className="flex items-center gap-6 text-sm text-slate-300">
                            <a href="https://elementssupplyai.com/resources-material-library" className="transition hover:text-white">
                                Material Library
                            </a>
                            <a href="https://elementssupplyai.com/resources-ebook-guide" className="transition hover:text-white">
                                E-Book Guide
                            </a>
                            <a href="https://elementssupplyai.com/contact" className="transition hover:text-white">
                                Contact
                            </a>
                            <Link to="/login" className="text-white transition hover:text-brand-primary">
                                Login
                            </Link>
                        </nav>
                    </header>

                    <main className="flex flex-1 flex-col gap-8 py-8 lg:flex-row lg:items-center">
                        <section className="max-w-2xl space-y-6">
                            <div className="py-1 text-xs font-medium text-[#4e7aff]">
                                <span>Welcome to {Branding.name}</span>
                            </div>
                            <h1 className="text-4xl font-semibold leading-tight text-white sm:text-5xl">
                                Orchestrate procurement, supplier intelligence, and digital twins in one AI-driven platform.
                            </h1>
                            <p className="text-base text-slate-300 sm:text-lg">
                                {Branding.description} Launch RFQs, compare quotes, and manage orders with a single source of truth
                                across every tenant.
                            </p>
                            <div className="flex flex-wrap gap-3">
                                <Button asChild size="lg" className="gap-2">
                                    <Link to="/register">
                                        Sign up
                                        <ArrowRight className="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button asChild size="lg" variant="outline" className="text-white bg-[#2d54c9] border-none hover:bg-[#2d54c9] hover:text-white">
                                    <Link to="/login">Login</Link>
                                </Button>
                            </div>
                            <p className="text-xs text-slate-400">
                                Access is available to approved organizations and invited suppliers.
                            </p>
                        </section>

                        <section className="relative w-full max-w-md overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/10 via-slate-900/70 to-slate-950/90 p-6 shadow-2xl backdrop-blur">
                            <div className="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-brand-primary/20 blur-3xl" />
                            <div className="pointer-events-none absolute -bottom-12 left-6 h-32 w-32 rounded-full bg-cyan-400/10 blur-3xl" />

                            <div className="relative flex items-start gap-4">
                                <div className="rounded-2xl border border-white/10 bg-white/10 p-3 text-brand-primary shadow-lg">
                                    <Sparkles className="h-5 w-5" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-white">AI workflow highlights</p>
                                    <p className="text-sm text-slate-300">
                                        Intelligent copilots surface pricing, lead-time, and risk insights in real time.
                                    </p>
                                </div>
                            </div>

                            <div className="relative mt-5 grid gap-3">
                                {HIGHLIGHTS.map((item, index) => (
                                    <div
                                        key={item.title}
                                        className="group flex gap-3 rounded-2xl border border-white/10 bg-slate-950/60 p-4 transition hover:border-white/20 hover:bg-slate-900/70"
                                    >
                                        <div className="mt-0.5 rounded-full bg-emerald-500/10 p-1 text-emerald-300 h-fit">
                                            <CheckCircle2 className="h-4 w-4" />
                                        </div>
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs font-semibold text-slate-400">0{index + 1}</span>
                                                <p className="text-sm font-semibold text-white">{item.title}</p>
                                            </div>
                                            <p className="text-xs text-slate-300">{item.description}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </main>

                    <section
                        id="learn-more"
                        className="mt-auto border-t border-white/10 pt-6 text-sm text-slate-300"
                    >
                        <div className="flex flex-wrap items-center gap-6">
                            <a
                                href="https://elementssupplyai.com/feature-supplier-matching"
                                className="flex items-center gap-2 text-slate-400 transition hover:text-white"
                            >
                                <HardHat className="h-4 w-4 text-brand-primary" />
                                <span>Supplier Matching</span>
                            </a>
                            <a href="https://elementssupplyai.com/contact" className="flex items-center gap-2 text-slate-400 transition hover:text-white">
                                <Mail className="h-4 w-4 text-brand-primary" />
                                <span>Contact us</span>
                            </a>
                            <Link to="/register" className="flex items-center gap-2 text-slate-400 transition hover:text-white">
                                <ArrowRight className="h-4 w-4 text-brand-primary" />
                                <span>Create a workspace</span>
                            </Link>
                            <a
                                href="https://elementssupplyai.com/privacy"
                                className="flex items-center gap-2 text-slate-400 transition hover:text-white"
                            >
                                <Lock className="h-4 w-4 text-brand-primary" />
                                <span>Privacy</span>
                            </a>
                            <a
                                href="https://elementssupplyai.com/terms"
                                className="flex items-center gap-2 text-slate-400 transition hover:text-white"
                            >
                                <Book className="h-4 w-4 text-brand-primary" />
                                <span>Terms</span>
                            </a>
                        </div>
                    </section>

                    <footer className="mt-6 border-t border-white/10 pt-4 text-xs text-slate-500">
                        © {new Date().getFullYear()} {Branding.name}. All rights reserved.
                    </footer>
                </div>
            </div>
        </div>
    );
}
