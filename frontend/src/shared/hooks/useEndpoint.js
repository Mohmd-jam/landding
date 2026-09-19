import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Data hook used by every page: runs a fetcher, tracks loading/error, ignores
 * responses from stale requests and exposes a manual `reload()`.
 */
export function useEndpoint(fetcher, deps = [], { skip = false, initial = null } = {}) {
  const [data, setData] = useState(initial);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(!skip && initial === null);
  const requestId = useRef(0);
  const fetcherRef = useRef(fetcher);

  fetcherRef.current = fetcher;

  const run = useCallback(async () => {
    const id = ++requestId.current;
    setLoading(true);
    setError(null);

    try {
      const result = await fetcherRef.current();

      if (id === requestId.current) {
        setData(result?.data ?? result ?? null);
        setLoading(false);
      }
    } catch (caught) {
      if (id === requestId.current) {
        setError(caught);
        setLoading(false);
      }
    }
  }, []);

  useEffect(() => {
    if (skip) {
      setLoading(false);
      return undefined;
    }

    run();

    return () => {
      requestId.current += 1;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);

  return { data, error, loading, reload: run, setData };
}
