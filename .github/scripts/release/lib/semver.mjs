// Minimal numeric SemVer helpers for stable tags (X.Y.Z only).

const STABLE = /^(\d+)\.(\d+)\.(\d+)$/;

export function parseVersion(value) {
  const m = STABLE.exec(String(value));
  return m ? [Number(m[1]), Number(m[2]), Number(m[3])] : null;
}

export function isStableVersion(value) {
  return parseVersion(value) !== null;
}

export function compareVersions(a, b) {
  const pa = parseVersion(a);
  const pb = parseVersion(b);
  if (!pa || !pb) throw new Error(`cannot compare non-stable versions ${a} / ${b}`);
  for (let i = 0; i < 3; i++) {
    if (pa[i] !== pb[i]) return pa[i] < pb[i] ? -1 : 1;
  }
  return 0;
}

export function bumpVersion(value, bump) {
  const p = parseVersion(value);
  if (!p) throw new Error(`invalid base version ${value}`);
  const [x, y, z] = p;
  switch (bump) {
    case 'major':
      return `${x + 1}.0.0`;
    case 'minor':
      return `${x}.${y + 1}.0`;
    case 'patch':
      return `${x}.${y}.${z + 1}`;
    default:
      throw new Error(`invalid bump ${bump}`);
  }
}
