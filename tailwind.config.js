import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    safelist: [
        'bg-emerald-50',
        'text-emerald-700',
        'ring-emerald-200',
        'dark:bg-emerald-950/40',
        'dark:text-emerald-200',
        'dark:ring-emerald-700/60',
        'bg-amber-50',
        'text-amber-700',
        'ring-amber-200',
        'dark:bg-amber-950/40',
        'dark:text-amber-200',
        'dark:ring-amber-700/60',
        'bg-rose-50',
        'text-rose-700',
        'ring-rose-200',
        'dark:bg-rose-950/40',
        'dark:text-rose-200',
        'dark:ring-rose-700/60',
        'bg-slate-50',
        'text-slate-700',
        'ring-slate-200',
        'dark:bg-slate-800',
        'dark:text-slate-200',
        'dark:ring-slate-600',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
