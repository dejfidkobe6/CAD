# BeSix CAD — Specifikace & Prompt pro samostatnou aplikaci cad.besix.cz

---

## 1. Úvod a vize produktu

**Název aplikace:** BeSix CAD  
**URL:** cad.besix.cz (samostatná aplikace, separátní od board.besix.cz)  
**Typ:** Samostatná webová aplikace v rámci ekosystému BeSix (vedle board.besix.cz a besix.cz)  
**Jazyk rozhraní:** Čeština / Slovenština  
**Cílová skupina:** Stavbyvedoucí, přípraváři, mistři — lidé, kteří potřebují rychle upravit výkres na stavbě, přidat kótu, zakreslit změnu, nebo vytvořit jednoduchý detail, aniž by otevírali plný AutoCAD.

**Klíčová filozofie:**  
BeSix CAD je **zjednodušený, intuitivní CAD editor** inspirovaný AutoCADem a ArchiCADem, ale navržený tak, aby ho zvládl používat každý na stavbě — bez školení. Žádné složité panely nástrojů, žádné stovky ikon. Jen to, co stavbař skutečně potřebuje.

---

## 2. Funkční požadavky

### 2.1 Import a čtení souborů

| Formát | Operace | Poznámka |
|--------|---------|----------|
| **DWG** | Import, čtení, editace | Parsování DWG vrstev, entit (čáry, oblouky, kóty, text, bloky). Využít knihovnu jako `libredwg` (WASM), `ogc-cad` nebo konverzi DWG→DXF na serveru |
| **DXF** | Import, čtení, editace | Otevřený formát, snazší parsing. Může sloužit jako meziformát pro DWG |
| **PDF** | Import, čtení, editace | Vektory z PDF extrahovat jako editovatelné entity. Rastrové PDF zobrazit jako podklad (trace layer) |

**Workflow importu:**
1. Uživatel nahraje soubor (drag & drop nebo dialog)
2. Aplikace parsuje obsah → zobrazí na plátně (canvas)
3. Jednotlivé entity (čáry, oblouky, text, bloky) jsou editovatelné
4. PDF podklady se zobrazí jako zamčená vrstva (trace), nad kterou se kreslí

### 2.2 Kreslicí nástroje (Drawing Tools)

Sada nástrojů musí pokrýt 90 % potřeb stavebního výkresu:

**Základní geometrie:**
- Čára (Line) — ortogonální snap (0°, 45°, 90°), volné kreslení
- Obdélník (Rectangle)
- Kružnice / Oblouk (Circle / Arc)
- Polygon — pravidelný i nepravidelný
- Lomená čára / Polyline
- Spline / Křivka

**Kótování (Dimensions):**
- Lineární kóta (vodorovná, svislá, šikmá)
- Řetězová kóta
- Kóta poloměru / průměru
- Výšková kóta (±0,000)
- Automatický přepočet měřítka

**Texty a popisky:**
- Jednořádkový text
- Víceřádkový text (Multiline)
- Odkazová čára s popiskem (Leader + text)
- Značky řezů (A-A, B-B)
- Značky detailů (kruh s číslem)

**Šrafování (Hatch):**
- Předdefinované vzory: beton, zdivo, zemina, izolace, dřevo, ocel
- Vlastní barva a měřítko šraf
- Asociativní šrafy (reagují na změnu obrysu)

**Bloky a symboly:**
- Knihovna stavebních symbolů: dveře, okna, schodiště, sanitární objekty, elektro značky
- Vlastní bloky — uložení a znovupoužití
- Vkládání s rotací a měřítkem

### 2.3 Editační nástroje

- **Výběr** — klik, obdélníkový výběr (window / crossing)
- **Přesun** (Move)
- **Kopírování** (Copy)
- **Rotace** (Rotate)
- **Měřítko** (Scale)
- **Zrcadlení** (Mirror)
- **Oříznutí** (Trim)
- **Prodloužení** (Extend)
- **Odsazení** (Offset)
- **Pole** (Array) — lineární, kruhové
- **Undo / Redo** — neomezená historie v rámci session

### 2.4 Vrstvy (Layers)

- Správce vrstev — seznam, viditelnost, zamčení, barva, typ čáry
- Předdefinované stavební vrstvy:
  - `0-NOSNÉ` (červená)
  - `1-PŘÍČKY` (zelená)
  - `2-KÓTY` (modrá)
  - `3-TEXT` (bílá)
  - `4-ŠRAFY` (šedá)
  - `5-TZB` (fialová)
  - `6-PODKLAD` (světle šedá, zamčená)
- Uživatel může přidávat vlastní vrstvy

### 2.5 Navigace na plátně

- **Zoom** — kolečkem myši, pinch na tabletu
- **Pan** — prostředním tlačítkem / dvěma prsty
- **Zoom extents** — zobrazit celý výkres
- **Zoom na výběr**
- **Grid / Mřížka** — zapnout/vypnout, nastavitelný rastr
- **Snap** — koncový bod, střed, průsečík, kolmice, tečna
- **Ortho mód** — kreslení pouze v 0°/90°

