import { createRouter, createWebHistory } from "vue-router";
import { ADMIN_TOKEN_STORAGE_KEY } from "../lib/api";
import HomePage from "../views/HomePage.vue";
import SubmitComplaint from "../views/SubmitComplaint.vue";
import LoginPage from "../views/LoginPage.vue";
import AdminDashboard from "../views/AdminDashboard.vue";
import ComplaintDetails from "../views/ComplaintDetails.vue";
import TrackComplaint from "../views/TrackComplaint.vue";
import NotFoundPage from "../views/NotFoundPage.vue";

const USER_STORAGE_KEY = "pablottermo_admin_user";

function readStoredAdminSession(): { userJson: string | null; token: string | null } {
  try {
    return {
      userJson: localStorage.getItem(USER_STORAGE_KEY),
      token: localStorage.getItem(ADMIN_TOKEN_STORAGE_KEY),
    };
  } catch {
    return { userJson: null, token: null };
  }
}

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/", component: HomePage },
    { path: "/submit-complaint", component: SubmitComplaint },
    { path: "/track-complaint", component: TrackComplaint },
    { path: "/admin/login", component: LoginPage },
    { path: "/admin", component: AdminDashboard },
    { path: "/admin/complaint/:id", component: ComplaintDetails },
    { path: "/:pathMatch(.*)*", component: NotFoundPage },
  ],
});

router.beforeEach((to) => {
  const { userJson, token } = readStoredAdminSession();
  const isLoginRoute = to.path === "/admin/login";
  const isProtectedAdminArea = to.path.startsWith("/admin") && !isLoginRoute;

  if (isProtectedAdminArea) {
    if (!userJson || !token) {
      return { path: "/admin/login", query: { redirect: to.fullPath } };
    }
    try {
      const parsed = JSON.parse(userJson) as { role?: string };
      if (parsed.role !== "admin") {
        return "/admin/login";
      }
    } catch {
      return "/admin/login";
    }
  }

  if (isLoginRoute && userJson && token) {
    try {
      const parsed = JSON.parse(userJson) as { role?: string };
      if (parsed.role === "admin") {
        return "/admin";
      }
    } catch {
      /* allow login page */
    }
  }

  return true;
});
