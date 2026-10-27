"""Append missing catalog entries and compile WordPress PHP/Jed translations.

Requires polib. Run from any directory:
    python tools/compile-translations.py --locale mn --merge-pot

Existing translations, obsolete entries and translator metadata are preserved.
"""

import argparse
import copy
import json
import re
from pathlib import Path

import polib


def entry_key(entry):
    """Context is part of a translation's identity."""
    return entry.msgctxt, entry.msgid


def compile_catalog(locale, merge_pot=False):
    """Build the plugin's PHP catalog and registered admin script's Jed catalog."""
    if not re.fullmatch(r"[a-z]{2,3}(?:_[A-Z]{2})?", locale):
        raise ValueError("Use a WordPress locale such as mn or en_US.")
    languages = Path(__file__).resolve().parent.parent / "languages"
    po_path = languages / f"ohmylms-{locale}.po"
    po = polib.pofile(str(po_path))
    original = {
        entry_key(entry): (entry.msgstr, dict(entry.msgstr_plural)) for entry in po
    }
    known = set(original)
    added = []
    if merge_pot:
        for entry in polib.pofile(str(languages / "ohmylms.pot")):
            if entry_key(entry) in known:
                continue
            fresh = copy.deepcopy(entry)
            fresh.msgstr = ""
            fresh.msgstr_plural = {index: "" for index in fresh.msgstr_plural}
            added.append(str(fresh))
            known.add(entry_key(fresh))
        if added:
            with po_path.open("a", encoding="utf-8", newline="\n") as stream:
                stream.write("\n" + "\n".join(added))
    po = polib.pofile(str(po_path))
    for entry in po:
        if entry_key(entry) in original:
            if (entry.msgstr, entry.msgstr_plural) != original[entry_key(entry)]:
                raise RuntimeError("An existing translation changed during merging.")
    po.save_as_mofile(str(languages / f"ohmylms-{locale}.mo"))
    messages = {
        "": {
            "domain": "ohmylms",
            "lang": locale,
            "plural-forms": po.metadata["Plural-Forms"],
        }
    }
    for entry in po.translated_entries():
        if not any(
            path.endswith((".js", ".jsx", ".mjs", ".json"))
            for path, _ in entry.occurrences
        ):
            continue
        key = (entry.msgctxt + "\x04" if entry.msgctxt else "") + entry.msgid
        messages[key] = (
            [entry.msgstr_plural[index] for index in sorted(entry.msgstr_plural)]
            if entry.msgid_plural
            else [entry.msgstr]
        )
    payload = {
        "translation-revision-date": po.metadata.get("PO-Revision-Date", ""),
        "generator": "OhMyLMS release catalog compilation",
        "domain": "messages",
        "locale_data": {"messages": messages},
    }
    # WordPress checks this registered-handle filename before a source-path hash.
    json_path = languages / f"ohmylms-{locale}-ohmylms.json"
    with json_path.open("w", encoding="utf-8", newline="\n") as stream:
        stream.write(
            json.dumps(payload, ensure_ascii=False, separators=(",", ":")) + "\n"
        )
    return {
        "entries": len(po),
        "added_untranslated": len(added),
        "translated_entries": len(po.translated_entries()),
        "javascript_translations": len(messages) - 1,
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--locale", default="mn")
    parser.add_argument("--merge-pot", action="store_true")
    arguments = parser.parse_args()
    print(json.dumps(compile_catalog(arguments.locale, arguments.merge_pot)))
