<template>
  <RouterView v-if="$route.matched.length" />
  <main v-else><p role="status">Загрузка…</p></main>
</template>

<style>
body {
  margin: 0;
  font-family: Arial, sans-serif;
  color: #222;
  background: #f6f7f9;
}

main {
  max-width: 760px;
  margin: 48px auto;
  padding: 24px;
  background: white;
  border-radius: 8px;
}

label {
  display: block;
  margin-bottom: 8px;
}

.form-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

input {
  flex: 1;
  min-width: 180px;
}

input,
button {
  padding: 10px 12px;
  font: inherit;
}

button {
  cursor: pointer;
}

button:disabled {
  cursor: default;
}

ul {
  padding-left: 20px;
}

li {
  margin: 12px 0;
  overflow-wrap: anywhere;
}

.error {
  color: #b42318;
}
</style>
