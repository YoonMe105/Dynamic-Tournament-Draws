"""
Parses one Asian Junior Ranking category PDF (BU11, GU19, ...) and prints
JSON to stdout: the Malaysia-listed players, each flagged with whether they
match your club's roster.

    python parse_uploaded_pdf.py BU_15.pdf --roster-file roster.json
"""

import argparse
import sys

import ranking_parser as rp
from roster_file import load_roster, print_json


def flag_club_players(parsed: dict, roster: dict) -> dict:
    """Adds "isClubPlayer" to every row. Member IDs are kept only for club
    players - they're not meant to leak into the output for anyone else."""
    players = []

    for p in parsed["players"]:
        row = dict(p)
        row["isClubPlayer"] = rp.is_kl_match(p.get("memberId", ""), roster)

        if not row["isClubPlayer"]:
            row.pop("memberId", None)

        players.append(row)

    return {
        "category": parsed["category"],
        "period": parsed["period"],
        "clubPlayers": sum(1 for p in players if p["isClubPlayer"]),
        "players": players,
    }


def main() -> None:
    parser = argparse.ArgumentParser(description="Parse one Asian Junior Ranking category PDF.")
    parser.add_argument("pdf", help="path to the ranking PDF, e.g. BU_15.pdf")
    parser.add_argument("--roster-file", required=True, help="JSON list of your club's players")
    args = parser.parse_args()

    roster = load_roster(args.roster_file)

    try:
        with open(args.pdf, "rb") as f:
            parsed = rp.parse_pdf(f.read())
    except OSError as e:
        sys.exit(f"Could not read {args.pdf}: {e}")

    if parsed["category"] is None:
        sys.exit(f"{args.pdf} doesn't look like an Asian Junior Ranking category PDF "
                 "(no 'BOYS/GIRLS UNDER ..' title on page 1).")

    print_json(flag_club_players(parsed, roster))


if __name__ == "__main__":
    main()
