import {ref} from "vue";
import {api} from "./api";

export const user = ref(null);
export const authError = ref("");
let initialized = false;

export async function loadUser() {
    if (initialized) return;
    authError.value = "";
    try {
        const data = await api("/api/user");
        user.value = data.data;
        initialized = true;
    } catch (error) {
        user.value = null;
        if (error.status === 401) {
            initialized = true;
        } else {
            authError.value = error.message;
        }
    }
}

export async function login(email, password) {
    await api("/sanctum/csrf-cookie");
    const data = await api("/api/login", {
        method: "POST",
        body: JSON.stringify({email, password}),
    });
    user.value = data.data;
    initialized = true;
    authError.value = "";
}

export async function logout() {
    try {
        await api("/sanctum/csrf-cookie");
        await api("/api/logout", {method: "POST"});
    } catch (error) {
        if (error.status !== 401) throw error;
    }
    user.value = null;
}
