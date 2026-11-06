---
name: مكتب فيصل حميضان الحميضان للمحاماة
description: Private case-lifecycle workspace and client portal, set in the office logo's charcoal ink on its cool-grey ground.
colors:
  logo-charcoal: "#303741"
  charcoal-deep: "#232a33"
  logo-grey: "#e1e5e6"
  cool-off-white: "#f4f5f4"
  surface: "#ffffff"
  accent-soft: "#e6e9ee"
  border: "#dde1e2"
  rule: "#e9ecec"
  control: "#c3c9d1"
  text: "#181c22"
  nav-ink: "#3f4650"
  muted: "#545c67"
  faint: "#5a626d"
  signal-red: "#a82a22"
  negative: "#b4232a"
  amber: "#8a5009"
  positive: "#136a43"
  pill-positive-bg: "#e1f3ea"
  pill-positive-ink: "#0e4a2f"
  pill-negative-bg: "#fbe8e6"
  pill-negative-ink: "#8a1f1a"
  pill-warning-bg: "#fdf3dc"
  pill-warning-ink: "#6f4106"
  warning-border: "#efd08a"
  pill-info-bg: "#e7ecf3"
  pill-info-ink: "#263a55"
  pill-neutral-bg: "#eceef2"
typography:
  hero-figure:
    fontFamily: "Readex Pro Variable, Readex Pro, system-ui, Segoe UI, Tahoma, sans-serif"
    fontSize: "40px"
    fontWeight: 600
    lineHeight: 1
    fontFeature: "tnum"
  display:
    fontFamily: "Readex Pro Variable, Readex Pro, system-ui, Segoe UI, Tahoma, sans-serif"
    fontSize: "30px"
    fontWeight: 600
    lineHeight: 1.25
  headline:
    fontFamily: "Readex Pro Variable, Readex Pro, system-ui, Segoe UI, Tahoma, sans-serif"
    fontSize: "28px"
    fontWeight: 600
    lineHeight: 1.375
  title:
    fontFamily: "Readex Pro Variable, Readex Pro, system-ui, Segoe UI, Tahoma, sans-serif"
    fontSize: "16px"
    fontWeight: 600
  body:
    fontFamily: "Readex Pro Variable, Readex Pro, system-ui, Segoe UI, Tahoma, sans-serif"
    fontSize: "14px"
    fontWeight: 350
    lineHeight: 1.65
  reading:
    fontFamily: "Readex Pro Variable, Readex Pro, system-ui, Segoe UI, Tahoma, sans-serif"
    fontSize: "16px"
    fontWeight: 350
    lineHeight: 2
  meta:
    fontFamily: "Readex Pro Variable, Readex Pro, system-ui, Segoe UI, Tahoma, sans-serif"
    fontSize: "12.5px"
    fontWeight: 350
  label:
    fontFamily: "Readex Pro Variable, Readex Pro, system-ui, Segoe UI, Tahoma, sans-serif"
    fontSize: "12px"
    fontWeight: 500
rounded:
  diamond: "1.5px"
  chip: "7px"
  control: "10px"
  card: "14px"
  sheet: "18px"
  band: "22px"
  track: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "20px"
  2xl: "24px"
  3xl: "28px"
