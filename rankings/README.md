# Ranking PDF pipeline

Parses Asian Squash Federation junior ranking PDFs and the SRAM National
Junior Ranking PDF, and flags which Malaysia-listed players match your own
club's roster.

## Setup

```
pip install pdfplumber requests
```

## The one thing you need to provide: a roster file

Every script here takes `--roster-file <path>` - a local JSON file
containing a list of your club's players, each shaped like:

```json
[
  {"name": "SAF IMTIYAZ CHE BIN SUHIR", "asfMemberNo": "2218969", "rankedinId": "R000123714"},
  {"name": "SOME OTHER PLAYER", "asfMemberNo": "", "rankedinId": "R000000000"}
]
```

`asfMemberNo` and/or `rankedinId` may be empty for a given player - a
player needs at least one of those on file to ever be matched. Matching is
**ID-only, no name fallback** (see `is_kl_match()` in `ranking_parser.py`
for why - short version: name matching kept needing another special case
for the next real-world name variant, and every loosening carried
collision risk).

Include your inactive/former players too if they might still show up in a
ranking PDF from before they went inactive.

How you produce this file is entirely up to you - export it from your own
database, generate it on the fly, whatever fits your setup. See
`example_roster.json` for a minimal working example.

## Scripts

- **`parse_uploaded_pdf.py <pdf> --roster-file <path>`** - parses one Asian
  ranking category PDF (BU11, GU19, etc.), prints JSON to stdout.
- **`parse_sram_pdf.py <pdf> --roster-file <path>`** - parses one SRAM
  ranking PDF (all categories in one file), prints JSON to stdout. Filters
  to `TARGET_STATE = "Kuala Lumpur"` near the top of the file - change that
  constant if your club is based in a different state.
- **`rankings_ingest.py --roster-file <path> [--out rankings.json]`** -
  downloads all 10 Asian ranking category PDFs from their fixed
  asiansquash.org source URLs and writes one combined `rankings.json`.
  Useful for a full resync; the other two scripts are for one PDF at a
  time.
- **`ranking_parser.py`** - shared library the other three import from. No
  file I/O beyond the PDF itself, no knowledge of any particular club's
  database - this is what you probably don't need to touch.
