"""
Looks up a tournament's players in one official ranking file (PDF, Excel
or CSV), for the admin "Check Rankings" page. Read-only: it prints what the PDF says and
never touches the database.

    python check_rankings.py ranking.pdf --players-file players.json
    python check_rankings.py ranking.xlsx --players-file players.json --type sram
    python check_rankings.py sg.xlsx --players-file players.json --type national --title "Singapore National Ranking"
    python check_rankings.py list.xlsx --players-file players.json --column "National Ranking"

players.json is written by the PHP page - one entry per registered player:

    [{"playerID": "P0001", "name": "Aleisters Loo", "dob": "2013-11-23",
      "gender": "Male", "asfMemberNo": "2218112"}, ...]

Works with both:
  - an Asian Junior Ranking category PDF (one category, e.g. BU15) ->
    compare with the players' AJSS ranking
  - the SRAM National Junior Ranking PDF (all categories) -> compare with
    the players' national ranking

Matching:
  - "id": the player's ASF member number is in the PDF row (exact)
  - "name_dob": same date of birth and the names share enough words.
    Only used when there's no ID match, and shown to the admin as
    "possible match - please confirm".

Prints {"ok": true, ...} or {"ok": false, "error": "..."} as JSON.
"""

import argparse
import json
import re
import sys
from datetime import date
from difflib import SequenceMatcher
from io import BytesIO

import pdfplumber

import ranking_parser as rp
from parse_excel import AmbiguousColumn, parse_spreadsheet
from parse_sram_pdf import is_sram_pdf, parse_sram_pdf

# Words that don't help tell two names apart
NAME_FILLERS = {"BIN", "BINTI", "BTE", "BT", "B", "A/L", "A/P", "AL", "AP", "ANAK", "S/O", "D/O", "BINT"}


def name_words(name: str) -> set:
    words = re.sub(r"[^A-Z/ ]", " ", (name or "").upper()).split()
    return {w for w in words if w not in NAME_FILLERS and len(w) > 1}


def words_alike(a: str, b: str) -> bool:
    """Same word, allowing a small spelling difference in longer words
    (ALEISTER / ALEISTERS, MUHAMMAD / MUHAMAD) - only ever used together with
    an exact date of birth match."""
    if a == b:
        return True

    if min(len(a), len(b)) < 4:
        return False

    return SequenceMatcher(None, a, b).ratio() >= 0.85


def names_agree(a: str, b: str) -> bool:
    """At least two shared words, or every word of a one-word name."""
    wa, wb = name_words(a), name_words(b)

    if not wa or not wb:
        return False

    # Pair each word with at most one alike word from the other name
    unused = set(wb)
    shared = 0

    for word in sorted(wa):
        match = next((other for other in sorted(unused) if words_alike(word, other)), None)
        if match is not None:
            unused.discard(match)
            shared += 1

    return shared >= 2 or shared == min(len(wa), len(wb))


def asian_dob_iso(dob: str) -> str:
    """d/m/yyyy -> yyyy-mm-dd"""
    day, month, year = dob.split("/")
    return f"{int(year):04d}-{int(month):02d}-{int(day):02d}"


def parse_asian_all_countries(pdf: pdfplumber.PDF) -> list:
    """Every player row (all countries, not only MAS) of an Asian ranking PDF."""
    rows = []

    for row in rp.extract_rows(pdf):
        m = rp.ROW_RE.match(row)

        if not m:
            continue

        rows.append({
            "rank": int(m.group("rank")),
            "name": re.sub(r"\s+", " ", m.group("name")).strip(),
            "country": m.group("country"),
            "asfMemberNo": m.group("memberid"),
            "dob": asian_dob_iso(m.group("dob")),
            "total": float(m.group("total").replace(",", "")),
        })

    return rows


def find_player(player: dict, rows: list, prefer_category: str | None = None) -> dict | None:
    """Best PDF row for one of our players: ID match first, then name + DOB."""
    asf = (player.get("asfMemberNo") or "").strip()

    if asf:
        by_id = [r for r in rows if r.get("asfMemberNo") == asf]
        if by_id:
            return dict(_pick(by_id, prefer_category), match="id")

    by_name = [r for r in rows
               if r.get("dob") == player.get("dob") and names_agree(player.get("name"), r.get("name"))]

    if by_name:
        return dict(_pick(by_name, prefer_category), match="name_dob")

    return None


def _pick(rows: list, prefer_category: str | None) -> dict:
    """If a player is listed in several categories, prefer the one they entered."""
    for r in rows:
        if prefer_category and r.get("category") == prefer_category:
            return r
    return min(rows, key=lambda r: r["rank"])


