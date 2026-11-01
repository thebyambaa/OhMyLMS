"""Merge a locale's PO into the current POT and compile the WordPress catalogs.

Requires polib (``python -m pip install polib==1.2.0``) and, for ``compile``, WP-CLI.
Run from any directory:

    python tools/merge-translations.py merge --locale mn [--extra other-mn.po]
    python tools/merge-translations.py compile --locale mn --wp "wp"
    python tools/merge-translations.py stats --locale mn [--untranslated 30]

``merge`` follows msgmerge semantics: translations of unchanged strings are kept, a changed
string gets the closest old translation (similarity >= 0.6) marked fuzzy with ``#| msgid``,
strings no longer in the POT become ``#~`` obsolete entries, and nothing is machine translated.
``--extra`` catalogs only fill entries the main PO leaves untranslated or fuzzy.

``compile`` runs ``wp i18n make-mo`` and ``wp i18n make-json --no-purge --use-map`` on the
finished (non-fuzzy) entries and names each Jed file after its script handle
(``ohmylms-<locale>-<handle>.json``), which is the first file
``wp_set_script_translations( <handle>, 'ohmylms', <plugin>/languages )`` looks for. Webpack chunks
are assigned to every entry whose runtime contains the chunk's content hash. It also writes
``ohmylms-<locale>-ohmylms.json`` with every JavaScript string, the fallback catalog for plugin
scripts without a handle file.
"""

import argparse
import difflib
import hashlib
import heapq
import json
import re
import shutil
import subprocess
import sys
import tempfile
from collections import Counter, defaultdict
from pathlib import Path

import polib

ROOT = Path(__file__).resolve().parent.parent
LANGUAGES = ROOT / "languages"
FUZZY_THRESHOLD = 0.6
JS_EXTENSIONS = (".js", ".jsx", ".mjs")

# Script handle => built files whose strings it needs. The first file is the registered src.
SCRIPT_HANDLES = {
    "ohmylms-admin-app": [
        "assets/dist/admin/admin-app.js",
        "assets/dist/admin/admin-app-extensions.js",
    ],
    "ohmylms-app-registration": ["assets/dist/admin/admin-app-registration.js"],
    "ohmylms-native-lesson-settings": ["assets/dist/admin/native-lesson-editor.js"],
    "ohmylms-browse-subjects-block": ["assets/dist/admin/browse-subjects-block.js"],
    "ohmylms-interactive-activity-block": [
        "assets/dist/admin/interactive-activity-block.js"
    ],
    "ohmylms-question-player-block": ["assets/dist/admin/question-player-block.js"],
    "ohmylms-quiz-player-block": ["assets/dist/admin/quiz-player-block.js"],
    "ohmylms-checkout-block": ["assets/blocks/js/checkout.js"],
    "ohmylms-dashboard-block": ["assets/blocks/js/dashboard.js"],
    "ohmylms-profile-block": ["assets/blocks/js/profile.js"],
    "ohmylms-buy-now-block": ["assets/blocks/js/buy-now.js"],
    "ohmylms-offer-button-block": ["assets/blocks/js/offer-button.js"],
    "ohmylms-membership-list-block": ["assets/blocks/js/membership-list.js"],
    "ohmylms-school-blocks": ["assets/schools/blocks.js"],
}
CHUNKS = "assets/dist/admin/chunks"
# Every JavaScript string: the catalog `InterfaceLanguage::script_catalog()` serves to plugin
# scripts without a file of their own (`ohmylms-<locale>-ohmylms.json`).
SHARED_CATALOG = "ohmylms"


def key(entry):
    return entry.msgctxt, entry.msgid


def is_translated(entry):
    if "fuzzy" in entry.flags:
        return False
    if entry.msgid_plural:
        return bool(entry.msgstr_plural) and all(entry.msgstr_plural.values())
    return bool(entry.msgstr)


def has_text(entry):
    if entry.msgid_plural:
        return any(entry.msgstr_plural.values())
    return bool(entry.msgstr)


def rank(entry):
    """Better source for a translation: translated > fuzzy > empty."""
    return 2 if is_translated(entry) else 1 if has_text(entry) else 0


def is_js(entry):
    return any(path.endswith(JS_EXTENSIONS) for path, _ in entry.occurrences)


