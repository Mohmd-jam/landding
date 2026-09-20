"""Generate icon.png + icon.ico from a PIL drawing (matches icon.svg idea)."""

from PIL import Image, ImageDraw

SIZE = 256
img = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
d = ImageDraw.Draw(img)

# gradient rounded square
for y in range(SIZE):
    t = y / SIZE
    r = round(0x4C + (0x3B - 0x4C) * t)
    g = round(0x6E + (0x5B - 0x6E) * t)
    b = round(0xF5 + (0xDB - 0xF5) * t)
    d.line([(0, y), (SIZE, y)], fill=(r, g, b, 255))
mask = Image.new("L", (SIZE, SIZE), 0)
ImageDraw.Draw(mask).rounded_rectangle([8, 8, SIZE - 8, SIZE - 8], radius=56, fill=255)
bg = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
bg.paste(img, mask=mask)
d = ImageDraw.Draw(bg)

# toolbox: handle + body
d.arc([70, 70, 186, 170], start=180, end=0, fill="white", width=14)
d.rounded_rectangle([56, 140, 200, 208], radius=18, outline="white", width=14)
d.rounded_rectangle([112, 124, 144, 154], radius=6, fill=(255, 212, 59, 255))

bg.save("assets/icon.png")
bg.resize((48, 48), Image.LANCZOS).save("assets/icon.ico", sizes=[(16, 16), (32, 32), (48, 48)])
print("wrote assets/icon.png + assets/icon.ico")
