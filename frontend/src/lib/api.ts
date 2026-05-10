const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? "http://127.0.0.1:8000/api";

interface ApiErrorPayload {
  message?: string;
  errors?: Record<string, string[]>;
}

export async function apiRequest<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    headers: {
      "Content-Type": "application/json",
      ...(options.headers ?? {}),
    },
    ...options,
  });

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
