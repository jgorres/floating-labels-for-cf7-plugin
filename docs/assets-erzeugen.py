#!/usr/bin/env python3
"""
Erzeugt die WordPress.org-Assets für Floating Labels for CF7:
animiertes icon.svg (CSS-Keyframes), animierte Icons (APNG, 128 und 256 px)
und animierte Banner (APNG, 772x250 und 1544x500), alle mit demselben Motiv:
ein Formularfeld bekommt den Fokus, das Label floatet auf die Rahmenlinie,
dann wird Text getippt, kurze Pause, zurück zum Ruhezustand, Schleife.

Autor: Jörn Gorres
Datum: 22.10.2026

Farben und Maße folgen den Defaults der Custom Properties in
plugin/assets/css/floating-labels.css (Variante Outlined). Schrift: Noto Sans.
Alle Bilder haben einen dünnen schwarzen Rand mit gerundeten Ecken; außerhalb
der Rundung sind sie transparent.

Aufruf:
  python3 docs/assets-erzeugen.py            schreibt nach assets/
  python3 docs/assets-erzeugen.py --sheet D  schreibt zusätzlich Kontaktbögen
                                             der Einzelbilder nach D (Prüfung)
"""

import pathlib
import sys

from PIL import Image, ImageDraw, ImageFont

# ── Parameter ──────────────────────────────────────────────────────────
OUT_DIR = pathlib.Path(__file__).resolve().parent.parent / "assets"

FONT_BOLD = "/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf"
FONT_REGULAR = "/usr/share/fonts/truetype/noto/NotoSans-Regular.ttf"

WHITE = (255, 255, 255, 255)
CLEAR = (0, 0, 0, 0)
BLACK = (0, 0, 0, 255)
BORDER = (0x80, 0x86, 0x8B)      # --jgor-flcf7-border-color
ACTIVE = (0x1A, 0x73, 0xE8)      # --jgor-flcf7-border-color-active
LABEL = (0x5F, 0x63, 0x68)       # --jgor-flcf7-label-color
TEXT = (0x1D, 0x23, 0x27)        # WordPress-Dunkelgrau (Eingabetext, Banner-Text)
SUBTEXT = (0x50, 0x57, 0x5E)

LABEL_SCALE = 0.72               # Verkleinerung des gefloateten Labels
SS = 2                           # Supersampling beim Zeichnen (Kantenglättung)
FRAME_RADIUS = 32 / 256          # Eckenradius des Bildrands, Anteil der kurzen Seite
FRAME_WIDTH = {128: 1, 256: 2, 772: 1, 1544: 2}  # Randstärke in px je Bildbreite

# Zeitplan (ms)
HOLD_REST = 900
FLOAT_FRAMES, FLOAT_MS = 10, 40  # Fokus + Floaten
TYPE_MS_ICON = 170               # je Buchstabe (Icon)
TYPE_MS_BANNER = 90              # je Buchstabe (Banner)
BLINK_MS, BLINKS = 400, 3        # Cursor blinkt nach dem Tippen
HOLD_END = 900
RETURN_FRAMES, RETURN_MS = 8, 40 # zurück in den Ruhezustand


# ── Hilfsfunktionen ────────────────────────────────────────────────────
def lerp(a, b, t):
    return a + (b - a) * t


def lerp_color(a, b, t):
    return tuple(int(round(lerp(x, y, t))) for x, y in zip(a, b))


def ease_out(t):
    return 1 - (1 - t) ** 3


def font(path, size):
    return ImageFont.truetype(path, int(round(size)))


def framed_canvas(width, height):
    """Transparentes Bild in Supersampling-Größe mit weißer, gerundeter Fläche
    und dünnem schwarzem Rand. Rückgabe: (Bild, Zeichenobjekt)."""
    img = Image.new("RGBA", (width * SS, height * SS), CLEAR)
    draw = ImageDraw.Draw(img)
    radius = int(round(min(width, height) * FRAME_RADIUS * SS))
    stroke = FRAME_WIDTH[width] * SS
    draw.rounded_rectangle((0, 0, width * SS - 1, height * SS - 1), radius=radius, fill=WHITE, outline=BLACK, width=stroke)
    return img, draw


