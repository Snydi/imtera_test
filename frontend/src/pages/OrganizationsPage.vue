<script setup>
import {onMounted, onUnmounted, ref} from "vue";
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
const name = ref("");
const organizations = ref([]);
const loading = ref(true);
const saving = ref(false);
const loadError = ref("");
const saveError = ref("");
const deletingId = ref(null);
const deleteError = ref("");
const refreshingId = ref(null);
const refreshErrors = ref({});
const selectedOrganization = ref(null);
const reviews = ref([]);
const reviewsPage = ref(1);
const reviewsLastPage = ref(1);
const reviewsTotal = ref(0);
const reviewsLoading = ref(false);
const reviewsError = ref("");
let pollingTimer = null;

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

async function syncOrganizations() {
    if (!organizations.value.some(isParsing)) return;

    try {
        const data = await api("/api/organizations");
        organizations.value = data.data;
        const selected = organizations.value.find(
            (item) => item.id === selectedOrganization.value?.id,
        );
        if (selected) selectedOrganization.value = selected;
    } catch (error) {
        handleUnauthorized(error);
    }
}

async function addOrganization() {
    if (saving.value) return;
    saving.value = true;
    saveError.value = "";
    try {
        const data = await api("/api/organizations", {
            method: "POST",
            body: JSON.stringify({name: name.value, url: url.value}),
        });
        organizations.value.unshift(data.data);
        url.value = "";
        name.value = "";
    } catch (error) {
        handleUnauthorized(error);
        saveError.value = error.message;
    } finally {
        saving.value = false;
    }
}

function formatRating(rating) {
    if (rating === null || rating === undefined) return "Нет рейтинга";

    return Number(rating).toLocaleString("ru-RU", {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    });
}

function isParsing(organization) {
    return ["queued", "running"].includes(organization.parsing_status);
}

function formatCount(count) {
    return count === null || count === undefined
        ? "—"
        : Number(count).toLocaleString("ru-RU");
}

function formatDate(value) {
    if (!value) return "Дата не указана";
    return new Intl.DateTimeFormat("ru-RU", {dateStyle: "long"}).format(
        new Date(value),
    );
}

async function refreshData(organization) {
    if (
        refreshingId.value !== null ||
        deletingId.value !== null ||
        saving.value ||
        signingOut.value
    )
        return;

    refreshingId.value = organization.id;
    refreshErrors.value = {...refreshErrors.value, [organization.id]: ""};

    try {
        const data = await api(`/api/organizations/${organization.id}/refresh`, {
            method: "POST",
        });
        organizations.value = organizations.value.map((item) =>
            item.id === organization.id ? data.data : item,
        );
    } catch (error) {
        handleUnauthorized(error);
        refreshErrors.value = {
            ...refreshErrors.value,
            [organization.id]: error.message,
        };
    } finally {
        refreshingId.value = null;
    }
}

async function showReviews(organization, page = 1) {
    selectedOrganization.value = organization;
    reviewsLoading.value = true;
    reviewsError.value = "";

    try {
        const data = await api(
            `/api/organizations/${organization.id}/reviews?page=${page}`,
        );
        reviews.value = data.data;
        reviewsPage.value = data.current_page;
        reviewsLastPage.value = data.last_page;
        reviewsTotal.value = data.total;
    } catch (error) {
        handleUnauthorized(error);
        reviewsError.value = error.message;
    } finally {
        reviewsLoading.value = false;
    }
}

