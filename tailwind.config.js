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
    // Delete toast classes
    'bg-red-50', 'border-red-200', 'text-red-800', 'text-red-700', 'bg-red-50', 'text-red-500', 'hover:bg-red-100', 'focus:ring-offset-red-50', 'focus:ring-red-600',
    'toast-message', 'close-toast',
    // Success message classes
    'px-4', 'py-3', 'rounded', 'mb-4',
    // Modal classes
    'fixed', 'inset-0', 'bg-black', 'bg-opacity-50', 'overflow-y-auto', 'h-full', 'w-full', 'hidden', 'z-50', 'flex', 'items-center', 'justify-center', 'p-4',
    'relative', 'mx-auto', 'max-w-4xl', 'max-h-[90vh]', 'overflow-hidden', 'shadow-xl', 'rounded-lg', 'bg-white',
    'hover:shadow-lg', 'hover:border-indigo-300', 'transition-all', 'duration-200',
    'bg-gradient-to-r', 'from-indigo-50', 'to-blue-50', 'border-indigo-100',
    'w-24', 'h-32', 'rounded-lg', 'shadow-sm', 'line-clamp-2', 'leading-tight', 'justify-center', 'text-center', 'mb-1', 'text-base', 'mb-3', 'px-3', 'py-1', 'bg-green-100', 'text-green-800', 'inline-flex', 'items-center', 'bg-indigo-50', 'text-indigo-700',
    // Tab classes
    'tab-button', 'active', 'border-indigo-500', 'text-indigo-600', 'border-transparent', 'text-gray-500', 'tab-content', 'hidden',
    // Disabled button classes
    'disabled:opacity-50', 'disabled:cursor-not-allowed'
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
}




