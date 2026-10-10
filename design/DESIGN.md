---
name: نظام الإدارة المالية — مسجد خالد بن الوليد
colors:
  surface: '#f0fdf1'
  surface-dim: '#d0ddd2'
  surface-bright: '#f0fdf1'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eaf7ec'
  surface-container: '#e4f1e6'
  surface-container-high: '#dfebe0'
  surface-container-highest: '#d9e6db'
  on-surface: '#131e17'
  on-surface-variant: '#3f4943'
  inverse-surface: '#28332c'
  inverse-on-surface: '#e7f4e9'
  outline: '#6f7a73'
  outline-variant: '#bec9c1'
  surface-tint: '#196b4c'
  primary: '#00563a'
  on-primary: '#ffffff'
  primary-container: '#1f6f50'
  on-primary-container: '#a1efc8'
  inverse-primary: '#8ad6b0'
  secondary: '#366851'
  on-secondary: '#ffffff'
  secondary-container: '#b8efd1'
  on-secondary-container: '#3c6e57'
  tertiary: '#755b00'
  on-tertiary: '#ffffff'
  tertiary-container: '#cea72c'
  on-tertiary-container: '#4f3d00'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#a5f3cc'
  primary-fixed-dim: '#8ad6b0'
  on-primary-fixed: '#002114'
  on-primary-fixed-variant: '#005137'
  secondary-fixed: '#b8efd1'
  secondary-fixed-dim: '#9dd2b6'
  on-secondary-fixed: '#002114'
  on-secondary-fixed-variant: '#1c503a'
  tertiary-fixed: '#ffe08e'
  tertiary-fixed-dim: '#ecc246'
  on-tertiary-fixed: '#241a00'
  on-tertiary-fixed-variant: '#584400'
  background: '#f0fdf1'
  on-background: '#131e17'
  surface-variant: '#d9e6db'
typography:
  display-lg:
    fontFamily: Cairo
    fontSize: 3rem
    fontWeight: '700'
    lineHeight: 3.75rem
  display-md:
    fontFamily: Cairo
    fontSize: 2.25rem
    fontWeight: '700'
    lineHeight: 3rem
  headline-lg:
    fontFamily: Cairo
    fontSize: 1.75rem
    fontWeight: '700'
    lineHeight: 2.5rem
  headline-lg-mobile:
    fontFamily: Cairo
    fontSize: 1.5rem
    fontWeight: '700'
    lineHeight: 2.25rem
  headline-md:
    fontFamily: Cairo
    fontSize: 1.375rem
    fontWeight: '600'
    lineHeight: 2rem
  headline-sm:
    fontFamily: Cairo
    fontSize: 1.125rem
    fontWeight: '600'
    lineHeight: 1.75rem
  body-lg:
    fontFamily: Cairo
    fontSize: 1.125rem
    fontWeight: '500'
    lineHeight: 1.875rem
  body-md:
    fontFamily: Cairo
    fontSize: 1rem
    fontWeight: '400'
    lineHeight: 1.75rem
  body-sm:
    fontFamily: Cairo
    fontSize: 0.875rem
    fontWeight: '400'
    lineHeight: 1.5rem
  label-lg:
    fontFamily: Cairo
    fontSize: 1rem
    fontWeight: '700'
    lineHeight: 1.5rem
  label-md:
    fontFamily: Cairo
    fontSize: 0.875rem
    fontWeight: '600'
    lineHeight: 1.25rem
  label-sm:
    fontFamily: Cairo
    fontSize: 0.75rem
    fontWeight: '600'
    lineHeight: 1.125rem
  kpi-number:
    fontFamily: Cairo
    fontSize: 2.5rem
    fontWeight: '800'
    lineHeight: 3rem
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1.5rem
  gutter-sm: 1rem
  margin: 2rem
  margin-mobile: 1rem
  space-xs: 0.375rem
  space-sm: 0.75rem
  space-md: 1.25rem
  space-lg: 2rem
  space-xl: 3rem
---

## Brand & Style

This design system governs the administrative and financial governance interface for **مسجد خالد بن الوليد** (Khaled Ibn Al-Walid Mosque). The application serves mosque committee trustees, elders, and community volunteers responsible for recording donations, zakat disbursements, maintenance contracts, and operational utilities. 

### Core Personality & Philosophy
- **Reverent & Trustworthy (أمانة ووقار):** Grounded in ethical responsibility, clarity, and institutional integrity.
- **Accessible & Effortless (بساطة ووضوح):** Tailored specifically for non-technical committee members and older volunteers. Elements are deliberate, spacious, high-contrast, and forgiving.
- **Culturally Attuned (روحانية إسلامية هادئة):** Employs balanced emerald green, subdued heritage gold accents, and warm off-white tones that echo traditional Islamic architectural aesthetics without visual clutter.

