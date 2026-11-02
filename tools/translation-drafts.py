#!/usr/bin/env python3
"""Export a locale's untranslated and fuzzy strings for drafting, and import the drafts back.

  python tools/translation-drafts.py export --po languages/ohmylms-mn.po --out <dir> [--chunks 8]
  python tools/translation-drafts.py import --po languages/ohmylms-mn.po --drafts <dir>
  python tools/translation-drafts.py approve --po languages/ohmylms-mn.po

`approve` marks every fuzzy draft as reviewed (clears `fuzzy` and the previous-msgid lines) when its
translation is complete and keeps the source's {tokens}, HTML tags and printf placeholders; run it after
a native speaker has read the PO.

`export` writes `glossary.json` (short strings that already have a reviewed translation) and
`chunk-NN.json` files: [{"key", "msgctxt", "msgid", "msgid_plural", "flags", "references",
"suggestion", "visible"}]. A drafter fills `"msgstr"` (or `"msgstr_plural": [one, other]`).

`import` writes each draft into the PO. Drafts for visible strings (admin menu, dashboard,
learner pages, checkout) are stored as finished translations; every other draft keeps the
`fuzzy` flag, so it stays out of the compiled .mo/.json until a reviewer clears it. A draft is
rejected when its `{tokens}` or HTML tags differ from the source, or, for strings flagged
php-format/javascript-format, its printf placeholders.
"""

import argparse
import json
import pathlib
import re
import sys

import polib

VISIBLE = (
    re.compile(r"^templates/"),
    re.compile(r"^packages/e-commerce/templates/"),
    re.compile(r"^assets/interactivity/"),
    re.compile(r"^includes/Admin/AdminNavigation\.php"),
    re.compile(r"^includes/Extensions/(Chrome|Catalog|Account|AuthForms|Reviews|Lesson|Video|Commerce)\.php"),
    re.compile(r"^includes/Shortcodes/"),
    re.compile(r"^includes/I18n/"),
    re.compile(r"^assets/src/app/(layout|routing)/"),
    re.compile(r"^assets/src/app/features/dashboard/"),
    # The POT references the built admin bundles, not the TypeScript sources.
    re.compile(r"^assets/dist/admin/admin-app\.js"),
    re.compile(r"^assets/dist/admin/chunks/admin-app-dashboard"),
)
PRINTF = re.compile(r"%(?:\d+\$)?[-+ 0#]*\d*(?:\.\d+)?[sdfuxXcoeEgG%]")
TOKEN = re.compile(r"\{[a-z][a-z0-9_]*\}")
TAG = re.compile(r"</?([a-zA-Z][a-zA-Z0-9]*)\b")
FORMAT_FLAGS = {"php-format", "javascript-format", "c-format"}


def key(entry):
    return (entry.msgctxt or "") + "\x04" + entry.msgid


def is_visible(entry):
    refs = [ref for ref, _ in entry.occurrences]
    return any(pattern.search(ref) for ref in refs for pattern in VISIBLE)


def needs_draft(entry):
    if entry.obsolete or not entry.msgid:
        return False
    if "fuzzy" in entry.flags:
        return True
    if entry.msgid_plural:
        return not all(entry.msgstr_plural.get(i) for i in (0, 1))
    return not entry.msgstr


def signature(text, formatted):
    # printf conversions only count in strings gettext marks as format strings; "100% of" is prose.
    printf = sorted(PRINTF.findall(text)) if formatted else []
    return printf, sorted(TOKEN.findall(text)), sorted(t.lower() for t in TAG.findall(text))


