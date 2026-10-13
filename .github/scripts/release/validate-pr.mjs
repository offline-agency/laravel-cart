#!/usr/bin/env node
// Pre-merge release metadata check (pull_request event, read-only).
// Fails when the PR's release labels / title / config override conflict, so
// conflicts are rejected before merge instead of blocking the release queue.

import { readFileSync } from 'node:fs';
import { pathToFileURL, fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { classify } from './lib/classify.mjs';

export function validatePullRequest(pr, config) {
  const result = classify({
    title: pr.title,
    body: pr.body,
    labels: pr.labels,
    override: config.bumpOverrides?.[String(pr.number)],
  });
  if (!result.ok) return { ok: false, message: `Release metadata conflict for PR #${pr.number}: ${result.conflict}` };
  return { ok: true, message: `PR #${pr.number} will be released as ${result.bump} (${result.reason}).` };
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  const here = dirname(fileURLToPath(import.meta.url));
  const config = JSON.parse(readFileSync(resolve(here, '../../release-config.json'), 'utf8'));
  const payload = JSON.parse(readFileSync(process.env.GITHUB_EVENT_PATH, 'utf8'));
  const { ok, message } = validatePullRequest(payload.pull_request, config);
  const safe = message.replace(/[\r\n%]/g, ' ');
  console.log(ok ? `::notice::${safe}` : `::error::${safe}`);
  process.exitCode = ok ? 0 : 1;
}