### 2.6 Měřítko výkresu

- Globální měřítko: 1:50, 1:100, 1:200 atd. (volitelné)
- Kóty automaticky přepočítávají hodnoty dle měřítka
- Při exportu se měřítko aplikuje na výstupní rozměr papíru

---

## 3. Razítko (Title Block / Rohové razítko)

### 3.1 Konfigurovatelné razítko

Uživatel si sám nastaví šablonu razítka, která se automaticky přidá při exportu:

**Konfigurovatelná pole:**
- **Logo** — upload PNG/SVG (typicky logo BeSix nebo investora)
- **Název projektu** — např. "Bytový dům Vinohrady"
- **Číslo výkresu** — např. "D.1.1.01"
- **Název výkresu** — např. "Půdorys 1.NP"
- **Měřítko** — automaticky z nastavení, nebo ruční přepis
- **Datum** — automatické nebo ruční
- **Vypracoval** — jméno autora (z přihlášení nebo ruční)
- **Kontroloval** — jméno kontrolora
- **Stupeň PD** — DSP / DPS / DSPS / RDS
- **Investor** — název investora
- **Revize** — tabulka revizí (datum, popis, autor)
- **Formát** — A4, A3, A2, A1, A0 (automaticky dle obsahu nebo ruční)

### 3.2 Správa šablon razítek

- Uživatel si může vytvořit více šablon (pro různé projekty / investory)
- Šablony se ukládají lokálně (localStorage / IndexedDB) nebo v rámci projektu na cad.besix.cz
- Razítko se umístí do pravého dolního rohu výkresu dle normy ČSN

---

## 4. Ukládání a správa souborů

### 4.1 Filozofie lokálního ukládání

Aby se šetřilo serverové místo, **výkresy se neukládají na server**. Místo toho:

1. **Uživatel pracuje s lokálním souborem** — otevře ho z disku, edituje v BeSix CAD
2. **BeSix CAD si pamatuje pouze cestu / referenci** — název souboru, poslední úprava, miniatura (thumbnail)
3. **Export / uložení** — uživatel stáhne upravený soubor zpět na svůj disk (DWG nebo PDF)
4. **Opětovné nahrání** — uživatel může soubor znovu nahrát a pokračovat v editaci

### 4.2 Session management

- Rozpracovaný výkres se drží v paměti prohlížeče (IndexedDB) po dobu session
- Automatický autosave každých 60 sekund do IndexedDB
- Při zavření prohlížeče — warning dialog ("Máte neuložené změny")
- Možnost obnovit poslední session po restartu

### 4.3 Propojení se StavbaBoard (board.besix.cz)

- Z karty ve StavbaBoard bude možné otevřít BeSix CAD přes odkaz na cad.besix.cz s parametrem výkresu
- Karta v board.besix.cz si uloží metadata: název souboru, datum poslední úpravy, thumbnail
- Samotný soubor zůstává u uživatele
- Komunikace mezi aplikacemi přes URL parametry (např. `cad.besix.cz?project=bytovy-dum&drawing=pudorys-1np`)

---

## 5. Export

### 5.1 Export do PDF

- Výstup: vektorové PDF (ne raster)
- Automatické přidání razítka dle vybrané šablony
- Volba formátu papíru (A4–A0)
- Volba orientace (na výšku / na šířku)
- Volba měřítka nebo "přizpůsobit stránce"
- Možnost exportu pouze vybraných vrstev
- Knihovna: `jsPDF` nebo `pdf-lib`

### 5.2 Export do DWG/DXF

- Výstup: DWG soubor kompatibilní s AutoCAD 2018+
- Alternativně DXF (otevřený formát, snazší generování)
- Zachování vrstev, barev, typů čar
- Razítko jako blok v DWG
- Knihovna: `dxf-writer` (JS), nebo serverová konverze DXF→DWG přes `LibreDWG` / `ODA File Converter`

### 5.3 Export do PNG/JPG

- Pro rychlé sdílení na WhatsApp / email
- Volitelné rozlišení (72 / 150 / 300 DPI)
- S razítkem nebo bez

---

## 6. Technická architektura

### 6.1 Backend — sdílení users s board.besix.cz

BeSix CAD sdílí **stejnou databázi uživatelů a auth systém** jako board.besix.cz. Zbytek je autonomní.

**Existující PHP backend (board.besix.cz) — sdílené:**
```
/api/auth.php        → action=me      (GET)  — vrátí přihlášeného usera
                     → action=logout  (POST) — odhlášení
/login.php           → přihlašovací stránka (sdílená pro celý ekosystém)
/dashboard.php       → přehled projektů (StavbaBoard)
```

