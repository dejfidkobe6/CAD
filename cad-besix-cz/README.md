# BeSix CAD

Zjednodušený webový CAD editor pro stavební výkresy.  
Součást ekosystému **BeSix** — samostatná aplikace na `cad.besix.cz`.

## Ekosystém

```
besix.cz           → Landing page
board.besix.cz     → StavbaBoard (Kanban)
cad.besix.cz       → BeSix CAD (tento repo)
```

## Architektura

- **Frontend:** Vanilla HTML/CSS/JS, Fabric.js canvas, jeden `index.html`
- **Backend:** PHP + MySQL (sdílená DB s board.besix.cz)
- **Auth:** Sdílená PHP session přes `domain=.besix.cz` (SSO)
- **Výkresy:** Ukládají se lokálně (IndexedDB), na server jen metadata

### Sdílené s board.besix.cz
- Tabulky: `users`, `projects`, `project_members`, `sessions`
- Auth: `/api/auth.php`, `/login.php`

### Autonomní (CAD only)
- Tabulky: `cad_title_block_templates`, `cad_drawing_metadata`, `cad_user_symbols`, `cad_default_symbols`
- API: `/api/cad.php`

## Struktura

```
cad-besix-cz/
├── index.html              ← Hlavní frontend (self-contained)
├── api/
│   └── cad.php             ← Backend API (razítka, metadata, symboly)
├── sql/
│   └── migration.sql       ← SQL pro nové tabulky
├── docs/
│   └── besix-cad-spec.md   ← Plná specifikace
├── assets/
│   └── (loga, ikony)
├── .gitignore
└── README.md
```

## Nasazení na cesky-hosting.cz

1. Vytvořit subdoménu `cad.besix.cz` v administraci
2. Spustit `sql/migration.sql` na sdílené databázi
3. Upravit DB credentials v `api/cad.php`
4. Nahrát soubory do `www/` složky subdomény
5. Ověřit SSL certifikát
6. Nastavit session cookie: `domain=.besix.cz`

## Vývoj lokálně

```bash
# Spustit PHP dev server
php -S localhost:8080

# Otevřít v prohlížeči
open http://localhost:8080
```

Bez PHP backendu aplikace spadne do offline režimu (localStorage).

## Tech stack

| Vrstva | Technologie |
|--------|-------------|
| Canvas | Fabric.js 6.x |
| DXF import | dxf-parser |
| DXF export | dxf-writer |
| PDF zobrazení | pdf.js (Mozilla) |
| PDF export | jsPDF + svg2pdf.js |
| Soubory | FileSaver.js |
| Font | Montserrat (Google Fonts) |
| Icons | Vlastní SVG |

## Licence

Proprietární — BeSix s.r.o.
