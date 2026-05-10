import { computed, reactive } from "vue";
import { apiRequest } from "../lib/api";

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

    state.user = response.user;
    localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(response.user));
    return response.user;
  };

  const logout = () => {
    state.user = null;
    localStorage.removeItem(USER_STORAGE_KEY);
  };

  return {
    user: computed(() => state.user),
    login,
    logout,
  };
}

function loadInitialUser(): User | null {
  try {
    const storedUser = localStorage.getItem(USER_STORAGE_KEY);
    return storedUser ? (JSON.parse(storedUser) as User) : null;
  } catch {
    return null;
  }
}
