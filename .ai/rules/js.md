---
paths:
    - 'resources/js/**'
---

# Js

> Diadaptasi dari repo Landing (`icsa2026-app`) saat Project setup, 2026-09-29. Bagian GSAP/ScrollSmoother/section landing tidak dibawa.

## UI copy full English

Semua teks yang tampil di UI (label, tombol, heading, toast, empty state, locale `Intl.DateTimeFormat`) berbahasa Inggris. Tidak berlaku untuk data konten dari deck (deskripsi kategori, kriteria rubrik — tampilkan apa adanya) dan dokumen internal (`.ai/`, brief).

## State yang bergantung waktu dihitung setelah mount

SSR Inertia masih aktif di config starter. Komponen yang state-nya bergantung `Date.now()` (mis. sisa waktu deadline di dashboard peserta) mulai dari `null`/placeholder dan dihitung di `useEffect`, jangan di render/`useState(() => ...)` — kalau tidak, hydration mismatch.

## Jangan spread page prop ke komponen kecuali bentuknya cocok

`<Comp {...pageProp} />` hanya benar kalau `pageProp` objek yang key-nya sama dengan props komponen. Array/collection datar di-spread menghasilkan prop `undefined` tanpa error TypeScript (tipe `Props` halaman ditulis tangan). Untuk list datar, kirim sebagai prop bernama (`<List items={items} />`).

## Regenerate Wayfinder dengan `--with-form` kalau dijalankan manual

`vite.config.ts` memakai `wayfinder({ formVariants: true })`. `php artisan wayfinder:generate` tanpa flag menimpa `resources/js/routes|actions` TANPA `.form` dan membuat `tsc` gagal di halaman auth/settings. Pakai `php artisan wayfinder:generate --with-form`, atau biarkan plugin Vite yang regenerate.

## Every data table uses the shared `useAppTable` infra (`hooks/table.ts`), not a one-off `useTable` call — installed version is v9, NOT the classic v8 API

Any table-based UI (admin CRUD list pages — Judges, Committees, Categories, etc. — and any other feature needing a data table) must use `@tanstack/react-table` (installed, headless table library) via the shared infra below, not a hand-rolled `<table>`, a page-local `useTable()` call, or another table library.

**The installed version is `@tanstack/react-table@9.x`** — a major rewrite from the classic v8 API most online examples (including the well-known "Kitchen Sink — Shadcn Base" example) target. `useReactTable`, `getCoreRowModel()`/`getSortedRowModel()`, and `flexRender()` do not exist in v9. The package ships its own bundled Claude Skills at `node_modules/@tanstack/table-core/skills/*` and `node_modules/@tanstack/react-table/skills/*` — read `getting-started`/`create-table-hook`/`migrate-v8-to-v9` there rather than trusting memory or a v8 web example if an API surface seems missing.

