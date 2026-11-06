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
  nav-group:
    textColor: "{colors.nav-ink}"
    padding: "0 12px"
    height: "64px"
  nav-group-active:
    textColor: "{colors.logo-charcoal}"
  subnav-pill:
    textColor: "{colors.nav-ink}"
    rounded: "{rounded.control}"
    padding: "0 12px"
    height: "36px"
  subnav-pill-active:
    backgroundColor: "{colors.logo-charcoal}"
    textColor: "{colors.surface}"
  dock-item:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.muted}"
    height: "64px"
  dock-item-active:
    textColor: "{colors.logo-charcoal}"
  tab:
    textColor: "{colors.muted}"
    padding: "10px"
---

# Design System: مكتب فيصل حميضان الحميضان للمحاماة

## Overview

**Creative North Star: "The Logo Sheet"**

The product is laid out like the office logo printed on its own paper. The logo's charcoal ink is used for type, primary actions and focus. The logo's cool-grey ground (mist) forms the frame: the office top bar, the portal header, the hero bands on the dashboard and client home, and the sign-in panel. Wide areas sit on a cool off-white, and work happens on white sheets and cards. The logo's geometry supplies the only ornament: the diamond and, from the same judicial vocabulary, the scale. On mist surfaces the diamond appears as a faint charcoal lattice (diamond outline, short bar and small filled diamond). As markers, a 7px diamond is used in the nav, the agenda and the pipeline legend; diamond nodes make up the dashboard's week ribbon and the case stage path; and the custom icon set is drawn from diamonds and scales. The original charcoal logo appears everywhere; the sign-in panel shows the full lockup at its original proportions inside an animated diamond-and-scale scene.

Density is office-grade. The body is 14px Readex Pro at a light 350 weight. Lists are separated by hairline rules, never by zebra striping or header bars. Numbers are always isolated LTR, tabular figures inside Arabic text, and dates are always shown as Gregorian with the Hijri date next to them. Colour does no decorating. Charcoal and its grey ramp carry structure and data, and red, amber and green appear only when they mean overdue, soon or collected. These state colours are dark enough to sit directly on the mist bands at 4.5:1 or better.

Motion is short, purposeful and always on the project's three curves. Pages fade up into place on navigation, dashboard sections arrive in a brief stagger, hero figures count up once, indicators slide rather than jump (selected day, tab underline, stage-path fill), and menus and sheets open from where they are summoned. Forms never animate in: the sign-in form and every working form are usable on the first frame. Under reduced motion all of it collapses to short fades or nothing. The system is light-only.

**Key Characteristics:**
- Logo-grey (mist) frame with a 7% charcoal lattice; cool off-white ground; white sheets and cards.
- Charcoal ink for text, primary buttons, focus and selection.
- The diamond and the scale are the only ornament, in markers, icons and the sign-in scene.
- One sans family (Readex Pro, self-hosted), weights 350/500/600, tabular isolated numerals.
- State colour is reserved for meaning; data is drawn in charcoal and grey.
- Short, curve-bound motion that guides attention; forms never animate in; reduced motion is fades only. Light theme only.

## Colors

A cool, near-monochrome system taken directly from the logo file: its charcoal ink and grey ground, plus three dark state hues held in reserve.

### Primary
- **Logo Charcoal** (logo-charcoal): The logo ink. It is the primary button fill, the text and focus-ring colour, the active tab and nav-group underline, the active sub-link pill, completed stage-path nodes and fill, the selected chart bar, today's week-ribbon node, `pill-accent` (active case) and the text-selection fill. `--primary`, `--accent`, `--ink` and `--focus` all resolve to it.
- **Charcoal Deep** (charcoal-deep): Primary-button hover only.

### Neutral
- **Logo Grey** (logo-grey): The frame. It fills the office top bar, the portal header, the two hero bands and the sign-in panel, always with the lattice. It is also the ghost-button hover fill on white.
- **Cool Off-White** (cool-off-white): The app ground behind sheets and cards, the sub-link row under the top bar (at 70%), wells, the stage-path detail panel, the bottom sheet's destination tiles, and secondary-button hover.
- **Surface** (surface): White sheets, cards, inputs, secondary buttons, the account menu, the mobile dock (at 95% with blur) and the bottom sheet.
- **Accent Soft** (accent-soft): A quiet charcoal-tinted fill for highlighted rows.
- **Border / Rule / Control** (border, rule, control): Three stroke weights. Border outlines sheets and cards and the stage-path baseline, rule separates list and table rows, and control strokes inputs, secondary buttons and not-yet-reached stage nodes (dashed).
- **Text / Muted / Faint** (text, muted, faint): Primary copy, secondary metadata, and placeholders, empty values and weekend days. Faint holds 4.86:1 on mist and 6.17:1 on white.
- **Nav Ink** (nav-ink): Inactive top-bar groups and sub-links on mist (7.5:1).

