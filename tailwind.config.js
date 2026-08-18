import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                nuit: '#02040C',
                azur: {
                    DEFAULT: '#12579B',
                    dark: '#0B3A68',
                },
                alerte: {
                    DEFAULT: '#E31E24',
                    dark: '#A5151A',
                },
                ambre: {
                    DEFAULT: '#FDC105',
                    dark: '#8A6600',
                },
                argent: '#D8DCE3',
                sonar: {
                    DEFAULT: '#12877F',
                    dark: '#0B5F59',
                },
                laiton: '#C99A2E',
            },
        },
    },

    plugins: [forms],
};