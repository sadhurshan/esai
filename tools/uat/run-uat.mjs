import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';

const repoRoot = process.cwd();
const envPath = path.join(repoRoot, '.env.uat');
const resultsPath = path.join(repoRoot, 'docs', 'UAT_RESULTS.md');

if (!fs.existsSync(envPath)) {
  console.error('Missing .env.uat. Run tools/uat/generate-uat-env.mjs first.');
  process.exit(1);
}

const envFile = fs.readFileSync(envPath, 'utf8');
for (const line of envFile.split(/\r?\n/)) {
  if (!line || line.trim().startsWith('#')) continue;
  const [key, ...rest] = line.split('=');
  if (!key) continue;
  process.env[key] = rest.join('=').trim();
}

const isWindows = process.platform === 'win32';
const command = isWindows ? 'cmd' : 'npx';
const args = isWindows
  ? ['/c', 'npx playwright test tests/e2e/uat']
  : ['playwright', 'test', 'tests/e2e/uat'];

const child = spawn(command, args, {
  stdio: ['inherit', 'pipe', 'pipe'],
  cwd: repoRoot,
  env: process.env,
  shell: !isWindows,
});

const header = `# UAT Results\n\nGenerated: ${new Date().toISOString()}\n\n`;
fs.writeFileSync(resultsPath, header, 'utf8');

child.stdout.on('data', (chunk) => {
  const text = chunk.toString();
  process.stdout.write(text);
  fs.appendFileSync(resultsPath, text);
});

child.stderr.on('data', (chunk) => {
  const text = chunk.toString();
  process.stderr.write(text);
  fs.appendFileSync(resultsPath, text);
});

child.on('exit', (code) => {
  process.exit(code ?? 1);
});
