import fs from 'node:fs';
import path from 'node:path';

const repoRoot = process.cwd();
const inputPath = path.join(
  repoRoot,
  'docs',
  'Backlog - Elements Supply AI - Backlog v1.0.csv',
);
const outputPath = path.join(repoRoot, 'docs', 'UAT_BACKLOG_CHECKLIST.md');

function parseCsv(text) {
  const rows = [];
  let row = [];
  let field = '';
  let inQuotes = false;

  for (let i = 0; i < text.length; i += 1) {
    const char = text[i];
    const next = text[i + 1];

    if (char === '"') {
      if (inQuotes && next === '"') {
        field += '"';
        i += 1;
        continue;
      }
      inQuotes = !inQuotes;
      continue;
    }

    if (char === ',' && !inQuotes) {
      row.push(field);
      field = '';
      continue;
    }

    if ((char === '\n' || char === '\r') && !inQuotes) {
      if (char === '\r' && next === '\n') {
        i += 1;
      }
      row.push(field);
      field = '';
      if (row.some((cell) => cell.length > 0)) {
        rows.push(row);
      }
      row = [];
      continue;
    }

    field += char;
  }

  if (field.length > 0 || row.length > 0) {
    row.push(field);
    rows.push(row);
  }

  return rows;
}

function sanitize(value) {
  return (value ?? '').trim();
}

function extractAcceptanceCriteria(description) {
  if (!description) return [];
  const text = String(description);
  const match = text.match(/Acceptance Criteria:\s*([\s\S]*)/i);
  if (!match) return [];
  const criteriaBlock = match[1];
  const lines = criteriaBlock.split(/\r?\n/);
  const items = [];
  for (const rawLine of lines) {
    const line = rawLine.trim();
    if (!line) continue;
    const isBullet = /^[-*•]\s+/.test(line);
    const cleaned = line.replace(/^[-*•]\s*/, '').trim();
    if (!cleaned) continue;
    if (isBullet || items.length === 0) {
      items.push(cleaned);
    } else {
      items[items.length - 1] = `${items[items.length - 1]} ${cleaned}`.trim();
    }
  }
  return items;
}

const csv = fs.readFileSync(inputPath, 'utf8');
const rows = parseCsv(csv);

if (rows.length === 0) {
  console.error('Backlog CSV is empty.');
  process.exit(1);
}

const header = rows[0].map((cell) => cell.trim());
const indexByName = Object.fromEntries(header.map((name, idx) => [name, idx]));

const issueTypeIndex = indexByName['Issue Type'];
const summaryIndex = indexByName['Summary'];
const epicNameIndex = indexByName['Epic Name'];
const epicLinkIndex = indexByName['Epic Link'];
const storyIdIndex = indexByName['Story ID'];
const descriptionIndex = indexByName.Description;

if (
  [issueTypeIndex, summaryIndex, epicNameIndex, epicLinkIndex, storyIdIndex].some(
    (value) => value === undefined,
  )
) {
  console.error('Backlog CSV headers are missing required columns.');
  process.exit(1);
}

const epicByKey = new Map();
const epicByPrefix = new Map();

for (const row of rows.slice(1)) {
  const issueType = sanitize(row[issueTypeIndex]);
  if (issueType !== 'Epic') {
    continue;
  }

  const epicKey = sanitize(row[epicNameIndex]);
  const epicSummary = sanitize(row[summaryIndex]);

  if (epicKey.length > 0 && epicSummary.length > 0) {
    epicByKey.set(epicKey, epicSummary);
    const prefixMatch = epicSummary.match(/^\[(.+?)\]/);
    if (prefixMatch?.[1]) {
      epicByPrefix.set(prefixMatch[1].trim(), epicSummary);
    }
  }
}

const epicOrder = [];
const epics = new Map();

for (const row of rows.slice(1)) {
  const issueType = sanitize(row[issueTypeIndex]);
  if (issueType !== 'Story') {
    continue;
  }

  const epicKey = sanitize(row[epicLinkIndex]);
  const epicNameFromKey = epicKey.length > 0 ? epicByKey.get(epicKey) : undefined;
  const epicNameFromRowRaw = sanitize(row[epicNameIndex]);
  const epicNameFromRow = epicNameFromRowRaw.length > 0 ? epicNameFromRowRaw : undefined;
  const storyId = sanitize(row[storyIdIndex]);
  const summary = sanitize(row[summaryIndex]);
  const description = descriptionIndex !== undefined ? sanitize(row[descriptionIndex]) : '';
  const acceptanceCriteria = extractAcceptanceCriteria(description);
  const prefixSource = storyId.length > 0 ? storyId : summary;
  const storyPrefix = prefixSource.match(/^[A-Za-z]+/)?.[0] ?? '';
  const epicNameFromPrefix = storyPrefix.length > 0 ? epicByPrefix.get(storyPrefix) : undefined;
  const epicName =
    epicNameFromKey ?? epicNameFromRow ?? epicNameFromPrefix ?? 'Uncategorized';
  if (!epics.has(epicName)) {
    epics.set(epicName, []);
    epicOrder.push(epicName);
  }

  epics.get(epicName).push({ storyId, summary, acceptanceCriteria });
}

let output = `# UAT Backlog Checklist\n\n`;
output += `Generated from docs/Backlog - Elements Supply AI - Backlog v1.0.csv.\n`;
output += `Update this file by running tools/uat/generate-uat-checklist.mjs from the repo root.\n\n`;

for (const epicName of epicOrder) {
  output += `## ${epicName}\n`;
  const stories = epics.get(epicName) ?? [];
  for (const story of stories) {
    const label = story.storyId
      ? `${story.storyId} — ${story.summary}`
      : story.summary;
    output += `- [ ] ${label}\n`;
    const criteria = story.acceptanceCriteria?.length
      ? story.acceptanceCriteria
      : ['TODO: clarify with spec'];
    for (const criterion of criteria) {
      output += `  - ${criterion}\n`;
    }
  }
  output += `\n`;
}

fs.writeFileSync(outputPath, output, 'utf8');
console.log(`Wrote ${outputPath}`);
