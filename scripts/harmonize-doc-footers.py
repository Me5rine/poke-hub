#!/usr/bin/env python3
"""Ajoute un pied de page index + charte aux .md du projet (hors vendor). Idempotent."""
from __future__ import annotations

import os
import pathlib

ROOT = pathlib.Path(__file__).resolve().parents[1]
DOCS = ROOT / "docs"
MARKER = "*Index de la documentation"
# Pas de backticks dans le libellé du lien (cassent le italique Markdown).
SKIP_PATHS = {
    DOCS / "README.md",
    DOCS / "REDACTION.md",
    ROOT / "README.md",
}


def rel_to(from_dir: pathlib.Path, target: pathlib.Path) -> str:
    return os.path.relpath(target, from_dir).replace("\\", "/")


def footer_lines(from_dir: pathlib.Path) -> str:
    r = rel_to(from_dir, DOCS / "README.md")
    e = rel_to(from_dir, DOCS / "REDACTION.md")
    return (
        "\n\n---\n\n"
        f"{MARKER} : [README du dossier docs]({r}) · "
        f"[Charte rédactionnelle]({e})*\n"
    )


def should_process(p: pathlib.Path) -> bool:
    if "vendor" in p.parts or ".git" in p.parts:
        return False
    if p.suffix.lower() != ".md":
        return False
    if p.resolve() in {x.resolve() for x in SKIP_PATHS}:
        return False
    under_docs = DOCS in p.parents or p.parent == DOCS
    under_modules = "modules" in p.parts and (ROOT / "modules") in p.parents
    root_audit = p.parent == ROOT and p.suffix.lower() == ".md"
    return under_docs or under_modules or root_audit


def main() -> None:
    updated = 0
    for p in sorted(ROOT.rglob("*.md")):
        if not should_process(p):
            continue
        text = p.read_text(encoding="utf-8")
        if MARKER in text:
            continue
        if not text.strip():
            continue
        new_text = text.rstrip() + footer_lines(p.parent)
        p.write_text(new_text, encoding="utf-8")
        updated += 1
        print("+", p.relative_to(ROOT))
    print("updated", updated)


if __name__ == "__main__":
    main()