def coverage(entries):
    active = [entry for entry in entries if not entry.obsolete and entry.msgid]
    translated = sum(1 for entry in active if is_translated(entry))
    fuzzy = sum(1 for entry in active if "fuzzy" in entry.flags)
    js = [entry for entry in active if is_js(entry)]
    return {
        "total": len(active),
        "translated": translated,
        "fuzzy": fuzzy,
        "untranslated": len(active) - translated - fuzzy,
        "obsolete": sum(1 for entry in entries if entry.obsolete),
        "js_total": len(js),
        "js_translated": sum(1 for entry in js if is_translated(entry)),
    }


def trigrams(text):
    text = text.lower()
    return {text[i : i + 3] for i in range(max(1, len(text) - 2))}


class FuzzyIndex:
    """Closest translated msgid by difflib ratio, prefiltered by shared trigrams."""

    def __init__(self, entries):
        self.entries = entries
        self.index = defaultdict(list)
        for position, entry in enumerate(entries):
            for gram in trigrams(entry.msgid):
                self.index[gram].append(position)

    def best(self, msgid, msgctxt):
        shared = Counter()
        for gram in trigrams(msgid):
            shared.update(self.index.get(gram, ()))
        best, best_score = None, FUZZY_THRESHOLD
        matcher = difflib.SequenceMatcher(None, autojunk=False)
        matcher.set_seq2(msgid)
        closest = heapq.nsmallest(40, shared.items(), key=lambda item: (-item[1], item[0]))
        for position, _ in closest:
            candidate = self.entries[position]
            matcher.set_seq1(candidate.msgid)
            if matcher.real_quick_ratio() < best_score or matcher.quick_ratio() < best_score:
                continue
            score = matcher.ratio() - (0 if candidate.msgctxt == msgctxt else 1e-6)
            if score >= best_score and (best is None or score > best_score):
                best, best_score = candidate, score
        return best


def copy_translation(target, source, nplurals):
    """Copy msgstr; returns True when singular/plural shape changed (needs review)."""
    if target.msgid_plural:
        if source.msgid_plural:
            target.msgstr_plural = {
                index: source.msgstr_plural.get(index, "") for index in range(nplurals)
            }
            return source.msgid_plural != target.msgid_plural
        target.msgstr_plural = {index: source.msgstr for index in range(nplurals)}
        return True
    if source.msgid_plural:
        target.msgstr = source.msgstr_plural.get(0, "")
        return True
    target.msgstr = source.msgstr
    return False


def merge(locale, extras, dry_run=False):
    po_path = LANGUAGES / f"ohmylms-{locale}.po"
    po = polib.pofile(str(po_path), wrapwidth=0)
    pot = polib.pofile(str(LANGUAGES / "ohmylms.pot"), wrapwidth=0)
    before = coverage(po)
    nplurals = int(re.search(r"nplurals\s*=\s*(\d+)", po.metadata["Plural-Forms"]).group(1))

    memory = {}
    for entry in po:
        if entry.msgid and rank(entry) >= rank(memory.get(key(entry), entry)):
            memory[key(entry)] = entry
    for path in extras:
        for entry in polib.pofile(str(path)):
            current = memory.get(key(entry))
            if entry.msgid and (current is None or rank(entry) > rank(current)):
                memory[key(entry)] = entry
    fuzzy_index = FuzzyIndex(
        [entry for entry in memory.values() if has_text(entry) and entry.msgid]
    )

    merged, used = [], set()
    counts = Counter()
    for template in pot:
        if not template.msgid:
            continue
        entry = polib.POEntry(
            msgctxt=template.msgctxt,
            msgid=template.msgid,
            msgid_plural=template.msgid_plural,
            comment=template.comment,
            occurrences=list(template.occurrences),
            flags=[flag for flag in template.flags if flag != "fuzzy"],
        )
        old = memory.get(key(template))
        fuzzy = False
        if old is not None:
            used.add(key(template))
            entry.tcomment = old.tcomment
            fuzzy = copy_translation(entry, old, nplurals) or "fuzzy" in old.flags
            if "fuzzy" in old.flags:
                entry.previous_msgctxt = old.previous_msgctxt
                entry.previous_msgid = old.previous_msgid
                entry.previous_msgid_plural = old.previous_msgid_plural
            counts["exact"] += 1
        else:
            candidate = fuzzy_index.best(template.msgid, template.msgctxt)
            if candidate is not None:
                copy_translation(entry, candidate, nplurals)
                entry.previous_msgctxt = candidate.msgctxt
                entry.previous_msgid = candidate.msgid
                entry.previous_msgid_plural = candidate.msgid_plural
                fuzzy = True
                counts["fuzzy_matched"] += 1
            else:
                if template.msgid_plural:
                    entry.msgstr_plural = {index: "" for index in range(nplurals)}
                counts["new"] += 1
        if fuzzy and has_text(entry):
            entry.flags.insert(0, "fuzzy")
        merged.append(entry)

    obsolete = []
    for entry in po:
        if entry.msgid and key(entry) not in used and has_text(entry):
            entry.obsolete = True
            entry.occurrences = []
            obsolete.append(entry)

    by_key = {key(entry): entry for entry in merged}
    for entry in po:
        new = by_key.get(key(entry))
        if new is None or memory[key(entry)] is not entry or entry.msgid_plural != new.msgid_plural:
            continue
        if entry.msgstr != new.msgstr or dict(entry.msgstr_plural) != dict(new.msgstr_plural):
            raise RuntimeError(f"Translation changed during merging: {entry.msgid!r}")

    metadata = dict(po.metadata)
    for field in ("Project-Id-Version", "POT-Creation-Date", "Report-Msgid-Bugs-To"):
        if field in pot.metadata:
            metadata[field] = pot.metadata[field]
    output = render(po.header, metadata, merged + obsolete)
    after = coverage(merged + obsolete)
    if not dry_run:
        po_path.write_text(output, encoding="utf-8", newline="\n")
    return {"before": before, "after": after, "merge": dict(counts)}


