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
        <div className="min-h-screen bg-slate-950 text-slate-100" style={{
            backgroundImage: 'url(/img/efa9c371-4ad2-49db-977f-098c4619ffc5-xxl.webp)',
            backgroundSize:'cover', 
            backgroundPosition:'bottom'
            }}>
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
                        <section className="space-y-6 text-center lg:text-left">
                            <div className="py-1 text-xs font-medium text-[#4e7aff]">
                                <span>Welcome to {Branding.name}</span>
                            </div>
                            <h1 className="text-3xl font-semibold leading-tight text-white sm:text-4xl lg:text-5xl">
                                Orchestrate procurement, supplier intelligence, and digital twins in one AI-driven platform.
                            </h1>
                            <p className="text-sm text-slate-300 sm:text-base lg:text-lg">
                                {Branding.description} Launch RFQs, compare quotes, and manage orders with a single source of truth
                                across every tenant.
                            </p>
                            <div className="flex flex-wrap justify-center gap-3 lg:justify-start">
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

                        <section className="relative w-full min-h-[250px] flex items-center justify-center lg:justify-normal sm:min-h-[350px] lg:min-h-[400px]">
                            <div className="relative w-full max-w-xs h-[250px] sm:max-w-md sm:h-[350px] lg:max-w-xl lg:h-[500px]">
                                <div className="absolute top-[5%] right-[5%] sm:left-[10%] animate-float-slow">
                                    <img 
                                        src="/img/parts/part-1-lg.webp" 
                                        alt="Machine part" 
                                        className="h-[250px] sm:h-[400px] lg:h-[600px] object-contain drop-shadow-2xl"
                                    />
                                </div>
                                <div className="absolute top-[25%] lg:top-[-5%] sm:top-[-10%] left-[5%] sm:left-[10%] animate-float-medium">
                                    <img 
                                        src="/img/parts/part-2-lg.webp" 
                                        alt="Machine part" 
                                        className="h-20 w-20 sm:h-32 sm:w-32 md:h-48 md:w-48 lg:h-52 lg:w-52 object-contain drop-shadow-2xl"
                                    />
                                </div>
                                <div className="absolute bottom-[-10%] sm:bottom-[-20%] left-[10%] sm:left-[15%] animate-float-fast">
                                    <img 
                                        src="/img/parts/part-3-lg.webp" 
                                        alt="Machine part" 
                                        className="h-24 w-24 sm:h-36 sm:w-36 md:h-52 md:w-52 lg:h-60 lg:w-60 object-contain drop-shadow-2xl"
                                    />
                                </div>
                                <div className="absolute hidden 2xl:block bottom-[15%] sm:bottom-[20%] right-[-15%] sm:right-[-30%] animate-float-slow">
                                    <img 
                                        src="/img/parts/part-4-lg.webp" 
                                        alt="Machine part" 
                                        className="h-[150px] sm:h-[200px] lg:h-[300px] object-contain drop-shadow-2xl"
                                    />
                                </div>
                            </div>
                            <style>{`
                                @keyframes float-slow {
                                    0%, 100% { transform: translateY(0px) rotate(0deg); }
                                    50% { transform: translateY(-12px) rotate(2deg); }
                                }
                                @keyframes float-medium {
                                    0%, 100% { transform: translateY(0px) rotate(0deg); }
                                    50% { transform: translateY(-18px) rotate(-2deg); }
                                }
                                @keyframes float-fast {
                                    0%, 100% { transform: translateY(0px) rotate(0deg); }
                                    50% { transform: translateY(-10px) rotate(1deg); }
                                }
                                .animate-float-slow {
                                    animation: float-slow 4s ease-in-out infinite;
                                }
                                .animate-float-medium {
                                    animation: float-medium 3s ease-in-out infinite;
                                }
                                .animate-float-fast {
                                    animation: float-fast 2.5s ease-in-out infinite;
                                }
                            `}</style>
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
