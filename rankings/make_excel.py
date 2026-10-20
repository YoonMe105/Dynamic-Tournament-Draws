"""
Writes a simple .xlsx report from JSON on stdin (used by
admin/admin_excel_export.php):

    {"sheet": "BU15 Seeding",
     "title": "Seeding Report - BU15",
     "info": [["Tournament", "testing 2"], ["Category", "BU15"]],
     "headers": ["No.", "Seed", "Player", ...],
     "rows": [[1, 1, "Jacob Curry", ...], ...],
     "widths": [6, 8, 28, ...]}

or several sheets: {"sheets": [{...}, {...}]} with the same keys per sheet.

A sheet can also place single cells under its header row instead of (or
as well as) rows - used for the draw bracket:

     "cells": [[row, column, "text", "style"], ...]

row / column start at 1 (row 1 = first row under the header). style is
"line" (text with a line under it, like a draw sheet), "bold", or "".

    python make_excel.py output.xlsx < data.json

Numbers stay numbers in Excel; None / "" become empty cells.
"""

import json
import sys

from openpyxl import Workbook
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter

PURPLE = "993D86"
THIN = Side(style="thin", color="DDDDDD")
LINE = Side(style="medium", color="555555")


def write_sheet(sheet, data):
    sheet.title = str(data.get("sheet") or "Report")[:31]

    row = 1

    if data.get("title"):
        sheet.cell(row=row, column=1, value=data["title"]).font = Font(bold=True, size=14, color=PURPLE)
        row += 2

    for label, value in data.get("info", []):
        sheet.cell(row=row, column=1, value=label).font = Font(bold=True)
        sheet.cell(row=row, column=2, value=value)
        row += 1

    if data.get("info"):
        row += 1

    headers = data.get("headers", [])

    for column, heading in enumerate(headers, start=1):
        cell = sheet.cell(row=row, column=column, value=heading)
        cell.font = Font(bold=True, color="FFFFFF")
        cell.fill = PatternFill("solid", fgColor=PURPLE)
        cell.alignment = Alignment(vertical="center")

    header_row = row

    if headers:
        sheet.freeze_panes = sheet.cell(row=header_row + 1, column=1)

    for values in data.get("rows", []):
        row += 1
        for column, value in enumerate(values, start=1):
            cell = sheet.cell(row=row, column=column, value=None if value == "" else value)
            cell.border = Border(bottom=THIN)

    if headers and data.get("rows"):
        sheet.auto_filter.ref = f"A{header_row}:{get_column_letter(len(headers))}{row}"

    for cell_row, column, value, style in data.get("cells", []):
        cell = sheet.cell(row=header_row + cell_row, column=column, value=None if value == "" else value)

        if style == "line":
            cell.border = Border(bottom=LINE)
        elif style == "bold":
            cell.font = Font(bold=True)

    for column, width in enumerate(data.get("widths", []), start=1):
        sheet.column_dimensions[get_column_letter(column)].width = width


def main():
    output = sys.argv[1]
    data = json.load(sys.stdin)

    book = Workbook()
    sheets = data.get("sheets") or [data]

    for index, sheet_data in enumerate(sheets):
        sheet = book.active if index == 0 else book.create_sheet()
        write_sheet(sheet, sheet_data)

    book.save(output)


if __name__ == "__main__":
    main()