**The reusable infra** (ported from TanStack's real "Kitchen Sink — Shadcn Base" example source on GitHub, ~2500 lines, ported 2026-09-18 session) lives in:

- `resources/js/hooks/features.ts` — the full `tableFeatures({...})` registration: sorting, filtering, faceting, column ordering/visibility/sizing/resizing/pinning, grouping+aggregation, pagination, row selection, row expansion, global fuzzy filter (`@tanstack/match-sorter-utils`). Shared across every table — don't fork a second `features.ts` per feature.
- `resources/js/hooks/table-contexts.ts` — scoped context factory (`createTableHookContexts<typeof features>()`) that exports `tableContext`, `cellContext`, `headerContext`, and the `useTableContext`, `useCellContext`, `useHeaderContext` hooks. This exists explicitly to eliminate circular import dependencies with `components/data-table/*`. All `components/data-table/*` components import context hooks from `@/hooks/table-contexts`.
- `resources/js/hooks/table.ts` — `createTableHook({...})` factory exporting `useAppTable` and `createAppColumnHelper`, wired to the scoped contexts and registered component maps.
- `resources/js/components/data-table/*` — `DataTableSearch` (rendered via `table.Search`), `DataTableFilterList` (via `table.FilterList`), `DataTableSortList` (via `table.SortList`), `DataTableViewOptions` (via `table.ViewOptions`), `DataTablePagination` (via `table.Pagination`), `ColumnHeader` (via `header.ColumnHeader`), and generic cells `SelectCell`/`TextCell`/`DateCell`/`GroupedCell` (via `cell.X`) + `SelectAllHeader`/`ResizeHandle` (via `header.X`). Any component inspecting table state must use `<Subscribe source={table.store} selector={...}>` to remain reactive.
- `resources/js/components/ui/{popover,calendar,command,faceted,sortable}.tsx` + `resources/js/lib/{data-table,composition,utils}.ts` + `resources/js/types/data-table.ts` — supporting primitives/filter-operator engine/types the infra depends on.

**A per-feature page only writes**: a `columns.tsx` (e.g. `resources/js/pages/admin/judges/columns.tsx`) via `createAppColumnHelper<RowType>().columns([...])`, referencing the shared cell/header components (`({ header }) => <header.ColumnHeader />`, `({ cell }) => <cell.TextCell />`) plus any genuinely domain-specific cell (e.g. Judges' `PhotoCell`), and the page itself calling `useAppTable({ columns, data })`.

**Rendering headers and cells in index.tsx — DO NOT use `table.FlexRender`!**:
`table.FlexRender` does NOT exist on the `table` object (it evaluates to `undefined` and crashes SSR/client render). Furthermore, header components (`header.ColumnHeader`) and cell components (`cell.TextCell`) require their respective context providers (`table.AppHeader` and `table.AppCell`).
Always render headers and cells like this (reference implementation di repo Landing: `icsa2026-app/resources/js/pages/admin/judges/index.tsx`):

```tsx
// Header:
<TableHead key={header.id}>
    {header.isPlaceholder ? null : (
        <table.AppHeader header={header}>
            {(h) => <h.FlexRender />}
        </table.AppHeader>
    )}
</TableHead>

// Cell:
<TableCell key={cell.id}>
    <table.AppCell cell={cell}>
        {(c) => <c.FlexRender />}
    </table.AppCell>
</TableCell>
```

**Critical constraint — do not install `@base-ui/react` or duplicate an existing Radix primitive.** The upstream example's shadcn primitives (`dialog.tsx`, `select.tsx`, `dropdown-menu.tsx`, `popover.tsx`, `checkbox.tsx`, `tooltip.tsx`) are built on `@base-ui/react/*` (`<Trigger render={<Button/>}>` idiom), but every primitive already in `resources/js/components/ui/` in this project is `@radix-ui/react-*`-based (`asChild` idiom). Per the existing "check for an already-installed shadcn primitive" rule below, the ported infra was rewritten to use this project's existing Radix `Dialog`/`Select`/`DropdownMenu`/`Checkbox`/`Tooltip` instead — every `render={<X/>}>children` call site became `asChild><X>children</X>`, and Radix's `Checkbox` uses its tri-state `checked={true|false|'indeterminate'}` prop, not a separate `indeterminate` prop. Only `Popover`, `Calendar`, `Command`, `Faceted` (custom compound, Popover+Command+Checkbox), and `Sortable` (dnd-kit wrapper) were net-new — those didn't exist in the project before. Never reintroduce `@base-ui/react` or add a second Dialog/Select/DropdownMenu/Checkbox/Tooltip — always adapt new ported code to the existing Radix ones.

When scaffolding a genuinely new shadcn primitive via `npx shadcn add <name>`, verify the generated file imports `@radix-ui/react-*` (or the unified `radix-ui` package) and not `@base-ui/react/*` before wiring anything to it — read the file right after generation. Also fix the CLI's occasional wrong `cn` import (`from "cn"` instead of `from "@/lib/utils"`) — happened for `table.tsx`, `popover.tsx`, `calendar.tsx`, and `command.tsx` in this project so far.

## Validasi per langkah wizard: `useHttp` + `transform`, data tetap di satu `useForm`

Wizard multi-step (Sign Up) menyimpan semua field di satu `useForm`; tombol "Next" memanggil endpoint validasi step lewat `useHttp` dengan `stepValidation.transform(() => form.data)` lalu `post(...)` — `transform` berbasis ref jadi langsung berlaku. 422 → `onError(errors)` (promise resolve `undefined`, tidak throw) → salin ke `form.setError`. Submit akhir tetap `form.post` ke route Fortify; saat error, lompat ke step pertama yang punya error. Precognition tidak dipakai karena `CreateNewUser` Fortify bukan FormRequest. (Sign Up, 2026-09-29.)

## Tabel server-side = `hooks/use-server-table.ts`, bukan `useAppTable` langsung

Tabel yang datanya dipaginasi Laravel memakai `useServerTable({ url, prop, columns, paginator, filters, defaultSort })`. Hook ini menjalankan `useAppTable` dengan `manualPagination/Sorting/Filtering` dan `rowCount = paginator.total`. State pagination/sort/search dikontrol dari prop `filters` server; perubahan dikirim lewat `router.get(..., { preserveState, replace, only: [prop, 'filters'] })`, dengan search di-debounce 300 ms. **Column id = key `sort` di server**; kolom yang tidak bisa di-sort server diberi `enableSorting: false`. Filter tambahan (kategori/status) memakai `Select` + `setFilter(key, value)`. Jangan render `table.FilterList`/`table.SortList`, karena engine operatornya client-side. Kolom diketik `ServerTableColumns<Row>`. Reference: `components/admin/participants/participants-table.tsx`. (Participants, 2026-09-29.)

## Field `<Form>` yang dikunci tapi tetap divalidasi server: `readOnly`, bukan `disabled`

Input `disabled` tidak ikut terkirim, jadi rule `required` di FormRequest gagal walaupun user tidak mengubah apa pun. Kalau field harus tetap terkirim saat terkunci (mis. bobot tahap di Settings → Judging setelah award dikonfirmasi), pakai `readOnly` + teks penjelas, lalu tolak perubahannya di server. Reference: `pages/admin/settings/judging.tsx`. (Skor Tahap 2 (2/2), 2026-10-01.)