def quote(text):
    """PO string lines; multi-line strings break after each newline, like WP-CLI."""
    parts = text.splitlines(True)
    if len(parts) <= 1:
        return [f'"{polib.escape(text)}"']
    return ['""'] + [f'"{polib.escape(part)}"' for part in parts]


def field(prefix, name, text):
    lines = quote(text)
    return [f"{prefix}{name} {lines[0]}"] + [f"{prefix}{line}" for line in lines[1:]]


def render_entry(entry):
    lines = []
    for line in entry.tcomment.splitlines() if entry.tcomment else []:
        lines.append(f"# {line}".rstrip())
    for line in entry.comment.splitlines() if entry.comment else []:
        lines.append(f"#. {line}".rstrip())
    for path, lineno in entry.occurrences:
        lines.append(f"#: {path}:{lineno}" if lineno else f"#: {path}")
    if entry.flags:
        lines.append("#, " + ", ".join(entry.flags))
    for name in ("msgctxt", "msgid", "msgid_plural"):
        previous = getattr(entry, f"previous_{name}")
        if previous is not None and not entry.obsolete:
            lines += field("#| ", name, previous)
    prefix = "#~ " if entry.obsolete else ""
    if entry.msgctxt is not None:
        lines += field(prefix, "msgctxt", entry.msgctxt)
    lines += field(prefix, "msgid", entry.msgid)
    if entry.msgid_plural:
        lines += field(prefix, "msgid_plural", entry.msgid_plural)
        for index in sorted(entry.msgstr_plural):
            lines += field(prefix, f"msgstr[{index}]", entry.msgstr_plural[index])
    else:
        lines += field(prefix, "msgstr", entry.msgstr)
    return "\n".join(lines)


def render(header, metadata, entries):
    head = [f"# {line}".rstrip() for line in header.splitlines()]
    head += ['msgid ""', 'msgstr ""']
    head += [f'"{polib.escape(f"{name}: {value}")}\\n"' for name, value in metadata.items()]
    return "\n\n".join(["\n".join(head)] + [render_entry(entry) for entry in entries]) + "\n"


def entry_handles():
    """Map each built JS file to the script handles that load it."""
    owners = defaultdict(set)
    chunks = sorted((ROOT / CHUNKS).glob("*.js"))
    for handle, files in SCRIPT_HANDLES.items():
        for relative in files:
            owners[relative].add(handle)
            script = ROOT / relative
            if not script.is_file() or not relative.startswith("assets/dist/admin/"):
                continue
            runtime = script.read_text(encoding="utf-8", errors="ignore")
            for chunk in chunks:
                digest = chunk.name.rsplit(".", 2)[-2]
                if re.fullmatch(r"[0-9a-f]{8,}", digest) and digest in runtime:
                    owners[f"{CHUNKS}/{chunk.name}"].add(handle)
    return owners


