export async function api(path, options = {}) {
  const token = document.cookie
    .split("; ")
    .find((cookie) => cookie.startsWith("XSRF-TOKEN="))
    ?.slice("XSRF-TOKEN=".length);
  let response;
  try {
    response = await fetch(path, {
      credentials: "same-origin",
      ...options,
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...(options.body ? { "Content-Type": "application/json" } : {}),
        ...(token ? { "X-XSRF-TOKEN": decodeURIComponent(token) } : {}),
        ...options.headers,
      },
    });
  } catch {
    throw new Error("Не удалось соединиться с сервером. Попробуйте ещё раз.");
  }
  if (response.status === 204) return null;
  let data;
  try {
    data = await response.json();
  } catch {
    const error = new Error(
      "Сервер вернул некорректный ответ. Попробуйте ещё раз.",
    );
    error.status = response.status;
    throw error;
  }
  if (!response.ok) {
    const message =
      Object.values(data?.errors ?? {}).flat()[0] || data?.message;
    const error = new Error(
      message || "Сервер вернул ошибку без описания. Попробуйте ещё раз.",
    );
    error.status = response.status;
    throw error;
  }
  return data;
}
