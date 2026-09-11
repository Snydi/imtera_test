<script setup>
import {ref} from "vue";
import {useRouter} from "vue-router";
import {authError, login} from "../auth";

const router = useRouter();
const email = ref("");
const password = ref("");
const submitting = ref(false);
const error = ref("");

async function submit() {
    if (submitting.value) return;
    submitting.value = true;
    error.value = "";
    authError.value = "";
    try {
        await login(email.value, password.value);
        password.value = "";
        await router.replace({name: "organizations"});
    } catch (failure) {
        error.value = failure.message;
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <main class="login-page">
        <h1>Вход</h1>
        <form @submit.prevent="submit">
            <label for="email">Email</label>
            <input
                id="email"
                v-model.trim="email"
                type="email"
                autocomplete="username"
                required
                maxlength="255"
                :disabled="submitting"
            />
            <label for="password">Пароль</label>
            <input
                id="password"
                v-model="password"
                type="password"
                autocomplete="current-password"
                required
                maxlength="1024"
                :disabled="submitting"
            />
            <p v-if="error || authError" class="error" role="alert">
                {{ error || authError }}
            </p>
            <button :disabled="submitting">
                {{ submitting ? "Вход…" : "Войти" }}
            </button>
        </form>
    </main>
</template>

<style scoped>
.login-page {
    max-width: 360px;
}

input {
    box-sizing: border-box;
    width: 100%;
    margin-bottom: 20px;
}
</style>