**User objekt z auth API (sdílený):**
```javascript
// GET /api/auth.php?action=me  →  { user: { id, name, email, avatar_color } }
_apiUser = {
  id: 42,
  name: "David",
  email: "david@besix.cz",
  avatar_color: "#4A5340"
};
```

**Role systém (sdílený):** owner, admin, member, viewer — role se řeší per-project, stejně jako v board.besix.cz.

**Nové PHP API endpointy pro BeSix CAD (autonomní):**
```
/api/cad.php         → action=save_templates   (POST)  — uloží šablony razítek
                     → action=load_templates   (GET)   — načte šablony razítek
                     → action=save_metadata    (POST)  — uloží metadata výkresu (název, thumbnail, cesta)
                     → action=load_metadata    (GET)   — načte seznam výkresů pro projekt
                     → action=delete_metadata  (DELETE) — smaže metadata výkresu
                     → action=save_symbols     (POST)  — uloží vlastní symboly/bloky
                     → action=load_symbols     (GET)   — načte knihovnu symbolů
```

**Databáze — nové tabulky (autonomní od board.besix.cz):**
```sql
-- Šablony razítek (per user, ne per project — uživatel si nese šablony napříč projekty)
CREATE TABLE cad_title_block_templates (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,                   -- FK → users.id (sdílená tabulka)
  name         VARCHAR(255) NOT NULL,          -- "BeSix default", "Investor ABC"
  logo_data    MEDIUMTEXT,                     -- base64 nebo URL
  fields_json  TEXT NOT NULL,                  -- { projectName, drawingNumber, stage, investor, ... }
  paper_size   VARCHAR(10) DEFAULT 'A3',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Metadata výkresů (reference — soubory zůstávají lokálně u uživatele)
CREATE TABLE cad_drawing_metadata (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  project_id   INT NOT NULL,                   -- FK → projects.id (sdílená tabulka)
  user_id      INT NOT NULL,                   -- FK → users.id (kdo nahrál)
  file_name    VARCHAR(255) NOT NULL,          -- "pudorys-1np.dxf"
  drawing_name VARCHAR(255),                   -- "Půdorys 1.NP"
  scale        VARCHAR(20) DEFAULT '1:100',
  paper_size   VARCHAR(10) DEFAULT 'A3',
  thumbnail    MEDIUMTEXT,                     -- base64 mini-preview
  file_hash    VARCHAR(64),                    -- SHA-256 pro detekci změn
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (project_id) REFERENCES projects(id)
);

-- Vlastní bloky/symboly uživatele
CREATE TABLE cad_user_symbols (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  name         VARCHAR(255) NOT NULL,
  category     VARCHAR(100),                   -- "dveře", "okna", "elektro"
  svg_data     MEDIUMTEXT NOT NULL,            -- SVG definice bloku
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### 6.2 Auth flow pro cad.besix.cz

```javascript
// Boot sekvence — IDENTICKÁ s board.besix.cz
async function bootAuth() {
  let res;
  try {
    // 1. Zkontroluj PHP session (sdílená cookie přes *.besix.cz)
    res = await fetch('/api/auth.php?action=me', { credentials: 'include' });
  } catch {
    // Žádný backend (local dev) — fallback na offline režim
    startOfflineMode();
    return;
  }

  if (!res.ok) {
    // Nepřihlášen → redirect na sdílenou login stránku
    window.location.href = 'https://board.besix.cz/login.php?redirect=cad';
    return;
  }

  const data = await res.json();
  _apiUser = data.user;

  // 2. Načti project_id z URL parametru (odkaz ze StavbaBoard)
  const projectId = new URLSearchParams(window.location.search).get('project_id');

  // 3. Načti šablony razítek a metadata výkresů
  await loadTitleBlockTemplates();
  if (projectId) await loadDrawingMetadata(projectId);

  // 4. Inicializuj CAD editor
  initCADEditor();
}
```

**Sdílená session cookie:** Obě aplikace (board.besix.cz i cad.besix.cz) běží na stejném serveru (cesky-hosting.cz) pod stejnou doménou `*.besix.cz`. PHP session cookie se nastaví s `domain=.besix.cz`, takže přihlášení na board.besix.cz platí automaticky i na cad.besix.cz.

```php
// V php.ini nebo v login.php — nastavení cookie pro celou doménu
session_set_cookie_params([
    'lifetime' => 604800,        // 7 dní
    'path'     => '/',
    'domain'   => '.besix.cz',   // sdílená cookie pro všechny subdomény
    'secure'   => true,
    'httponly'  => true,
    'samesite'  => 'Lax'
]);
```

### 6.3 Sync strategie — stejný vzor jako board.besix.cz

BeSix CAD přebírá osvědčený sync vzor z board.besix.cz:

```
Šablony razítek:     Uloží se na server (malá data, potřeba přístupu z více zařízení)
Metadata výkresů:    Uloží se na server (jen reference — název, thumbnail, hash)
Samotné výkresy:     LOKÁLNĚ v IndexedDB (velká data, uživatel si je stáhne/nahraje)
Vlastní symboly:     Uloží se na server (malá data, potřeba synchronizace)
```

**Timestamp-based sync** (identický vzor jako kanban sync v board.besix.cz):
```javascript
// Stejný princip: serverTs > localTs → apply, serverTs === localTs → skip, serverTs < localTs → push
async function syncTemplatesFromServer() {
  const res = await fetch('/api/cad.php?action=load_templates', { credentials: 'include' });
  const data = await res.json();
  // ... timestamp porovnání ...
}
```

### 6.4 Frontend (v prohlížeči)

```
Rendering engine:     HTML5 Canvas (2D context) nebo WebGL
                      Doporučeno: Fabric.js / Konva.js / Paper.js / vlastní engine