### Design Movement
**Refined Contemporary Arabic Functionalism with Tactile Warmth.** The aesthetic balances clean structural grids with generous click targets, large Arabic numeral typography, soft elevation tiers, and high-contrast color boundaries designed to avoid eye fatigue and eliminate accounting entry errors.

## Colors

The palette draws directly from mosque architectural finishes: deep hand-woven prayer carpet emeralds, brushed brass/gilded accents, and limestone off-white masonry.

### Functional Roles
- **Primary (`#1F6F50`):** Mosque Emerald. Drives primary navigation landmarks, main action buttons, major category groupings, and active state indicators.
- **Secondary (`#144934`):** Deep Forest. Used for sticky right-aligned navigation backgrounds, headers, and prominent financial totals to ensure maximum contrast.
- **Tertiary Accent (`#C9A227`):** Warm Gold. Reserved strictly for commemorative badges, verified donation seals, financial audit highlights, and focus borders.
- **Canvas & Surfaces:**
  - Base Background: `#FAF9F5` (Soft warm parchment white)
  - Secondary Canvas/Sub-panels: `#F6F8F6` (Whisper mint off-white)
  - Card/Modal Surfaces: `#FFFFFF` (Pristine optical white)
- **Typography Scale:**
  - Ink Primary: `#1E2922` (High-contrast deep spruce charcoal; never pure `#000000` to prevent visual harshness)
  - Ink Secondary: `#4B5563` (Neutral slate for column headers and subtitles)
  - Ink Muted: `#6B7280` (Helper text and structural borders)
- **Financial Status Indicators:**
  - **الوارد / تبرعات (Inflow / Positive):** `#16A34A` (Container: `#DCFCE7`, Border: `#86EFAC`)
  - **المصروفات (Expense / Debit):** `#DC2626` (Container: `#FEE2E2`, Border: `#FCA5A5`)
  - **قيد المراجعة (Pending / Audit):** `#D97706` (Container: `#FEF3C7`, Border: `#FCD34D`)

## Typography

The type system uses **Cairo** for its tall x-height, clear open counters, and high distinction between similar Arabic glyphs.

### Rules & Formatting
- **Right-to-Left Directionality:** All typographic roots must enforce `dir="rtl"` with explicit `text-align: right;` by default.
- **Financial Numerals:** All amounts must pair with the currency suffix `جنيه مصري (ج.م)`. Numerals use standard Eastern/Western Arabic digits consistently across the board with tabular formatting (`font-variant-numeric: tabular-nums;`) to keep decimal amounts aligned.
- **Legibility for Senior Volunteers:** No essential operational label or value may drop below `14px` (`0.875rem`). Body text is set generously at `16px` (`1rem`) minimum with relaxed `1.75` line height to accommodate diacritics and Arabic descenders without clipping.

## Layout & Spacing

The layout is built on a desktop-first, right-to-left 12-column grid anchored by a dedicated, persistent right navigation rail.

### Layout Model
- **Permanent Right Sidebar (القائمة الجانبية):** Fixed width of `280px` placed at the right (`right: 0`), holding primary mosque ledger categories, donation receipt creation, and profile status.
- **Main Ledger Canvas:** Fluid grid with max width clamped at `1600px` to maintain legible row length in accounting tables. Margin: `2rem` (`32px`), Gutters: `1.5rem` (`24px`).
- **Breakpoints:**
  - **Desktop (شاشات المكتب):** `≥ 1280px` — Full persistent sidebar, 3-to-4 column KPI card layout, expanded financial tables.
  - **Tablet (الأجهزة اللوحية):** `768px – 1279px` — Sidebar collapses to an icon-labeled rail (`88px`), 2-column KPI grid.
  - **Compact Tablet / Mobile:** `< 768px` — Bottom action bar, stacked single-column KPI stat cards, scrollable responsive tables.

## Elevation & Depth

Visual hierarchy uses warm, low-intensity elevation to maintain a clean, physical paper-ledger feel.

