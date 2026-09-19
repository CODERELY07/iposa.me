import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Instrument Serif"', ...defaultTheme.fontFamily.serif],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Warm neutrals: food businesses, not a bank.
                ink: colors.stone,
                // Single brand accent: saffron. Money direction uses emerald / rose only.
                brand: {
                    50: '#fff9eb',
                    100: '#ffefc6',
                    200: '#ffdc88',
                    300: '#ffc44a',
                    400: '#ffad20',
                    500: '#f98b07',
                    600: '#dd6602',
                    700: '#b74506',
                    800: '#94350c',
                    900: '#7a2c0d',
                    950: '#461402',
                },
                gain: colors.emerald,
                loss: colors.rose,
            },
        },
    },

    plugins: [forms],
};