async function deleteOrganization(organization) {
    if (
        deletingId.value !== null ||
        refreshingId.value !== null ||
        saving.value ||
        signingOut.value
    )
        return;
    if (
        !window.confirm(
            `Удалить «${organization.name || organization.url}» из списка?`,
        )
    )
        return;
    deletingId.value = organization.id;
    deleteError.value = "";
    try {
        await api(`/api/organizations/${organization.id}`, {method: "DELETE"});
        organizations.value = organizations.value.filter(
            (item) => item.id !== organization.id,
        );
        const remainingErrors = {...refreshErrors.value};
        delete remainingErrors[organization.id];
        refreshErrors.value = remainingErrors;
        if (selectedOrganization.value?.id === organization.id) {
            selectedOrganization.value = null;
            reviews.value = [];
        }
    } catch (error) {
        handleUnauthorized(error);
        deleteError.value = error.message;
    } finally {
        deletingId.value = null;
    }
}

onMounted(async () => {
    await loadOrganizations();
    pollingTimer = window.setInterval(syncOrganizations, 2000);
});

onUnmounted(() => window.clearInterval(pollingTimer));
</script>

<template>
    <main class="organizations-page">
        <header class="page-header">
            <div>
                <h1>Организации</h1>
            </div>
            <div class="account">
                <span class="account-email">{{ user?.email }}</span>
                <button
                    class="button-secondary"
                    type="button"
                    :disabled="
            signingOut || saving || deletingId !== null || refreshingId !== null
          "
                    @click="signOut"
                >
                    {{ signingOut ? "Выход…" : "Выйти" }}
                </button>
            </div>
        </header>
        <p v-if="logoutError" class="error" role="alert">{{ logoutError }}</p>

        <form class="organization-form" @submit.prevent="addOrganization">
            <div class="form-heading">
                <h2>Новая организация</h2>
            </div>
            <div class="field">
                <label for="organization-name">Название компании</label>
                <input
                    id="organization-name"
                    v-model.trim="name"
                    type="text"
                    required
                    maxlength="255"
                    :disabled="
            saving ||
            signingOut ||
            loading ||
            !!loadError ||
            deletingId !== null ||
            refreshingId !== null
          "
                    :aria-invalid="!!saveError"
                    :aria-describedby="saveError ? 'save-error' : undefined"
                />
            </div>
            <div class="field">
                <label for="organization-url"
                >Ссылка на организацию в Яндекс Картах</label
                >
                <div class="form-row">
                    <input
                        id="organization-url"
                        v-model.trim="url"
                        type="url"
                        required
                        maxlength="2048"
                        :disabled="
              saving ||
              signingOut ||
              loading ||
              !!loadError ||
              deletingId !== null ||
              refreshingId !== null
            "
                        :aria-invalid="!!saveError"
                        :aria-describedby="saveError ? 'save-error' : undefined"
                    />
                    <button
                        :disabled="
              saving ||
              signingOut ||
              loading ||
              !!loadError ||
              deletingId !== null ||
              refreshingId !== null
            "
                    >
                        {{ saving ? "Сохранение…" : "Добавить" }}
                    </button>
                </div>
            </div>
            <p v-if="saveError" id="save-error" class="error" role="alert">
                {{ saveError }}
            </p>
        </form>

        <section
            class="organizations-section"
            aria-labelledby="organizations-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="organizations-title">Сохранённые компании</h2>
                </div>
                <span v-if="!loading && !loadError" class="counter">
          {{ organizations.length }}
        </span>
            </div>

            <p v-if="deleteError" class="error" role="alert">{{ deleteError }}</p>
            <div v-if="loading" class="state-message" role="status">
                <span class="loader" aria-hidden="true"></span>
                Загрузка организаций…
            </div>
            <div v-else-if="loadError" class="state-message">
                <p class="error" role="alert">{{ loadError }}</p>
                <button type="button" @click="loadOrganizations">Повторить</button>
            </div>
            <div v-else-if="organizations.length" class="organizations-table-wrap">
                <table aria-label="Сохранённые организации">
                    <thead>
                    <tr>
                        <th scope="col">Организация</th>
                        <th scope="col">Рейтинг</th>
                        <th scope="col">Оценки</th>
                        <th scope="col">Отзывы</th>
                        <th class="actions-heading" scope="col">Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="organization in organizations" :key="organization.id">
                        <td data-label="Организация">
                            <div class="organization-details">
                                <a
                                    class="organization-link"
                                    :href="organization.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {{ organization.name || "Без названия" }}
                                    <span aria-hidden="true">↗</span>
                                </a>
                                <span
                                    v-if="refreshErrors[organization.id]"
                                    class="row-error"
                                    role="alert"
                                >
                    {{ refreshErrors[organization.id] }}
                  </span>
                                <span v-if="isParsing(organization)" class="parse-status">
                    {{
                                        organization.parsing_status === "queued"
                                            ? "Ожидает обработки"
                                            : `Обновление: ${organization.parsing_progress}%`
                                    }}
                  </span>
                                <span
                                    v-else-if="organization.parsing_status === 'failed'"
                                    class="row-error"
                                    role="alert"
                                >
                    {{ organization.parsing_error }}
                  </span>
                            </div>
                        </td>
                        <td data-label="Рейтинг">
                <span
                    class="rating"
                    :class="{ 'rating-empty': organization.rating == null }"
                >
                  <span v-if="organization.rating != null" aria-hidden="true"
                  >★</span
                  >
                  {{ formatRating(organization.rating) }}
                </span>
                        </td>
                        <td data-label="Оценки">
                            {{ formatCount(organization.rating_count) }}
                        </td>
                        <td data-label="Отзывы">
                            {{ formatCount(organization.review_count) }}
                        </td>
                        <td data-label="Действия">
                            <div class="organization-actions">
                                <button
                                    class="reviews-button"
                                    type="button"
                                    :disabled="
                      organization.review_count === null ||
                      isParsing(organization)
                    "
                                    @click="showReviews(organization)"
                                >
                                    Отзывы
                                </button>
                                <button
                                    class="refresh-button"
                                    type="button"
                                    :disabled="
                      refreshingId !== null ||
                      deletingId !== null ||
                      saving ||
                      signingOut
                    "
                                    :aria-label="`Обновить данные ${organization.name || organization.url}`"
                                    title="Обновить рейтинг, счётчики и отзывы"
                                    @click="refreshData(organization)"
                                >
                                    <svg
                                        :class="{ spinning: refreshingId === organization.id }"
                                        aria-hidden="true"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            d="M20 11a8 8 0 1 0-2.34 5.66M20 4v7h-7"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                        />
                                    </svg>
                                </button>
                                <button
                                    class="delete-button"
                                    type="button"
                                    :disabled="
                      deletingId !== null ||
                      refreshingId !== null ||
                      saving ||
                      signingOut
                    "
                                    :aria-label="`Удалить ${organization.name || organization.url}`"
                                    @click="deleteOrganization(organization)"
                                >
                                    {{
                                        deletingId === organization.id ? "Удаление…" : "Удалить"
                                    }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="empty-state">
                <div class="empty-state-icon" aria-hidden="true">＋</div>
                <strong>Здесь пока пусто</strong>
                <span>Добавьте первую организацию с помощью формы выше.</span>
            </div>
        </section>

        <section
            v-if="selectedOrganization"
            class="reviews-section"
            aria-labelledby="reviews-title"
        >
            <div class="section-heading">
                <div>
                    <h2 id="reviews-title">Отзывы: {{ selectedOrganization.name }}</h2>
                    <span class="reviews-summary">
            Сохранено {{ formatCount(reviewsTotal) }}
          </span>
                </div>
                <button
                    class="button-secondary"
                    type="button"
                    @click="selectedOrganization = null"
                >
                    Закрыть
                </button>
            </div>

            <div v-if="reviewsLoading" class="state-message" role="status">
                <span class="loader" aria-hidden="true"></span>
                Загрузка отзывов…
            </div>
            <div v-else-if="reviewsError" class="state-message">
                <p class="error" role="alert">{{ reviewsError }}</p>
                <button
                    type="button"
                    @click="showReviews(selectedOrganization, reviewsPage)"
                >
                    Повторить
                </button>
            </div>
            <div v-else-if="reviews.length" class="reviews-list">
                <article v-for="review in reviews" :key="review.id" class="review-card">
                    <header class="review-header">
                        <strong>{{ review.author }}</strong>
                        <span
                            class="review-rating"
                            :aria-label="`Оценка ${review.rating} из 5`"
                        >
              {{
                                "★".repeat(review.rating)
                            }}<span aria-hidden="true">{{
                                "★".repeat(5 - review.rating)
                            }}</span>
            </span>
                    </header>
                    <time :datetime="review.reviewed_at">{{
                            formatDate(review.reviewed_at)
                        }}
                    </time>
                    <p>{{ review.text || "Пользователь оставил оценку без текста." }}</p>
                </article>

                <nav
                    v-if="reviewsLastPage > 1"
                    class="pagination"
                    aria-label="Страницы отзывов"
                >
                    <button
                        type="button"
                        :disabled="reviewsPage === 1 || reviewsLoading"
                        @click="showReviews(selectedOrganization, reviewsPage - 1)"
                    >
                        Назад
                    </button>
                    <span>Страница {{ reviewsPage }} из {{ reviewsLastPage }}</span>
                    <button
                        type="button"
                        :disabled="reviewsPage === reviewsLastPage || reviewsLoading"
                        @click="showReviews(selectedOrganization, reviewsPage + 1)"
                    >
                        Далее
                    </button>
                </nav>
            </div>
            <div v-else class="empty-state">
                <strong>Отзывов пока нет</strong>
                <span>В карточке организации нет отзывов с текстом или оценкой.</span>
            </div>
        </section>
    </main>
</template>

<style scoped>
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    padding-bottom: 30px;
    border-bottom: 1px solid var(--border);
}