# ── Zustände der Animation ─────────────────────────────────────────────
def timeline(typed_text, type_ms):
    """Liste von (Zustand, Dauer ms). Zustand: focus 0..1, float 0..1,
    chars (getippte Zeichen), caret (bool)."""
    frames = []
    frames.append((dict(focus=0.0, float=0.0, chars=0, caret=False), HOLD_REST))
    for i in range(1, FLOAT_FRAMES + 1):
        t = ease_out(i / FLOAT_FRAMES)
        # Cursor erst, wenn das Label den Platz freigegeben hat.
        frames.append((dict(focus=t, float=t, chars=0, caret=t > 0.6), FLOAT_MS))
    for n in range(1, len(typed_text) + 1):
        frames.append((dict(focus=1.0, float=1.0, chars=n, caret=True), type_ms))
    for b in range(BLINKS):
        frames.append((dict(focus=1.0, float=1.0, chars=len(typed_text), caret=(b % 2 == 1)), BLINK_MS))
    frames.append((dict(focus=1.0, float=1.0, chars=len(typed_text), caret=False), HOLD_END))
    for i in range(1, RETURN_FRAMES + 1):
        t = 1 - ease_out(i / RETURN_FRAMES)
        frames.append((dict(focus=t, float=t, chars=0, caret=False), RETURN_MS))
    return frames


