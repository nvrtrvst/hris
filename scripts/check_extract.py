import json
from collections import Counter

d = json.load(open(r'D:\yayasan-apps\hris\scripts\jadwal_extracted.json', encoding='utf-8-sig'))

days = Counter(e['hari'] for e in d['jadwal'])
print('By day:')
for k, v in sorted(days.items()):
    print(f'  {k}: {v}')

kelas = Counter(e['kelas'] for e in d['jadwal'])
print('By kelas:')
for k, v in sorted(kelas.items()):
    print(f'  {k}: {v}')

print()
print('Sample entries:')
for e in d['jadwal'][:5]:
    print(f'  {e["hari"]} {e["jam_mulai"]}-{e["jam_selesai"]} {e["kelas"]} [{e["kode"]}] {e["guru"]} - {e["mapel"]}')

print()
print('Last entries:')
for e in d['jadwal'][-5:]:
    print(f'  {e["hari"]} {e["jam_mulai"]}-{e["jam_selesai"]} {e["kelas"]} [{e["kode"]}] {e["guru"]} - {e["mapel"]}')
