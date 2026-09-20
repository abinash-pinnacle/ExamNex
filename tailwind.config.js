/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './resources/views/**/*.blade.php',
        './app/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            colors: {
                // Dynamic brand colour driven by CSS variables (set per-request from Settings).
                brand: {
                    DEFAULT: 'rgb(var(--brand-rgb) / <alpha-value>)',
                    dark: 'rgb(var(--brand-dark-rgb) / <alpha-value>)',
                    ink: '#0f2547',
                },
            },
        },
    },
    plugins: [],
};
