# Phase 04 — Frontend foundation

**Duration**: 1.5–2 d
**Tier**: any
**Required reading**: `CLAUDE.md` §§ 6–7, `docs/ARCHITECTURE.md` § 10

## Goal

Layout, navigation, i18n, dark/light, responsive design system. After this phase every page is reachable, bilingual, and looks consistent on mobile and desktop.

## Definition of Done

- [ ] All Inertia pages routable: `/sk/...` and `/en/...` for `/`, `/console`, `/pendulum`, `/ball-beam`, `/logs`, `/api-docs`, `/stats`
- [ ] Each page wrapped in `<AppLayout>` with header, nav, footer
- [ ] Language switcher swaps `/sk` ↔ `/en` preserving the rest of the path (and query/hash)
- [ ] Dark/light mode persists in `localStorage`, respects `prefers-color-scheme` initially
- [ ] Mobile nav (hamburger) under 768 px
- [ ] axe-core: zero critical/serious issues on every page in both themes
- [ ] Tailwind tokens defined in CSS variables; no `[#...]` arbitrary colours in JSX
- [ ] Vitest setup with first component test passing
- [ ] ESLint rule (or custom check) flags inline string literals in JSX outside of `useT()`

## Prerequisites

Phase 01 complete. Phase 03 useful but not required.

## Tasks

### 4.1 Locale-prefixed routes

```php
Route::prefix('{locale}')
    ->where(['locale' => 'sk|en'])
    ->middleware(SetLocale::class)
    ->group(function () {
        Route::get('/', HomePage::class)->name('home');
        Route::get('/console', ConsolePage::class)->name('console');
        Route::get('/pendulum', PendulumPage::class)->name('pendulum');
        Route::get('/ball-beam', BallBeamPage::class)->name('ball-beam');
        Route::get('/logs', LogsPage::class)->name('logs');
        Route::get('/api-docs', ApiDocsPage::class)->name('api-docs');
        Route::get('/stats', StatsPage::class)->name('stats');
    });

Route::get('/', fn () => redirect('/' . request()->cookie('locale', 'sk')));
```

`SetLocale` middleware reads `{locale}`, calls `App::setLocale()`, sets the cookie if it differs.

### 4.2 i18n strings

`resources/js/i18n/sk.ts`:

```typescript
export const sk = {
  nav: { home: 'Domov', console: 'Konzola', pendulum: 'Inverzné kyvadlo',
         ballBeam: 'Gulička na tyči', logs: 'Logy', apiDocs: 'API dokumentácia',
         stats: 'Štatistika' },
  // ...
} as const;

export type Translation = typeof sk;
```

`en.ts` mirrors the structure exactly. TypeScript enforces parity via `Translation`.

`useT()` hook reads the current locale from Inertia shared props and returns the matching object.

### 4.3 Layout + Header + Footer

`resources/js/Layouts/AppLayout.tsx`:

```typescript
type AppLayoutProps = { children: ReactNode; title?: string };

export default function AppLayout({ children, title }: AppLayoutProps) {
  return (
    <div className="min-h-screen bg-surface text-on-surface">
      <Head title={title} />
      <Header />
      <main className="container mx-auto px-4 py-8">{children}</main>
      <Footer />
    </div>
  );
}
```

`Header` includes nav links, hamburger for mobile, `LanguageSwitcher`, `ThemeToggle`.

`LanguageSwitcher`:

```typescript
export function LanguageSwitcher() {
  const { url } = usePage();
  const switchTo = (target: 'sk' | 'en') => {
    const newUrl = url.replace(/^\/(sk|en)/, `/${target}`);
    router.visit(newUrl, { preserveScroll: true, preserveState: true });
  };
  return (
    <div className="flex gap-1">
      <button onClick={() => switchTo('sk')}>SK</button>
      <button onClick={() => switchTo('en')}>EN</button>
    </div>
  );
}
```

### 4.4 Theme tokens

Tailwind 4 with CSS vars + `data-theme` on `<html>`:

```css
:root {
  --color-surface: #ffffff;
  --color-on-surface: #0f172a;
  --color-primary: #3b82f6;
}
[data-theme="dark"] {
  --color-surface: #0f172a;
  --color-on-surface: #f8fafc;
}
```

Tailwind config maps `bg-surface`, `text-on-surface`, etc. to these.

`useTheme()` hook persists in `localStorage` and respects `prefers-color-scheme` initially.

### 4.5 UI primitives

`resources/js/components/ui/`:

- `Button` (variant `primary | secondary | ghost`, size `sm | md | lg`)
- `Card`
- `Input`, `Textarea`, `Label`, `FieldError`
- `Badge`, `Spinner`, `Modal`
- `Skeleton` (for loading states)

Each gets a Vitest test for rendering and basic interaction.

### 4.6 Responsive

Tailwind defaults (`sm 640`, `md 768`, `lg 1024`, `xl 1280`). Mobile-first. Open Chrome DevTools at 375 px and walk every page.

### 4.7 a11y

- Every interactive element has an accessible name
- `focus-visible:ring` everywhere
- Contrast ≥ 4.5:1 in both themes
- Skip-to-main-content link
- `<html lang>` matches the current locale

Add an axe check via Playwright:

```json
"a11y": "playwright test tests/a11y.spec.ts"
```

### 4.8 i18n drift guard

ESLint rule catches inline string literals in JSX. Custom plugin or `eslint-plugin-i18next`. Whitelist non-text usages (test IDs, class names) explicitly.

## Quality gates

- [ ] `npm run qa` green
- [ ] Manual run-through in Chrome and Firefox at 375 px and 1440 px
- [ ] Language switcher works on every page (no 404s)
- [ ] axe-core: zero critical/serious
- [ ] First Vitest test green

## Risks

| Risk | Mitigation |
|---|---|
| i18n drift | ESLint rule catches inline strings; CI enforces |
| Tailwind 4 syntax differences from v3 | Read migration guide once at the start |

## Hand-off to next phase

Phase 05 (console), 06/07 (animations), 08 (api-docs), 09 (stats) need the layout, theme tokens, UI primitives, i18n hook.

## Agent brief (copy-paste)

> Read `CLAUDE.md` §§ 6–7, `docs/ARCHITECTURE.md` § 10, and this phase markdown.
>
> Build:
> 1. Locale-prefixed route group + `SetLocale` middleware
> 2. `resources/js/i18n/{sk,en}.ts` with the `Translation` type enforcing parity
> 3. `resources/js/Layouts/AppLayout.tsx` + `Header` / `Footer` / `LanguageSwitcher` / `ThemeToggle`
> 4. UI primitives in `resources/js/components/ui/` with Vitest tests
> 5. Tailwind 4 theme tokens for light/dark
> 6. Stub Inertia pages for every route returning `<AppLayout>`
>
> Constraints: functional components only, default export the component, props as `type ${Name}Props`, no inline strings in JSX (all via `useT()`), no `any`.
>
> Run `npm run qa`. Manually verify language switcher and theme toggle on every page. axe-core report attached to PR.
>
> PR labelled `phase:04`.
