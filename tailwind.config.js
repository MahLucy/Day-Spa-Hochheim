/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        teal: {
          50:  '#f0fdfa',
          100: '#ccfbf1',
          200: '#99f6e4',
          300: '#5eead4',
          400: '#2dd4bf',
          500: '#14b8a6',
          600: '#0d9488',
          700: '#0f766e',
          800: '#115e59',
          900: '#134e4a',
        },
        spa: {
          dark:    '#004567',
          mid:     '#1B7C9F',
          light:   '#4a9bb5',
          accent:  '#92DEFD',
          pale:    '#e8f4f6',
          cream:   '#faf8f5',
          text:    '#2c3e50',
          muted:   '#7f8c8d',
        }
      },
      fontFamily: {
        display: ['Poppins', 'sans-serif'],
        body:    ['Poppins', 'sans-serif'],
      },
      boxShadow: {
        'soft':  '0 2px 20px rgba(0,0,0,0.06)',
        'card':  '0 4px 30px rgba(0,0,0,0.08)',
        'hover': '0 8px 40px rgba(0,0,0,0.12)',
      },
      borderRadius: {
        'xl2': '1.25rem',
        'xl3': '1.5rem',
      }
    },
  },
  plugins: [],
}
