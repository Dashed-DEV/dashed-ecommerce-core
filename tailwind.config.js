const colors = require('tailwindcss/colors')

module.exports = {
    content: [
        './resources/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './vendor/Dashed-DEV/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                danger: colors.rose,
                primary: colors.orange,
                success: colors.green,
                warning: colors.yellow,
                // test: colors.yellow,
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
    ],
}
