const DEFAULT_API_BASE_URL = "http://127.0.0.1:8000/api";

export const ADMIN_TOKEN_STORAGE_KEY = "pablottermo_admin_token";

function normalizeApiBaseUrl(value?: string): string {
  const trimmed = (value ?? DEFAULT_API_BASE_URL).trim();
  const withoutWrappingQuotes = trimmed.replace(/^['"]|['"]$/g, "");
  const withProtocol = /^https?:\/\//i.test(withoutWrappingQuotes)
    ? withoutWrappingQuotes
    : `https://${withoutWrappingQuotes}`;

  return withProtocol.replace(/\/+$/, "");
}

export const API_BASE_URL = normalizeApiBaseUrl(import.meta.env.VITE_API_BASE_URL);

interface ApiErrorPayload {
  message?: string;
  errors?: Record<string, string[]>;
}

export function adminAuthHeaders(): Record<string, string> {
  try {
    const token = localStorage.getItem(ADMIN_TOKEN_STORAGE_KEY);
    return token ? { Authorization: `Bearer ${token}` } : {};
  } catch {
    return {};
  }
}

function formatApiErrorPayload(payload: ApiErrorPayload, fallback: string): string {
  if (payload.errors && Object.keys(payload.errors).length > 0) {
    const lines = Object.entries(payload.errors).flatMap(([field, messages]) =>
      messages.map((message) => `${field}: ${message}`),
    );
    return [payload.message, ...lines].filter(Boolean).join("\n");
  }

  return payload.message ?? fallback;
}

export async function fetchAuthenticatedObjectUrl(resourceUrl: string): Promise<string> {
  const response = await fetch(resourceUrl, { headers: adminAuthHeaders() });

  if (!response.ok) {
    let message = `Failed to load file (${response.status}).`;
    try {
      const payload = (await response.json()) as ApiErrorPayload;
      message = formatApiErrorPayload(payload, message);
    } catch {
      // ignore non-JSON error bodies
    }
    throw new Error(message);
  }

  const blob = await response.blob();
  return URL.createObjectURL(blob);
}

export async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const isMultipart = options.body instanceof FormData;
  const requestPath = path.startsWith("/") ? path : `/${path}`;
  const endpoint = `${API_BASE_URL}${requestPath}`;
  let response: Response;

  try {
    // eslint-disable-next-line no-new
    new URL(endpoint);
  } catch {
    throw new Error(`Invalid API URL. Check VITE_API_BASE_URL value: ${API_BASE_URL}`);
  }

  const auth = adminAuthHeaders();
  const mergedHeaders: HeadersInit = isMultipart
    ? { ...auth, ...(options.headers ?? {}) }
    : {
        "Content-Type": "application/json",
        ...auth,
        ...(options.headers ?? {}),
      };

  try {
    response = await fetch(endpoint, {
      ...options,
      headers: mergedHeaders,
    });
  } catch {
    throw new Error(`Network request failed. Check API server at ${API_BASE_URL} and CORS settings.`);
  }

  if (!response.ok) {
    let message = "Request failed.";
    try {
      const payload = (await response.json()) as ApiErrorPayload;
      message = formatApiErrorPayload(payload, message);
    } catch {
      // response body was not JSON
    }
    throw new Error(message);
  }

  return (await response.json()) as T;
}
