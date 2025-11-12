/* eslint-env node */
/* global module */
/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,ts,jsx,tsx}',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    primary: '#0E2230',
                    accent: '#0073B1',
                    success: '#1A9E55',
                    warning: '#E5A50A',
                    danger: '#D92D20',
                    background: '#F5F7FA',
                    card: '#FFFFFF',
                    border: '#E1E4E8',
                    text: {
                        primary: '#0E2230',
                        secondary: '#6B7280',
                    },
                },
            },
            fontFamily: {
                sans: ['"Manrope"', '"Inter"', 'system-ui', 'sans-serif'],
            },
            spacing: {
                18: '4.5rem',
                22: '5.5rem',
                26: '6.5rem',
                30: '7.5rem',
            },
        },
    },
};