DWG/DXF parsing:      dxf-parser (JS), LibreDWG (WASM), nebo ODA SDK
PDF parsing:          pdf.js (Mozilla) pro zobrazení, pdf-lib pro editaci
Kótování:             Vlastní modul nad canvas engine
Šrafování:            Canvas pattern fill / custom hatch engine
Snap engine:          Vlastní kalkulace na základě geometrie entit
UI framework:         Vanilla JS + Montserrat font (konzistentní s board.besix.cz)
Ikony:                Lucide Icons / vlastní SVG sada
```

### 6.5 Datový model výkresu (v paměti / IndexedDB)

```javascript
// Hlavní datová struktura výkresu
const drawing = {
  id: "uuid",
  name: "Půdorys 1.NP",
  created: "2026-04-12T10:00:00Z",
  modified: "2026-04-12T14:30:00Z",
  scale: "1:100",
  paperSize: "A3",
  orientation: "landscape",
  
  // Globální nastavení
  settings: {
    gridSize: 100,        // mm
    snapEnabled: true,
    orthoEnabled: false,
    units: "mm"
  },
  
  // Vrstvy
  layers: [
    {
      id: "layer-0",
      name: "0-NOSNÉ",
      color: "#FF0000",
      lineType: "continuous",
      visible: true,
      locked: false,
      printable: true
    }
  ],
  
  // Entity (všechny kreslicí objekty)
  entities: [
    {
      id: "entity-uuid",
      type: "line",           // line, rect, circle, arc, polyline, text, dimension, hatch, block, image
      layer: "layer-0",
      properties: {
        startX: 0, startY: 0,
        endX: 1000, endY: 0,
        lineWeight: 0.35,
        lineType: "continuous",
        color: "bylayer"
      }
    },
    {
      id: "entity-uuid-2",
      type: "dimension",
      layer: "layer-koty",
      properties: {
        dimType: "linear",
        point1: { x: 0, y: 0 },
        point2: { x: 1000, y: 0 },
        textPosition: { x: 500, y: 150 },
        value: 1000,           // auto-calculated
        textOverride: null,    // pokud chce uživatel přepsat
        precision: 0,
        suffix: "",
        style: "default"
      }
    }
  ],
  
  // Šablona razítka
  titleBlock: {
    templateId: "template-besix-default",
    fields: {
      projectName: "Bytový dům Vinohrady",
      drawingNumber: "D.1.1.01",
      drawingTitle: "Půdorys 1.NP",
      scale: "1:100",
      date: "04/2026",
      author: "David",
      checker: "",
      stage: "DPS",
      investor: "ABC Development s.r.o.",
      revisions: []
    },
    logo: "data:image/png;base64,..."   // nebo URL
  },
  
  // Historie akcí pro undo/redo
  undoStack: [],
  redoStack: []
};
```

### 6.6 Struktura UI

```
┌─────────────────────────────────────────────────────────────┐
│  BeSix CAD                    [Soubor ▾] [Nástroje] [?]     │  ← Horní lišta
├────────┬────────────────────────────────────────────┬────────┤
│        │                                            │        │
│  N     │                                            │  V     │
│  á     │         PLÁTNO / CANVAS                    │  r     │
│  s     │         (výkresový prostor)                 │  s     │
│  t     │                                            │  t     │
│  r     │                                            │  v     │
│  o     │                                            │  y     │
│  j     │                                            │        │
│  e     │                                            │        │
│        │                                            │        │
├────────┴────────────────────────────────────────────┴────────┤
│  Příkazový řádek: _                          [x:0 y:0] 1:100│  ← Stavový řádek
└─────────────────────────────────────────────────────────────┘
```

**Levý panel (Nástroje) — vertikální toolbar:**
```
┌──────┐
│ ↖    │  Výběr (Select)
│ ╱    │  Čára (Line)
│ ▭    │  Obdélník (Rectangle)  
│ ○    │  Kružnice (Circle)
│ ⌒    │  Oblouk (Arc)
│ ⌇    │  Polyline
│ A    │  Text
│ ↔    │  Kóta (Dimension)
│ ▧    │  Šrafy (Hatch)
│ ◫    │  Blok / Symbol
│ ✂    │  Trim
│ ↕    │  Offset
│ ⊞    │  Měřítko (Scale)
│ ↻    │  Rotace
│ ⊡    │  Mirror
│ 📐   │  Snap nastavení
│ 📏   │  Grid nastavení
└──────┘
```

**Pravý panel (Vlastnosti + Vrstvy):**
```
┌─────────────────┐
│ VLASTNOSTI       │
│ ─────────────── │
│ Typ: Čára       │
│ Vrstva: NOSNÉ   │
│ Barva: Červená  │
│ Tloušťka: 0.35  │
│ Typ čáry: ───── │
│ Délka: 3450 mm  │
│                 │
│ VRSTVY          │
│ ─────────────── │
│ 👁 🔒 ■ NOSNÉ   │
│ 👁 🔓 ■ PŘÍČKY  │
│ 👁 🔓 ■ KÓTY    │
│ 👁 🔓 ■ TEXT    │
│ 👁 🔓 ■ ŠRAFY   │
│ ⊘ 🔓 ■ PODKLAD │
└─────────────────┘
```

---

## 7. UX principy — co dělat jinak než AutoCAD

### 7.1 Zjednodušení

- **Žádný příkazový řádek jako primární vstup** — nástroje se vybírají kliknutím, příkazový řádek je volitelný pro pokročilé
- **Kontextové menu** — pravé tlačítko myši nabídne relevantní akce pro vybraný objekt
- **Inline editace** — dvojklik na text = rovnou editace, dvojklik na kótu = přepis hodnoty
- **Drag & drop import** — přetáhnout DWG/PDF rovnou do okna
- **Vizuální snap indikátory** — jasné barevné značky (zelené kroužky) u snap bodů
- **Touch podpora** — funkční na tabletu (stavbyvedoucí na stavbě s iPadem)

### 7.2 Stavební specifika

- **Předdefinované šablony** — "Nový půdorys", "Nový řez", "Nový detail"
- **Stavební knihovna symbolů** — dveře, okna, schody, výtah, WC, umyvadlo, vana, kotel
- **Rychlé kreslení stěn** — nástroj "Stěna" s nastavitelnou tloušťkou (150/300/450 mm)
- **Místnosti** — automatický výpočet plochy při uzavření obrysu
- **Výškové kóty** — formát ±0,000 dle české normy

### 7.3 Klávesové zkratky

| Zkratka | Akce |
|---------|------|
| `L` | Čára |
| `R` | Obdélník |
| `C` | Kružnice |
| `D` | Kóta |
| `T` | Text |
| `H` | Šrafy |
| `M` | Přesun |
| `O` | Offset |
| `TR` | Trim |
| `ESC` | Zrušit akci / Deselect |
| `Delete` | Smazat vybrané |
| `Ctrl+Z` | Undo |
| `Ctrl+Y` | Redo |
| `Ctrl+S` | Export/Uložit |
| `Ctrl+A` | Vybrat vše |
| `F7` | Grid on/off |
| `F8` | Ortho on/off |
| `F3` | Snap on/off |

---

## 8. Doporučené knihovny a technologie

### 8.1 Rendering & Canvas

| Knihovna | Účel | Poznámka |
|---------|------|----------|
| **Fabric.js** | 2D canvas framework | Výborné pro interaktivní objekty, výběr, transformace. Dobré pro MVP |
| **Paper.js** | 2D vektorová grafika | Silné v geometrii, Bézier křivkách. Vhodné pro přesné kreslení |
| **Konva.js** | 2D canvas framework | Alternativa k Fabric.js, lepší performance s mnoha objekty |
| **Three.js** | WebGL (2D/3D) | Overkill pro 2D, ale připraveno na budoucí 3D |
| **Custom Canvas** | Vlastní engine | Maximální kontrola, ale nejvíc práce |

**Doporučení pro MVP:** Fabric.js — nejlepší poměr funkcionality a složitosti implementace.

### 8.2 DWG/DXF

| Knihovna | Účel |
|---------|------|
| **dxf-parser** (npm) | Čtení DXF souborů v JS |
| **dxf-writer** (npm) | Zápis DXF souborů v JS |
| **LibreDWG** (WASM) | Nativní DWG čtení/zápis, kompilovaný do WebAssembly |
| **ODA File Converter** | Serverová konverze DWG↔DXF (free tool od Open Design Alliance) |

### 8.3 PDF

| Knihovna | Účel |
|---------|------|
| **pdf.js** (Mozilla) | Zobrazení PDF v canvasu |
| **pdf-lib** | Programatická tvorba a editace PDF |
| **jsPDF** | Generování PDF z canvasu |

### 8.4 Export

| Knihovna | Účel |
|---------|------|
| **jsPDF** | PDF export s vektorovým obsahem |
| **canvg** | SVG → Canvas konverze |
| **html2canvas** | Screenshot canvasu (pro PNG/JPG) |
| **FileSaver.js** | Stahování souborů na disk |

---

## 9. Fáze implementace

### Fáze 1 — MVP (4–6 týdnů)
- [x] Plátno s pan/zoom
- [x] Základní kreslení: čára, obdélník, kružnice, text
- [x] Výběr a přesun objektů
- [x] Vrstvy (základní)
- [x] Undo/Redo
- [x] Export do PDF (s razítkem)
- [x] Import DXF
- [x] Konfigurace razítka (základní)

### Fáze 2 — Core CAD (4–6 týdnů)
- [ ] Kótování (lineární, řetězové)
- [ ] Šrafování (předdefinované vzory)
- [ ] Polyline, oblouk, spline
- [ ] Snap engine (endpoint, midpoint, intersection)
- [ ] Ortho mód
- [ ] Trim / Extend / Offset
- [ ] Import PDF jako podklad
- [ ] Klávesové zkratky

### Fáze 3 — Stavební nástroje (3–4 týdny)
- [ ] Knihovna stavebních symbolů
- [ ] Nástroj "Stěna" s tloušťkou
- [ ] Automatický výpočet ploch místností
- [ ] Výškové kóty (±0,000)
- [ ] Značky řezů a detailů
- [ ] Rozšířená správa razítek (šablony)
- [ ] Export do DXF/DWG

### Fáze 4 — Integrace & Polish (2–3 týdny)
- [ ] Propojení se StavbaBoard (board.besix.cz → cad.besix.cz přes URL parametry)
- [ ] Deployment na cad.besix.cz (vlastní subdoména)
- [ ] Touch/tablet podpora
- [ ] Performance optimalizace (virtualizace canvasu)
- [ ] Offline režim (Service Worker)
- [ ] Export do PNG/JPG

---

## 10. Design systém — vizuální konzistence s ekosystémem BeSix

### 10.1 Barvy

```css
:root {
  /* BeSix Brand — konzistentní s board.besix.cz */
  --bg: #1e2710;
  --surface: #1a2a0e;
  --surface2: rgba(255,255,255,0.07);
  --surface3: rgba(255,255,255,0.11);
  --border: rgba(255,255,255,0.1);
  --border2: rgba(255,255,255,0.16);
  --accent: #c9922a;
  --accent2: #d4a035;
  --text: rgba(255,255,255,0.88);
  --text2: rgba(255,255,255,0.5);
  --muted: rgba(255,255,255,0.3);
  --danger: #ff3b30;
  --green: #34c759;
  --blue: #007aff;
  
  /* CAD specifické */
  --cad-canvas-bg: #1a1a2e;          /* Tmavé pozadí plátna (jako AutoCAD) */
  --cad-grid: rgba(255,255,255,0.06); /* Mřížka */
  --cad-crosshair: #ffffff;           /* Kurzor */
  --cad-snap-point: #00ff88;          /* Snap indikátor */
  --cad-selection: rgba(0,122,255,0.3); /* Výběrový obdélník (--blue) */
  --cad-toolbar-bg: #0d0d0d;          /* Toolbar pozadí */
  --cad-panel-bg: #141414;            /* Boční panel */
  
  /* Barvy vrstev (default) */
  --layer-nosne: #ff4444;
  --layer-pricky: #44cc44;
  --layer-koty: #4488ff;
  --layer-text: #ffffff;
  --layer-srafy: #888888;
  --layer-tzb: #cc44ff;
  --layer-podklad: #555555;
}
```

### 10.2 Typografie

```css
/* UI prvky — konzistentní s board.besix.cz */
font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, sans-serif;

