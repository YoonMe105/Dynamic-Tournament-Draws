"""
Parses one SRAM National Junior Ranking PDF (all categories in one file)
and prints JSON to stdout: players from TARGET_STATE, each flagged with
whether they match your club's roster.

    python parse_sram_pdf.py "SRAM Ranking as at 01-Aug-2026.pdf" --roster-file roster.json
    python parse_sram_pdf.py ranking.pdf --roster-file roster.json --all-states

Layout notes (checked against the 01-Aug-2026 edition):
  - every page starts with a title block whose third line names the
    category, e.g. "BU09 Compulsory Points Top 3 points ..."
  - each player row starts: Rank, Total points, masked IC ("170821******"),
    name, state, DOB (dd-mm-yy), [AJSS member ID], Rankedin ID, results...
  - long names and two-word states wrap onto a second line
    ("Negeri" / "Sembilan", "Kuala" / "Lumpur"); only the first line is
    used, so the state is rebuilt from its first word (see STATE_FIRST_WORDS)
    and the name may be cut short. Matching is by ID, so that's fine.
"""

import argparse
import re
import sys
from datetime import date
from io import BytesIO

import pdfplumber

import ranking_parser as rp
from roster_file import load_roster, print_json

# Only players from this state are kept (change it if your club is based
# elsewhere, or pass --all-states).
TARGET_STATE = "Kuala Lumpur"

TITLE_RE = re.compile(r"SRAM\s+National\s+Junior\s+Ranking", re.IGNORECASE)
PERIOD_RE = re.compile(r"As\s+at\s+(\d{1,2}-[A-Za-z]{3}-\d{4})", re.IGNORECASE)
PAGE_CATEGORY_RE = re.compile(r"^\s*([BG]U\d{2})\s+Compulsory", re.MULTILINE)

# First line of a player row. "rest" is name + state (state is the last word).
ROW_RE = re.compile(
    r"^(?P<rank>\d+)\s+(?P<total>[\d,]+\.\d{2})\s+(?P<maskedic>\d{6}\*+)\s+(?P<rest>.+?)\s+"
    r"(?P<dob>\d{2}-\d{2}-\d{2})\s+(?:(?P<asf>\d{5,8})\s+)?(?P<rankedin>R\d{9})\b"
)

# Last word of "rest" -> full state name. Covers the words seen in real
# PDFs, including cut-off ("Terengga") and wrapped ("Negeri") states.
STATE_FIRST_WORDS = {
    "JOHOR": "Johor",
    "KEDAH": "Kedah",
    "KELANTAN": "Kelantan",
    "KELANTA": "Kelantan",
    "MELAKA": "Melaka",
    "NEGERI": "Negeri Sembilan",
    "PAHANG": "Pahang",
    "PENANG": "Penang",
    "PERAK": "Perak",
    "PERLIS": "Perlis",
    "SABAH": "Sabah",
    "SARAWAK": "Sarawak",
    "SELANGOR": "Selangor",
    "TERENGGANU": "Terengganu",
    "TERENGGA": "Terengganu",
    "KUALA": "Kuala Lumpur",
    "KL": "Kuala Lumpur",
    "PUTRAJAYA": "Putrajaya",
    "LABUAN": "Labuan",
    "BJSS": "BJSS",
}


def _split_name_state(rest: str) -> tuple[str, str]:
    words = rest.split()
    state = STATE_FIRST_WORDS.get(words[-1].upper())

    if state is None or len(words) == 1:
        return rest, ""

    return " ".join(words[:-1]), state


def _dob_iso(dob: str) -> str:
    """dd-mm-yy -> yyyy-mm-dd (junior players, so always 20yy unless that's in the future)."""
    day, month, year = dob.split("-")
    year = int(year)
    year += 2000 if 2000 + year <= date.today().year else 1900
    return f"{year:04d}-{month}-{day}"


def is_sram_pdf(pdf: pdfplumber.PDF) -> bool:
    return bool(TITLE_RE.search(pdf.pages[0].extract_text() or ""))


def parse_sram_pdf(pdf: pdfplumber.PDF) -> dict:
    """Returns {"period": str|None, "players": [...]} with every ranked
    player from every category (no state filter, no club flag)."""
    first_page = pdf.pages[0].extract_text() or ""
    period = PERIOD_RE.search(first_page)

    players = []

    for page in pdf.pages:
        category = PAGE_CATEGORY_RE.search(page.extract_text() or "")

        if not category:
            continue

        # extract_rows() takes anything with a .pages list
        for row in rp.extract_rows(_OnePage(page)):
            m = ROW_RE.match(row)

            if not m:
                continue

            name, state = _split_name_state(m.group("rest"))

            players.append({
                "category": category.group(1),
                "rank": int(m.group("rank")),
                "name": re.sub(r"\s+", " ", name).strip(),
                "state": state,
                "dob": _dob_iso(m.group("dob")),
                "total": float(m.group("total").replace(",", "")),
                "asfMemberNo": m.group("asf") or "",
                "rankedinId": m.group("rankedin"),
            })

    return {"period": period.group(1) if period else None, "players": players}


class _OnePage:
    def __init__(self, page):
        self.pages = [page]


def main() -> None:
    parser = argparse.ArgumentParser(description="Parse one SRAM National Junior Ranking PDF.")
    parser.add_argument("pdf", help="path to the SRAM ranking PDF")
    parser.add_argument("--roster-file", required=True, help="JSON list of your club's players")
    parser.add_argument("--all-states", action="store_true",
                        help=f"keep players from every state, not just {TARGET_STATE}")
    args = parser.parse_args()

    roster = load_roster(args.roster_file)

    try:
        with open(args.pdf, "rb") as f:
            data = f.read()
    except OSError as e:
        sys.exit(f"Could not read {args.pdf}: {e}")

    with pdfplumber.open(BytesIO(data)) as pdf:
        if not is_sram_pdf(pdf):
            sys.exit(f"{args.pdf} doesn't look like an SRAM National Junior Ranking PDF.")
        parsed = parse_sram_pdf(pdf)

    players = []

    for p in parsed["players"]:
        if not args.all_states and p["state"] != TARGET_STATE:
            continue

        row = dict(p)
        row["isClubPlayer"] = (rp.is_kl_match(p["rankedinId"], roster)
                               or rp.is_kl_match(p["asfMemberNo"], roster))

        # IDs only stay in the output for club players
        if not row["isClubPlayer"]:
            row.pop("rankedinId", None)
            row.pop("asfMemberNo", None)

        players.append(row)

    print_json({
        "period": parsed["period"],
        "state": None if args.all_states else TARGET_STATE,
        "clubPlayers": sum(1 for p in players if p["isClubPlayer"]),
        "players": players,
    })


if __name__ == "__main__":
    main()
