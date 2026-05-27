#!/usr/bin/env python3
import argparse
import re
from pathlib import Path


MARKERS = ("Ã", "Ø", "Ù", "ï¿½", "�")
FRAGMENT_PATTERN = re.compile(r"[A-Za-z0-9ÃØÙÆâ€™€šŽœžŸƒ¢Â]{4,}")


def marker_count(text: str) -> int:
    return sum(text.count(m) for m in MARKERS)


def fix_whole_text(text: str) -> str | None:
    try:
        return text.encode("latin-1", errors="strict").decode("utf-8", errors="strict")
    except Exception:
        return None


def fix_whole_text_cp1252(text: str) -> str | None:
    try:
        return text.encode("cp1252", errors="strict").decode("utf-8", errors="strict")
    except Exception:
        return None


def fix_fragment(fragment: str) -> str:
    try:
        fixed = fragment.encode("latin-1", errors="strict").decode("utf-8", errors="strict")
    except Exception:
        try:
            fixed = fragment.encode("cp1252", errors="strict").decode("utf-8", errors="strict")
        except Exception:
            return fragment
    if any("\u0600" <= c <= "\u06FF" for c in fixed):
        return fixed
    return fragment


def fix_fragments(text: str) -> str:
    return FRAGMENT_PATTERN.sub(lambda m: fix_fragment(m.group(0)), text)


def choose_best(original: str) -> tuple[str, bool]:
    candidates = [original]

    whole = fix_whole_text(original)
    if whole is not None:
        candidates.append(whole)

    candidates.append(fix_fragments(original))
    whole_cp1252 = fix_whole_text_cp1252(original)
    if whole_cp1252 is not None:
        candidates.append(whole_cp1252)
        candidates.append(fix_fragments(whole_cp1252))
    if whole is not None:
        candidates.append(fix_fragments(whole))

    best = min(candidates, key=marker_count)
    changed = best != original and marker_count(best) < marker_count(original)
    return best, changed


def process_file(path: Path, write: bool, backup_ext: str) -> tuple[bool, str]:
    raw = path.read_bytes()

    try:
        text = raw.decode("utf-8")
    except UnicodeDecodeError:
        text = raw.decode("latin-1")

    # Repair obvious leading replacement for UTF-8 BOM before comment.
    if text.startswith("?//"):
        text = text[1:]

    fixed, changed = choose_best(text)
    if not changed:
        # still save BOM replacement correction if any
        if fixed != path.read_text(encoding="utf-8", errors="ignore"):
            changed = True
        else:
            return False, "no-change"

    if write:
        backup_path = path.with_suffix(path.suffix + backup_ext)
        if not backup_path.exists():
            backup_path.write_bytes(raw)
        path.write_text(fixed, encoding="utf-8", newline="")
    return True, "updated" if write else "would-update"


def main() -> int:
    parser = argparse.ArgumentParser(description="Targeted mojibake fixer for selected files.")
    parser.add_argument("files", nargs="+", help="One or more file paths to process.")
    parser.add_argument("--write", action="store_true", help="Write changes to disk.")
    parser.add_argument("--backup-ext", default=".encbak", help="Backup suffix for original bytes.")
    args = parser.parse_args()

    updated = []
    skipped = []

    for file_arg in args.files:
        path = Path(file_arg).resolve()
        if not path.exists() or not path.is_file():
            skipped.append((file_arg, "not-found"))
            continue
        try:
            changed, reason = process_file(path, args.write, args.backup_ext)
            if changed:
                updated.append(str(path))
            else:
                skipped.append((str(path), reason))
        except Exception as exc:
            skipped.append((str(path), f"error: {exc}"))

    print(f"Updated: {len(updated)}")
    for p in updated:
        print(f" - {p}")
    print(f"Skipped: {len(skipped)}")
    for p, reason in skipped:
        print(f" - {p} ({reason})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
