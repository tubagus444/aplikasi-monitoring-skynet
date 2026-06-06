import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import daisyui from 'daisyui';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './vendor/robsontenorio/mary/src/View/Components/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms, daisyui],

    // Catatan: tema "skynet" & "skynet-dark" TIDAK didefinisikan di sini.
    // DaisyUI v5 tidak membaca `daisyui.themes` dari tailwind.config.js (itu format v4).
    // Sumber kebenaran tema ada di resources/css/app.css sebagai CSS variables --color-*.
};