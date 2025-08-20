export const createFetchOptions = (data) => ({
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
});

export const handleResponse = async (response) => {
    if (response.status >= 400 && response.status < 600) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    return response.json();
};

export const loadConfiguration = async (url) => {
    const response = await fetch(url);
    return await handleResponse(response);
};
