const DEFAULT_API_BASE_URL = "http://127.0.0.1:8000/api";

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

export async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const isMultipart = options.body instanceof FormData;
  const requestPath = path.startsWith("/") ? path : `/${path}`;
  const endpoint = `${API_BASE_URL}${requestPath}`;
  let response: Response;

  try {
    // Validate URL early to surface config problems clearly.
    // eslint-disable-next-line no-new
    new URL(endpoint);
  } catch {
    throw new Error(`Invalid API URL. Check VITE_API_BASE_URL value: ${API_BASE_URL}`);
  }

  try {
    response = await fetch(endpoint, {
      headers: isMultipart
        ? (options.headers ?? {})
        : {
            "Content-Type": "application/json",
            ...(options.headers ?? {}),
          },
      ...options,
    });
  } catch {
    throw new Error(`Network request failed. Check API server at ${API_BASE_URL} and CORS settings.`);
  }

  if (!response.ok) {
    let errorMessage = "Request failed.";

    try {
      const payload = (await response.json()) as ApiErrorPayload;
      errorMessage = payload.message ?? errorMessage;
    } catch {
      // keep default message when response isn't JSON
    }

    throw new Error(errorMessage);
  }

  return (await response.json()) as T;
}
