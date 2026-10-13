// Local git access for ancestry and first-parent history (read-only, except
// for an optional `git fetch` of the default branch inside GitHub Actions).

import { execFileSync, spawnSync } from 'node:child_process';

const SHA = /^[0-9a-f]{40}$/;

export function createGit(cwd) {
  const run = (args) => execFileSync('git', args, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
  const check = (sha) => {
    if (!SHA.test(sha ?? '')) throw new Error(`invalid commit sha "${sha}"`);
    return sha;
  };
  return {
    hasCommit(sha) {
      check(sha);
      return spawnSync('git', ['cat-file', '-e', `${sha}^{commit}`], { cwd, stdio: 'ignore' }).status === 0;
    },
    /** First-parent history of `head`, newest first. */
    firstParent(head) {
      const out = run(['rev-list', '--first-parent', check(head)]);
      return out ? out.split('\n') : [];
    },
    /** True when `ancestor` is reachable from (or equal to) `descendant`. Unknown commits => false. */
    isAncestor(ancestor, descendant) {
      check(ancestor);
      check(descendant);
      const r = spawnSync('git', ['merge-base', '--is-ancestor', ancestor, descendant], { cwd, stdio: 'ignore' });
      return r.status === 0;
    },
    fetchBranch(branch) {
      if (!/^[\w./-]+$/.test(branch)) throw new Error(`invalid branch ${branch}`);
      run(['fetch', '--quiet', '--no-tags', 'origin', `+refs/heads/${branch}:refs/remotes/origin/${branch}`]);
    },
  };
}
