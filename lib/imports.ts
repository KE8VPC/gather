export function csvRows(text: string): string[][] {
  const rows: string[][] = [];
  let row: string[] = [],
    cell = '',
    quoted = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (c === '"') {
      if (quoted && text[i + 1] === '"') {
        cell += '"';
        i++;
      } else quoted = !quoted;
    } else if (c === ',' && !quoted) {
      row.push(cell);
      cell = '';
    } else if ((c === '\n' || c === '\r') && !quoted) {
      if (c === '\r' && text[i + 1] === '\n') i++;
      row.push(cell);
      if (row.some(Boolean)) rows.push(row);
      row = [];
      cell = '';
    } else cell += c;
  }
  if (quoted) throw new Error('The CSV contains an unclosed quoted field.');
  row.push(cell);
  if (row.some(Boolean)) rows.push(row);
  return rows;
}
export function csvSources(text: string): string[] {
  const rows = csvRows(text.replace(/^\uFEFF/, ''));
  if (!rows.length) return [];
  const headers = rows[0].map((x) => x.trim().toLowerCase());
  const col = headers.findIndex((x) =>
    ['channel url', 'channel_url', 'url', 'feed url', 'feed_url'].includes(x),
  );
  const idcol = headers.findIndex((x) => x === 'channel id');
  return [
    ...new Set(
      (col >= 0
        ? rows.slice(1).map((r) => r[col])
        : idcol >= 0
          ? rows.slice(1).map((r) => r[idcol])
          : rows.flat()
      )
        .filter((v) => v && /^(https:\/\/|UC[\w-]{22}$)/.test(v.trim()))
        .map((v) => v.trim()),
    ),
  ];
}
