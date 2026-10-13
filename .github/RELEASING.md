# Releasing laravel-cart

Every pull request merged into `main` after the v4.0.0 activation anchor gets
its own SemVer tag and GitHub Release, in `main` history order, after its CI has
passed. The publisher is `.github/workflows/release.yml`. The logic is plain
Node (no dependencies) in `.github/scripts/release/`, and the settings are in
`.github/release-config.json`.

## Policy

- **One PR, one release.** A PR that is merged into `main` (from this repo or
  from a fork) is released as one stable tag `X.Y.Z` (no `v` prefix) at its exact
  merge commit, plus one GitHub Release. Direct pushes and bot commits on `main`
  are not released.
- **Strict order.** Releases follow `main`'s first-parent history. The time an
  event arrives and `merged_at` play no part in the order. If a PR is not
  releasable yet, no PR after it is released either.
- **CI gate.** A PR is released only when the `CI passed` check run, created by
  the GitHub Actions app in the `CI` workflow's push run for that exact merge
  SHA, has concluded `success`.
- **Frozen allocations.** Tags are never moved, overwritten or deleted. The
  workflow never commits to `main`.
- **Current version** is the highest numeric `X.Y.Z` tag whose commit is an
  ancestor of the PR's merge commit. GitHub Releases play no part in this. For
  example, the 3.1.5 release does not override the 3.2.0 tag, which has no
  release.

## Choosing the bump: labels and titles

| Signal | Bump |
|---|---|
| `release:major` label, `type!:` / `type(scope)!:` title, or a `BREAKING CHANGE:` footer in the PR body | major |
| `release:minor` label or a `feat:` title | minor |
| `release:patch` label or a `fix` / `docs` / `chore` / `ci` / `build` / `refactor` / `perf` / `test` / `style` / `revert` / `deps` title | patch |
| none of the above | patch (fallback) |

The rules are applied in a fixed order:

1. Two or more `release:*` labels are a **conflict**.
2. A label (or a `bumpOverrides` entry in `release-config.json`) that disagrees
   with the bump implied by the title or footer is a **conflict**.
3. Otherwise the explicit label or override wins, then the title or footer,
   then the patch fallback.

The **Release metadata** check (`release-metadata.yml`, read-only
`pull_request`) fails on a conflict, so conflicts are caught before merge. If a
conflict still reaches `main` (for example, labels were edited after merge),
that PR and every PR after it are **blocked** until the labels are fixed. See
recovery below.

`release-config.json` has `bumpOverrides: {"44": major}`, because the title of
PR #44 ("v4.0.0: ...") is not a Conventional Commit and would otherwise fall
back to patch. Adding the `release:major` label to #44 as well is harmless:
both sources agree.

## Activation anchor

`anchor.pr` (44, the `develop` → `main` v4.0.0 PR) is the first managed PR.
The anchor is inclusive: its merge commit becomes 4.0.0, computed as major from
3.2.0. Until #44 is merged, every run is a no-op that says so. PRs merged before
the anchor are never touched.

Bootstrap: merging #44 is also what installs this workflow, so GitHub may not
fire the new `pull_request_target` workflow for that merge. That does not
matter. When CI finishes on the #44 merge commit, it fires `workflow_run`, and
that run releases 4.0.0.

## How runs are triggered

| Trigger | Purpose |
|---|---|
| `pull_request_target` (closed, merged, base `main`) | Fast path. Usually ends in "waiting" because post-merge CI is still running. |
| `workflow_run` of `CI` (completed, `push`, `main`, same repository, path `.github/workflows/ci.yml`) | Automatic resume once post-merge CI finishes. |
| `workflow_dispatch` (`pr`, `dry_run` default `true`) | Manual inspection and recovery. |

All three triggers run the same reconcile:

1. Read `main` HEAD and its first-parent history, from the anchor's merge commit onward.
2. Read stable tags and their release markers, and list existing releases.
3. Walk the commits from oldest to newest:
   - **Commit already tagged with a marker:**
     - If its release exists: `released`, nothing to do.
     - If its release is missing: `tag-only`. A live run creates the missing
       release for that same tag.
   - **PR merge commit without a tag:** re-query the PR and check that it is
     merged into `main` of this repository with this merge SHA. Then classify
     the bump, compute the version and check CI:
     - `pending` (CI still running or not started): stop and exit successfully.
       The next CI completion resumes the run.
     - `blocked` (failure, cancelled, skipped, timed_out, action_required, a
       missing check, or a conflict): stop and fail the job loudly.
     - Success: create the annotated tag object at the merge SHA (`git/tags` +
       `git/refs`), then the release.
4. With `pr` set (dispatch), the walk stops after that PR.

