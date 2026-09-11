import { createRouter, createWebHistory } from "vue-router";
import { loadUser, user } from "./auth";
import LoginPage from "./pages/LoginPage.vue";
import OrganizationsPage from "./pages/OrganizationsPage.vue";

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/", redirect: { name: "organizations" } },
    { path: "/login", name: "login", component: LoginPage },
    {
      path: "/organizations",
      name: "organizations",
      component: OrganizationsPage,
      meta: { requiresAuth: true },
    },
    { path: "/:pathMatch(.*)*", redirect: { name: "organizations" } },
  ],
});

router.beforeEach(async (to) => {
  await loadUser();
  if (to.meta.requiresAuth && !user.value) return { name: "login" };
  if (to.name === "login" && user.value) return { name: "organizations" };
});

export default router;
