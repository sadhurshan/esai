import fs from 'node:fs';
import path from 'node:path';

const repoRoot = process.cwd();
const inputPath = path.join(repoRoot, 'docs', 'Stable Staging Data.md');
const outputPath = path.join(repoRoot, '.env.uat');

const content = fs.readFileSync(inputPath, 'utf8');

function matchLine(regex) {
  const match = content.match(regex);
  return match?.[1]?.trim() ?? '';
}

const baseUrl = matchLine(/Staging base url:\s*(.+)/i);
const buyerEmail = matchLine(/Buyer test account:[\s\S]*?Username:\s*(.+)/i);
const buyerPassword = matchLine(/Buyer test account:[\s\S]*?Password:\s*(.+)/i);
const supplierEmail = matchLine(/Supplier test account:[\s\S]*?Username:\s*(.+)/i);
const supplierPassword = matchLine(/Supplier test account:[\s\S]*?Password:\s*(.+)/i);
const adminEmail = matchLine(/Super admin test account:[\s\S]*?Username:\s*(.+)/i);
const adminPassword = matchLine(/Super admin test account:[\s\S]*?Password:\s*(.+)/i);

const envLines = [
  `PLAYWRIGHT_BASE_URL=${baseUrl}`,
  `PLAYWRIGHT_BUYER_EMAIL=${buyerEmail}`,
  `PLAYWRIGHT_BUYER_PASSWORD=${buyerPassword}`,
  `PLAYWRIGHT_SUPPLIER_EMAIL=${supplierEmail}`,
  `PLAYWRIGHT_SUPPLIER_PASSWORD=${supplierPassword}`,
  `PLAYWRIGHT_ADMIN_EMAIL=${adminEmail}`,
  `PLAYWRIGHT_ADMIN_PASSWORD=${adminPassword}`,
  '',
];

fs.writeFileSync(outputPath, envLines.join('\n'), 'utf8');
console.log(`Wrote ${outputPath}`);