# ----------------------------------------------------------------------
# Reading the uploaded file
# ----------------------------------------------------------------------

TYPES = {
    "asian": {"title": "Asian Junior Ranking", "rankingField": "ajss_ranking"},
    "sram": {"title": "SRAM National Junior Ranking", "rankingField": "national_ranking"},
    # Any other country's national ranking (spreadsheets)
    "national": {"title": "National Ranking", "rankingField": "national_ranking"},
    "world": {"title": "World Ranking", "rankingField": "world_ranking"},
}


class CheckError(Exception):
    """A problem to show the admin as-is."""


def read_pdf(path: str) -> tuple[dict, list]:
    with open(path, "rb") as f:
        pdf_bytes = f.read()

    with pdfplumber.open(BytesIO(pdf_bytes)) as pdf:

        if is_sram_pdf(pdf):
            parsed = parse_sram_pdf(pdf)
            return {"type": "sram", "period": parsed["period"], "category": None}, parsed["players"]

        category = rp.detect_category(pdf)

        if category is None:
            raise CheckError("This PDF isn't an Asian Junior Ranking category PDF or an "
                             "SRAM National Junior Ranking PDF.")

        rows = parse_asian_all_countries(pdf)

        for r in rows:
            r["category"] = category

        return {"type": "asian", "period": rp.detect_period(pdf), "category": category}, rows


# Which typed ranking column belongs to each ranking type
TYPE_COLUMN = {"asian": "ajss", "sram": "national", "national": "national", "world": "world"}
COLUMN_TYPE = {"ajss": "asian", "national": "national", "world": "world"}
COLUMN_LABEL = {"ajss": "AJSS Ranking", "national": "National Ranking", "world": "World Ranking"}


def ranking_type_from_text(text: str) -> str | None:
    text = text.upper()

    if "SRAM" in text:
        return "sram"
    if "ASIAN" in text or "AJSS" in text:
        return "asian"
    if "WORLD" in text or "PSA" in text:
        return "world"
    if "NATIONAL" in text:
        return "national"
    return None


def read_chosen_column(path: str, ranking_type: str, column: str) -> tuple[dict, list]:
    """Spreadsheet where the admin named the column to check."""
    try:
        parsed = parse_spreadsheet(path, rank_column=column)
    except AmbiguousColumn as e:
        raise CheckError(f'"{e.wanted}" fits more than one column: '
                         + ", ".join(f'"{h}"' for h in e.headings) + ". Type the full column name.")

    rows = parsed["rows"]

    if not rows and not parsed["rankColumns"]:
        found = ", ".join(f'"{h}"' for h in parsed["headers"][:25])
        raise CheckError(f'No column called "{column}" was found next to a player name column. '
                         + (f"The file's columns are: {found}." if found else
                            "No header row with a Name / Player column was found."))

    # Which saved ranking to compare: from the column name, then the file's title
    if ranking_type == "auto":
        ranking_type = ranking_type_from_text(column) or ranking_type_from_text(parsed["text"]) or "national"

    for r in rows:
        r["rank"] = r["ranks"].get("plain")

    rows = [r for r in rows if r["rank"] is not None]

    if not rows:
        raise CheckError(f'The "{column}" column is empty for every player in this file.')

    return finish_spreadsheet(parsed, rows, ranking_type)


def finish_spreadsheet(parsed: dict, rows: list, ranking_type: str) -> tuple[dict, list]:
    categories = {r["category"] for r in rows}
    category = categories.pop() if len(categories) == 1 else None

    # "As at 01-Aug-2026", "Updated: 7 September 2026" or "RANKING AUGUST 2026"
    period = (re.search(r"(?:as\s+at|updated\s*:?)\s*([0-9]{1,2}[-/ ][A-Za-z0-9]{1,9}[-/ ][0-9]{2,4})",
                        parsed["text"], re.IGNORECASE)
              or re.search(r"RANKING\s+([A-Za-z]{3,9}\s+\d{4})", parsed["text"], re.IGNORECASE))

    return {"type": ranking_type, "period": period.group(1).title() if period else None, "category": category}, rows