def compile_catalogs(locale, wp):
    po = polib.pofile(str(LANGUAGES / f"ohmylms-{locale}.po"), wrapwidth=0)
    owners = entry_handles()
    for entry in po:
        for path, _ in entry.occurrences:
            if path.endswith(JS_EXTENSIONS):
                owners[path].add(SHARED_CATALOG)
    targets = {handle: files[0] for handle, files in SCRIPT_HANDLES.items()}
    targets[SHARED_CATALOG] = "ohmylms.js"
    mapping = {
        source: sorted(targets[handle] for handle in handles)
        for source, handles in owners.items()
    }
    written = {}
    with tempfile.TemporaryDirectory() as scratch:
        scratch = Path(scratch)
        # WP-CLI compiles fuzzy and empty entries too; only finished translations may ship.
        finished = scratch / f"ohmylms-{locale}.po"
        shipped = [entry for entry in po if is_translated(entry) and not entry.obsolete]
        finished.write_text(
            render(po.header, po.metadata, shipped), encoding="utf-8", newline="\n"
        )
        run(f'{wp} i18n make-mo "{finished}" "{scratch}"')
        shutil.copy(scratch / f"ohmylms-{locale}.mo", LANGUAGES / f"ohmylms-{locale}.mo")
        (scratch / "map.json").write_text(json.dumps(mapping), encoding="utf-8")
        out = scratch / "json"
        out.mkdir()
        run(
            f'{wp} i18n make-json "{finished}" "{out}" --no-purge '
            f'--use-map="{scratch / "map.json"}"'
        )
        for handle, target in targets.items():
            digest = hashlib.md5(target.encode("utf-8")).hexdigest()
            produced = out / f"ohmylms-{locale}-{digest}.json"
            destination = LANGUAGES / f"ohmylms-{locale}-{handle}.json"
            if produced.is_file():
                shutil.copy(produced, destination)
                data = json.loads(produced.read_text(encoding="utf-8"))
                written[handle] = len(data["locale_data"]["messages"]) - 1
            elif destination.is_file():
                destination.unlink()
    return {"mo": f"ohmylms-{locale}.mo", "json_messages": written}


def run(command):
    result = subprocess.run(command, shell=True, cwd=ROOT, capture_output=True, text=True)
    if result.returncode:
        sys.stderr.write(result.stdout + result.stderr)
        raise SystemExit(f"Command failed: {command}")


def stats(locale, untranslated):
    po = polib.pofile(str(LANGUAGES / f"ohmylms-{locale}.po"))
    report = {"coverage": coverage(po)}
    if untranslated:
        missing = [
            entry
            for entry in po
            if entry.msgid and not entry.obsolete and not is_translated(entry)
        ]
        report["untranslated"] = [
            {"msgid": entry.msgid, "refs": [path for path, _ in entry.occurrences][:3]}
            for entry in missing[:untranslated]
        ]
    return report


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    commands = parser.add_subparsers(dest="command", required=True)
    merge_parser = commands.add_parser("merge")
    merge_parser.add_argument("--locale", default="mn")
    merge_parser.add_argument("--extra", action="append", default=[], type=Path)
    merge_parser.add_argument("--dry-run", action="store_true")
    compile_parser = commands.add_parser("compile")
    compile_parser.add_argument("--locale", default="mn")
    compile_parser.add_argument("--wp", default="wp", help="WP-CLI command prefix")
    stats_parser = commands.add_parser("stats")
    stats_parser.add_argument("--locale", default="mn")
    stats_parser.add_argument("--untranslated", type=int, default=0)
    arguments = parser.parse_args()
    if not re.fullmatch(r"[a-z]{2,3}(?:_[A-Z]{2})?", arguments.locale):
        raise SystemExit("Use a WordPress locale such as mn or en_US.")
    if arguments.command == "merge":
        result = merge(arguments.locale, arguments.extra, arguments.dry_run)
    elif arguments.command == "compile":
        result = compile_catalogs(arguments.locale, arguments.wp)
    else:
        result = stats(arguments.locale, arguments.untranslated)
    print(json.dumps(result, ensure_ascii=False, indent=1))
