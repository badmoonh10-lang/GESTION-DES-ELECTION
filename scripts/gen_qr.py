import json
import os
import sys

try:
    import qrcode
except ImportError:
    print("il faut d'abord installer la dependence: qrcode avec 'pip install qrcode[pil]'.", file=sys.stderr)
    sys.exit(1)


def main() -> None:
    if len(sys.argv) != 3:
        print("Usage: gen_qr.py <input_json_path> <output_png_path>", file=sys.stderr)
        sys.exit(1)

    input_path = sys.argv[1]
    output_path = sys.argv[2]

    if not os.path.isfile(input_path):
        print(f"Input file not found: {input_path}", file=sys.stderr)
        sys.exit(1)

    with open(input_path, "r", encoding="utf-8") as f:
        data = json.load(f)

    # Data expected: small JSON with elector info + profile photo path
    payload = json.dumps(data, ensure_ascii=False)

    qr = qrcode.QRCode(
        version=None,
        error_correction=qrcode.constants.ERROR_CORRECT_M,
        box_size=4,
        border=1,
    )
    qr.add_data(payload)
    qr.make(fit=True)

    img = qr.make_image(fill_color="black", back_color="white")
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    img.save(output_path)


if __name__ == "__main__":
    main()


