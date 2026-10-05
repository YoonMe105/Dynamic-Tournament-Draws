"""
Downloads all 10 Asian Junior Ranking category PDFs and writes one
combined JSON file, with each player flagged against your club's roster.

    python rankings_ingest.py --roster-file roster.json --out rankings.json

The PDF links are read from the ranking page each time (their file names
change with every new edition, e.g. BU_15_7093f69115.pdf), so nothing here
needs editing when a new month is published.
"""

import argparse
import json
import re
import sys

import requests

import ranking_parser as rp
from parse_uploaded_pdf import flag_club_players
from roster_file import load_roster

RANKING_PAGE = "https://www.asiansquash.org/ranking"
UPLOADS_BASE = "https://api.asiansquash.org/"

# e.g. uploads/BU_15_7093f69115.pdf
PDF_LINK_RE = re.compile(r"uploads/([BG]U)_(\d{2})_[A-Za-z0-9]+\.pdf")


def find_pdf_links() -> dict:
    """Returns {"BU11": url, ...} for every category linked from the ranking page."""
    response = requests.get(RANKING_PAGE, headers=rp.HEADERS, timeout=30)
    response.raise_for_status()

    links = {}

    for m in PDF_LINK_RE.finditer(response.text):
        category = m.group(1) + m.group(2)

        if category in rp.KNOWN_CATEGORIES and category not in links:
            links[category] = UPLOADS_BASE + m.group(0)

    return links


def main() -> None:
    parser = argparse.ArgumentParser(description="Download and parse all Asian Junior Ranking PDFs.")
    parser.add_argument("--roster-file", required=True, help="JSON list of your club's players")
    parser.add_argument("--out", default="rankings.json", help="output file (default: rankings.json)")
    args = parser.parse_args()

    roster = load_roster(args.roster_file)

    try:
        links = find_pdf_links()
    except requests.RequestException as e:
        sys.exit(f"Could not load {RANKING_PAGE}: {e}")

    missing = sorted(rp.KNOWN_CATEGORIES - set(links))

    if missing:
        print(f"Warning: no PDF link found for {', '.join(missing)}", file=sys.stderr)

    categories = {}

    for category in sorted(links):
        print(f"Downloading {category} ...", file=sys.stderr)

        try:
            response = requests.get(links[category], headers=rp.HEADERS, timeout=60)
            response.raise_for_status()
        except requests.RequestException as e:
            print(f"  skipped: {e}", file=sys.stderr)
            continue

        parsed = rp.parse_pdf(response.content)
        result = flag_club_players(parsed, roster)
        result["source"] = links[category]

        categories[category] = result
        print(f"  {len(result['players'])} Malaysia-listed players, {result['clubPlayers']} from your roster",
              file=sys.stderr)

    with open(args.out, "w", encoding="utf-8") as f:
        json.dump({"categories": categories}, f, ensure_ascii=False, indent=2)

    print(f"Wrote {args.out}", file=sys.stderr)


if __name__ == "__main__":
    main()
