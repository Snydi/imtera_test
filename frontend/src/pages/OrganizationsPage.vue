<script setup>
import {onMounted, ref} from "vue";
import {useRouter} from "vue-router";
import {api} from "../api";
import {logout, user} from "../auth";

const router = useRouter();
const signingOut = ref(false);
const logoutError = ref("");

async function signOut() {
    if (signingOut.value) return;
    signingOut.value = true;
    logoutError.value = "";
    try {
        await logout();
        await router.replace({name: "login"});
    } catch (error) {
        logoutError.value = error.message;
    } finally {
        signingOut.value = false;
    }
}

function handleUnauthorized(error) {
    if (error.status === 401) {
        user.value = null;
        router.replace({name: "login"});
    }
}

const url = ref("");
const organizations = ref([]);
const loading = ref(true);
const saving = ref(false);
const loadError = ref("");
const saveError = ref("");

async function loadOrganizations() {
    loading.value = true;
    loadError.value = "";
    try {
        const data = await api("/api/organizations");
        organizations.value = data.data;
    } catch (error) {
        handleUnauthorized(error);
        loadError.value = error.message;
    } finally {
        loading.value = false;
    }
}

async function addOrganization() {
    if (saving.value) return;
    saving.value = true;
    saveError.value = "";
    try {
        const data = await api("/api/organizations", {
            method: "POST",
            body: JSON.stringify({url: url.value}),
        });
        organizations.value.unshift(data.data);
        url.value = "";
    } catch (error) {
        handleUnauthorized(error);
        saveError.value = error.message;
    } finally {
        saving.value = false;
    }
}

onMounted(loadOrganizations);
</script>

<template>
    <main>
        <h1>Организации</h1>
        <div class="account">
            <span>{{ user?.email }}</span>
            <button type="button" :disabled="signingOut || saving" @click="signOut">
                {{ signingOut ? "Выход…" : "Выйти" }}
            </button>
        </div>
        <p v-if="logoutError" class="error" role="alert">{{ logoutError }}</p>
        <form @submit.prevent="addOrganization">
            <label for="organization-url"
            >Ссылка на организацию в Яндекс.Картах</label
            >
            <div class="form-row">
                <input
                    id="organization-url"
                    v-model.trim="url"
                    type="url"
                    required
                    maxlength="2048"
                    placeholder="https://yandex.ru/maps/org/..."
                    :disabled="saving || signingOut || loading || !!loadError"
                    :aria-invalid="!!saveError"
                    :aria-describedby="saveError ? 'save-error' : undefined"
                />
                <button :disabled="saving || signingOut || loading || !!loadError">
                    {{ saving ? "Сохранение…" : "Добавить" }}
                </button>
            </div>
            <p v-if="saveError" id="save-error" class="error" role="alert">
                {{ saveError }}
            </p>
        </form>

        <p v-if="loading" role="status">Загрузка организаций…</p>
        <div v-else-if="loadError">
            <p class="error" role="alert">{{ loadError }}</p>
            <button type="button" @click="loadOrganizations">Повторить</button>
        </div>
        <ul v-else-if="organizations.length" aria-label="Сохранённые организации">
            <li v-for="organization in organizations" :key="organization.id">
                <a :href="organization.url" target="_blank" rel="noopener noreferrer">
                    {{ organization.url }}
                </a>
            </li>
        </ul>
        <p v-else>Пока нет организаций. Добавьте первую ссылку.</p>
    </main>
</template>

<style scoped>
.account {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 24px;
}
</style>
