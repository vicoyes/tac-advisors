export function withBase(path = '') {
  const baseUrl = import.meta.env.BASE_URL;
  const normalizedBase = baseUrl.endsWith('/') ? baseUrl : `${baseUrl}/`;
  const normalizedPath = path.replace(/^\/+/, '');

  return normalizedPath ? `${normalizedBase}${normalizedPath}` : normalizedBase;
}

export function normalizeRoute(path = '') {
  const trimmed = path.replace(/^\/+|\/+$/g, '');
  return trimmed ? `/${trimmed}` : '/';
}

export function stripBase(pathname = '') {
  const baseUrl = import.meta.env.BASE_URL.replace(/\/$/, '');

  if (baseUrl && pathname.startsWith(baseUrl)) {
    const stripped = pathname.slice(baseUrl.length);
    return stripped || '/';
  }

  return pathname || '/';
}
