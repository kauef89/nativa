/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./assets/src/**/*.{vue,js,ts,jsx,tsx}",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        // Paleta Primary: Blue (Azul Padrão)
        primary: {
          50: "#eff6ff",
          100: "#dbeafe",
          200: "#bfdbfe",
          300: "#93c5fd",
          400: "#60a5fa",
          500: "#3b82f6", // Azul Vibrante
          600: "#2563eb",
          700: "#1d4ed8",
          800: "#1e40af",
          900: "#1e3a8a",
          950: "#172554",
        },

        // Surfaces Material You (Stone - Quente)
        "surface-base": "#0c0a09", // Stone 950
        "surface-1": "#1c1917", // Stone 900
        "surface-2": "#292524", // Stone 800
        "surface-3": "#44403c", // Stone 700
        "surface-4": "#57534e", // Stone 600

        // Compatibilidade Legada (Escala Stone)
        surface: {
          0: "#ffffff",
          50: "#fafaf9",
          100: "#f5f5f4",
          200: "#e7e5e4",
          300: "#d6d3d1",
          400: "#a8a29e",
          500: "#78716c",
          600: "#57534e",
          700: "#44403c",
          800: "#292524",
          900: "#1c1917",
          950: "#0c0a09",
        },
      },
      fontFamily: {
        sans: ["Nunito", "sans-serif"],
      },
    },
  },
  plugins: [],
};
