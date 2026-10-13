#!/usr/bin/env node
// Release reconcile entry point.
//
//   In GitHub Actions:   node .github/scripts/release/cli.mjs --event
//   Locally (read-only): GITHUB_TOKEN=$(gh auth token) node .github/scripts/release/cli.mjs --dry-run [--pr 44]
//
// Local runs are always dry runs unless --execute is passed explicitly.

import { appendFileSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { parseArgs } from 'node:util';
import { createGitHub, fetchTransport, readOnlyTransport } from './lib/github.mjs';
import { createGit } from './lib/git.mjs';
import { interpretEvent } from './lib/events.mjs';
import { formatReport, reconcile } from './lib/reconcile.mjs';

const here = dirname(fileURLToPath(import.meta.url));
export const DEFAULT_CONFIG_PATH = resolve(here, '../../release-config.json');

export function loadConfig(path = DEFAULT_CONFIG_PATH) {
  return JSON.parse(readFileSync(path, 'utf8'));
}

const USAGE = `usage: cli.mjs [--event] [--dry-run | --execute] [--pr N] [--repo owner/name] [--config path]`;

export async function run({ argv = [], env = {}, cwd = process.cwd(), transport = null, config = null, print = console.log }) {
  let args;
  try {
    ({ values: args } = parseArgs({
      args: argv,
      options: {
        event: { type: 'boolean', default: false },
        'dry-run': { type: 'boolean', default: false },
        execute: { type: 'boolean', default: false },
        pr: { type: 'string' },
        repo: { type: 'string' },
        config: { type: 'string' },
        help: { type: 'boolean', default: false },
      },
      strict: true,
    }));
  } catch (e) {
    print(`error: ${e.message}\n${USAGE}`);
    return 2;
  }
  if (args.help) {
    print(USAGE);
    return 0;
  }
  const cfg = config ?? loadConfig(args.config ? resolve(cwd, args.config) : undefined);
  const inActions = env.GITHUB_ACTIONS === 'true';
  const annotate = (level, msg) => print(inActions ? `::${level}::${msg.replace(/[\r\n%]/g, ' ')}` : `${level}: ${msg}`);

  let request;
  let repository;
  if (args.event) {
    if (args['dry-run'] || args.execute || args.pr) {
      print('error: --event takes dry_run/pr from the event payload, not from flags');
      return 2;
    }
    repository = env.GITHUB_REPOSITORY;
    const payload = JSON.parse(readFileSync(env.GITHUB_EVENT_PATH, 'utf8'));
    request = interpretEvent({ eventName: env.GITHUB_EVENT_NAME, payload, repository, ref: env.GITHUB_REF, config: cfg });
  } else {
    if (args['dry-run'] && args.execute) {
      print('error: --dry-run and --execute are mutually exclusive');
      return 2;
    }
    if (args.pr !== undefined && !/^[1-9]\d*$/.test(args.pr)) {
      print(`error: --pr must be a PR number, got "${args.pr}"`);
      return 2;
    }
    repository = args.repo ?? env.GITHUB_REPOSITORY ?? cfg.repository;
    request = { action: 'reconcile', dryRun: !args.execute, targetPr: args.pr ? Number(args.pr) : null, trigger: 'command line' };
  }

  if (request.action === 'ignore') {
    print(`Event ignored: ${request.reason}`);
    return 0;
  }
  if (request.action === 'error') {
    annotate('error', request.reason);
    return 1;
  }

  let rawTransport = transport;
  if (!rawTransport) rawTransport = fetchTransport({ token: env.GITHUB_TOKEN || env.GH_TOKEN });
  const gh = createGitHub({ transport: request.dryRun ? readOnlyTransport(rawTransport) : rawTransport, repository });
  const git = createGit(cwd);

  print(`Trigger: ${request.trigger}; repository ${repository}; ${request.dryRun ? 'dry run' : 'live run'}${request.targetPr ? `; target PR #${request.targetPr}` : ''}`);
  let result;
  try {
    result = await reconcile({ gh, git, config: cfg, dryRun: request.dryRun, targetPr: request.targetPr, allowFetch: inActions });
  } catch (e) {
    annotate('error', `release reconcile failed: ${e.message}`);
    return 1;
  }
  const lines = formatReport(result);
  for (const line of lines) print(line);
  if (env.GITHUB_STEP_SUMMARY) {
    appendFileSync(env.GITHUB_STEP_SUMMARY, `### Release reconcile\n\n\`\`\`text\n${lines.join('\n').replace(/```/g, "'''")}\n\`\`\`\n`);
  }
  if (result.status === 'blocked') {
    const blocked = result.entries.find((e) => e.state === 'blocked');
    annotate('error', `release blocked at PR #${blocked?.pr}: ${blocked?.detail}`);
    return 1;
  }
  if (result.status === 'waiting') {
    annotate('notice', 'waiting for the required CI check; the CI workflow_run completion resumes the release automatically');
  }
  return 0;
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  process.exitCode = await run({ argv: process.argv.slice(2), env: process.env });
}
