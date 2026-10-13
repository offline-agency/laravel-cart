// Temporary git repositories in os.tmpdir(), isolated from user/global config.

import { execFileSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const ENV = {
  ...process.env,
  GIT_CONFIG_GLOBAL: '/dev/null',
  GIT_CONFIG_NOSYSTEM: '1',
  LC_ALL: 'C',
  GIT_AUTHOR_NAME: 'Test',
  GIT_AUTHOR_EMAIL: 'test@example.com',
  GIT_COMMITTER_NAME: 'Test',
  GIT_COMMITTER_EMAIL: 'test@example.com',
};

export function createTmpRepo() {
  const dir = mkdtempSync(join(tmpdir(), 'release-test-'));
  let counter = 0;
  const git = (...args) => execFileSync('git', args, { cwd: dir, env: ENV, encoding: 'utf8' }).trim();
  git('init', '-q', '-b', 'main');
  git('config', 'commit.gpgsign', 'false');

  const repo = {
    dir,
    git,
    head: (ref = 'main') => git('rev-parse', ref),
    /** Squash/rebase-style merge: one new commit on main. */
    commit(message = `commit ${++counter}`, branch = 'main') {
      if (branch !== 'main') git('checkout', '-q', branch);
      git('commit', '-q', '--allow-empty', '-m', message);
      const sha = git('rev-parse', 'HEAD');
      if (branch !== 'main') git('checkout', '-q', 'main');
      return sha;
    },
    /** True merge commit (--no-ff) of a topic branch into main. */
    mergeCommit(message = `merge ${++counter}`) {
      const topic = `topic-${counter}`;
      git('checkout', '-q', '-b', topic, 'main');
      git('commit', '-q', '--allow-empty', '-m', `${message} (topic work)`);
      git('checkout', '-q', 'main');
      git('merge', '-q', '--no-ff', '-m', message, topic);
      return git('rev-parse', 'HEAD');
    },
    branch(name, from = 'main') {
      git('branch', name, from);
    },
    cleanup() {
      rmSync(dir, { recursive: true, force: true });
    },
  };
  repo.commit('initial');
  return repo;
}
