// Machine-readable release marker embedded in annotated tag messages and
// release bodies:  <!-- laravel-cart-release {"pr":44,"sha":"...",...} -->

import { isStableVersion } from './semver.mjs';

const SHA = /^[0-9a-f]{40}$/;

export function formatMarker(name, data) {
  const json = JSON.stringify({
    pr: data.pr,
    sha: data.sha,
    bump: data.bump,
    reason: data.reason,
    version: data.version,
    previous: data.previous ?? null,
  })
    // Keep the HTML comment unbreakable: no raw "<" or ">" inside it.
    .replace(/</g, '\\u003c')
    .replace(/>/g, '\\u003e');
  return `<!-- ${name} ${json} -->`;
}

/**
 * @returns {null | {ok: true, marker: object} | {ok: false, error: string}}
 */
export function parseMarker(name, text) {
  if (typeof text !== 'string') return null;
  const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const m = new RegExp(`<!-- ${escaped} (\\{[^>]*\\}) -->`).exec(text);
  if (!m) return text.includes(`<!-- ${name}`) ? { ok: false, error: 'malformed release marker' } : null;
  let data;
  try {
    data = JSON.parse(m[1]);
  } catch {
    return { ok: false, error: 'release marker is not valid JSON' };
  }
  if (!Number.isInteger(data.pr) || data.pr <= 0) return { ok: false, error: 'marker has invalid pr' };
  if (typeof data.sha !== 'string' || !SHA.test(data.sha)) return { ok: false, error: 'marker has invalid sha' };
  if (!isStableVersion(data.version)) return { ok: false, error: 'marker has invalid version' };
  return { ok: true, marker: data };
}
