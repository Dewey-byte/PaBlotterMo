export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? "http://127.0.0.1:8000/api";

interface ApiErrorPayload {
  message?: string;
  errors?: Record<string, string[]>;
}

export async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const isMultipart = options.body instanceof FormData;
  let response: Response;

  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
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
