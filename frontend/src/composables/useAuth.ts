import { computed, reactive } from "vue";
import { ADMIN_TOKEN_STORAGE_KEY, apiRequest } from "../lib/api";

export type UserRole = "resident" | "admin";

export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  contactNumber?: string;
}

interface LoginResponse {
  message: string;
  token: string;
  user: User;
}

const USER_STORAGE_KEY = "pablottermo_admin_user";

const state = reactive<{ user: User | null }>({
  user: loadInitialUser(),
});

export function useAuth() {
  const login = async (email: string, password: string): Promise<User> => {
    const response = await apiRequest<LoginResponse>("/admin/login", {
      method: "POST",
      body: JSON.stringify({ email, password }),
    });

    localStorage.setItem(ADMIN_TOKEN_STORAGE_KEY, response.token);
    state.user = response.user;
    localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(response.user));
    return response.user;
  };

  const logout = async (): Promise<void> => {
    try {
      await apiRequest<{ message: string }>("/admin/logout", {
        method: "POST",
        body: JSON.stringify({}),
      });
    } catch {
      // Token may already be invalid; still clear local session.
    }
    state.user = null;
    localStorage.removeItem(USER_STORAGE_KEY);
    localStorage.removeItem(ADMIN_TOKEN_STORAGE_KEY);
  };

  const setUser = (nextUser: User | null) => {
    state.user = nextUser;
    if (nextUser) {
      localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(nextUser));
      return;
    }
    localStorage.removeItem(USER_STORAGE_KEY);
    localStorage.removeItem(ADMIN_TOKEN_STORAGE_KEY);
  };

  return {
    user: computed(() => state.user),
    login,
    logout,
    setUser,
  };
}

function loadInitialUser(): User | null {
  try {
    const storedUser = localStorage.getItem(USER_STORAGE_KEY);
    const token = localStorage.getItem(ADMIN_TOKEN_STORAGE_KEY);
    if (!storedUser || !token) {
      localStorage.removeItem(USER_STORAGE_KEY);
      localStorage.removeItem(ADMIN_TOKEN_STORAGE_KEY);
      return null;
    }
    return JSON.parse(storedUser) as User;
  } catch {
    localStorage.removeItem(USER_STORAGE_KEY);
    localStorage.removeItem(ADMIN_TOKEN_STORAGE_KEY);
    return null;
  }
}
