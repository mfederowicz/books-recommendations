/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./templates/**/*.{html,twig}",
    "./src/**/*.{php,html,twig}",
    "./public/**/*.{html,twig}",
  ],
  safelist: [
    // Toast message classes that might not be detected
    'bg-green-50', 'border-green-200', 'text-green-400', 'text-green-800', 'text-green-700', 'bg-green-100', 'text-green-500', 'focus:ring-green-600', 'focus:ring-offset-green-50',
    'bg-red-50', 'border-red-200', 'text-red-400', 'text-red-800', 'text-red-700', 'bg-red-100', 'text-red-500', 'focus:ring-red-600', 'focus:ring-offset-red-50',
    'toast-message', 'close-toast'
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
}




