"""
Shared PDF-parsing logic for the Asian Junior Ranking and SRAM ranking
pipelines.

Used by parse_uploaded_pdf.py (one Asian ranking category PDF),
parse_sram_pdf.py (one combined SRAM ranking PDF), and rankings_ingest.py
(bulk refresh of all 10 Asian ranking categories at once).

This file has no knowledge of any particular club's player database - see
build_roster() below. To match ranking PDF rows against your own club's
roster, get your players into a list of dicts shaped like:

    [{"name": "...", "rankedinId": "R000123456", "asfMemberNo": "2218213"}, ...]

(either field may be missing/empty for a given player - only players with
at least one ID on file can ever match) and pass that list to
build_roster(). Nothing else in this file - row reconstruction,
category/period detection, ID matching - needs to change.

The PDF text is drawn column-by-column, not row-by-row, so rows have to be
rebuilt from each word's on-page position (see extract_rows) - reading text
in stream order (which is what pdftotext, and most PDF-parsing libraries in
other languages, do) scrambles every row. That's the reason this has to be
Python: pdfplumber gives access to actual glyph coordinates.
"""

import re
from io import BytesIO

import pdfplumber

# Used when fetching ranking-source PDFs (asiansquash.org) - a plain browser
# UA, not tied to any particular site.
HEADERS = {"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36"}

KNOWN_CATEGORIES = {"BU11", "BU13", "BU15", "BU17", "BU19", "GU11", "GU13", "GU15", "GU17", "GU19"}

# Only anchors the fixed columns - Rank, Name, Country, then (skipping the
# variable-width "+/-" indicator) Member ID, DOB, then (skipping the
# variable per-tournament columns) Events, Total, Average.
#
# `name` is greedy (.+), not lazy - confirmed against a real upload that a
# lazy name capture can stop at the player's own surname when it happens to
# be exactly 3 uppercase letters (e.g. "TNG", "LIM", "TAN", "ONG" - common
# Malaysian/Singaporean/HK surnames), since that also satisfies
# [A-Z]{3} and lets the rest of the pattern still match further along the
# line. Greedy backtracking instead prefers the *last* 3-letter uppercase
# token before the member ID/DOB/events/total/average tail, which is always
# the real country code.
ROW_RE = re.compile(
    r"^(?P<rank>\d+)\s+(?P<name>.+)\s+(?P<country>[A-Z]{3})\s+"
    r".*?(?P<memberid>\d{5,8})\s+(?P<dob>\d{1,2}/\d{1,2}/\d{4})\s+.*?"
    r"(?P<events>\d+)\s+(?P<total>[\d,]+\.\d{2})\s+(?P<average>[\d,]+\.\d{2})\s*$"
)

# The first page of every category PDF has a "BOYS UNDER 19" / "GIRLS UNDER
# 13" style title - used to auto-detect which category an uploaded PDF is.
CATEGORY_HEADER_RE = re.compile(r"(BOYS|GIRLS)\s+UNDER\s+0?(\d{1,2})", re.IGNORECASE)

# Same page also has a title like "ASIAN JUNIOR RANKING AUGUST 2026" - the
# month/year this edition covers.
PERIOD_HEADER_RE = re.compile(r"ASIAN JUNIOR RANKING\s+([A-Z]+)\s+(\d{4})", re.IGNORECASE)


def is_kl_match(id_value: str, roster: dict) -> bool:
    """True if `id_value` (an ASF/Rankedin ID from the source PDF row)
    matches a roster entry's ID on file.

    ID-only by design - no name-based fallback. Name matching (aliasing,
    bounded truncation, connector stripping) kept needing another special
    case for the next real-world name variant, and each loosening carried
    collision risk. A player who hasn't been given an ASF/Rankedin ID in
    the club database yet simply won't match here - fixing that is a
    roster data-entry task, not a parsing one.
    """
    return bool(id_value) and id_value in roster["by_id"]


