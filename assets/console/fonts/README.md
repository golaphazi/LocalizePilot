# Console fonts

The console uses two faces, both self-hosted. WordPress.org plugin guidelines
rule out loading fonts from a third-party host, so nothing here may be swapped
for a CDN link.

## Inter — bundled

`inter-var.woff2` is the latin subset of Inter as a variable font, covering
weights 100–900 in one 48 KB file. Licensed under the SIL Open Font License
1.1, which permits redistribution inside a GPL plugin.

Used for body copy, table text, labels, and KPI captions.

## Satoshi — not bundled

The Figma file specifies **Satoshi** for headings, navigation, buttons and
metric values. It is not bundled here, because Satoshi ships under the Indian
Type Foundry / Fontshare licence and redistribution inside a distributed plugin
needs to be confirmed before the binary is committed.

Until then `--lp-font-display` falls back to Inter and then to the system
stack, which is metrically close enough that no layout breaks.

### To bundle it once the licence is confirmed

1. Download the woff2 files from <https://www.fontshare.com/fonts/satoshi>.
2. Save them here as `satoshi-500.woff2` and `satoshi-700.woff2`
   (Medium for nav and body buttons, Bold for headings and metrics).
3. Add the two `@font-face` blocks to `assets/console/css/fonts.css`:

   ```css
   @font-face {
       font-family: "Satoshi";
       font-style: normal;
       font-weight: 500;
       font-display: swap;
       src: url("../fonts/satoshi-500.woff2") format("woff2");
   }

   @font-face {
       font-family: "Satoshi";
       font-style: normal;
       font-weight: 700;
       font-display: swap;
       src: url("../fonts/satoshi-700.woff2") format("woff2");
   }
   ```

4. Copy the Fontshare licence text into this directory.

No other change is needed — `--lp-font-display` already lists Satoshi first.

### If the licence does not clear

The nearest open substitutes are **General Sans** (Fontshare, same foundry) and
**Plus Jakarta Sans** (SIL OFL, on Google Fonts). Swap the first entry of
`--lp-font-display` in `tokens.css`; nothing else references the family.
