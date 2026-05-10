import { createRouter, createWebHistory } from "vue-router";
import HomePage from "../views/HomePage.vue";
import SubmitComplaint from "../views/SubmitComplaint.vue";
import LoginPage from "../views/LoginPage.vue";
import AdminDashboard from "../views/AdminDashboard.vue";
import ComplaintDetails from "../views/ComplaintDetails.vue";
import TrackComplaint from "../views/TrackComplaint.vue";

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/", component: HomePage },
    { path: "/submit-complaint", component: SubmitComplaint },
    { path: "/track-complaint", component: TrackComplaint },
    { path: "/admin/login", component: LoginPage },
    { path: "/admin", component: AdminDashboard },
    { path: "/admin/complaint/:id", component: ComplaintDetails },
  ],
});
