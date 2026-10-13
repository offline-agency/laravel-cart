// Required-check evaluation: the `CI passed` check run created by the GitHub
// Actions app, in the check suite of the CI workflow's *push* run for the exact SHA.

const FAILED = new Set(['failure', 'cancelled', 'skipped', 'timed_out', 'action_required', 'neutral', 'stale', 'startup_failure']);

const latest = (items) => items.reduce((a, b) => (a === null || b.id > a.id ? b : a), null);
const workflowPath = (path) => String(path ?? '').split('@')[0];

/**
 * @returns {Promise<{state: 'success'|'pending'|'absent'|'failure', conclusion?: string, detail: string}>}
 */
export async function ciStatus({ gh, config, sha }) {
  const { workflowFile, checkName, appSlug } = config.ci;
  const waiver = config.ciWaivers?.[sha];
  if (waiver) return { state: 'success', detail: `waived in release-config.json: ${waiver}` };

  const runs = (await gh.listWorkflowRuns(workflowFile, sha)).filter(
    (r) =>
      r.head_sha === sha &&
      r.event === 'push' &&
      r.head_branch === config.defaultBranch &&
      workflowPath(r.path) === workflowFile &&
      (r.head_repository?.full_name ?? gh.repository) === gh.repository,
  );
  const run = latest(runs);
  if (!run) return { state: 'absent', detail: `no "${config.ci.workflowName}" push run for this commit yet` };
  if (run.status !== 'completed') {
    return { state: 'pending', detail: `CI run ${run.id} (attempt ${run.run_attempt ?? 1}) is ${run.status}` };
  }

  const checks = (await gh.listCheckRuns(sha, checkName)).filter(
    (c) => c.name === checkName && c.head_sha === sha && c.app?.slug === appSlug && c.check_suite?.id === run.check_suite_id,
  );
  const check = latest(checks);
  if (!check) {
    return {
      state: 'failure',
      conclusion: 'missing',
      detail: `CI run ${run.id} completed (${run.conclusion}) without a "${checkName}" check run`,
    };
  }
  if (check.status !== 'completed') return { state: 'pending', detail: `"${checkName}" is ${check.status}` };
  if (check.conclusion === 'success') return { state: 'success', detail: `"${checkName}" succeeded (run ${run.id})` };
  return {
    state: 'failure',
    conclusion: check.conclusion,
    detail: `"${checkName}" concluded ${check.conclusion} (run ${run.id})${FAILED.has(check.conclusion) ? '' : ' (unexpected conclusion)'}`,
  };
}
