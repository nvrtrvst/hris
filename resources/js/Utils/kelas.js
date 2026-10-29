export function labelKelas(c) {
    const base = `${c.grade ?? ''} ${c.name ?? ''}`.trim();
    const section = String(c.section ?? '').trim();

    if (section && !base.split(/\s+/).includes(section)) {
        return `${base} ${section}`.trim();
    }

    return base;
}
