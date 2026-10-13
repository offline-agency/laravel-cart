// Thin GitHub REST adapter. The transport is injectable: production uses
// global fetch, tests use an in-memory fake that routes the same paths.

export class HttpError extends Error {
  constructor(method, path, status, data) {
    const detail = data && typeof data === 'object' && data.message ? `: ${data.message}` : '';
    super(`GitHub API ${method} ${path} -> HTTP ${status}${detail}`);
    this.status = status;
    this.data = data;
  }
}

export function fetchTransport({ token, baseUrl = 'https://api.github.com', fetchImpl = globalThis.fetch }) {
  if (!token) throw new Error('GITHUB_TOKEN (or GH_TOKEN) is required');
  return async (method, path, body) => {
    const res = await fetchImpl(baseUrl + path, {
      method,
      headers: {
        Accept: 'application/vnd.github+json',
        Authorization: `Bearer ${token}`,
        'X-GitHub-Api-Version': '2022-11-28',
        'User-Agent': 'laravel-cart-release',
        ...(body === undefined ? {} : { 'Content-Type': 'application/json' }),
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
    const text = await res.text();
    let data = null;
    if (text) {
      try {
        data = JSON.parse(text);
      } catch {
        data = text;
      }
    }
    return { status: res.status, data };
  };
}

/** Defence in depth for dry runs: any non-GET request throws before reaching the network. */
export function readOnlyTransport(transport) {
  return async (method, path, body) => {
    if (method !== 'GET') throw new Error(`dry run: refusing ${method} ${path}`);
    return transport(method, path, body);
  };
}

const enc = encodeURIComponent;

export function createGitHub({ transport, repository }) {
  if (!/^[\w.-]+\/[\w.-]+$/.test(repository ?? '')) throw new Error(`invalid repository "${repository}"`);
  const base = `/repos/${repository}`;

  async function call(method, path, body, { allow404 = false } = {}) {
    const { status, data } = await transport(method, path, body);
    if (allow404 && status === 404) return null;
    if (status < 200 || status >= 300) throw new HttpError(method, path, status, data);
    return data;
  }

  async function paginate(path, key) {
    const out = [];
    for (let page = 1; page <= 100; page++) {
      const sep = path.includes('?') ? '&' : '?';
      const data = await call('GET', `${path}${sep}per_page=100&page=${page}`);
      const items = (key ? data?.[key] : data) ?? [];
      out.push(...items);
      if (items.length < 100) break;
    }
    return out;
  }

  return {
    repository,
    getPull: (n) => call('GET', `${base}/pulls/${Number(n)}`, undefined, { allow404: true }),
    pullsForCommit: async (sha) => (await call('GET', `${base}/commits/${sha}/pulls?per_page=100`)) ?? [],
    recentClosedPulls: async (branch) =>
      (await call('GET', `${base}/pulls?state=closed&base=${enc(branch)}&sort=updated&direction=desc&per_page=100`)) ?? [],
    branchSha: async (branch) => (await call('GET', `${base}/branches/${enc(branch)}`)).commit.sha,
    listTagRefs: () => paginate(`${base}/git/matching-refs/tags`),
    getTagObject: (sha) => call('GET', `${base}/git/tags/${sha}`),
    createTagObject: ({ tag, message, sha }) =>
      call('POST', `${base}/git/tags`, { tag, message, object: sha, type: 'commit' }),
    createRef: (ref, sha) => call('POST', `${base}/git/refs`, { ref, sha }),
    listReleases: () => paginate(`${base}/releases`),
    createRelease: (payload) => call('POST', `${base}/releases`, payload),
    listWorkflowRuns: async (workflowFile, sha) => {
      const file = workflowFile.split('/').pop();
      const data = await call('GET', `${base}/actions/workflows/${enc(file)}/runs?head_sha=${sha}&event=push&per_page=100`);
      return data?.workflow_runs ?? [];
    },
    listCheckRuns: async (sha, name) => {
      const data = await call('GET', `${base}/commits/${sha}/check-runs?check_name=${enc(name)}&filter=all&per_page=100`);
      return data?.check_runs ?? [];
    },
  };
}
