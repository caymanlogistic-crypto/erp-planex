from __future__ import annotations

import os
import zipfile
from datetime import datetime
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
OUTPUT_DIR = ROOT / "tmp" / "audit"
ARCHIVE_NAME = f"erp_planex_audit_{datetime.now().strftime('%Y%m%d_%H%M%S')}.zip"
OUTPUT_PATH = OUTPUT_DIR / ARCHIVE_NAME

EXCLUDED_DIRS = {
    ".git",
    "tmp",
    "logs",
    "__pycache__",
}

EXCLUDED_FILES = {
    ".env",
}

EXCLUDED_SUFFIXES = {
    ".zip",
    ".7z",
    ".rar",
}

EXCLUDED_PREFIX_PATHS = {
    Path("storage") / "companies",
}


def should_exclude(path: Path) -> bool:
    rel = path.relative_to(ROOT)
    parts = set(rel.parts)

    if parts & EXCLUDED_DIRS:
        return True

    if path.name in EXCLUDED_FILES:
        return True

    if path.suffix.lower() in EXCLUDED_SUFFIXES:
        return True

    for prefix in EXCLUDED_PREFIX_PATHS:
        try:
            rel.relative_to(prefix)
            return True
        except ValueError:
            continue

    return False


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    included = 0
    with zipfile.ZipFile(OUTPUT_PATH, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=6) as archive:
        for path in ROOT.rglob("*"):
            if path == OUTPUT_PATH:
                continue
            if should_exclude(path):
                continue
            if path.is_dir():
                continue
            archive.write(path, path.relative_to(ROOT).as_posix())
            included += 1

    print(f"archive={OUTPUT_PATH}")
    print(f"files={included}")
    print(f"size={OUTPUT_PATH.stat().st_size}")


if __name__ == "__main__":
    main()
