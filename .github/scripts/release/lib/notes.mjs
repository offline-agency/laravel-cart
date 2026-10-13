// Release notes and tag message rendering. PR title/body are data only.

const UPGRADE_HEADING = /\b(breaking[ -]changes?|migration|migrating|upgrade|upgrading)\b/i;
const MAX_SECTION_CHARS = 30000;

export function extractUpgradeSections(body) {
  if (!body) return [];
  const lines = String(body).replace(/\r\n?/g, '\n').split('\n');
  const sections = [];
  let current = null;
  let inFence = false;
  for (const line of lines) {
    if (/^\s*(```|~~~)/.test(line)) inFence = !inFence;
    const h = inFence ? null : /^(#{1,6})\s+(.*?)\s*#*\s*$/.exec(line);
    if (h) {
      const level = h[1].length;
      if (current && level <= current.level) {
        sections.push(current);
        current = null;
      }
      if (!current && UPGRADE_HEADING.test(h[2])) {
        current = { level, heading: h[2], lines: [] };
        continue;
      }
    }
    if (current) current.lines.push(line);
  }
  if (current) sections.push(current);
  return sections
    .map((s) => {
      let text = s.lines.join('\n').trim();
      if (text.length > MAX_SECTION_CHARS) text = `${text.slice(0, MAX_SECTION_CHARS)}\n\n_(truncated, see the pull request)_`;
      return { heading: s.heading, text };
    })
    .filter((s) => s.text.length > 0);
}

function oneLine(value) {
  return String(value ?? '').replace(/[\r\n]+/g, ' ').trim();
}

export function buildTagMessage({ version, pr, sha, bump, reason, marker }) {
  return [
    `Release ${version}`,
    '',
    `PR #${pr.number}: ${oneLine(pr.title)}`,
    `Commit: ${sha}`,
    `Bump: ${bump} (${oneLine(reason)})`,
    '',
    marker,
    '',
  ].join('\n');
}

export function buildReleaseBody({ repository, version, previous, pr, sha, bump, reason, marker }) {
  const server = 'https://github.com';
  const parts = [
    `## ${version}`,
    '',
    `- Pull request: [#${pr.number} ${oneLine(pr.title)}](${pr.html_url ?? `${server}/${repository}/pull/${pr.number}`})`,
    `- Released commit: \`${sha}\``,
    `- Bump: **${bump}** (${oneLine(reason)})`,
  ];
  if (previous) {
    parts.push(`- Full changelog: [${previous}...${version}](${server}/${repository}/compare/${previous}...${version})`);
  }
  for (const section of extractUpgradeSections(pr.body)) {
    parts.push('', `### ${oneLine(section.heading)}`, '', section.text);
  }
  parts.push('', marker, '');
  return parts.join('\n');
}
