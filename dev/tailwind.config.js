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
  content: [
    './src/**/*.php',
    './src/**/*.vue',
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
        // primary: '#CDD905',
        // secondary: '#DAE605',
        background: '#F8FAFC',
        primary: '#273A8A',
        secondary: '#4b71fc',
        thirdly: '#FBFBFC',
        fourthly: '#ECB390',
        fifthly: '#34313F',
        sixthly: '#2A2833',
        seventhly: '#44414F',
        eightly: '#d7d6d9'
      },
    },
  },
  plugins: [],
}
