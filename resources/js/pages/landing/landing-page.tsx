import { ArrowRight, Book, HardHat, Lock, Mail, Sparkles } from 'lucide-react';
import { Helmet } from 'react-helmet-async';
import { Link, Navigate } from 'react-router-dom';

import { Button } from '@/components/ui/button';
import { Branding } from '@/config/branding';
import { useAuth } from '@/contexts/auth-context';

export function LandingPage() {
    const { isAuthenticated, isLoading, requiresEmailVerification } = useAuth();

    if (isLoading) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-slate-950 text-white">
                <div className="flex items-center gap-3 text-sm text-slate-200">
                    <Sparkles className="text-brand-primary h-4 w-4 animate-pulse" />
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
        <div
            className="min-h-screen bg-slate-950 text-slate-100"
            style={{
                backgroundImage:
                    'url(/img/efa9c371-4ad2-49db-977f-098c4619ffc5-xxl.webp)',
                backgroundSize: 'cover',
                backgroundPosition: 'bottom',
            }}
        >
            <Helmet>
                <title>Welcome • {Branding.name}</title>
            </Helmet>
            <div className="relative overflow-hidden">
                <div className="pointer-events-none absolute inset-0">
                    <div className="bg-brand-primary/20 absolute top-0 -left-24 h-80 w-80 rounded-full blur-3xl" />
                    <div className="absolute top-32 right-0 h-96 w-96 rounded-full bg-cyan-400/10 blur-3xl" />
                    <div className="absolute bottom-0 left-1/3 h-72 w-72 rounded-full bg-emerald-400/10 blur-3xl" />
                </div>

                <div className="max-w-8xl relative mx-auto flex min-h-screen w-full flex-col px-5 pt-6 pb-8 sm:px-8">
                    <header className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3 backdrop-blur">
                        <div className="flex items-center gap-3">
                            <img
                                src={Branding.logo.whiteText}
                                alt={Branding.name}
                                className="h-8"
                            />
                        </div>
                        <nav className="flex items-center gap-6 text-sm text-slate-300">
                            <a
                                href="https://elementssupplyai.com/resources-material-library"
                                className="transition hover:text-white"
                            >
                                Material Library
                            </a>
                            <a
                                href="https://elementssupplyai.com/resources-ebook-guide"
                                className="transition hover:text-white"
                            >
                                E-Book Guide
                            </a>
                            <a
                                href="https://elementssupplyai.com/contact"
                                className="transition hover:text-white"
                            >
                                Contact
                            </a>
                            <Link
                                to="/login"
                                className="hover:text-brand-primary text-white transition"
                            >
                                Login
                            </Link>
                        </nav>
                    </header>

                    <main className="flex flex-1 flex-col gap-8 py-8 lg:flex-row lg:items-center">
                        <section className="space-y-6 text-center lg:text-left">
                            <div className="py-1 text-xs font-medium text-[#4e7aff]">
                                <span>Welcome to {Branding.name}</span>
                            </div>
                            <h1 className="text-3xl leading-tight font-semibold text-white sm:text-4xl lg:text-5xl">
                                Orchestrate procurement, supplier intelligence,
                                and digital twins in one AI-driven platform.
                            </h1>
                            <p className="text-sm text-slate-300 sm:text-base lg:text-lg">
                                {Branding.description} Launch RFQs, compare
                                quotes, and manage orders with a single source
                                of truth across every tenant.
                            </p>
                            <div className="flex flex-wrap justify-center gap-3 lg:justify-start">
                                <Button asChild size="lg" className="gap-2">
                                    <Link to="/register">
                                        Sign up
                                        <ArrowRight className="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button
                                    asChild
                                    size="lg"
                                    variant="outline"
                                    className="border-none bg-[#2d54c9] text-white hover:bg-[#2d54c9] hover:text-white"
                                >
                                    <Link to="/login">Login</Link>
                                </Button>
                            </div>
                            <p className="text-xs text-slate-400">
                                Access is available to approved organizations
                                and invited suppliers.
                            </p>
                        </section>

                        <section className="relative flex min-h-[250px] w-full items-center justify-center sm:min-h-[350px] lg:min-h-[400px] lg:justify-normal">
                            <div className="relative h-[250px] w-full max-w-xs sm:h-[350px] sm:max-w-md lg:h-[500px] lg:max-w-xl">
                                <div className="animate-float-slow absolute top-[5%] right-[5%] sm:left-[10%]">
                                    <img
                                        src="/img/parts/part-1-lg.webp"
                                        alt="Machine part"
                                        className="h-[250px] object-contain drop-shadow-2xl sm:h-[400px] lg:h-[600px]"
                                    />
                                </div>
                                <div className="animate-float-medium absolute top-[25%] left-[5%] sm:top-[-10%] sm:left-[10%] lg:top-[-5%]">
                                    <img
                                        src="/img/parts/part-2-lg.webp"
                                        alt="Machine part"
                                        className="h-20 w-20 object-contain drop-shadow-2xl sm:h-32 sm:w-32 md:h-48 md:w-48 lg:h-52 lg:w-52"
                                    />
                                </div>
                                <div className="animate-float-fast absolute bottom-[-10%] left-[10%] sm:bottom-[-20%] sm:left-[15%]">
                                    <img
                                        src="/img/parts/part-3-lg.webp"
                                        alt="Machine part"
                                        className="h-24 w-24 object-contain drop-shadow-2xl sm:h-36 sm:w-36 md:h-52 md:w-52 lg:h-60 lg:w-60"
                                    />
                                </div>
                                <div className="animate-float-slow absolute right-[-15%] bottom-[15%] hidden sm:right-[-30%] sm:bottom-[20%] 2xl:block">
                                    <img
                                        src="/img/parts/part-4-lg.webp"
                                        alt="Machine part"
                                        className="h-[150px] object-contain drop-shadow-2xl sm:h-[200px] lg:h-[300px]"
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
                                <HardHat className="text-brand-primary h-4 w-4" />
                                <span>Supplier Matching</span>
                            </a>
                            <a
                                href="https://elementssupplyai.com/contact"
                                className="flex items-center gap-2 text-slate-400 transition hover:text-white"
                            >
                                <Mail className="text-brand-primary h-4 w-4" />
                                <span>Contact us</span>
                            </a>
                            <Link
                                to="/register"
                                className="flex items-center gap-2 text-slate-400 transition hover:text-white"
                            >
                                <ArrowRight className="text-brand-primary h-4 w-4" />
                                <span>Create a workspace</span>
                            </Link>
                            <a
                                href="https://elementssupplyai.com/privacy"
                                className="flex items-center gap-2 text-slate-400 transition hover:text-white"
                            >
                                <Lock className="text-brand-primary h-4 w-4" />
                                <span>Privacy</span>
                            </a>
                            <a
                                href="https://elementssupplyai.com/terms"
                                className="flex items-center gap-2 text-slate-400 transition hover:text-white"
                            >
                                <Book className="text-brand-primary h-4 w-4" />
                                <span>Terms</span>
                            </a>
                        </div>
                    </section>

                    <footer className="mt-6 border-t border-white/10 pt-4 text-xs text-slate-500">
                        © {new Date().getFullYear()} {Branding.name}. All rights
                        reserved.
                    </footer>
                </div>
            </div>
        </div>
    );
}
