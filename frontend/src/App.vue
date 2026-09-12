<template>
    <RouterView v-if="$route.matched.length"/>
    <main v-else><p role="status">Загрузка…</p></main>
</template>

<style>
:root {
    font-family: Inter,
    ui-sans-serif,
    system-ui,
    -apple-system,
    BlinkMacSystemFont,
    "Segoe UI",
    sans-serif;
    color: #172033;
    background: #f4f4f2;
    font-synthesis: none;
    --primary: #e56b1f;
    --primary-dark: #bd4f0f;
    --primary-soft: #fff1e7;
    --danger: #c2415d;
    --danger-soft: #fff0f3;
    --border: #e2ded8;
    --muted: #67728a;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-width: 320px;
    min-height: 100vh;
    color: #172033;
    background: #f4f4f2;
}

main {
    width: calc(100% - 32px);
    max-width: 900px;
    margin: 56px auto;
    padding: 36px;
    background: white;
    border: 1px solid rgba(220, 228, 242, 0.8);
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(42, 37, 32, 0.07);
}

h1 {
    margin: 0;
    font-size: clamp(30px, 5vw, 42px);
    line-height: 1.1;
    letter-spacing: -0.04em;
}

label {
    display: block;
    margin-bottom: 8px;
    color: #374158;
    font-size: 14px;
    font-weight: 650;
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

input {
    flex: 1;
    min-width: 180px;
    color: #172033;
    background: #fafbff;
    border: 1px solid var(--border);
    border-radius: 8px;
    outline: none;
    transition: border-color 160ms ease,
    box-shadow 160ms ease,
    background 160ms ease;
}

input:focus {
    background: #fff;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(229, 107, 31, 0.14);
}

input,
button {
    padding: 12px 16px;
    font: inherit;
}

button {
    cursor: pointer;
    color: #fff;
    font-weight: 650;
    background: var(--primary);
    border: 0;
    border-radius: 8px;
    box-shadow: none;
    transition: transform 160ms ease,
    background 160ms ease,
    box-shadow 160ms ease;
}

button:hover:not(:disabled) {
    background: var(--primary-dark);
    box-shadow: none;
    transform: translateY(-1px);
}

button:focus-visible,
a:focus-visible {
    outline: 3px solid rgba(229, 107, 31, 0.22);
    outline-offset: 3px;
}

button:disabled {
    cursor: not-allowed;
    opacity: 0.55;
    box-shadow: none;
}

ul {
    padding: 0;
    list-style: none;
}

li {
    margin: 12px 0;
    overflow-wrap: anywhere;
}

.error {
    padding: 11px 14px;
    color: #a72e49;
    background: var(--danger-soft);
    border: 1px solid #ffd2dc;
    border-radius: 10px;
}

@media (max-width: 600px) {
    main {
        width: calc(100% - 20px);
        margin: 20px auto;
        padding: 24px 20px;
        border-radius: 10px;
    }
}
</style>
