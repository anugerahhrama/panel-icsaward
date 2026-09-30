---
paths:
    - resources/css/app.css
---

# Css

> Diadaptasi dari repo Landing (`icsa2026-app`) saat Project setup, 2026-09-29.

## Token brand ditambahkan, token shadcn tidak ditimpa

Token brand (`--color-ink/surface/deep`, `--color-brand-*`, `--font-display/--font-body` Plus Jakarta Sans) adalah entri `@theme` tambahan di samping token shadcn & `--font-sans` (Instrument Sans) — jangan timpa token shadcn (`--color-primary`, dst.) atau `--font-sans`. Font dimuat lewat `bunny(...)` di `vite.config.ts`; tambah entri, jangan ganti yang ada. Warna brand selalu berprefix `brand-` (bare `primary` menimpa shadcn, bare `cyan`/`green` bertabrakan dengan palet default Tailwind).

## Jangan beri nama token `--color-*` sesuai keyword utility Tailwind

Tailwind v4 membuat `bg-*`/`text-*`/`border-*` dari tiap token warna. Token bernama `base` (dulu di Landing) menimpa `text-base` (ukuran font) sehingga teks `Input`/`Heading` jadi berwarna krem tak terbaca. Cek nama token baru terhadap keyword skala Tailwind (`base`, `sm`, `lg`, `xl`, `none`, `auto`, …).

## Kontras: pilih aksen sesuai latar

Di latar gelap (`bg-deep`) pakai `brand-cyan`/`brand-mint`/`brand-green`; di latar terang (`bg-surface`) teks kecil pakai `brand` (≈5.7:1) atau `brand-teal` (≈4.9:1) — cyan/mint/green hanya sebagai tint (`/10`), border, atau teks dekoratif besar. `surface` `#F0FAFE` jangan dipertebal tanpa memindah eyebrow ke `text-brand`.

## Gradien Key Visual lewat utility `bg-kv-*` dengan stop token

`bg-kv-corners` dan `bg-kv-stage` memakai `var(--color-brand-*)`, jangan tambah hex baru. Teks putih hanya di zona hijau/biru gradien — zona mint/cyan-light terlalu terang (putih di `#3FFFC1` ≈1.3:1); pakai scrim `brand-dark` radial di zona teks.

## Override warna shadcn per-area: timpa `--color-*`, bukan `--primary`

`@theme` (bukan `inline`) mendefinisikan `--color-primary: var(--primary)` di `:root`, jadi nilainya sudah ter-resolve di sana — menimpa `--primary` di elemen turunan tidak berpengaruh ke `bg-primary`. Untuk men-scope warna (mis. tombol brand di layout auth) pakai arbitrary property `[--color-primary:var(--color-brand)]` (+ `--color-primary-foreground`, `--color-ring`). (Sign Up, 2026-09-29.)