components:
  button-primary:
    backgroundColor: "{colors.logo-charcoal}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "0 16px"
    height: "38px"
  button-primary-hover:
    backgroundColor: "{colors.charcoal-deep}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text}"
    rounded: "{rounded.control}"
    padding: "0 16px"
    height: "38px"
  button-secondary-hover:
    backgroundColor: "{colors.cool-off-white}"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.logo-charcoal}"
    rounded: "{rounded.control}"
    padding: "0 16px"
    height: "38px"
  button-ghost-hover:
    backgroundColor: "{colors.logo-grey}"
  button-danger:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.negative}"
    rounded: "{rounded.control}"
    padding: "0 16px"
    height: "38px"
  button-danger-hover:
    backgroundColor: "{colors.pill-negative-bg}"
  button-sm:
    padding: "0 12px"
    height: "32px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text}"
    rounded: "{rounded.control}"
    padding: "0 12px"
    height: "42px"
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.card}"
    padding: "16px 20px 20px"
  sheet:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.sheet}"
    padding: "24px 28px"
  well:
    backgroundColor: "{colors.cool-off-white}"
    rounded: "{rounded.card}"
  mist-band:
    backgroundColor: "{colors.logo-grey}"
    textColor: "{colors.logo-charcoal}"
    rounded: "{rounded.band}"
    padding: "24px 32px 16px"
  pill-neutral:
    backgroundColor: "{colors.pill-neutral-bg}"
    textColor: "{colors.logo-charcoal}"
    rounded: "{rounded.chip}"
    padding: "0 9px"
    height: "23px"
  pill-accent:
    backgroundColor: "{colors.logo-charcoal}"
    textColor: "{colors.surface}"
    rounded: "{rounded.chip}"
    padding: "0 9px"
    height: "23px"
  pill-positive:
    backgroundColor: "{colors.pill-positive-bg}"
    textColor: "{colors.pill-positive-ink}"
    rounded: "{rounded.chip}"
    height: "23px"
  pill-negative:
    backgroundColor: "{colors.pill-negative-bg}"
    textColor: "{colors.pill-negative-ink}"
    rounded: "{rounded.chip}"
    height: "23px"
  pill-warning:
    backgroundColor: "{colors.pill-warning-bg}"
    textColor: "{colors.pill-warning-ink}"
    rounded: "{rounded.chip}"
    height: "23px"
  pill-info:
    backgroundColor: "{colors.pill-info-bg}"
    textColor: "{colors.pill-info-ink}"
    rounded: "{rounded.chip}"
    height: "23px"
  nav-item:
    textColor: "{colors.nav-ink}"
    rounded: "{rounded.control}"
    padding: "0 12px"
    height: "40px"
  nav-item-active:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.logo-charcoal}"
  tab:
    textColor: "{colors.muted}"
    padding: "10px"
---

# Design System: مكتب فيصل حميضان الحميضان للمحاماة

## Overview

**Creative North Star: "The Logo Sheet"**

The product is laid out like the office logo printed on its own paper. The logo's charcoal ink is used for type, primary actions and focus. The logo's cool-grey ground (mist) forms the frame: the office side rail, the portal header, the hero bands on the dashboard and client home, and the sign-in panel. Wide areas sit on a cool off-white, and work happens on white sheets and cards. The logo's corner geometry comes through as the only ornament. On mist surfaces it appears as a faint charcoal lattice (diamond outline, short bar and small filled diamond). As markers, a 7px diamond is used in the nav, the agenda and the pipeline legend, and diamond nodes make up the dashboard's week ribbon. The original charcoal logo mark appears everywhere; the sign-in panel shows the full lockup at its original proportions.

Density is office-grade. The body is 14px Readex Pro at a light 350 weight. Lists are separated by hairline rules, never by zebra striping or header bars. Numbers are always isolated LTR, tabular figures inside Arabic text, and dates are always shown as Gregorian with the Hijri date next to them. Colour does no decorating. Charcoal and its grey ramp carry structure and data, and red, amber and green appear only when they mean overdue, soon or collected. These state colours are dark enough to sit directly on the mist bands at 4.5:1 or better.

The workspace is still. Nothing animates in on load: figures appear at their final value and bars and nodes render in place. Motion is limited to touch and state feedback: a press scale, colour changes on hover (fine pointers only), and the dialog and drawer opening. The system is light-only.

**Key Characteristics:**
- Logo-grey (mist) frame with a 7% charcoal lattice; cool off-white ground; white sheets and cards.
- Charcoal ink for text, primary buttons, focus and selection.
- The diamond is the only ornament.
- One sans family (Readex Pro, self-hosted), weights 350/500/600, tabular isolated numerals.
- State colour is reserved for meaning; data is drawn in charcoal and grey.
- No entrance motion; feedback motion only. Light theme only.

## Colors

A cool, near-monochrome system taken directly from the logo file: its charcoal ink and grey ground, plus three dark state hues held in reserve.

### Primary
- **Logo Charcoal** (logo-charcoal): The logo ink. It is the primary button fill, the text and focus-ring colour, the active tab underline, the selected chart bar, today's week-ribbon node, `pill-accent` (active case) and the text-selection fill. `--primary`, `--accent`, `--ink` and `--focus` all resolve to it.
- **Charcoal Deep** (charcoal-deep): Primary-button hover only.