.account {
    display: flex;
    align-items: center;
    gap: 12px;
}

.account-email {
    max-width: 220px;
    overflow: hidden;
    color: var(--muted);
    font-size: 14px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.button-secondary {
    color: #44506a;
    background: #f0f3fa;
    box-shadow: none;
}

.button-secondary:hover:not(:disabled) {
    color: var(--primary-dark);
    background: var(--primary-soft);
    box-shadow: none;
}

.organization-form {
    margin-top: 30px;
    padding: 24px;
    background: #faf9f7;
    border: 1px solid var(--border);
    border-radius: 10px;
}

.form-heading,
.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

h2 {
    margin: 0;
    font-size: 20px;
    letter-spacing: -0.02em;
}

.field {
    margin-top: 20px;
}

#organization-name {
    width: 100%;
}

.organizations-section {
    margin-top: 34px;
}

.counter {
    display: grid;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    place-items: center;
    color: var(--primary-dark);
    font-weight: 750;
    background: var(--primary-soft);
    border-radius: 8px;
}

.organizations-table-wrap {
    margin-top: 18px;
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 15px 16px;
    text-align: left;
    vertical-align: middle;
    border-bottom: 1px solid var(--border);
}

th {
    color: var(--muted);
    font-size: 12px;
    font-weight: 750;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    background: #faf9f7;
}