### Tertiary (state, meaning only)
- **Signal Red** (signal-red): Overdue and expired. It colours the overdue finding line and the deadlines-this-week figure in the hero, the nearest-deadline countdown and meter when 3 or fewer days remain, alerted counters, and the claims count badge (and its dot on the dock's "more" item).
- **Negative** (negative): Field errors, required asterisks, danger buttons, and the overdue agenda group heading.
- **Amber** (amber): Soon: a deadline 10 or fewer days away, the deadline ring on week-ribbon nodes, items needing determination, and the non-production environment strip.
- **Positive** (positive): Collected money (including the client's collected figure on the portal band) and the "clean" scan state.
- **Pill pairs** (pill-*-bg / pill-*-ink): Tinted chip fills, each paired with a darkened ink of the same hue. Warning adds a 1px inset border (warning-border).

### Named Rules
**The Meaning-Only Rule.** Red, amber and green appear only to say overdue, soon or collected. Charts, pipelines, stage paths and agenda type markers use charcoal and greys, never a state hue.

**The Logo Ground Frames the Work Rule.** Logo grey with the lattice belongs to the frame (office top bar, portal header, sign-in panel) and the two hero bands. Inner working pages sit on a single white sheet over the off-white ground, with no band.

**The One Palette for Every Surface Rule.** The state colours are used as-is on white, off-white and mist alike. There are no lightened or "on-dark" variants.

## Typography

**Display Font:** Readex Pro Variable (self-hosted via @fontsource; fallback system-ui, Segoe UI, Tahoma)
**Body Font:** Readex Pro Variable (same family)

**Character:** A single modern Arabic sans throughout. Hierarchy comes from size and a narrow weight band (350 body, 500 controls and labels, 600 headings and figures), not from a second family.

### Hierarchy
- **Hero figure** (600, 34px mobile / 40px desktop, line-height 1, tabular, charcoal): Figures in the hero bands; they count up on first view. Money figures use `clamp(26px, 7.5vw, 40px)` (36px cap on the portal). A one-off 64px variant shows the nearest-deadline day count.
- **Display** (600, 26px mobile / 30px desktop, tight): Hero-band titles.
- **Headline** (600, 24px mobile / 28px desktop, line-height 1.375, balanced): Page titles and the sign-in title (24px). Titles over 90 characters drop to 20/22px.
- **Title** (600, 16px): Panel and dialog titles. Portal section headings use 17px.
- **Body** (350, 14px, line-height 1.65): All working text, table cells, inputs, buttons (at 500) and top-bar groups. Sub-links are 13.5px.
- **Reading** (350, 16px, line-height 2, max 820px): The memo editor and memo preview. h2 is 19/700 and h3 is 17/600.
- **Meta** (350, 12.5px, muted): Secondary lines, table headers (at 500), field hints and errors, labels for the hero figures, stage-path labels.
- **Label** (500, 12px): Pills, counts, small units and bottom-sheet group names. Dock labels are 11.5px.

### Named Rules
**The Isolated Numeral Rule.** Every number, reference, date and amount is wrapped LTR-isolated with tabular figures (`.num`, `<bdi dir="ltr">`), so it never reorders inside Arabic text.

**The Dual Calendar Rule.** A date is never shown alone. Gregorian comes first, with the Umm al-Qura Hijri date beside it at 0.88em in muted, suffixed with هـ.

**The No-Case-Tricks Rule.** No uppercase, no tracking and no small labels above headings. Hierarchy comes from size and weight alone.

## Layout

- **Office shell:** no side rail. A sticky mist top bar, 64px tall with a charcoal/10% bottom hairline, holds the logo (full brand from `xl`, compact below), the grouped navigation (from `lg`), global search filling the remaining width, and the account button. When the open group has more than one destination, a sub-link row sits directly under it on off-white at 70%. Below `lg` the group nav and account button are hidden; a fixed 5-slot bottom dock (64px plus the safe-area inset) takes over, and the page reserves that height at its foot.
- **Content width:** the top bar, sub-link row and main content share a 1480px maximum, centred, with 12/20px inline padding (bar) and 0/16/20px (main) at base/`sm`/`lg`.
- **Working pages:** one white sheet (18px radius, 28px inline and 24px block padding at `sm`, minimum 60dvh). Below `sm` the sheet runs edge to edge with square corners and no side borders.
- **Dashboard:** draws its own surfaces, no sheet. A full-width mist band with the title, dual-calendar date, finding line and nearest-deadline link on the start side, and a small primary button plus ghost buttons on the end. Below that is a 2-column (mobile) / 4-column (`lg`) figure row, each figure separated by a 1px charcoal/15% inline-start rule, then the 7-column week ribbon. The content below is a `1.6fr / 1fr` grid at `xl` with 16px gaps: the agenda on the start side and a stack of panels on the end.
- **Portal:** a mist header over a centred column with a 1120px maximum, the mist hero band, then stacked full-width cards of linked rows. Inner portal pages use the same white sheet.
- **Sign-in:** a split layout at `lg` (`1fr / 1.1fr`). One side is a mist panel holding the animated scene with the full logo lockup centred at `min(340px, 62%)`. The other side is a 420px form sheet on off-white. Below `lg` the form sits under a 210px mist card (22px radius) showing the small scene and a 150px logo.
- **Rhythm:** a 4px base. 8, 12, 16 and 20px are the working steps: 20px card padding, 16px grid gaps, 12px table cell inline padding, and 10–12px list-row block padding.
- **Breakpoints:** Tailwind defaults (sm 640, lg 1024, xl 1280). Mobile must work at 390px, touch targets in the dock and sheet are at least 48px, and coarse pointers get 16px inputs.

## Elevation & Depth

Mostly flat tonal layering (logo-grey frame, off-white ground, white surface), with a very soft ambient shadow to lift cards off the ground. Sheets, mist bands and the top bar have no shadow; the bar is separated by a hairline only.

### Shadow Vocabulary
- **Card ambient** (`box-shadow: 0 1px 2px rgba(27,31,37,.04), 0 12px 28px -22px rgba(27,31,37,.35)`): Dashboard panels and portal list cards.
- **Primary lift** (`box-shadow: 0 1px 0 rgba(255,255,255,.08) inset, 0 6px 14px -8px rgba(27,31,37,.7)`): Primary buttons only.
- **Nav lift** (`box-shadow: 0 1px 2px rgba(48,55,65,.08)`): The white active link in the portal header; the sliding selected-day indicator uses a near twin (`0 1px 3px rgba(48,55,65,.12)`).
- **Menu** (`box-shadow: 0 12px 32px -12px rgba(35,42,51,.35)`): The account menu popover.
- **Dialog** (`box-shadow: 0 24px 64px rgba(20,24,30,0.22)`, backdrop `rgba(20,24,30,0.38)`): Modal dialogs. The mobile bottom sheet uses a `rgba(35,42,51,0.4)` scrim and no shadow.
- **Input focus halo** (`box-shadow: 0 0 0 4px rgba(48,55,65,0.12)`): Input focus, together with a border that turns charcoal.

### Named Rules
**The Sheet Is Flat Rule.** Full-page sheets carry only a border. Shadow belongs to cards that sit directly on the ground and to floating menus, and depth is never stacked inside a sheet.

## Shapes

Gently rounded rectangles that step down in radius as they get smaller: band 22px (also the bottom sheet's top corners), sheet 18px, card 14px (also the account menu and stage detail panel), control 10px (also sub-link pills), chip 7px. Bars in charts take a 6px top radius. Progress tracks (meter, pipeline) are full pills. The diamond is a 7px square rotated 45° with a 1.5px corner (6px with a 1px corner on the dock). Week-ribbon nodes are 4px-rounded rotated squares, 14–32px in size depending on the day's load; stage-path nodes are 40px rotated squares with a 10px corner. The lattice tile is 56px: a diamond outline, a short bar and a small filled diamond, with strokes in charcoal at 7% and the fill at 5%. Active indicators are 3px bars with a 3px top radius. 1px strokes do all the dividing.

## Components

### Buttons
Quiet and solid. They respond to touch, not to hovering for show.
- **Shape:** control radius (10px), 38px tall (32px small), 7px gap between icon and label, weight 500.
- **Primary:** charcoal fill, white text, primary-lift shadow. Hover goes to charcoal-deep. It is also the main action inside the mist hero bands.
- **Secondary (default variant):** white with a control-colour stroke. Hover fills with off-white.
- **Ghost:** transparent with charcoal text. Hover fills with logo grey on white, and with white at 70% on mist.
- **Danger:** white with negative text and a `#e7b7b5` stroke. Hover fills with the negative pill tint.
- **States:** press scales to 0.97 over 120ms (disabled under reduced motion). Disabled is 60% opacity. Hover applies only under `(hover: hover) and (pointer: fine)`. Loading shows a spinning loader.

### Icons
A custom judicial set (`Components/Icons`): 24px grid, 1.6 stroke, round caps and joins, `currentColor`, default 20px. Diamonds and the scale recur across the set (home is a diamond on a base, cases a scale, more four diamonds, objection and closed are diamonds with a mark). Each icon maps to one function. It is used in the top bar (19px), sub-links (17px), dock (22px), bottom sheet (20px), case and client tabs, document lists and stage-path nodes (18px).

### Chips (Pills)
- **Style:** 23px tall, 7px radius, 12px/500, 9px padding. Tinted fill with a same-hue darkened ink. Accent is solid charcoal with white text, used for the "active" case status.
- **Tones:** neutral, positive, negative, warning (with inset border), info, accent.

### Cards / Containers
- **Card:** white, 1px border, 14px radius, card-ambient shadow. Panel headers are 16px/600 titles with an optional muted 13px description and end-aligned muted text links. The body has 20px padding.
- **Sheet:** white, 1px border, 18px radius, no shadow. It holds every inner working page and the sign-in form.
- **Well:** off-white fill, 14px radius, no border. Used for empty states (centred, 1.5-stroke icon in faint).

### Inputs / Fields
- **Style:** 42px tall, 10px radius, control-colour stroke, white fill. The label sits above at 14px/500 with a 6px gap. Hints and errors are 12.5px below.
- **Focus:** the border turns charcoal plus a 4px charcoal halo at 12%. Hover darkens the stroke to `#aab2bb`.
- **Error:** the border goes negative, the error message gets `role="alert"`, and required fields show a negative asterisk.
- **Date input:** day/month/year fields with a Gregorian/Hijri segmented switch in the label row. The active segment is solid charcoal. The stored value is always Gregorian, and the other calendar is shown as a hint.

### Navigation
- **Office top bar (mist):** six groups — الرئيسية، الملفات (القضايا، العملاء والوكالات، المطالبات الواردة)، التنفيذ، المواعيد والمهام (المواعيد والعدادات، المهام)، التقارير، إدارة المكتب (الحسابات، الإعدادات، حالة النظام). Each group is a full-height 14px item with a 19px icon, nav-ink at rest, charcoal on hover with a 150ms colour change. The open group is 500 charcoal with a 3px charcoal underline on the bar's bottom edge carrying a small charcoal diamond at its centre. The claims count is a signal-red badge on the files group.
- **Sub-link row:** for a multi-destination group, 36px pills (10px radius, 13.5px, 17px icon) on the off-white strip. The current link is solid charcoal with white text; others hover to white.
- **Account menu:** a 32px charcoal initial disc opens a 240px white menu (14px radius, menu shadow) that pops from the button in 160ms, holding the name, account security and sign-out. It closes on outside press or Escape.
- **Mobile dock:** five 64px slots — الرئيسية، القضايا، المواعيد، التنفيذ، المزيد — with 22px icons over 11.5px labels, muted at rest. The current slot is 500 charcoal with a 6px diamond above its icon; press scales to 0.94. "More" carries a signal dot when claims wait.
- **Bottom sheet ("more"):** white, 22px top corners, grab handle, up to 85dvh. Every destination grouped under 12px muted group names as 48px two-column tiles on off-white (current tile solid charcoal), then account security and sign-out. It rises in 300ms on the drawer curve and drops in 200ms; Escape and the scrim close it.
- **Portal header (mist):** muted links with a 10px radius. The active link is white with the nav lift.
- **Tabs:** muted 14px, optionally with a leading icon at 80% opacity and a muted count. The selected tab is 600 text with a 3px charcoal underline that grows in over 180ms. Arrow keys follow RTL.
- **Tables:** 12.5px/500 muted headers with no header bar. Rows are separated by rule lines, with a light hover.

### Hero Band (signature)
Mist with lattice, 22px radius, no shadow. Charcoal title, muted dual-calendar date, a finding line (signal red when something is overdue), charcoal figures separated by inline-start rules, and primary and ghost actions. Figures count up from zero the first time they enter view (900ms, exponential ease-out) and animate from the previous to the new value when data changes (500ms); the screen-reader value is always the final one.

### Week Ribbon (signature)
Seven diamond nodes on a charcoal/15% hairline. Each node's size reflects the day's load, and nodes pop in (scale 0.6 to 1, 520ms) with the dashboard stagger. Today is solid charcoal; days with load are mid-grey; empty days are charcoal/30% outlines. A day with a deadline gets an amber ring. Under each node are the weekday, Hijri day, Gregorian day/month and the count. Clicking a day filters the agenda; a single white 12px-radius indicator slides to the selected day (260ms in-out).

### Stage Path (signature)
The eight case stages (الإعداد، الصلح، القيد، المحكمة، الحكم، الاعتراض، التنفيذ، منتهية) as 40px rotated-square nodes on a 2px border-colour baseline, each with its stage icon and a 12.5px label. Past stages are solid charcoal with white icons; the current stage is white with a 2px charcoal ring (offset 2px) that pulses outward twice; later stages are dashed control-colour outlines with faint icons. A charcoal fill line grows along the baseline to the current stage (700ms). Pressing a stage shows its read-only details in an off-white 14px panel below, which swaps with a 200ms fade-up; stage changes stay in the page's own actions. It scrolls horizontally under 640px. Used on the office and portal case pages.
- **Stage dots:** the compact list form, eight small diamonds (6px; the current one 10px solid charcoal, past ones charcoal/70%, later ones control outlines), used in portal case lists.

### Sign-in Scene (signature)
Behind the unaltered full logo on the mist panel: three concentric diamond frames in charcoal (10/14/18% opacity) that rotate and scale into place in sequence, the innermost drawing its outline, and a large faint scale (16% charcoal) whose stand draws in while the beam swings and the pans counter-bob before settling (1.8s). The logo itself only fades and rises in (600ms). The mobile card uses the diamonds without the scale. The form beside it has no entrance motion.

### Data Marks
- **BarChart:** grey bars (`#c9cfd7`), with the active bar in charcoal. Hover or focus reads the value into the caption, with a 200ms colour transition.
- **Pipeline:** one 16px track split into stages using a charcoal-to-grey ramp. Each legend entry is a diamond plus a link, and hovering it dims the other segments to 25% opacity.
- **Meter:** an 8px track in rule colour, filled with ink, positive, signal or amber.

### Motion
Three curves only: ease-out (`cubic-bezier(0.23, 1, 0.32, 1)`) for entering and feedback, ease-in-out (`cubic-bezier(0.77, 0, 0.175, 1)`) for things moving across the screen, ease-drawer (`cubic-bezier(0.32, 0.72, 0, 1)`) for sheets. Page enter is a 220ms fade with a 6px rise. Dashboard sections stagger in at 420ms with 50ms steps (capped at 300ms). Under reduced motion every entrance becomes a 150–200ms fade and every spatial movement (node pop, path fill, ring pulse, scene, slide) is removed.

## Do's and Don'ts

### Do:
- **Do** put logo grey plus the lattice on the frame (top bar, portal header, sign-in panel) and the two hero bands only. Inner pages get one white sheet on the off-white ground.
- **Do** use the original charcoal logo files as shipped. On the sign-in panel the full lockup keeps its original proportions and only fades in; the scene moves around it.
- **Do** use the diamond (7px rotated square) as the list, legend and active-nav marker, and draw new icons in the judicial set's grammar (24 grid, 1.6 stroke, round caps, diamond and scale motifs).
- **Do** keep charts, pipelines and stage paths in charcoal and grey. Use signal red, amber or green only for overdue, soon or collected, and use them directly on mist.
- **Do** wrap every number, reference and date LTR-isolated with tabular figures, and pair every Gregorian date with its Hijri date.
- **Do** keep motion short and on the three project curves: page enter 220ms, stagger 420ms/50ms, indicators that slide instead of jump, menus 160ms, sheets 300ms up / 200ms down. Respect `prefers-reduced-motion` with fades only.
- **Do** keep the 2px charcoal focus outline and the radius steps band 22 > sheet 18 > card 14 > control 10 > chip 7.

### Don't:
- **Don't** animate forms in. Sign-in and working forms are visible and usable on the first frame.
- **Don't** reintroduce a side rail, a dark charcoal frame, lightened "on-dark" state variants, or a dark theme. The system is light-only.
- **Don't** use a state hue as a chart, stage or category colour.
- **Don't** add a second typeface, uppercase, letter-spacing, or small labels above headings.
- **Don't** add zebra striping or filled header bars to tables. Rows are divided by rules alone.
- **Don't** add a decorative motif other than the diamond, the logo-corner lattice and the scale (no gradients, glows or unrelated illustrations).
- **Don't** load fonts, scripts or images from external hosts. Every asset is self-hosted under the strict CSP.
- **Don't** put a shadow on a full-page sheet or stack elevated cards inside a sheet.
