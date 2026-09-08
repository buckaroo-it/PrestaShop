/*
 *
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0 Academic Free License (AFL 3.0)
 */
/** @type {import('tailwindcss').Config} */
module.exports = {
  important: '#app',
  content: [
    './src/**/*.php',
    './src/**/*.vue',
    './lang/**/*.json',
  ],
  theme: {
    fontFamily: {
      sans: ['Open Sans', 'sans-serif'],
      serif: ['Open Sans', 'serif'],
      display: ['Open Sans'],
      body: ['Open Sans'],
      // fa: ['Font Awesome 5 Pro']
    },
    extend: {
      colors: {
        background: '#F8FAFC',
        primary: '#CDF564',
        secondary: '#E6FAB2',
        thirdly: '#FBFBFC',
        fourthly: '#163255',
        fifthly: '#163255',
        sixthly: '#2B4867',
        seventhly: '#335579',
        eightly: '#d7d6d9'
      },
    },
  },
  plugins: [],
}