tbody tr:last-child td {
    border-bottom: 0;
}

tbody tr {
    transition: background 160ms ease;
}

tbody tr:hover {
    background: #fffcfa;
}

.actions-heading {
    text-align: right;
}

.organization-details {
    display: grid;
    gap: 5px;
    min-width: 0;
}

.organization-link {
    width: fit-content;
    color: #202941;
    font-weight: 750;
    line-height: 1.3;
    text-decoration: none;
}

.organization-link:hover {
    color: var(--primary);
}

.organization-link span {
    margin-left: 3px;
    color: var(--primary);
    font-size: 14px;
}

.organization-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.organization-actions button {
    padding: 9px 12px;
    font-size: 13px;
    box-shadow: none;
}

.reviews-button,
.refresh-button {
    color: var(--primary-dark);
    background: var(--primary-soft);
}

.reviews-button:hover:not(:disabled),
.refresh-button:hover:not(:disabled) {
    color: #fff;
    background: var(--primary);
    box-shadow: none;
}

.reviews-button:disabled {
    cursor: default;
    opacity: 0.75;
}

.refresh-button {
    display: grid;
    width: 36px;
    height: 36px;
    padding: 0 !important;
    place-items: center;
}

.refresh-button svg {
    width: 18px;
    height: 18px;
}

