"""
Reads a player registration spreadsheet (.xlsx / .xls / .csv) for
admin/admin_register_players_excel.php and prints JSON to stdout:

    {"headers": [...], "columns": {"first_name": "First Name", ...},
     "sheets": ["BU19", "BU15", ...],
     "rows": [{"sheet": "BU19", "row": 2, "player_id": "", "first_name": "Ali", ...}, ...]}

    python read_registration_sheet.py players.xlsx

Every sheet with a recognisable header row is read, so a file with one
sheet per category (BU19, BU15, GU13 ...) works: the PHP page uses the
sheet name as the category when a row has none. Sheets without a header
row (like the template's "Instructions") are skipped. The header row can
be anywhere in the first rows of a sheet and the columns in any order;
headings are matched loosely (see FIELDS). Checking the values against the
database is done by the PHP page.
"""

import json
import re
import sys
from pathlib import Path

from parse_excel import _sheets, clean_id, to_iso_date, to_rank

# field -> headings it may have (compared without spaces / punctuation, lower case)
FIELDS = {
    "player_id": ["playerid", "id"],
    "first_name": ["firstname", "givenname", "first"],
    "last_name": ["lastname", "surname", "familyname", "last"],
    "full_name": ["fullname", "name", "playername"],
    "gender": ["gender", "sex"],
    "dob": ["dateofbirth", "dob", "birthdate", "birthday"],
    "nationality": ["nationality", "country"],
    "email": ["email", "emailaddress", "mail"],
    "contact": ["contactnumber", "contact", "phone", "phonenumber", "mobile"],
    "category": ["category", "event", "division"],
    "national_ranking": ["nationalranking", "national"],
    "ajss_ranking": ["ajssranking", "ajss", "asianranking"],
    "world_ranking": ["worldranking", "world", "psaranking", "psa"],
    "payment": ["payment", "paymentstatus", "paid"],
    "amount_paid": ["amountpaid", "amount"],
    "endorsement": ["endorsement", "endorsed"],
}

HEADER_ROWS_TO_SEARCH = 15
MAX_ROWS = 1000


def _key(text) -> str:
    return re.sub(r"[^a-z0-9]", "", str(text or "").lower())


def _match_header(row: list) -> dict:
    """{field: column index} for a possible header row."""
    found = {}

    for index, cell in enumerate(row):
        key = _key(cell)

        if not key:
            continue

        for field, names in FIELDS.items():
            if field not in found and key in names:
                found[field] = index
                break

    return found


def _is_header(found: dict) -> bool:
    # At least two known headings, so a list of column names (like the
    # template's Instructions sheet) is not taken for a header row
    has_name = ("first_name" in found and "last_name" in found) or "full_name" in found
    return len(found) >= 2 and (has_name or "player_id" in found)


def _text(value) -> str:
    if value is None:
        return ""
    return clean_id(value) if isinstance(value, float) else str(value).strip()


def _sheet_rows(rows: list, header_index: int, found: dict, sheet_name: str, limit: int) -> list:
    """Player rows under the header row of one sheet."""
    result_rows = []

    for offset, data in enumerate(rows[header_index + 1:], start=header_index + 2):

        if len(result_rows) >= limit:
            break

        data = list(data or [])

        def cell(field):
            index = found.get(field)
            return data[index] if index is not None and index < len(data) else None

        item = {"sheet": sheet_name, "row": offset}

        for field in FIELDS:
            value = cell(field)

            if field == "dob":
                item["dob"] = to_iso_date(value) or ""
                item["dob_raw"] = _text(value)
            elif field.endswith("_ranking"):
                rank = to_rank(value)
                item[field] = str(rank) if rank else ""
            else:
                item[field] = _text(value)

        # Completely empty rows are skipped
        if not any(item[f] for f in FIELDS if f != "dob") and not item["dob_raw"]:
            continue

        result_rows.append(item)

    return result_rows


def read(path: str) -> dict:
    headers, columns, sheets, all_rows = [], {}, [], []

    for sheet_name, rows in _sheets(Path(path)):

        for header_index, row in enumerate(rows[:HEADER_ROWS_TO_SEARCH]):

            found = _match_header(list(row or []))

            if not _is_header(found):
                continue

            sheet_headers = [_text(cell) for cell in row]

            for field, index in found.items():
                columns.setdefault(field, sheet_headers[index])

            for heading in sheet_headers:
                if heading and heading not in headers:
                    headers.append(heading)

            sheets.append(str(sheet_name))
            all_rows += _sheet_rows(rows, header_index, found, str(sheet_name), MAX_ROWS - len(all_rows))
            break

        if len(all_rows) >= MAX_ROWS:
            break

    if not sheets:
        return {"error": "No header row found. The first row should have column headings such as "
                         "First Name, Last Name, Gender, Date of Birth (or Player ID)."}

    return {"headers": headers, "columns": columns, "sheets": sheets, "rows": all_rows}


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No file given."}))
        return

    try:
        result = read(sys.argv[1])
    except Exception as exc:  # unreadable / not a spreadsheet
        result = {"error": "The file could not be read: " + str(exc)}

    sys.stdout.reconfigure(encoding="utf-8")
    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()
