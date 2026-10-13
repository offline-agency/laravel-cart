// Translate a GitHub Actions event into a reconcile request, or reject it.
// Every accepted event reconciles from history; the event only decides
// dry-run vs. write and an optional target PR (dispatch).

export function interpretEvent({ eventName, payload, repository, ref, config }) {
  const branch = config.defaultBranch;
  const ignore = (reason) => ({ action: 'ignore', reason });

  if (repository !== config.repository) {
    return ignore(`repository ${repository} is not ${config.repository} (fork?)`);
  }

  switch (eventName) {
    case 'pull_request_target': {
      const pr = payload?.pull_request;
      if (payload?.action !== 'closed' || !pr) return ignore(`pull_request_target action "${payload?.action}" is not "closed"`);
      if (pr.merged !== true) return ignore(`PR #${pr.number} was closed without merging`);
      if (pr.base?.ref !== branch) return ignore(`PR #${pr.number} targets ${pr.base?.ref}, not ${branch}`);
      if (pr.base?.repo?.full_name !== repository) return ignore(`PR #${pr.number} base repository is not ${repository}`);
      return { action: 'reconcile', dryRun: false, targetPr: null, trigger: `merge of PR #${pr.number}` };
    }
    case 'workflow_run': {
      const run = payload?.workflow_run;
      if (!run) return ignore('workflow_run payload missing');
      if (payload.action !== 'completed') return ignore(`workflow_run action "${payload.action}" is not "completed"`);
      const path = String(run.path ?? '').split('@')[0];
      if (path !== config.ci.workflowFile) return ignore(`workflow_run from ${path || 'unknown workflow'}, not ${config.ci.workflowFile}`);
      if (run.event !== 'push') return ignore(`workflow_run event is "${run.event}", not "push"`);
      if (run.head_branch !== branch) return ignore(`workflow_run head branch is "${run.head_branch}", not ${branch}`);
      if (run.head_repository?.full_name !== repository) {
        return ignore(`workflow_run head repository is ${run.head_repository?.full_name}, not ${repository}`);
      }
      if (run.repository?.full_name && run.repository.full_name !== repository) {
        return ignore(`workflow_run repository is ${run.repository.full_name}`);
      }
      return { action: 'reconcile', dryRun: false, targetPr: null, trigger: `CI run ${run.id} on ${run.head_sha}` };
    }
    case 'workflow_dispatch': {
      if (ref !== `refs/heads/${branch}`) return ignore(`workflow_dispatch must run from ${branch}, got ${ref}`);
      const inputs = payload?.inputs ?? {};
      const rawPr = String(inputs.pr ?? '').trim();
      if (rawPr !== '' && !/^[1-9]\d*$/.test(rawPr)) {
        return { action: 'error', reason: `input "pr" must be a PR number, got "${rawPr}"` };
      }
      const dry = inputs.dry_run;
      const dryRun = !(dry === false || dry === 'false');
      return {
        action: 'reconcile',
        dryRun,
        targetPr: rawPr === '' ? null : Number(rawPr),
        trigger: `manual dispatch${rawPr ? ` for PR #${rawPr}` : ''}`,
      };
    }
    default:
      return ignore(`unsupported event ${eventName}`);
  }
}
