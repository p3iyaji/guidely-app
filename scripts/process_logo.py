"""Process the GuidelyEdu logo JPG into app-ready PNG assets.

- Tight-crops to the artwork (icon + wordmark) with a small margin.
- Emits public/logo.png    -> full lockup, white background kept (badge on blue surfaces).
- Emits public/favicon.png -> just the circular icon, transparent bg, rounded corners.
"""
from PIL import Image, ImageDraw

SRC = r"C:\Users\admin\Downloads\guidely-edu-logo.jpg"
OUT_DIR = r"C:\Users\admin\Documents\guidely-app\public"


def content_bbox(im, threshold=240):
    """Bounding box of pixels that differ from the near-white background."""
    px = im.load()
    w, h = im.size
    min_x, min_y, max_x, max_y = w, h, -1, -1
    for y in range(h):
        for x in range(w):
            r, g, b = px[x, y]
            if r < threshold or g < threshold or b < threshold:
                if x < min_x:
                    min_x = x
                if x > max_x:
                    max_x = x
                if y < min_y:
                    min_y = y
                if y > max_y:
                    max_y = y
    return (min_x, min_y, max_x + 1, max_y + 1)


def main():
    im = Image.open(SRC).convert("RGB")

    bbox = content_bbox(im)
    print(f"Content bbox: {bbox} in {im.size}")

    # Full lockup with white background kept (reads well on the blue brand surfaces).
    margin = 6
    x0, y0, x1, y1 = bbox
    crop_box = (max(0, x0 - margin), max(0, y0 - margin), min(im.size[0], x1 + margin), min(im.size[1], y1 + margin))
    logo = im.crop(crop_box)
    logo_path = OUT_DIR + r"\logo.png"
    logo.save(logo_path, "PNG")
    print("Wrote", logo_path, logo.size)

    # Favicon: crop just the circular icon (leftmost ~21% of content width).
    cw = x1 - x0
    ch = y1 - y0
    icon_w = int(cw * 0.215)
    icon_box = (x0, max(0, y0 - margin), x0 + icon_w, min(im.size[1], y1 + margin))
    icon = im.crop(icon_box).convert("RGBA")

    # Make near-white pixels transparent so only the purple disc + cap remain.
    ipx = icon.load()
    iw, ih = icon.size
    for y in range(ih):
        for x in range(iw):
            r, g, b, a = ipx[x, y]
            if r > 235 and g > 235 and b > 235:
                ipx[x, y] = (r, g, b, 0)

    # Round the corners of the icon tile.
    radius = min(iw, ih) // 4
    mask = Image.new("L", (iw, ih), 0)
    md = ImageDraw.Draw(mask)
    md.rounded_rectangle((0, 0, iw - 1, ih - 1), radius=radius, fill=255)
    icon.putalpha(mask)

    fav_path = OUT_DIR + r"\favicon.png"
    icon.save(fav_path, "PNG")
    print("Wrote", fav_path, icon.size)


if __name__ == "__main__":
    main()
