"""
Loads the --roster-file every script here takes: a local JSON list of your
club's players, e.g.

    [{"name": "SAF IMTIYAZ CHE BIN SUHIR", "asfMemberNo": "2218969", "rankedinId": "R000123714"}]

and turns it into the lookup ranking_parser.is_kl_match() expects.
"""

import json
import sys

import ranking_parser as rp


def load_roster(path: str) -> dict:
    try:
        with open(path, encoding="utf-8") as f:
            players = json.load(f)
    except (OSError, ValueError) as e:
        sys.exit(f"Could not read roster file {path}: {e}")

    if not isinstance(players, list):
        sys.exit(f"Roster file {path} must contain a JSON list of players.")

    return rp.build_roster(players)


def print_json(data) -> None:
    # Windows sends redirected output in the local code page otherwise
    sys.stdout.reconfigure(encoding="utf-8")
    json.dump(data, sys.stdout, ensure_ascii=False, indent=2)
    sys.stdout.write("\n")
