import json
import os
import sys
from pathlib import Path

os.environ.setdefault("OPENCV_IO_MAX_IMAGE_PIXELS", "40000000")

import cv2
import numpy as np

PROJECT_ROOT = Path(__file__).resolve().parents[1]
UPLOAD_ROOT = (PROJECT_ROOT / "storage" / "app" / "private" / "food-scans").resolve()
MAX_IMAGE_BYTES = 10 * 1024 * 1024
ALLOWED_FORMATS = {".jpg", ".jpeg", ".png", ".webp"}


def respond(payload: dict, exit_code: int = 0) -> int:
    print(json.dumps(payload, ensure_ascii=False))
    return exit_code


def process_image(image_argument: str) -> dict:
    image_path = Path(image_argument).resolve(strict=True)

    try:
        image_path.relative_to(UPLOAD_ROOT)
    except ValueError as exception:
        raise ValueError("Caminho de imagem não permitido.") from exception

    if not image_path.is_file() or image_path.suffix.lower() not in ALLOWED_FORMATS:
        raise ValueError("Arquivo de imagem não permitido.")

    image_size = image_path.stat().st_size
    if image_size == 0 or image_size > MAX_IMAGE_BYTES:
        raise ValueError("Tamanho de imagem inválido.")

    encoded_image = np.fromfile(image_path, dtype=np.uint8)
    decoded_image = cv2.imdecode(encoded_image, cv2.IMREAD_UNCHANGED)
    if decoded_image is None:
        raise ValueError("Não foi possível ler a imagem.")

    height, width = decoded_image.shape[:2]
    if width <= 0 or height <= 0 or width * height > 40_000_000:
        raise ValueError("Dimensões de imagem não permitidas.")

    channels = 1 if decoded_image.ndim == 2 else int(decoded_image.shape[2])

    return {
        "image": {
            "width": int(width),
            "height": int(height),
            "channels": channels,
            "format": image_path.suffix.lower().lstrip("."),
            "size_bytes": image_size,
        },
        "processing": {
            "engine": "OpenCV",
            "version": cv2.__version__,
            "recognized_foods": False,
        },
    }


if __name__ == "__main__":
    if len(sys.argv) != 2:
        sys.exit(respond({"error": "Informe uma única imagem para processamento."}, 2))

    try:
        sys.exit(respond(process_image(sys.argv[1])))
    except Exception:
        sys.exit(respond({"error": "Não foi possível processar a imagem."}, 1))