def detect_category(pdf: pdfplumber.PDF) -> str | None:
    text = pdf.pages[0].extract_text() or ""
    m = CATEGORY_HEADER_RE.search(text)
    if not m:
        return None
    prefix = "B" if m.group(1).upper() == "BOYS" else "G"
    category = f"{prefix}U{m.group(2).zfill(2)}"
    return category if category in KNOWN_CATEGORIES else None


def detect_period(pdf: pdfplumber.PDF) -> str | None:
    text = pdf.pages[0].extract_text() or ""
    m = PERIOD_HEADER_RE.search(text)
    return f"{m.group(1).title()} {m.group(2)}" if m else None


# A 3+ digit rank (rank >= 100) can run out of column width in the source
# PDF and get glued straight onto the next word with no space in the text
# stream itself (e.g. "412Kesavan", confirmed against a real upload where
# this silently dropped hundreds of rows from one category alone, rank
# ~100 onward - the fixed rank column has room for 1-2 digit ranks to stay
# separated, but not 3). Split the leading digit run off before the
# row_words[0].isdigit() check below, which would otherwise reject the
# whole row as header/footer junk.
_LEADING_RANK_RE = re.compile(r"^(\d+)(\D.+)$")


def extract_rows(pdf: pdfplumber.PDF) -> list[str]:
    """Rebuilds each visual table row by clustering words by on-page position."""
    rows = []
    for page in pdf.pages:
        words = page.extract_words(use_text_flow=False, keep_blank_chars=False)
        buckets: dict[int, list] = {}
        for w in words:
            key = round(w["top"] / 3) * 3
            buckets.setdefault(key, []).append(w)
        for key in sorted(buckets):
            row_words = sorted(buckets[key], key=lambda w: w["x0"])
            if not row_words:
                continue
            first_text = row_words[0]["text"]
            glued = _LEADING_RANK_RE.match(first_text)
            if glued:
                texts = [glued.group(1), glued.group(2)] + [w["text"] for w in row_words[1:]]
            elif first_text.isdigit():
                texts = [w["text"] for w in row_words]
            else:
                continue  # not a player row (header/footer junk)
            rows.append(" ".join(texts))
    return rows


def parse_malaysia_players(pdf: pdfplumber.PDF) -> list[dict]:
    players = []
    for row in extract_rows(pdf):
        m = ROW_RE.match(row)
        if not m or m.group("country") != "MAS":
            continue
        players.append({
            "rank": int(m.group("rank")),
            "name": re.sub(r"\s+", " ", m.group("name")).strip(),
            "total": float(m.group("total").replace(",", "")),
            "average": float(m.group("average").replace(",", "")),
            # Used to match by ID in the ingest scripts, then dropped
            # before the result is saved/published - not meant to leak
            # ASF membership numbers for non-matched players into public
            # output.
            "memberId": m.group("memberid"),
        })
    return players


def parse_pdf(pdf_bytes: bytes) -> dict:
    """Returns {"category": str|None, "period": str|None, "players": [...]} for one ranking PDF (not yet club-flagged)."""
    with pdfplumber.open(BytesIO(pdf_bytes)) as pdf:
        category = detect_category(pdf)
        period = detect_period(pdf)
        players = parse_malaysia_players(pdf)
    players.sort(key=lambda p: p["rank"])
    return {"category": category, "period": period, "players": players}


def build_roster(players: list[dict]) -> dict:
    """Returns {"by_id": {...}} for is_kl_match(), from a list of player
    dicts (each with an optional "name" and "rankedinId"/"asfMemberNo").

    How `players` is obtained - your own database, an API, a plain JSON
    export - is entirely up to you; this function only needs the list.

    Include inactive players too, not just active ones - a player who's
    gone inactive in the club database can still show up in a ranking PDF,
    and should still be matched.

    "by_id" merges rankedinId and asfMemberNo into one lookup - their
    formats never collide (ASF numbers are plain digits, Rankedin IDs
    start with "R"), so one dict is simplest.
    """
    by_id = {}
    for p in players:
        for id_value in (p.get("rankedinId"), p.get("asfMemberNo")):
            if id_value:
                by_id[id_value] = p.get("name")
    return {"by_id": by_id}