### Neutral
- **Logo Grey** (logo-grey): The frame. It fills the side rail, the portal header, the two hero bands and the sign-in panel, always with the lattice. It is also the ghost-button hover fill on white.
- **Cool Off-White** (cool-off-white): The app ground behind sheets and cards, the sticky top bar (at 90% with blur), wells and empty states, and secondary-button hover.
- **Surface** (surface): White sheets, cards, inputs, secondary buttons, and the active nav item on the mist rail.
- **Accent Soft** (accent-soft): A quiet charcoal-tinted fill for highlighted rows.
- **Border / Rule / Control** (border, rule, control): Three stroke weights. Border outlines sheets and cards, rule separates list and table rows, and control strokes inputs and secondary buttons.
- **Text / Muted / Faint** (text, muted, faint): Primary copy, secondary metadata, and placeholders, empty values and weekend days. Faint holds 4.86:1 on mist and 6.17:1 on white.
- **Nav Ink** (nav-ink): Inactive rail items and sign-out on mist (7.5:1).

### Tertiary (state, meaning only)
- **Signal Red** (signal-red): Overdue and expired. It colours the overdue finding line and the deadlines-this-week figure in the hero, the nearest-deadline countdown and meter when 3 or fewer days remain, alerted counters, and the claims count badge.
- **Negative** (negative): Field errors, required asterisks, danger buttons, and the overdue agenda group heading.
- **Amber** (amber): Soon: a deadline 10 or fewer days away, the deadline ring on week-ribbon nodes, items needing determination, and the non-production environment strip.
- **Positive** (positive): Collected money (including the client's collected figure on the portal band) and the "clean" scan state.
- **Pill pairs** (pill-*-bg / pill-*-ink): Tinted chip fills, each paired with a darkened ink of the same hue. Warning adds a 1px inset border (warning-border).

### Named Rules
**The Meaning-Only Rule.** Red, amber and green appear only to say overdue, soon or collected. Charts, pipelines and agenda type markers use charcoal and greys, never a state hue.

**The Logo Ground Frames the Work Rule.** Logo grey with the lattice belongs to the frame (rail, portal header, sign-in panel) and the two hero bands. Inner working pages sit on a single white sheet over the off-white ground, with no band.

**The One Palette for Every Surface Rule.** The state colours are used as-is on white, off-white and mist alike. There are no lightened or "on-dark" variants.

## Typography

**Display Font:** Readex Pro Variable (self-hosted via @fontsource; fallback system-ui, Segoe UI, Tahoma)
**Body Font:** Readex Pro Variable (same family)

**Character:** A single modern Arabic sans throughout. Hierarchy comes from size and a narrow weight band (350 body, 500 controls and labels, 600 headings and figures), not from a second family.

### Hierarchy
- **Hero figure** (600, 34px mobile / 40px desktop, line-height 1, tabular, charcoal): Static figures in the hero bands. Money figures use `clamp(26px, 7.5vw, 40px)` (36px cap on the portal). A one-off 64px variant shows the nearest-deadline day count.
- **Display** (600, 26px mobile / 30px desktop, tight): Hero-band titles.
- **Headline** (600, 24px mobile / 28px desktop, line-height 1.375, balanced): Page titles. Titles over 90 characters drop to 20/22px.
- **Title** (600, 16px): Panel and dialog titles. Portal section headings use 17px.
- **Body** (350, 14px, line-height 1.65): All working text, table cells, inputs and buttons (buttons at 500).
- **Reading** (350, 16px, line-height 2, max 820px): The memo editor and memo preview. h2 is 19/700 and h3 is 17/600.
- **Meta** (350, 12.5px, muted): Secondary lines, table headers (at 500), field hints and errors, labels for the hero figures.
- **Label** (500, 12px): Pills, counts and small units.

### Named Rules
**The Isolated Numeral Rule.** Every number, reference, date and amount is wrapped LTR-isolated with tabular figures (`.num`, `<bdi dir="ltr">`), so it never reorders inside Arabic text.

**The Dual Calendar Rule.** A date is never shown alone. Gregorian comes first, with the Umm al-Qura Hijri date beside it at 0.88em in muted, suffixed with هـ.

**The No-Case-Tricks Rule.** No uppercase, no tracking and no small labels above headings. Hierarchy comes from size and weight alone.

## Layout

- **Office shell:** a fixed 256px mist side rail at `lg` and above, with a slide-in mist drawer of up to 300px (86vw) below `lg`. The top bar is sticky, 56px tall (64px at `lg`), in off-white at 90% with blur, and holds global search centred.
- **Working pages:** one white sheet (18px radius, 28px inline and 24px block padding) that fills the viewport height. Below `sm` the sheet runs edge to edge with square corners and no side borders.
- **Dashboard:** a full-width mist band with the title, dual-calendar date, finding line and nearest-deadline link on the start side, and a small primary button plus ghost buttons on the end. Below that is a 2-column (mobile) / 4-column (`lg`) figure row, each figure separated by a 1px charcoal/15% inline-start rule, then the 7-column week ribbon. The content below is a `1.6fr / 1fr` grid at `xl` with 16px gaps: the agenda on the start side and a stack of panels on the end.
- **Portal:** a mist header over a centred column with a 1120px maximum, the mist hero band, then stacked full-width cards of linked rows.
- **Sign-in:** a split layout at `lg` (`1fr / 1.1fr`). One side is a mist panel showing the full logo lockup centred at `min(380px, 70%)`, with three large outlined rotated squares (charcoal/10%) bleeding off its corners. The other side is a 420px form sheet on off-white. Below `lg` only the form shows, under the full logo.
- **Rhythm:** a 4px base. 8, 12, 16 and 20px are the working steps: 20px card padding, 16px grid gaps, 12px table cell inline padding, and 10–12px list-row block padding.
- **Breakpoints:** Tailwind defaults (sm 640, lg 1024, xl 1280). Mobile must work at 390px, and coarse pointers get 16px inputs.

## Elevation & Depth

Mostly flat tonal layering (logo-grey frame, off-white ground, white surface), with a very soft ambient shadow to lift cards off the ground. Sheets and mist bands have no shadow.

### Shadow Vocabulary
- **Card ambient** (`box-shadow: 0 1px 2px rgba(27,31,37,.04), 0 12px 28px -22px rgba(27,31,37,.35)`): Dashboard panels and portal list cards.
- **Primary lift** (`box-shadow: 0 1px 0 rgba(255,255,255,.08) inset, 0 6px 14px -8px rgba(27,31,37,.7)`): Primary buttons only.
- **Nav lift** (`box-shadow: 0 1px 2px rgba(48,55,65,.08)`): The white active item on the mist rail and portal nav.
- **Dialog** (`box-shadow: 0 24px 64px rgba(20,24,30,0.22)`, backdrop `rgba(20,24,30,0.38)`): Modal dialogs and the drawer scrim.
- **Input focus halo** (`box-shadow: 0 0 0 4px rgba(48,55,65,0.12)`): Input focus, together with a border that turns charcoal.

### Named Rules
**The Sheet Is Flat Rule.** Full-page sheets carry only a border. Shadow belongs to cards that sit directly on the ground, and depth is never stacked inside a sheet.

## Shapes

Gently rounded rectangles that step down in radius as they get smaller: band 22px, sheet 18px, card 14px, control 10px, chip 7px. Bars in charts take a 6px top radius. Progress tracks (meter, pipeline) are full pills. The diamond is a 7px square rotated 45° with a 1.5px corner. Week-ribbon nodes are 4px-rounded rotated squares, 14–32px in size depending on the day's load. The lattice tile is 56px: a diamond outline, a short bar and a small filled diamond, with strokes in charcoal at 7% and the fill at 5%. 1px strokes do all the dividing.

## Components

### Buttons
Quiet and solid. They respond to touch, not to hovering for show.
- **Shape:** control radius (10px), 38px tall (32px small), 7px gap between icon and label, weight 500.
- **Primary:** charcoal fill, white text, primary-lift shadow. Hover goes to charcoal-deep. It is also the main action inside the mist hero bands.
- **Secondary (default variant):** white with a control-colour stroke. Hover fills with off-white.
- **Ghost:** transparent with charcoal text. Hover fills with logo grey on white, and with white at 70% on mist.
- **Danger:** white with negative text and a `#e7b7b5` stroke. Hover fills with the negative pill tint.
- **States:** press scales to 0.97 over 120ms (disabled under reduced motion). Disabled is 60% opacity. Hover applies only under `(hover: hover) and (pointer: fine)`. Loading shows a spinning Lucide loader.

### Chips (Pills)
- **Style:** 23px tall, 7px radius, 12px/500, 9px padding. Tinted fill with a same-hue darkened ink. Accent is solid charcoal with white text, used for the "active" case status.
- **Tones:** neutral, positive, negative, warning (with inset border), info, accent.

### Cards / Containers
- **Card:** white, 1px border, 14px radius, card-ambient shadow. Panel headers are 16px/600 titles with an optional muted 13px description and end-aligned muted text links. The body has 20px padding.
- **Sheet:** white, 1px border, 18px radius, no shadow. It holds every inner working page and the sign-in form.
- **Well:** off-white fill, 14px radius, no border. Used for empty states (centred, 1.5-stroke Lucide icon in faint).

### Inputs / Fields
- **Style:** 42px tall, 10px radius, control-colour stroke, white fill. The label sits above at 14px/500 with a 6px gap. Hints and errors are 12.5px below.
- **Focus:** the border turns charcoal plus a 4px charcoal halo at 12%. Hover darkens the stroke to `#aab2bb`.
- **Error:** the border goes negative, the error message gets `role="alert"`, and required fields show a negative asterisk.
- **Date input:** day/month/year fields with a Gregorian/Hijri segmented switch in the label row. The active segment is solid charcoal. The stored value is always Gregorian, and the other calendar is shown as a hint.

### Navigation
- **Office rail (mist):** the charcoal logo mark plus the office name and licence, then 40px items with a 10px radius, 14px nav-ink text and 18px 1.5-stroke Lucide icons. Hover fills with white at 70%. The active item is white with the nav-lift shadow, 500 charcoal text, and a charcoal diamond on its inline-start edge. Group labels are 12px muted. The claims count is a signal-red badge.
- **Portal header (mist):** muted links with a 10px radius. The active link is white with the nav lift.
- **Tabs:** muted 14px. The selected tab is 600 text with a static 3px charcoal underline. Arrow keys follow RTL.
- **Tables:** 12.5px/500 muted headers with no header bar. Rows are separated by rule lines, with a light hover.

### Hero Band (signature)
Mist with lattice, 22px radius, no shadow. Charcoal title, muted dual-calendar date, a finding line (signal red when something is overdue), static charcoal figures separated by inline-start rules, and primary and ghost actions. It renders in place.

### Week Ribbon (signature)
Seven diamond nodes on a charcoal/15% hairline. Each node's size reflects the day's load. Today is solid charcoal; days with load are mid-grey; empty days are charcoal/30% outlines. A day with a deadline gets an amber ring. Under each node are the weekday, Hijri day, Gregorian day/month and the count. Clicking a day filters the agenda, and the selected day gets a white fill.

### Data Marks
- **BarChart:** grey bars (`#c9cfd7`), with the active bar in charcoal. Hover or focus reads the value into the caption, with a 200ms colour transition.
- **Pipeline:** one 16px track split into stages using a charcoal-to-grey ramp. Each legend entry is a diamond plus a link, and hovering it dims the other segments to 25% opacity.
- **Meter:** an 8px track in rule colour, filled with ink, positive, signal or amber.

## Do's and Don'ts

### Do:
- **Do** put logo grey plus the lattice on the frame and the two hero bands only. Inner pages get one white sheet on the off-white ground.
- **Do** use the original charcoal logo files as shipped. On the sign-in panel the full lockup keeps its original proportions.
- **Do** use the diamond (7px rotated square) as the list, legend and active-nav marker.
- **Do** keep charts and pipelines in charcoal and grey. Use signal red, amber or green only for overdue, soon or collected, and use them directly on mist.
- **Do** wrap every number, reference and date LTR-isolated with tabular figures, and pair every Gregorian date with its Hijri date.
- **Do** limit motion to feedback: press scale, colour transitions on fine-pointer hover, and the dialog and drawer opening. Respect `prefers-reduced-motion`.
- **Do** keep the 2px charcoal focus outline and the radius steps band 22 > sheet 18 > card 14 > control 10 > chip 7.

### Don't:
- **Don't** add entrance motion, count-ups or load-time reveals to working pages. By the owner's rule, motion belongs only to a public intro section, and there isn't one.
- **Don't** reintroduce a dark charcoal frame, lightened "on-dark" state variants, or a dark theme. The system is light-only.
- **Don't** use a state hue as a chart or category colour.
- **Don't** add a second typeface, uppercase, letter-spacing, or small labels above headings.
- **Don't** add zebra striping or filled header bars to tables. Rows are divided by rules alone.
- **Don't** add a decorative motif other than the diamond and the logo-corner lattice (no gradients, glows or illustrations).
- **Don't** load fonts, scripts or images from external hosts. Every asset is self-hosted under the strict CSP.
- **Don't** put a shadow on a full-page sheet or stack elevated cards inside a sheet.