# ── Ein Feld zeichnen ──────────────────────────────────────────────────
def draw_field(draw, box, state, label, typed, size, stroke, radius, pad):
    """Zeichnet ein Outlined-Feld mit Label, Eingabetext und Cursor.
    box = (x0, y0, x1, y1) in Pixeln des gezeichneten Bildes."""
    x0, y0, x1, y1 = box
    cy = (y0 + y1) / 2
    border = lerp_color(BORDER, ACTIVE, state["focus"])
    draw.rounded_rectangle(box, radius=radius, fill=WHITE, outline=border, width=stroke)

    # Label: ruht links in der Mitte, floatet auf die obere Rahmenlinie.
    f = state["float"]
    lsize = lerp(size, size * LABEL_SCALE, f)
    lfont = font(FONT_REGULAR, lsize)
    lx = x0 + pad
    ly = lerp(cy, y0, f)
    lcolor = lerp_color(LABEL, ACTIVE, state["focus"])
    bbox = draw.textbbox((0, 0), label, font=lfont, anchor="ls")
    lw = bbox[2] - bbox[0]
    if f > 0.6:
        # Lücke in der Rahmenlinie hinter dem Label (Notch)
        gap = int(lsize * 0.25)
        draw.rectangle((lx - gap, y0 - stroke, lx + lw + gap, y0 + stroke), fill=WHITE)
    draw.text((lx, ly), label, font=lfont, fill=lcolor, anchor="lm")

    # Eingabetext und Cursor
    tfont = font(FONT_REGULAR, size)
    shown = typed[: state["chars"]]
    tx = x0 + pad
    if shown:
        draw.text((tx, cy), shown, font=tfont, fill=TEXT, anchor="lm")
        tbox = draw.textbbox((tx, cy), shown, font=tfont, anchor="lm")
        tx = tbox[2] + int(size * 0.08)
    if state["caret"]:
        ch = size * 1.05
        draw.rectangle((tx, cy - ch / 2, tx + max(2, stroke // 2), cy + ch / 2), fill=TEXT)


# ── Icon ───────────────────────────────────────────────────────────────
ICON_LABEL, ICON_TYPED = "Name", "John"


def icon_frame(state, px):
    """Ein Icon-Bild in px x px (gezeichnet mit Supersampling)."""
    s = px * SS / 256
    img, draw = framed_canvas(px, px)
    box = (24 * s, 92 * s, 232 * s, 164 * s)
    draw_field(draw, box, state, ICON_LABEL, ICON_TYPED, size=30 * s, stroke=int(7 * s), radius=int(14 * s), pad=int(22 * s))
    return img.resize((px, px), Image.LANCZOS)


# ── Banner ─────────────────────────────────────────────────────────────
BANNER_W, BANNER_H = 1544, 500
TITLE = "Floating Labels for CF7"
SUBTITLE = "Floating labels for Contact Form 7. Pure CSS, no JavaScript."
FIELD1_LABEL, FIELD1_TYPED = "Your email", "john@example.com"
FIELD2_LABEL, FIELD2_TYPED = "Your name", "John Doe"


def banner_frame(state, width):
    """Ein Banner-Bild in width x (width * 500 / 1544)."""
    height = int(round(width * BANNER_H / BANNER_W))
    s = width * SS / BANNER_W
    img, draw = framed_canvas(width, height)

    draw.text((80 * s, 150 * s), TITLE, font=font(FONT_BOLD, 66 * s), fill=TEXT, anchor="ls")
    draw.text((82 * s, 206 * s), SUBTITLE, font=font(FONT_REGULAR, 28 * s), fill=SUBTEXT, anchor="ls")

    common = dict(size=32 * s, stroke=int(6 * s), radius=int(14 * s), pad=int(24 * s))
    draw_field(draw, (930 * s, 118 * s, 1474 * s, 212 * s), state, FIELD1_LABEL, FIELD1_TYPED, **common)
    done = dict(focus=0.0, float=1.0, chars=len(FIELD2_TYPED), caret=False)
    draw_field(draw, (930 * s, 268 * s, 1474 * s, 362 * s), done, FIELD2_LABEL, FIELD2_TYPED, **common)
    return img.resize((width, height), Image.LANCZOS)


# ── APNG schreiben ─────────────────────────────────────────────────────
def write_apng(path, frames, durations):
    first, rest = frames[0], frames[1:]
    first.save(path, format="PNG", save_all=True, append_images=rest, duration=durations, loop=0, disposal=0, blend=0)
    print(f"  ok  {path.relative_to(OUT_DIR.parent)} ({len(frames)} Bilder, {path.stat().st_size // 1024} KB)")


def contact_sheet(frames, path, cols=8):
    w, h = frames[0].size
    rows = (len(frames) + cols - 1) // cols
    sheet = Image.new("RGBA", (w * cols, h * rows), (235, 235, 235, 255))
    for i, fr in enumerate(frames):
        sheet.paste(fr, ((i % cols) * w, (i // cols) * h))
    sheet.save(path)


# ── Animiertes SVG-Icon ────────────────────────────────────────────────
def write_svg(path, total_ms, t_float, t_type, t_hold, t_return):
    """icon.svg mit CSS-Keyframes; Geometrie wie icon_frame() (256er Raster)."""
    pct = lambda ms: f"{100 * ms / total_ms:.1f}%"
    rest_end = pct(HOLD_REST)
    float_end = pct(t_float)
    type_end = pct(t_type)
    hold_end = pct(t_hold)
    return_end = pct(t_return)
    dur = f"{total_ms / 1000:.1f}s"
    svg = f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="256" height="256" role="img" aria-label="Floating Labels for CF7">
<style>
  @keyframes jgor-border {{ 0%, {rest_end} {{ stroke: #80868b; }} {float_end}, {hold_end} {{ stroke: #1a73e8; }} {return_end}, 100% {{ stroke: #80868b; }} }}
  @keyframes jgor-label {{ 0%, {rest_end} {{ transform: translate(0, 0) scale(1); fill: #5f6368; }} {float_end}, {hold_end} {{ transform: translate(0, -36px) scale({LABEL_SCALE}); fill: #1a73e8; }} {return_end}, 100% {{ transform: translate(0, 0) scale(1); fill: #5f6368; }} }}
  @keyframes jgor-notch {{ 0%, {rest_end} {{ opacity: 0; }} {float_end}, {hold_end} {{ opacity: 1; }} {return_end}, 100% {{ opacity: 0; }} }}
  @keyframes jgor-typed {{ 0%, {float_end} {{ width: 0; }} {type_end}, {hold_end} {{ width: 84px; }} {return_end}, 100% {{ width: 0; }} }}
  @keyframes jgor-caret {{ 0%, {rest_end} {{ opacity: 0; }} {float_end}, {type_end} {{ opacity: 1; }} {hold_end}, 100% {{ opacity: 0; }} }}
  .jgor-field {{ animation: jgor-border {dur} ease-out infinite; }}
  .jgor-label {{ transform-origin: 46px 128px; animation: jgor-label {dur} ease-out infinite; }}
  .jgor-notch {{ animation: jgor-notch {dur} step-end infinite; }}
  .jgor-typed {{ animation: jgor-typed {dur} steps(4, end) infinite; }}
  .jgor-caret {{ animation: jgor-caret {dur} step-end infinite; }}
  @media (prefers-reduced-motion: reduce) {{
    .jgor-field, .jgor-label, .jgor-notch, .jgor-typed, .jgor-caret {{ animation: none; }}
  }}
</style>
<rect x="1" y="1" width="254" height="254" rx="32" fill="#fff" stroke="#000" stroke-width="2"/>
<rect class="jgor-field" x="24" y="92" width="208" height="72" rx="14" fill="#fff" stroke="#80868b" stroke-width="7"/>
<rect class="jgor-notch" x="40" y="85" width="56" height="14" fill="#fff" opacity="0"/>
<text class="jgor-label" x="46" y="128" dominant-baseline="central" font-family="'Noto Sans', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif" font-size="30" fill="#5f6368">{ICON_LABEL}</text>
<clipPath id="jgor-typed-clip"><rect class="jgor-typed" x="46" y="100" width="0" height="56"/></clipPath>
<text x="46" y="128" dominant-baseline="central" font-family="'Noto Sans', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif" font-size="30" fill="#1d2327" clip-path="url(#jgor-typed-clip)">{ICON_TYPED}</text>
<rect class="jgor-caret" x="48" y="112" width="3" height="32" fill="#1d2327" opacity="0"/>
</svg>
"""
    path.write_text(svg, encoding="utf-8")
    print(f"  ok  {path.relative_to(OUT_DIR.parent)}")


# ── Hauptprogramm ──────────────────────────────────────────────────────
def main():
    sheet_dir = None
    if len(sys.argv) >= 3 and sys.argv[1] == "--sheet":
        sheet_dir = pathlib.Path(sys.argv[2])
        sheet_dir.mkdir(parents=True, exist_ok=True)
    OUT_DIR.mkdir(parents=True, exist_ok=True)

    print("Icons ...")
    icon_tl = timeline(ICON_TYPED, TYPE_MS_ICON)
    durations = [d for _, d in icon_tl]
    for px in (256, 128):
        frames = [icon_frame(st, px) for st, _ in icon_tl]
        write_apng(OUT_DIR / f"icon-{px}x{px}.png", frames, durations)
        if sheet_dir and px == 256:
            contact_sheet(frames, sheet_dir / "icon-frames.png")

    # Zeitmarken für das SVG aus demselben Zeitplan
    t_float = HOLD_REST + FLOAT_FRAMES * FLOAT_MS
    t_type = t_float + len(ICON_TYPED) * TYPE_MS_ICON
    t_hold = t_type + BLINKS * BLINK_MS + HOLD_END
    t_return = t_hold + RETURN_FRAMES * RETURN_MS
    write_svg(OUT_DIR / "icon.svg", sum(durations), t_float, t_type, t_hold, t_return)

    print("Banner ...")
    banner_tl = timeline(FIELD1_TYPED, TYPE_MS_BANNER)
    durations = [d for _, d in banner_tl]
    for width in (1544, 772):
        frames = [banner_frame(st, width) for st, _ in banner_tl]
        write_apng(OUT_DIR / f"banner-{width}x{int(round(width * BANNER_H / BANNER_W))}.png", frames, durations)
        if sheet_dir and width == 772:
            contact_sheet(frames, sheet_dir / "banner-frames.png", cols=3)


if __name__ == "__main__":
    main()
