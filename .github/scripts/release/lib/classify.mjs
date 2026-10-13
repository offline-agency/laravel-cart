// Deterministic bump classification for a pull request.
//
// Explicit sources: `release:major|minor|patch` labels and the optional
// config override (bumpOverrides[pr]). Inferred source: the Conventional
// Commit title (`type(scope)!:`) plus a `BREAKING CHANGE:` footer in the body.
// Any disagreement between sources is a conflict. No signal => patch fallback.

export const BUMPS = ['patch', 'minor', 'major'];
const PATCH_TYPES = new Set([
  'fix', 'docs', 'chore', 'ci', 'build', 'refactor', 'perf', 'test', 'style', 'revert', 'deps',
]);
const LABEL = /^release:(major|minor|patch)$/;
const TITLE = /^([A-Za-z]+)(\([^()\r\n]*\))?(!)?:\s*\S/;
const BREAKING_FOOTER = /^BREAKING[ -]CHANGE:\s*\S/m;

export function releaseLabels(labels) {
  return (labels ?? [])
    .map((l) => (typeof l === 'string' ? l : l?.name))
    .filter((name) => typeof name === 'string' && LABEL.test(name));
}

export function inferFromTitle(title, body) {
  const m = TITLE.exec(String(title ?? ''));
  const footer = BREAKING_FOOTER.test(String(body ?? ''));
  if (m && m[3]) return { bump: 'major', reason: `conventional title "${m[1].toLowerCase()}${m[2] ?? ''}!"` };
  if (footer) return { bump: 'major', reason: 'BREAKING CHANGE footer in PR body' };
  if (!m) return null;
  const type = m[1].toLowerCase();
  if (type === 'feat') return { bump: 'minor', reason: 'conventional title type "feat"' };
  if (PATCH_TYPES.has(type)) return { bump: 'patch', reason: `conventional title type "${type}"` };
  return null;
}

/**
 * @returns {{ok: true, bump: string, reason: string, source: string} | {ok: false, conflict: string}}
 */
export function classify({ title, body, labels, override }) {
  const names = [...new Set(releaseLabels(labels))];
  if (names.length > 1) {
    return { ok: false, conflict: `multiple release labels: ${names.join(', ')}` };
  }
  const explicit = [];
  if (names.length === 1) {
    explicit.push({ bump: LABEL.exec(names[0])[1], reason: `label ${names[0]}`, source: 'label' });
  }
  if (override) {
    const bump = typeof override === 'string' ? override : override.bump;
    if (!BUMPS.includes(bump)) return { ok: false, conflict: `invalid config override bump "${bump}"` };
    const why = typeof override === 'object' && override.reason ? `: ${override.reason}` : '';
    explicit.push({ bump, reason: `config override${why}`, source: 'override' });
  }
  if (explicit.length === 2 && explicit[0].bump !== explicit[1].bump) {
    return { ok: false, conflict: `${explicit[0].reason} (${explicit[0].bump}) disagrees with ${explicit[1].reason} (${explicit[1].bump})` };
  }
  const inferred = inferFromTitle(title, body);
  if (explicit.length > 0) {
    const e = explicit[0];
    if (inferred && inferred.bump !== e.bump) {
      return { ok: false, conflict: `${e.reason} (${e.bump}) disagrees with ${inferred.reason} (${inferred.bump})` };
    }
    const reasons = explicit.map((x) => x.reason);
    if (inferred) reasons.push(inferred.reason);
    return { ok: true, bump: e.bump, reason: reasons.join('; '), source: e.source };
  }
  if (inferred) return { ok: true, bump: inferred.bump, reason: inferred.reason, source: 'title' };
  return { ok: true, bump: 'patch', reason: 'no release label or conventional title: patch fallback', source: 'fallback' };
}