/* Příkazový řádek */
font-family: 'JetBrains Mono', 'Fira Code', monospace;

/* Výkresový text */
font-family: 'ISOCPEUR', 'Simplex', sans-serif;  /* CAD styl písma */
```

### 10.3 Ikony

Vlastní SVG ikony ve stylu technického kreslení — tenké čáry, přesné tvary, monochromatické. Konzistentní s minimalistickým stylem BeSix.

---

## 11. Hosting & Deployment — cad.besix.cz

### 11.1 Sdílený server s board.besix.cz

Obě aplikace běží na **stejném serveru** (cesky-hosting.cz), aby sdílely:
- PHP session (cookie `domain=.besix.cz`)
- Společnou databázi MySQL (tabulky `users`, `projects` sdílené; tabulky `cad_*` autonomní)
- Společné PHP API soubory pro auth

```
cesky-hosting.cz server:
├── board.besix.cz/
│   ├── index.html          (StavbaBoard frontend)
│   ├── login.php           (sdílená přihlašovací stránka)
│   ├── dashboard.php       (výběr projektů)
│   └── api/
│       ├── auth.php        (sdílené — session management)
│       ├── projects.php    (sdílené — projekty, členové, role)
│       ├── invitations.php (StavbaBoard specifické)
│       ├── kanban.php      (StavbaBoard specifické)
│       └── upload_bg.php   (StavbaBoard specifické)
│
├── cad.besix.cz/
│   ├── index.html          (BeSix CAD frontend)
│   └── api/
│       └── cad.php         (CAD specifické — razítka, metadata, symboly)
│
└── databáze (MySQL):
    ├── users               ← SDÍLENÁ
    ├── projects            ← SDÍLENÁ
    ├── project_members     ← SDÍLENÁ
    ├── sessions            ← SDÍLENÁ
    ├── kanban_states       ← board.besix.cz only
    ├── invitations         ← board.besix.cz only
    ├── cad_title_block_templates  ← cad.besix.cz only
    ├── cad_drawing_metadata       ← cad.besix.cz only
    └── cad_user_symbols           ← cad.besix.cz only