### Elevation Levels
- **Level 0 (Flat Surface):** Canvas background (`#FAF9F5`), muted table header rows (`#F6F8F6`). No shadow; 1px subtle structural border (`#E5E7EB`).
- **Level 1 (Cards & Data Containers):** `0 1px 3px 0 rgba(30, 41, 34, 0.05), 0 1px 2px -1px rgba(30, 41, 34, 0.05)` paired with a soft warm border (`1px solid #EAEBE4`). Used for KPI metric cards and financial transaction rows.
- **Level 2 (Dropdowns & Popovers):** `0 4px 6px -1px rgba(30, 41, 34, 0.08), 0 2px 4px -2px rgba(30, 41, 34, 0.06)`. Used for account category selector menus and date-range pickers.
- **Level 3 (Modals & Entry Dialogs):** `0 20px 25px -5px rgba(30, 41, 34, 0.12), 0 8px 10px -6px rgba(30, 41, 34, 0.08)`. Used for new donation receipt modals and voucher creation forms. Backdrop uses `#144934` at 40% opacity with a light `backdrop-filter: blur(4px)`.

## Shapes

The design system uses a balanced roundedness (`roundedness: 2`, `8px` / `0.5rem` base border radius). This prevents the harshness of boxy forms while avoiding overly playful pill shapes that could undermine the formal gravity of mosque financial stewardship.

- **Base Controls (`rounded` / `8px`):** Input fields, select boxes, table action buttons, and individual transaction rows.
- **Cards & Summary Containers (`rounded-lg` / `16px`):** KPI summary blocks, balance statements, and ledger table containers.
- **Modals & Flyout Sheets (`rounded-xl` / `24px`):** Large financial entry sheets and receipt viewports.
- **Pills (`rounded-full` / `9999px`):** Restricted solely to status chips (e.g., `تم الصرف`, `مؤكد`, `معلق`) and avatar initials.

## Components

### 1. Buttons (الأزرار)
- **Minimum Target Size:** `48px` height for standard actions; `56px` for primary financial creation tasks (e.g., "تسجيل تبرع جديد").
- **Primary:** Background `#1F6F50`, text `#FFFFFF`, font weight `700`. Hover: `#144934`. Focus: 3px outer ring `#C9A227`.
- **Secondary / Outlined:** Border `2px solid #1F6F50`, background `#FFFFFF`, text `#1F6F50`. Hover: background `#F6F8F6`.
- **Destructive:** Background `#DC2626`, text `#FFFFFF`. Hover: `#B91C1C`.

### 2. KPI Statistic Cards (بطاقات المؤشرات المالية)
- Used for high-level figures: الإيرادات (Income), المصروفات (Expenses), رصيد الصندوق (Cash Balance), and أموال الزكاة (Zakat Reserves).
- Structure: White card, `16px` padding, subtle gold accent top-border (`3px solid #C9A227`) for total balance.
- Value presentation: Large `kpi-number` font (`2.5rem`), bold weight, accompanied by a clear currency indicator badge `ج.م`. Trend indicators must include both a color token and an explicit text indicator (e.g., `+12% عن الشهر السابق`), not color alone.

### 3. Financial Input Fields (حقول الإدخال المالي)
- **Dimensions:** Minimum height `50px`. Padding: right `16px`, left `16px`.
- **Currency Fields:** Integrated left prefix displaying `ج.م` in bold slate text (`#4B5563`), preventing entry ambiguity.
- **Labels:** Always persistently visible on top (`margin-bottom: 8px`), never hidden inside placeholders. Font size `1rem`, font weight `600`, color `#1E2922`.
- **Error States:** Border `2px solid #DC2626` with explicit explanatory helper text below in Arabic with an alert icon.

### 4. Status Chips & Badges (شارات الحالة)
- **الوارد (Income):** Background `#DCFCE7`, Text `#15803D`, Border `#86EFAC`.
- **المصروف (Expense):** Background `#FEE2E2`, Text `#B91C1C`, Border `#FCA5A5`.
- **قيد الاعتماد (Pending):** Background `#FEF3C7`, Text `#B45309`, Border `#FCD34D`.
- Padding: `4px 12px`, border radius `9999px`, font size `0.875rem`, font weight `700`.

### 5. Ledger Tables (جداول المعاملات المالية)
- **Row Height:** Generous `60px` minimum row height to support touch and distinct pointer clicks.
- **Alternating Contrast:** Header row `#F6F8F6` with bold text `#1E2922`. Hover row background `#FAF9F5`.
- **Alignment:** Transaction descriptions and categories aligned to the right; financial figures aligned to the left with right-justified numeric values for clean decimal scanning.

### 6. Quick Donation Drawer / Modal (نافذة سند القبض)
- Dedicated quick-entry dialog for cash receipts during Friday prayers (صلاة الجمعة) or community gatherings.
- Features prominent preset denomination chips (`100 ج.م`, `200 ج.م`, `500 ج.م`, `1,000 ج.م`) for rapid, single-click entry without manual keyboard typing.