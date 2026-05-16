---
name: MCMS
description: Medical Center Management System — clinical dashboard for Arabic-speaking clinic staff
colors:
  primary: "#22B896"
  primary-foreground: "#FFFFFF"
  background: "#F0F5F7"
  foreground: "#111827"
  card: "#FFFFFF"
  card-foreground: "#111827"
  secondary: "#E6F5F3"
  secondary-foreground: "#111827"
  muted: "#EDF1F4"
  muted-foreground: "#6B7A8D"
  accent: "#E8F6F4"
  accent-foreground: "#111827"
  destructive: "#EF4444"
  destructive-foreground: "#FAFAFA"
  border: "#D1D9E0"
  input: "#C8D2DC"
  ring: "#2DD4B8"
  success-500: "#1FB896"
  warning-500: "#D4922A"
  info-500: "#2B7DE9"
  accent-mint: "#1A9E7A"
  accent-teal: "#22A8B8"
  accent-coral: "#E85D5D"
  accent-violet: "#8B5CF6"
  sidebar: "#1A1F2E"
  sidebar-foreground: "#EDF1F5"
  sidebar-primary: "#2DD4B8"
  sidebar-accent: "#252B3D"
  sidebar-border: "#2D3448"
  surface: "#FFFFFF"
  surface-secondary: "#F7FAFB"
  surface-tertiary: "#EDF1F4"
typography:
  display:
    fontFamily: "Manrope, Tajawal, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: "-0.025em"
  headline:
    fontFamily: "Manrope, Tajawal, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: "-0.02em"
  title:
    fontFamily: "Manrope, Tajawal, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.4
  body:
    fontFamily: "Manrope, Tajawal, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.6
  label:
    fontFamily: "Manrope, Tajawal, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: "0.05em"
rounded:
  sm: "0.5rem"
  md: "0.625rem"
  lg: "0.75rem"
  xl: "1rem"
  "2xl": "1.25rem"
  "3xl": "1.5rem"
spacing:
  xs: "0.25rem"
  sm: "0.5rem"
  md: "0.75rem"
  lg: "1rem"
  xl: "1.25rem"
  "2xl": "1.5rem"
  "3xl": "2rem"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.primary-foreground}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
  button-secondary:
    backgroundColor: "{colors.secondary}"
    textColor: "{colors.secondary-foreground}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 1rem"
  card-default:
    backgroundColor: "{colors.card}"
    textColor: "{colors.card-foreground}"
    rounded: "{rounded.2xl}"
  input-default:
    backgroundColor: "{colors.card}"
    textColor: "{colors.foreground}"
    rounded: "{rounded.lg}"
    padding: "0.5rem 0.75rem"
---

# Design System: MCMS

## 1. Overview

**Creative North Star: "The Clinical Instrument"**

MCMS is designed like a precision medical instrument — every element exists to serve the clinical workflow, nothing is decorative. The interface is warm enough to feel human (soft cream backgrounds, rounded corners, gentle shadows) but precise enough to feel authoritative (tight typography, clear hierarchy, purposeful color). It reads like a modern hospital system built by people who understand that clinic staff are working under pressure with real patients waiting.

The system explicitly rejects generic SaaS dashboard clichés: no gradient hero metrics, no identical card grids, no glassmorphism as a default, no dark-mode-for-the-sake-of-cool. Color encodes status and category, never decoration. Arabic is the primary reading direction — RTL is native, not adapted.

**Key Characteristics:**
- Warm clinical aesthetic — cream and off-white surfaces with mint/teal accents
- Data-dense but scannable — power users need information at a glance
- Color-coded departments and statuses with dot indicators
- Soft elevation with subtle borders, never heavy shadows
- Arabic-native typography with Tajawal as the primary Arabic typeface
- Mobile-responsive table cards that stack on small screens

## 2. Colors

A warm clinical palette anchored in mint/teal, with semantic colors for status and department coding. The background is a soft cream (not stark white), creating a calm reading surface for long shifts.

