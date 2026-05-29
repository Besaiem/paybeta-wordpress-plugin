/**
 * bump-php-version.mjs
 *
 * Updates the plugin version string in three places:
 *   1. The "Version:" line in the WordPress plugin header comment
 *   2. The PAYBETA_VERSION constant definition
 *   3. The "Stable tag:" line in readme.txt
 *
 * Called automatically by release-it via the `after:bump` hook:
 *   node scripts/bump-php-version.mjs <newVersion>
 */

import { readFileSync, writeFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');

const newVersion = process.argv[2];

if (!newVersion || !/^\d+\.\d+\.\d+/.test(newVersion)) {
  console.error(`Usage: node scripts/bump-php-version.mjs <version>\nExample: node scripts/bump-php-version.mjs 1.2.0`);
  process.exit(1);
}

// ─── 1. Update paybeta-payment-gateway.php ───────────────────────────────────

const phpFile = resolve(ROOT, 'paybeta-payment-gateway.php');
let php = readFileSync(phpFile, 'utf8');

// Plugin header: " * Version:           1.0.0"
php = php.replace(
  /^( \* Version:\s+)[\d.]+$/m,
  `$1${newVersion}`,
);

// Constant definition: define( 'PAYBETA_VERSION', '1.0.0' );
php = php.replace(
  /(define\s*\(\s*'PAYBETA_VERSION',\s*')[^']+(')/,
  `$1${newVersion}$2`,
);

writeFileSync(phpFile, php, 'utf8');
console.log(`✔ Updated paybeta-payment-gateway.php → ${newVersion}`);

// ─── 2. Update readme.txt ─────────────────────────────────────────────────────

const readmeFile = resolve(ROOT, 'readme.txt');
let readme = readFileSync(readmeFile, 'utf8');

// "Stable tag: 1.0.0"
readme = readme.replace(
  /^(Stable tag:\s+)[\d.]+$/m,
  `$1${newVersion}`,
);

writeFileSync(readmeFile, readme, 'utf8');
console.log(`✔ Updated readme.txt → ${newVersion}`);
