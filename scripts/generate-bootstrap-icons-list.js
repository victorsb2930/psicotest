const fs = require('fs');
const path = require('path');

function log(...args) { console.log('[generate-icons]', ...args); }

const projectRoot = path.resolve(__dirname, '..');
const pkgPath = path.join(projectRoot, 'package.json');
let pkg = {};
try { pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8')); } catch (e) { /* ignore */ }

// Try to locate bootstrap-icons CSS inside node_modules
const cssCandidates = [
  path.join(projectRoot, 'node_modules', 'bootstrap-icons', 'font', 'bootstrap-icons.css'),
];

let cssPath = cssCandidates.find(p => fs.existsSync(p));
if (!cssPath) {
  log('bootstrap-icons CSS not found in node_modules. Skipping generation.');
  process.exit(0);
}

try {
  const css = fs.readFileSync(cssPath, 'utf8');
  const re = /\.bi-([a-z0-9-]+)\s*::?before\s*\{/gi;
  const set = new Set();
  let m;
  while ((m = re.exec(css)) !== null) {
    set.add(`bi-${m[1]}`);
  }
  const icons = Array.from(set).sort();
  const out = {
    version: (pkg.dependencies && pkg.dependencies['bootstrap-icons']) || (pkg.devDependencies && pkg.devDependencies['bootstrap-icons']) || null,
    icons
  };
  const outDir = path.join(projectRoot, 'public');
  if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });
  const outPath = path.join(outDir, 'bootstrap-icons-list.json');
  fs.writeFileSync(outPath, JSON.stringify(out, null, 2), 'utf8');
  log('Wrote', outPath, 'with', icons.length, 'icons');
} catch (e) {
  // console.error('[generate-icons] error:', e);
  process.exit(1);
}