.rating {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #8a5200;
    font-weight: 750;
    white-space: nowrap;
}

.rating span {
    color: #f4a000;
}

.rating-empty {
    color: var(--muted);
    font-weight: 500;
}

.row-error {
    max-width: 390px;
    color: var(--danger);
    font-size: 12px;
    line-height: 1.35;
}

.parse-status,
.reviews-summary {
    color: var(--muted);
    font-size: 12px;
}

.spinning {
    animation: spin 700ms linear infinite;
}

.delete-button {
    color: var(--danger);
    background: var(--danger-soft);
}

.delete-button:hover:not(:disabled) {
    color: #fff;
    background: var(--danger);
    box-shadow: none;
}

.state-message,
.empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 150px;
    margin-top: 18px;
    color: var(--muted);
    background: #fafbfe;
    border: 1px dashed #cad4e6;
    border-radius: 10px;
}

.empty-state {
    flex-direction: column;
    text-align: center;
}

.empty-state strong {
    color: #30394f;
}

.empty-state span {
    font-size: 14px;
}

.empty-state-icon {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    color: var(--primary);
    font-size: 24px;
    background: var(--primary-soft);
    border-radius: 12px;
}

.loader {
    width: 18px;
    height: 18px;
    border: 2px solid #f4cfb8;
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 700ms linear infinite;
}

.reviews-section {
    margin-top: 34px;
}

.reviews-section .section-heading > div {
    display: grid;
    gap: 5px;
}

.reviews-list {
    display: grid;
    gap: 12px;
    margin-top: 18px;
}

.review-card {
    padding: 18px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 10px;
}

.review-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.review-rating {
    color: #f4a000;
    letter-spacing: 1px;
    white-space: nowrap;
}

.review-rating span {
    color: #dfe3ea;
}

.review-card time {
    display: block;
    margin-top: 5px;
    color: var(--muted);
    font-size: 12px;
}

.review-card p {
    margin: 14px 0 0;
    line-height: 1.55;
    white-space: pre-wrap;
}

.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-top: 8px;
}

.pagination span {
    color: var(--muted);
    font-size: 14px;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 720px) {
    .page-header,
    .form-heading,
    .reviews-section .section-heading {
        align-items: stretch;
        flex-direction: column;
    }

    .account {
        justify-content: space-between;
    }

    .organization-form {
        padding: 20px;
    }

    .organizations-table-wrap {
        overflow: visible;
        border: 0;
    }

    table,
    thead,
    tbody,
    tr,
    td {
        display: block;
    }

    thead {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
    }

    tbody {
        display: grid;
        gap: 12px;
    }

    tbody tr {
        padding: 14px 16px;
        border: 1px solid var(--border);
        border-radius: 10px;
    }

    tbody tr:last-child td,
    td {
        display: grid;
        grid-template-columns: 95px minmax(0, 1fr);
        gap: 12px;
        padding: 8px 0;
        border: 0;
    }

    td::before {
        color: var(--muted);
        font-size: 12px;
        font-weight: 750;
        content: attr(data-label);
    }

    .organization-actions {
        justify-content: flex-start;
    }

    .review-header,
    .pagination {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (max-width: 460px) {
    .account {
        align-items: stretch;
        flex-direction: column;
    }

    .form-row {
        flex-direction: column;
    }

    .form-row button {
        width: 100%;
    }
}
</style>
