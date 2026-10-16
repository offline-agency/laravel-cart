import { readFile, writeFile, readdir, stat } from 'node:fs/promises';
import { resolve, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const read = (path) => readFile(resolve(root, path), 'utf8');
const source = await read('examples/quick-start.php');
const excerpt = source.split('    // docs:start\n')[1]?.split('    // docs:end')[0];
if (!excerpt) throw new Error('Missing maintained example markers');
const snippet = excerpt.split('\n').map(line => line.replace(/^    /, '')).join('\n').trimEnd();
const usage = await read('docs/v4/usage.md');
const generated = usage.replace(/(<!-- example:quick-start:start -->)[\s\S]*?(<!-- example:quick-start:end -->)/,
    `$1\n\n\`\`\`php\n${snippet}\n\`\`\`\n\n$2`);
if (process.argv.includes('--write')) await writeFile(resolve(root, 'docs/v4/usage.md'), generated);
else if (usage !== generated) throw new Error('Stale example snippet: node scripts/docs.mjs --write');

async function markdownFiles(directory) {
    const entries = await readdir(resolve(root, directory), { withFileTypes: true });
    const nested = await Promise.all(entries.filter(e => e.name !== 'superpowers').map(e => e.isDirectory()
        ? markdownFiles(`${directory}/${e.name}`)
        : e.name.endsWith('.md') ? [`${directory}/${e.name}`] : []));
    return nested.flat();
}
const files = ['README.md', 'UPGRADE.md', 'CONTRIBUTING.md', 'LARASTAN.md', 'SECURITY.md', ...await markdownFiles('docs')];
const slug = text => text.toLowerCase().replace(/<[^>]*>/g, '').replace(/[^\p{L}\p{N}\s_-]/gu, '').replace(/ /g, '-');
function anchors(text) {
    const result = new Set([...text.matchAll(/<a\s+(?:id|name)="([^"]+)"/g)].map(m => m[1]));
    const counts = new Map();
    for (const match of text.replace(/```[\s\S]*?```/g, '').matchAll(/^#{1,6}\s+(.+)$/gm)) {
        const base = slug(match[1]);
        const count = counts.get(base) ?? 0;
        result.add(count ? `${base}-${count}` : base);
        counts.set(base, count + 1);
    }
    return result;
}
const errors = [];
for (const file of files) {
    const body = (await read(file)).replace(/```[\s\S]*?```/g, '');
    for (const match of body.matchAll(/(?<!!)\[[^\]]*\]\(([^\s)]+)(?:\s+"[^"]*")?\)/g)) {
        const href = match[1];
        if (/^(https?:|mailto:|app:)/.test(href)) continue;
        const [path, fragment] = href.split('#');
        const target = path ? resolve(root, dirname(file), decodeURIComponent(path)) : resolve(root, file);
        try {
            await stat(target);
            if (fragment && target.endsWith('.md') && !anchors(await readFile(target, 'utf8')).has(decodeURIComponent(fragment))) {
                errors.push(`${file}: missing anchor ${href}`);
            }
        } catch { errors.push(`${file}: missing target ${href}`); }
    }
}
const provider = await read('src/CartServiceProvider.php');
for (const file of files) {
    for (const match of (await read(file)).matchAll(/--tag[= ]([a-z][a-z-]*)/g)) {
        if (!provider.includes(`'${match[1]}'`)) errors.push(`${file}: unknown publish tag ${match[1]}`);
    }
}
if (errors.length) throw new Error(errors.join('\n'));
console.log(`Documentation: ${files.length} Markdown files, internal links/anchors, publish tags and generated snippet checked.`);
