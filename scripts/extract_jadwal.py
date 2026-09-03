import pdfplumber
import json
import re
import sys

pdf_path = sys.argv[1] if len(sys.argv) > 1 else r'C:\Users\End\Downloads\JADWAL PELAJARAN+ PIKET TETAP 2026-2027_3AGUSTUS.pdf'

pdf = pdfplumber.open(pdf_path)

# ── Step 1: Extract kode mapping from page 5 ──
page5 = pdf.pages[4]
t5 = page5.extract_tables()
kode_map = {}
current_teacher = None
for table in t5:
    for row in table:
        if not row or row == ['No.', 'NAMA GURU', 'KODE', 'MATA PELAJARAN']:
            continue
        no, name, kode, mapel = row[0] or '', row[1] or '', row[2] or '', row[3] or ''
        if name.strip():
            current_teacher = name.strip()
        if kode.strip():
            kode_map[kode.strip()] = {
                'guru': current_teacher,
                'mapel': mapel.strip()
            }

print(f"Kode map: {len(kode_map)} entries", file=sys.stderr)

# ── Step 2: Extract timetable from pages 1-3 ──
DAYS_PER_PAGE = [
    ['Senin', 'Selasa'],
    ['Rabu', 'Kamis'],
    ['Jumat'],
]

KELAS_HEADER = [
    'X MPLB 1', 'X MPLB 2', 'X MPLB 3', 'X MPLB 4',
    'X PBR 1', 'X PBR 2',
    'X DKV 1', 'X DKV 2',
    'X TKR 1', 'X TKR 2',
    'XI MPLB 1', 'XI MPLB 2', 'XI PBR', 'XI DKV', 'XI TKR',
    'XII MPLB 1', 'XII MPLB 2', 'XII MPLB 3',
    'XII PBR 1', 'XII PBR 2',
    'XII DKV 1', 'XII DKV 2',
    'XII TKR',
]

# Time slots (jam number -> time range)
TIME_SLOTS = {
    1: ('07:00', '08:10'),   # upacara on Senin
    2: ('08:10', '08:50'),
    3: ('08:50', '09:30'),
    4: ('09:30', '10:10'),
    5: ('10:30', '11:10'),
    6: ('11:10', '11:50'),
    7: ('11:50', '12:30'),
    8: ('13:00', '13:40'),
    9: ('13:40', '14:20'),
    10: ('14:20', '15:00'),
    11: ('15:00', '15:40'),
}

TIME_SLOTS_ALT = {
    1: ('07:30', '08:10'),   # non-Senin
}

SKIP_KEYWORDS = ['ISTIRAHAT', 'SHOLAT', 'PROGRAM', 'TRANS', 'UPACARA', 'BENDERA',
                 'PEMBIASAAN', 'DZUHUR', 'BERJAMAAH', 'KEPUTRIAN']

def is_skip_row(row):
    """Check if row is a break/special program row."""
    waktu = row[2] or ''
    kelas_cell = row[3] or ''
    combined = f"{waktu} {kelas_cell}"
    for kw in SKIP_KEYWORDS:
        if kw in combined.upper():
            return True
    return False

def parse_jam_ke(val):
    """Parse jam number from column 1."""
    if not val:
        return None
    val = val.strip()
    if val.isdigit():
        return int(val)
    return None

jadwal_entries = []
unknown_codes = set()

for page_idx in range(3):
    page = pdf.pages[page_idx]
    tables = page.extract_tables()
    if not tables:
        continue
    table = tables[0]
    days = DAYS_PER_PAGE[page_idx]
    current_day_idx = 0

    for row_idx, row in enumerate(table):
        # Skip header rows
        if row_idx < 2:
            continue

        # Detect day transition: if col 0 has text
        col0 = (row[0] or '').strip()
        if col0 and any(c.isalpha() for c in col0):
            # Check if this is a known day indicator
            if current_day_idx < len(days) - 1:
                # Check if next rows are for a new day
                # by looking for PROGRAM TRANS or jam 1
                next_jam = parse_jam_ke(row[1])
                if next_jam is None and ('PROGRAM' in col0.upper() or 'TRANS' in col0.upper()):
                    current_day_idx += 1
                    continue
                elif row_idx > 10:  # After first day's jam 11
                    current_day_idx += 1

        if current_day_idx >= len(days):
            continue

        day = days[current_day_idx]

        # Skip break rows
        if is_skip_row(row):
            continue

        # Parse jam number
        jam_ke = parse_jam_ke(row[1])
        if jam_ke is None or jam_ke not in TIME_SLOTS:
            continue

        # Get time range
        time_range = row[2] or ''
        # Try to parse actual time from PDF
        time_match = re.match(r'(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})', time_range)
        if time_match:
            jam_mulai = time_match.group(1)
            jam_selesai = time_match.group(2)
        else:
            jam_mulai, jam_selesai = TIME_SLOTS[jam_ke]

        # Parse kelas cells (columns 3 onwards)
        for col_offset, kelas_name in enumerate(KELAS_HEADER):
            col_idx = 3 + col_offset
            if col_idx >= len(row):
                break
            cell = (row[col_idx] or '').strip()
            if not cell or cell.upper() in ['UPACARA', 'BENDERA']:
                continue

            # Cell contains kode(s)
            codes = re.findall(r'[A-Z0-9]+[A-Z]?', cell)
            for code in codes:
                if code in kode_map:
                    entry = kode_map[code]
                    jadwal_entries.append({
                        'kelas': kelas_name,
                        'hari': day,
                        'jam_mulai': jam_mulai,
                        'jam_selesai': jam_selesai,
                        'kode': code,
                        'guru': entry['guru'],
                        'mapel': entry['mapel'],
                    })
                else:
                    unknown_codes.add(code)

output = {
    'unit': 'SMK',
    'tahun_ajaran': '2026/2027',
    'semester': 1,
    'total_entries': len(jadwal_entries),
    'unique_kelas': len(set(e['kelas'] for e in jadwal_entries)),
    'unique_guru': len(set(e['guru'] for e in jadwal_entries)),
    'jadwal': jadwal_entries,
}

if unknown_codes:
    print(f"Unknown codes: {unknown_codes}", file=sys.stderr)

print(f"Total entries: {len(jadwal_entries)}", file=sys.stderr)
print(f"Unique kelas: {output['unique_kelas']}", file=sys.stderr)
print(f"Unique guru: {output['unique_guru']}", file=sys.stderr)

json.dump(output, sys.stdout, indent=2, ensure_ascii=False)