### Primary
- **Clinical Mint** (#22B896 / oklch(0.72 0.14 175)): The primary action color. Used for primary buttons, active states, links, and the ring focus indicator. Carries approximately 15-20% of interactive surfaces. Paired with white text for contrast.

### Secondary
- **Soft Mint** (#E6F5F3 / oklch(0.95 0.03 175)): Background tint for secondary buttons, selected states, and subtle highlights. Used when the primary mint would be too dominant.

### Tertiary
- **Teal** (#22A8B8 / oklch(0.68 0.12 190)): Secondary accent for charts, department coding, and visual variety. Never competes with the primary mint.
- **Coral** (#E85D5D / oklch(0.62 0.22 5)): Urgency and destructive actions. Used sparingly for critical alerts, delete actions, and overdue indicators.
- **Violet** (#8B5CF6 / oklch(0.65 0.24 280)): Tertiary accent for specific departments (lab, diagnostics) and chart series differentiation.

### Neutral
- **Warm Cream** (#F0F5F7 / oklch(0.96 0.01 210)): Primary background. Soft enough to reduce eye strain during long shifts, warm enough to feel human.
- **Pure White** (#FFFFFF): Card and surface backgrounds. Creates depth against the cream page background.
- **Near Black** (#111827 / oklch(0.20 0.03 260)): Primary text color. High contrast against all backgrounds.
- **Slate Gray** (#6B7A8D / oklch(0.52 0.04 230)): Secondary text, labels, and muted content.
- **Border Gray** (#D1D9E0 / oklch(0.86 0.02 220)): Default border color. Soft enough to recede, visible enough to define structure.
- **Dark Navy** (#1A1F2E / oklch(0.14 0.03 240)): Sidebar background. Provides strong contrast with the light content area.

### Semantic
- **Success Green** (#1FB896): Confirmed appointments, completed tasks, positive metrics.
- **Warning Amber** (#D4922A): Pending items, approaching deadlines, attention needed.
- **Info Blue** (#2B7DE9): Informational messages, help content, neutral status.

### Named Rules

**The Status Dot Rule.** Status is communicated through colored dots paired with text labels, never color alone. A green dot + "مؤكد" (confirmed) is accessible; a green background alone is not.

**The Department Color Rule.** Each department has a consistent color assignment (mint = appointments, teal = general clinic, violet = lab, coral = billing, etc.). Colors are used as dots and bar fills, never as full backgrounds.

**The Cream Surface Rule.** The page background is always warm cream (#F0F5F7), never pure white. Cards are white on cream, creating natural depth without shadows.

## 3. Typography

**Display Font:** Manrope (Latin) / Tajawal (Arabic) with Instrument Sans fallback
**Body Font:** Same stack — single typeface family for consistency
**Label Font:** Same stack, uppercase with tracking

**Character:** A modern geometric sans with Arabic support. Manrope provides clean, readable Latin characters; Tajawal provides warm, professional Arabic. The pairing feels contemporary but not trendy — appropriate for a medical system that needs to feel established and trustworthy.

### Hierarchy
- **Page Title** (600, 1.5rem / 24px, 1.3 line-height, -0.025em tracking): Main page headings. Appears at the top of each view.
- **Section Label** (500, 0.75rem / 12px, uppercase, 0.1em tracking, muted-foreground): Section headers within pages. Always uppercase with wide tracking for scannability.
- **Card Value** (700, 1.875rem / 30px, tabular-nums): Dashboard metric values. Bold and large for at-a-glance reading.
- **Body Text** (400, 0.875rem / 14px, 1.6 line-height, muted-foreground): Default content text. Relaxed line height for readability during long reading sessions.
- **Badge Text** (500, 0.75rem / 12px): Status badges, tags, and small labels. Medium weight for legibility at small sizes.
- **Table Header** (700, 0.68rem / 11px, uppercase, 0.1em tracking): Data table column headers. Compact but authoritative.

### Named Rules

**The Single Family Rule.** One typeface stack for all text. Hierarchy is achieved through weight, size, and case — never through font switching. This keeps the interface cohesive and reduces visual noise.

**The Tabular Num Rule.** All numeric values (metrics, times, quantities) use `tabular-nums` for consistent alignment in tables and dashboards.

**The Arabic-First Rule.** Tajawal is listed before Instrument Sans in the font stack. Arabic text renders in Tajawal by default; Latin falls back to Manrope. Line height and spacing are tuned for Arabic reading comfort.

## 4. Elevation

A hybrid system using subtle shadows combined with tonal layering. Surfaces are never flat — they have gentle depth that creates hierarchy without heaviness. The elevation vocabulary is purposeful: flat surfaces for data tables, soft shadows for cards, clay-style shadows for interactive elements.

### Shadow Vocabulary
- **Flat** (`0 1px 2px rgba(15, 23, 42, 0.05)`): Minimal elevation for data tables and subtle containers. Barely perceptible.
- **Soft** (`0 10px 28px rgba(15, 23, 42, 0.08)`): Default card elevation. Soft, diffuse shadow that creates depth without drama.
- **Clay** (`0 14px 30px rgba(26, 126, 102, 0.10), 0 2px 4px rgba(15, 23, 42, 0.05)`): Interactive cards and buttons. Mint-tinted shadow that reinforces the brand color.
- **Neumorphic** (`0 0 0 1px rgba(209, 217, 224, 0.82), 0 8px 22px rgba(15, 23, 42, 0.10)`): Form inputs and controls. Subtle inset feel with a border ring.
- **Glass** (`0 14px 32px rgba(15, 23, 42, 0.10)`): Overlay panels and alerts. Used with backdrop blur for floating elements.
- **Critical** (`0 0 0 2px rgba(239, 68, 68, 0.35), 0 14px 26px rgba(180, 40, 40, 0.18)`): Error states and critical alerts. Red ring + shadow combination.

### Named Rules

**The Mint Shadow Rule.** Interactive elements (cards, buttons) that respond to hover use mint-tinted shadows (clay elevation). This creates a subtle brand reinforcement — the shadow itself carries the primary color.

**The Flat Table Rule.** Data tables use flat elevation only. Tables contain dense information; adding shadow would compete with the data. Depth comes from the table shell's subtle gradient background instead.

**The Border-Plus-Shadow Rule.** Every elevated surface has both a border and a shadow. The border defines the edge crisply; the shadow provides depth. Neither alone is sufficient.

## 5. Components

### Buttons
- **Shape:** Gently curved edges (0.75rem / 12px radius)
- **Primary:** Clinical Mint (#22B896) background with white text. Padding: 0.5rem 1rem. Font: 0.875rem, 500 weight.
- **Secondary:** Soft Mint (#E6F5F3) background with dark text. Same padding and typography as primary.
- **Ghost:** Transparent background with border. Used for tertiary actions.
- **Hover:** Primary buttons lift slightly (translateY(-1px)) with enhanced mint shadow. Secondary buttons darken the mint tint.
- **Focus:** 3px ring in bright mint (#2DD4B8) with 0.18 opacity.
- **Active:** Returns to flat position, shadow reduces.

### Cards / Containers
- **Corner Style:** Rounded rectangles (1.25rem / 20px radius for main cards)
- **Background:** White (#FFFFFF) on cream page background (#F0F5F7)
- **Border:** Soft gray (#D1D9E0 at 0.8 opacity), 1px
- **Shadow Strategy:** Clay elevation for interactive cards, soft elevation for static cards
- **Internal Padding:** 1rem (16px) standard, 1.25rem (20px) for dashboard metric cards
- **Hover Treatment:** Border shifts to mint-tinted, shadow enhances with mint glow

### Inputs / Fields
- **Style:** Neumorphic treatment — subtle gradient background with border and soft shadow
- **Background:** Linear gradient from white (0.94 opacity) to surface-neutral-1
- **Border:** Soft gray (#D1D9E0), 1px
- **Radius:** 0.5rem (8px) — slightly tighter than cards
- **Focus:** Border shifts to primary mint, 3px mint ring appears
- **Padding:** 0.5rem 0.75rem (8px 12px)
- **Font:** 0.875rem, matching body text

### Navigation (Sidebar)
- **Style:** Dark navy (#1A1F2E) sidebar with light text (#EDF1F5)
- **Width:** Fixed width, collapsible on mobile
- **Active State:** Light mint-tinted background (#252B3D) with mint dot indicator
- **Hover:** Slightly lighter background, no color shift
- **Typography:** 0.875rem, 400 weight for items; 0.68rem uppercase for section labels
- **Section Labels:** Uppercase, wide tracking, muted color — creates clear grouping
- **Mobile:** Collapses to icon-only or slides out as overlay

### Status Badges
- **Style:** Pill-shaped (full border-radius), small padding (0.125rem 0.5rem)
- **Background:** Tinted version of status color at 10-15% opacity
- **Text:** Status color at 70-80% saturation
- **Dot:** 1.5rem circular dot inside badge, same color as text
- **Variants:** Success (green), Warning (amber), Danger (red), Info (blue), Neutral (gray)

### Data Tables
- **Shell:** Rounded container (1.25rem radius) with subtle gradient background
- **Header:** Light gray gradient background, uppercase labels, sticky on scroll
- **Rows:** Alternating subtle tint on even rows, mint gradient on hover
- **Hover:** Mint left-border accent (3px) appears on row hover
- **Mobile:** Cards stack vertically with data-label prefixes
- **Font:** 0.875rem body, 0.68rem uppercase headers

## 6. Do's and Don'ts

### Do:
- **Do** use warm cream (#F0F5F7) as the page background, never pure white. Cards are white on cream for natural depth.
- **Do** pair every status color with a text label and dot indicator. Color alone is never the sole signal.
- **Do** use mint-tinted shadows (clay elevation) for interactive elements to reinforce brand identity.
- **Do** maintain Arabic-first typography with Tajawal as the primary Arabic typeface.
- **Do** use tabular-nums for all numeric values in tables and dashboards.
- **Do** apply the border-plus-shadow rule: every elevated surface has both a 1px border and a shadow.
- **Do** keep the sidebar dark navy (#1A1F2E) for strong contrast with the light content area.

### Don't:
- **Don't** use generic SaaS dashboard clichés — no gradient hero metrics, no identical card grids, no glassmorphism as a default.
- **Don't** use color as the sole indicator of status. Always pair with text labels, icons, or patterns.
- **Don't** use pure black (#000000) or pure white (#FFFFFF) for text — use near-black (#111827) and warm white tints.
- **Don't** apply heavy shadows. The maximum shadow is soft (0.08 opacity); critical alerts use a red ring, not a heavy shadow.
- **Don't** use border-left or border-right greater than 1px as a colored accent stripe on cards or list items.
- **Don't** use gradient text (background-clip: text with gradient). Use solid colors with weight or size for emphasis.
- **Don't** treat dark mode as the default. Light mode is primary; dark mode is an option for specific user preferences.
- **Don't** use playful or consumer-health-app aesthetics — no bright neons, no emoji-heavy interfaces, no overly rounded everything.
- **Don't** create dense enterprise-software interfaces that feel like spreadsheets. Data density must remain scannable.
- **Don't** use side-stripe borders as colored accents. Rewrite with full borders, background tints, or leading icons.