```

### 11.2 DNS nastavení

- `cad.besix.cz` → subdoména na cesky-hosting.cz (CNAME nebo A záznam)
- SSL certifikát (Let's Encrypt) — wildcard `*.besix.cz` nebo separátní
- Session cookie `domain=.besix.cz` zajistí SSO mezi subdoménami

### 11.3 Ekosystém BeSix — mapa aplikací

```
besix.cz              → Landing page (marketing, kontakt)
board.besix.cz         → StavbaBoard (Kanban, úkoly, tým)
cad.besix.cz           → BeSix CAD (výkresy, kreslení, editace)
     │                      │
     └──── sdílená DB ──────┘
           users, projects, sessions
           cookie domain=.besix.cz (SSO)
```

---

## 12. Prompt pro implementaci (pro AI / vývojáře)

```
Jsi expertní frontend vývojář specializovaný na CAD aplikace.

Tvůj úkol: Vytvořit webovou CAD aplikaci "BeSix CAD" jako samostatnou aplikaci na cad.besix.cz.

KONTEXT:
- BeSix je stavební firma, aplikace slouží pro editaci stavebních výkresů
- Samostatná aplikace v ekosystému BeSix (vedle besix.cz a board.besix.cz)
- Sdílení uživatelů: BeSix CAD sdílí users/auth s board.besix.cz přes PHP session cookie (domain=.besix.cz)
- Zbytek dat je autonomní — CAD má vlastní tabulky a API endpointy
- Design: tmavý režim, barvy #1e2710 (bg), #c9922a (accent/gold), Montserrat font
- Jazyk UI: Čeština
- Technologie: Vanilla HTML/CSS/JS, jeden HTML soubor (self-contained)