def export(args):
    po = polib.pofile(args.po)
    out = pathlib.Path(args.out)
    out.mkdir(parents=True, exist_ok=True)
    glossary = {
        e.msgid: e.msgstr
        for e in po
        if not e.obsolete and e.msgstr and "fuzzy" not in e.flags and not e.msgid_plural and len(e.msgid.split()) <= 4
    }
    (out / "glossary.json").write_text(json.dumps(glossary, ensure_ascii=False, indent=1, sort_keys=True), encoding="utf-8")
    items = []
    for e in po:
        if not needs_draft(e):
            continue
        items.append({
            "key": key(e),
            "msgctxt": e.msgctxt,
            "msgid": e.msgid,
            "msgid_plural": e.msgid_plural or None,
            "flags": [f for f in e.flags if f != "fuzzy"],
            "references": [ref for ref, _ in e.occurrences][:3],
            "suggestion": (e.msgstr or e.msgstr_plural.get(0) or None) if "fuzzy" in e.flags else None,
            "visible": is_visible(e),
        })
    items.sort(key=lambda i: (not i["visible"], i["references"][0] if i["references"] else "", i["msgid"]))
    size = -(-len(items) // args.chunks)
    for n in range(args.chunks):
        chunk = items[n * size:(n + 1) * size]
        (out / f"chunk-{n + 1:02d}.json").write_text(json.dumps(chunk, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"{len(items)} strings ({sum(i['visible'] for i in items)} visible) in {args.chunks} chunks of up to {size}; glossary {len(glossary)}")


def import_drafts(args):
    po = polib.pofile(args.po)
    by_key = {key(e): e for e in po if not e.obsolete}
    stored = live = rejected = 0
    problems = []
    for path in sorted(pathlib.Path(args.drafts).glob("chunk-*.json")):
        for item in json.loads(path.read_text(encoding="utf-8")):
            entry = by_key.get(item["key"])
            if entry is None:
                continue
            forms = item.get("msgstr_plural") if entry.msgid_plural else [item.get("msgstr")]
            if not forms or not all(isinstance(f, str) and f.strip() for f in forms):
                continue
            sources = [entry.msgid, entry.msgid_plural] if entry.msgid_plural else [entry.msgid]
            formatted = bool(FORMAT_FLAGS & set(entry.flags))
            if any(signature(src, formatted) != signature(form, formatted) for src, form in zip(sources, forms)):
                rejected += 1
                problems.append(f"{path.name}: {entry.msgid[:60]!r}")
                continue
            if entry.msgid_plural:
                entry.msgstr_plural = {0: forms[0], 1: forms[1] if len(forms) > 1 else forms[0]}
            else:
                entry.msgstr = forms[0]
            entry.flags = [f for f in entry.flags if f != "fuzzy"]
            if item.get("visible"):
                live += 1
            else:
                entry.flags.append("fuzzy")
            stored += 1
    po.save(args.po)
    print(f"stored {stored} drafts ({live} live, {stored - live} fuzzy for review); rejected {rejected}")
    for line in problems[:40]:
        print("  rejected", line)


def approve(args):
    po = polib.pofile(args.po)
    approved = 0
    problems = []
    for entry in po:
        if entry.obsolete or "fuzzy" not in entry.flags:
            continue
        sources = [entry.msgid, entry.msgid_plural] if entry.msgid_plural else [entry.msgid]
        forms = [entry.msgstr_plural.get(0, ""), entry.msgstr_plural.get(1, "")] if entry.msgid_plural else [entry.msgstr]
        formatted = bool(FORMAT_FLAGS & set(entry.flags))
        if not all(f.strip() for f in forms) or any(
            signature(src, formatted) != signature(form, formatted) for src, form in zip(sources, forms)
        ):
            problems.append(entry.msgid[:70])
            continue
        entry.flags = [f for f in entry.flags if f != "fuzzy"]
        entry.previous_msgctxt = entry.previous_msgid = entry.previous_msgid_plural = None
        approved += 1
    po.save(args.po)
    print(f"approved {approved} reviewed drafts; {len(problems)} kept fuzzy (empty or placeholder mismatch)")
    for line in problems[:40]:
        print("  kept fuzzy", repr(line))


def main():
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    sub = parser.add_subparsers(dest="command", required=True)
    e = sub.add_parser("export")
    e.add_argument("--po", required=True)
    e.add_argument("--out", required=True)
    e.add_argument("--chunks", type=int, default=8)
    i = sub.add_parser("import")
    i.add_argument("--po", required=True)
    i.add_argument("--drafts", required=True)
    a = sub.add_parser("approve")
    a.add_argument("--po", required=True)
    args = parser.parse_args()
    {"export": export, "import": import_drafts, "approve": approve}[args.command](args)


if __name__ == "__main__":
    sys.exit(main())