def read_spreadsheet(path: str, ranking_type: str) -> tuple[dict, list]:
    parsed = parse_spreadsheet(path)
    rows = parsed["rows"]
    typed = parsed["rankColumns"] - {"plain"}

    if not rows:
        raise CheckError("No ranking table was found in this file. It needs a header row with at least "
                         "a Rank (or Position, or e.g. National Ranking) column and a Name (or Player) column.")

    # A file with typed columns (e.g. "National Ranking" and "AJSS Ranking")
    if ranking_type == "auto" and typed:

        if len(typed) > 1:
            raise CheckError("This file has " + " and ".join(COLUMN_LABEL[t] for t in sorted(typed)) + " columns. "
                             "Type the column to check in the 'Column to check' box (e.g. National Ranking).")

        ranking_type = COLUMN_TYPE[next(iter(typed))]

    if ranking_type == "auto":
        ranking_type = ranking_type_from_text(parsed["text"])

        if ranking_type is None:
            raise CheckError("Couldn't tell which column to check. Type the column name from the file "
                             "in the 'Column to check' box (e.g. National Ranking) and check again.")

    # Use the column for this ranking: its own typed column if the file has one,
    # otherwise the plain Rank column
    column = TYPE_COLUMN[ranking_type]

    if column not in parsed["rankColumns"]:
        if "plain" not in parsed["rankColumns"]:
            raise CheckError(f"This file has no {COLUMN_LABEL[column]} column "
                             f"(it has: {', '.join(COLUMN_LABEL[t] for t in sorted(typed))}).")
        column = "plain"

    for r in rows:
        r["rank"] = r["ranks"].get(column)

    # Players with no ranking in that column aren't in this ranking
    rows = [r for r in rows if r["rank"] is not None]

    if not rows:
        raise CheckError(f"The {COLUMN_LABEL.get(column, 'Rank')} column is empty for every player in this file.")

    return finish_spreadsheet(parsed, rows, ranking_type)


# ----------------------------------------------------------------------
# Comparing
# ----------------------------------------------------------------------

def check_file(path: str, players: list, ranking_type: str = "auto", title: str = "", column: str = "") -> dict:
    is_pdf = path.lower().endswith(".pdf")

    if is_pdf:
        meta, rows = read_pdf(path)
    else:
        meta, rows = (read_chosen_column(path, ranking_type, column.strip()) if column.strip()
                      else read_spreadsheet(path, ranking_type))

    if not rows:
        raise CheckError("No player rows could be read from this file.")

    category = meta["category"]

    result = {
        "ok": True,
        "source": "pdf" if is_pdf else "spreadsheet",
        "type": meta["type"],
        # Spreadsheets: the name the admin typed (or the column checked); PDFs keep their own title
        "title": ((title.strip() or column.strip()) if not is_pdf else "") or TYPES[meta["type"]]["title"],
        "rankingField": TYPES[meta["type"]]["rankingField"],
        "period": meta["period"],
        "category": category,
        # One-category Asian list: only check players of that gender
        "gender": ("Male" if category.startswith("B") else "Female")
                  if meta["type"] == "asian" and category else None,
        "rowsInFile": len(rows),
        "checkedOn": date.today().isoformat(),
        "matches": {},
    }

    for player in players:
        if result["gender"] and (player.get("gender") or "").capitalize() != result["gender"]:
            continue

        found = find_player(player, rows, player.get("category"))

        result["matches"][player["playerID"]] = None if found is None else {
            "match": found["match"],
            "rank": found["rank"],
            "category": found.get("category"),
            "name": found["name"],
            "where": found.get("country") or found.get("state") or "",
            "dob": found.get("dob"),
        }

    return result


def main() -> None:
    parser = argparse.ArgumentParser(description="Look up a tournament's players in a ranking PDF or spreadsheet.")
    parser.add_argument("file", help="ranking PDF, Excel (.xlsx/.xls) or CSV file")
    parser.add_argument("--players-file", required=True)
    parser.add_argument("--type", choices=["auto", "asian", "sram", "national", "world"], default="auto",
                        help="spreadsheets only: which ranking the file is (PDFs are detected)")
    parser.add_argument("--title", default="", help="spreadsheets only: ranking name to show in the report")
    parser.add_argument("--column", default="", help="spreadsheets only: header of the column to check")
    args = parser.parse_args()

    try:
        with open(args.players_file, encoding="utf-8") as f:
            players = json.load(f)

        output = check_file(args.file, players, args.type, args.title, args.column)

    except CheckError as e:
        output = {"ok": False, "error": str(e)}

    except Exception as e:  # report any other failure to the page as JSON
        output = {"ok": False, "error": f"Could not read the file: {e}"}

    sys.stdout.reconfigure(encoding="utf-8")
    json.dump(output, sys.stdout, ensure_ascii=False)


if __name__ == "__main__":
    main()
