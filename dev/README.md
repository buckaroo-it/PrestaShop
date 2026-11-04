# Admin configuration app

Vue 3 + Vite app behind the module's configuration page (Settings, Payment
methods, Verification methods, Order payment methods). The build writes the
shipped assets to `../views` (`views/js/buckaroo.vue.js`, the hashed
`vendor`/`i18n` chunks and `views/css/buckaroo3.vue.css`); commit them together
with the source change.

```bash
npm ci                 # install the pinned dependencies
npm run dev            # Vite dev server
npm run build          # build into ../views
npm test               # Vitest unit tests
npm run test:watch     # tests in watch mode
npm run test:coverage  # tests + coverage report in coverage/ (fails below the thresholds)
```

## Tests

- Specs live in `tests/` (`*.spec.js`), run in happy-dom with
  `@vue/test-utils`. Config: `vitest.config.js`.
- `vitest.config.js` is deliberately separate from `vite.config.js`: the build
  config has a plugin that deletes the hashed bundles in `views/js` when Vite
  starts, and Vitest starts Vite.
- `tests/setup.js` installs the real i18n plugin (English) for every mounted
  component.
- `tests/mocks/api.js` replaces `src/lib/api.ts`:

  ```js
  vi.mock('@/lib/api', () => import('../mocks/api'))
  respond('buckaroo_config_creditcards', { status: true, creditcards: [...] }, 'get')
  fail('buckaroo_config_settings', new Error('boom'), 'post')
  expect(callsTo('buckaroo_config_settings', 'post')[0].payload).toEqual({...})
  ```

- `tests/helpers.js`: `mountApp` / `mountAndSettle` mount a component with the
  values `Dashboard.vue` provides (`csrfToken`, `adminUrl`, `baseUrl`, `config`,
  `view`, `hasUnsavedChanges`, `initialRouteDetail`).

CI (`.github/workflows/tests.yml`, job "Admin UI tests") runs
`npm run test:coverage` on Node 22.

### Without Node on your machine

```bash
docker run --rm -v "$PWD/dev":/app/dev -v buckaroo-dev-node-modules:/app/dev/node_modules \
  -w /app/dev node:22 sh -c 'npm ci && npm run test:coverage'
```

Run it from the module root. The named volume keeps the Linux `node_modules`
apart from a local macOS/Windows install. On an arm64 machine (Apple Silicon)
add the native build binaries once, because `package-lock.json` only records
the x64 ones:

```bash
docker run --rm -v "$PWD/dev":/app/dev -v buckaroo-dev-node-modules:/app/dev/node_modules -w /app/dev node:22 \
  sh -c 'npm i --no-save "@rollup/rollup-linux-arm64-gnu@$(node -p "require(\"rollup/package.json\").version")" "@esbuild/linux-arm64@$(node -p "require(\"esbuild/package.json\").version")"'
```