BACKEND ARCHITEKTURA (PHP + MySQL):
Sdílené s board.besix.cz:
  - /api/auth.php?action=me        (GET)  → { user: { id, name, email, avatar_color } }
  - /api/auth.php?action=logout    (POST) → odhlášení
  - /login.php                     → sdílená přihlašovací stránka (redirect s ?redirect=cad)
  - DB tabulky: users, projects, project_members, sessions

Autonomní pro CAD:
  - /api/cad.php?action=save_templates    (POST)  → uloží šablony razítek
  - /api/cad.php?action=load_templates    (GET)   → načte šablony razítek
  - /api/cad.php?action=save_metadata     (POST)  → uloží metadata výkresu
  - /api/cad.php?action=load_metadata     (GET)   → načte seznam výkresů projektu
  - /api/cad.php?action=delete_metadata   (DELETE) → smaže metadata
  - /api/cad.php?action=save_symbols      (POST)  → uloží vlastní bloky/symboly
  - /api/cad.php?action=load_symbols      (GET)   → načte knihovnu symbolů
  - DB tabulky: cad_title_block_templates, cad_drawing_metadata, cad_user_symbols

AUTH FLOW (identický s board.besix.cz):
  1. bootAuth() → fetch('/api/auth.php?action=me', { credentials: 'include' })
  2. Pokud 401 → redirect na board.besix.cz/login.php?redirect=cad
  3. Pokud OK → _apiUser = data.user → initCADEditor()
  4. Session cookie sdílená přes domain=.besix.cz (SSO bez opětovného přihlášení)

SYNC VZOR (osvědčený z board.besix.cz):
  - Timestamp-based: serverTs > localTs → apply, === → skip, < → push
  - Šablony razítek → server (malá data, potřeba multi-device)
  - Metadata výkresů → server (jen reference + thumbnail)
  - Samotné výkresy → IndexedDB (velká data, uživatel exportuje/importuje)
  - Exponential backoff na chyby, sendBeacon na beforeunload