Each tag message and each release body contains a marker like this:

```
<!-- laravel-cart-release {"pr":44,"sha":"<merge sha>","bump":"major","reason":"...","version":"4.0.0","previous":"3.2.0"} -->
```

A PR counts as released when a tag carries the marker for its PR and SHA. This
is why reruns are no-ops and why dropped or reordered events are harmless.

**Concurrency:** `group: release` with `cancel-in-progress: false` means a
single publisher. GitHub keeps only **one pending run** per group, and a newer
pending run replaces the older one. Dropping a queued event is harmless because
the run that does go ahead reconciles the whole history.

The release job has `contents: write`, `pull-requests: read`, `checks: read`
and `actions: read`. The workflow default is `permissions: {}`. The job checks
out only the trusted default-branch commit (`github.sha`) with
`persist-credentials: false`. It never checks out PR code and never runs
Composer.

> Tags and releases created with `GITHUB_TOKEN` **do not trigger other
> workflows** (for example, `on: release` or `on: push: tags`). Packagist picks
> up new tags through its GitHub webhook, which is unaffected.

## Admin setup (one time)

1. **Labels:** create `release:major`, `release:minor` and `release:patch`:
   ```sh
   gh label create release:major --color B60205 --description "Release as a major version"
   gh label create release:minor --color 0E8A16 --description "Release as a minor version"
   gh label create release:patch --color C5DEF5 --description "Release as a patch version"
   ```
2. **Branch protection / ruleset on `main`:** require the status checks
   `CI passed` and `Release metadata`.
3. **Actions permissions** (Settings → Actions → General):
   - Workflow permissions may stay "Read repository contents", because the job
     requests `contents: write` itself.
   - Do not block GitHub Actions from creating tags.
   - If an org or repo policy restricts `GITHUB_TOKEN`, allow it to create
     releases.
4. **Tag protection rules / rulesets:** if they cover `*.*.*`, the GitHub
   Actions bot must be allowed to create tags. It never needs to update or
   delete them.
5. **Keep `ci.yml` stable:**
   - Workflow `name: CI`, at path `.github/workflows/ci.yml`.
   - It runs on `push` to `main` and has an aggregate `CI passed` job.
   - Do not rename any of these without updating `release-config.json` and
     `release.yml`.

## Inspecting and recovering

Always start with a dry run (the dispatch default):

```sh
gh workflow run release.yml -f dry_run=true            # everything pending
gh workflow run release.yml -f dry_run=true -f pr=57   # up to PR #57
```

You can also run it locally. This is read-only: dry run is the default, and the
CLI refuses any write request:

```sh
GITHUB_TOKEN=$(gh auth token) node .github/scripts/release/cli.mjs --dry-run --pr 44
```

For each PR, the dry run prints:

- the exact merge SHA
- the current version
- the bump and the reason for it
- the next tag
- the `CI passed` status
- the state: `released`, `tag-only`, `ready`, `pending`, `blocked`, `predicted`, `queued` or `ignored`

If the report looks right, run again with `-f dry_run=false`.

| Failure | What happens | Recovery |
|---|---|---|
| CI failed, cancelled, skipped or timed out on a merge commit | That PR and all later PRs are blocked, and the job fails. | Re-run the CI run. Its completion resumes the release automatically, or you can dispatch it. |
| CI can never pass for that commit (for example, the CI infrastructure was broken and the fix landed later) | Blocked. | Add `"ciWaivers": {"<sha>": "reason"}` to `release-config.json` through a PR, then dispatch. |
| Label/title conflict on a merged PR | Blocked. | Fix the labels on the merged PR, then dispatch. |
| Tag was created but the release API failed | State `tag-only`; the job fails. | Any later run (automatic or dispatch) creates the release for the **same** tag. |
| An existing tag's marker names another SHA or PR, or the computed version is already taken by another commit | Explicit conflict, no writes. | Investigate manually. Never move or delete published tags. |
| A missed event (Actions outage, a dropped queue entry) | Nothing is lost. | The next CI completion or a dispatch catches up in order. |
| CI never started for a merge commit | The run stays `pending`. | Re-run or trigger CI for that commit, then dispatch. |

## Tests

The tests need no packages. They use an in-memory fake GitHub API and
temporary git repositories:

```sh
node --test .github/scripts/release/test/*.test.mjs
```

## Known state at activation

Tag `3.2.0` (annotated, at `278f113`) has **no GitHub Release**, and the latest
release is 3.1.5. This is intentional: 3.2.0 is not backfilled. Version
computation reads tags, so 4.0.0 is still computed from 3.2.0. The first
automatic release, 4.0.0, becomes "latest".
