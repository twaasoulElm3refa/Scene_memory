const fs = require('fs');
const path = require('path');

const langs = ['ar', 'de', 'en', 'es', 'fa', 'fr', 'hi', 'it', 'ja', 'ru', 'tr', 'ur', 'zh'];
const baseDir = path.resolve(__dirname, '..');
const i18nDir = path.join(baseDir, 'resources/js/i18n');
const scriptsDir = path.join(baseDir, 'scripts/i18n_comparisons');

for (const lang of langs) {
  const jsPath = path.join(scriptsDir, `${lang}.js`);
  const cjsPath = path.join(scriptsDir, `${lang}.cjs`);
  if (fs.existsSync(jsPath) && !fs.existsSync(cjsPath)) {
    fs.renameSync(jsPath, cjsPath);
  }
}

for (const lang of langs) {
  const jsonPath = path.join(i18nDir, `${lang}.json`);
  const cjsPath = path.join(scriptsDir, `${lang}.cjs`);

  if (!fs.existsSync(jsonPath)) {
    console.error(`Missing JSON: ${jsonPath}`);
    process.exit(1);
  }
  if (!fs.existsSync(cjsPath)) {
    console.error(`Missing CJS: ${cjsPath}`);
    process.exit(1);
  }

  const existingJson = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
  const comparisonsData = require(cjsPath);

  existingJson.comparisons = comparisonsData;

  fs.writeFileSync(jsonPath, JSON.stringify(existingJson, null, 2) + '\n', 'utf8');
  console.log(`Merged comparisons for ${lang}: ${Object.keys(comparisonsData).length} keys/sections.`);
}

console.log('All 13 i18n JSON files successfully updated with comparisons data!');