POŽADAVKY:
1. Canvas-based editor (doporučeno Fabric.js) s pan/zoom
2. Kreslicí nástroje: čára, obdélník, kružnice, oblouk, polyline, text, kóty, šrafy
3. Editace: výběr, přesun, kopie, rotace, měřítko, trim, offset, mirror
4. Systém vrstev s barvami a viditelností
5. Import DXF/DWG souborů (dxf-parser)
6. Import PDF jako podkladová vrstva (pdf.js)
7. Export do PDF s konfigurovatelným razítkem (jsPDF / pdf-lib)
8. Export do DXF (dxf-writer)
9. Snap systém (endpoint, midpoint, intersection, perpendicular)
10. Ortho mód, grid, klávesové zkratky
11. Undo/Redo (neomezené)
12. Lokální ukládání výkresů (IndexedDB) — soubory se NEukládají na server
13. Autosave do IndexedDB každých 60 sekund
14. Responsivní — funkční na tabletu
15. Konfigurovatelné razítko: logo, název projektu, číslo výkresu, autor, datum, měřítko, stupeň PD, revize
16. Knihovna stavebních symbolů (dveře, okna, schody)

UI LAYOUT:
- Horní lišta: logo BeSix CAD, menu (Soubor, Editace, Zobrazení, Nástroje, Export), user avatar (vpravo)
- Levý panel: vertikální toolbar s nástroji (ikony + tooltip)
- Střed: canvas (tmavé pozadí #1a1a2e, bílá mřížka)
- Pravý panel: vlastnosti vybraného objektu + správce vrstev
- Spodní lišta: stavový řádek (souřadnice kurzoru, měřítko, snap/ortho status, sync dot)

RAZÍTKO (Title Block):
- Umístění: pravý dolní roh výkresu
- Pole: logo, projekt, číslo výkresu, název, měřítko, datum, autor, kontrola, stupeň PD, investor, revize
- Uživatel si konfiguruje přes dialog (Settings → Razítko)
- Více šablon razítek — ukládají se na server přes /api/cad.php
- Automaticky se přidá při exportu do PDF/DXF

UKLÁDÁNÍ:
- Výkresové soubory se NEukládají na server — jen v IndexedDB prohlížeče
- Na server se ukládají jen: šablony razítek, metadata (název, thumbnail, hash), vlastní symboly
- Export = stažení souboru na disk uživatele (DWG/DXF/PDF)
- Import = nahrání souboru z disku do editoru
- Ze StavbaBoard (board.besix.cz) lze otevřít CAD přes: cad.besix.cz?project_id=123&drawing_id=456

PROPOJENÍ SE STAVBABOARD:
- Odkaz z karty ve StavbaBoard → otevře cad.besix.cz s project_id v URL
- Sdílený user (SSO přes PHP session cookie)
- Sdílený project_id — CAD metadata se váží na stejné projekty jako kanban
- CAD data jsou autonomní — vlastní API, vlastní DB tabulky

VÝSTUP:
- Frontend: jeden self-contained HTML soubor (index.html pro cad.besix.cz)
- Backend: jeden PHP soubor (api/cad.php) + SQL migrace pro nové tabulky
- Všechny CSS a JS inline v HTML
- Externí knihovny přes CDN (Fabric.js, dxf-parser, pdf.js, jsPDF, FileSaver.js)
- Produkční kvalita, profesionální UI konzistentní s board.besix.cz
```

---

## 13. Otevřené otázky k rozhodnutí

1. **DWG podpora** — Nativní DWG parsing v browseru je složitý. Preferuješ:
   - **(a)** Pouze DXF (jednodušší, open-source) s doporučením konvertovat DWG→DXF externě?
   - **(b)** DWG přes WASM (LibreDWG) — náročnější na implementaci, ale přímá podpora?
   - **(c)** Serverová konverze DWG→DXF (PHP wrapper pro ODA File Converter na cesky-hosting.cz)?

2. **Rendering engine** — Fabric.js (rychlý start) vs. Paper.js (lepší geometrie) vs. vlastní canvas engine (plná kontrola)?

3. **Offline vs. Online** — Má BeSix CAD fungovat i kompletně offline (Service Worker + bundlované knihovny), nebo stačí CDN?

4. **Mobilní podpora** — Priorita? Tablet (iPad) na stavbě by byl killer feature, ale dotyková obsluha CAD nástrojů je náročná na UX.

5. **Login redirect flow** — Při nepřihlášení na cad.besix.cz: redirect na board.besix.cz/login.php s parametrem `?redirect=cad`, po úspěšném loginu redirect zpět? Nebo vlastní login.php na cad.besix.cz sdílející stejný auth kód?

6. **Sdílení cad.php** — PHP soubor `api/cad.php` umístit fyzicky na cad.besix.cz, nebo symlink/include ze sdílené složky s board.besix.cz?

---

*Dokument vytvořen: 12. dubna 2026*  
*Verze: 2.0 — PHP backend architektura, sdílené users, autonomní CAD data*  
*Autor: BeSix Development Team*
