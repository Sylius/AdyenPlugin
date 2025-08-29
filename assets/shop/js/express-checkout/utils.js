export const createFetchOptions = (data) => ({
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
});

export const loadConfiguration = async (url) => {
    const response = await fetch(url);
    return await response.json();
};

export const showErrorMessage = (message, selector) => {
    clearErrorMessage();

    const errorElement = document.createElement('div');
    errorElement.className = 'adyen-payment-error';
    errorElement.innerHTML = `
                <span class="error-message">${message}</span>
                <button class="error-close" onclick="this.parentElement.remove()">×</button>
            `;

    errorElement.style.cssText = `
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
                border-radius: 4px;
                padding: 12px 15px;
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 14px;
                animation: fadeIn 0.3s ease-in;
            `;

    const $container = document.getElementById(selector);
    $container.insertBefore(errorElement, $container.firstChild);
}

export const clearErrorMessage = () => {
    const existingError = document.querySelector('.adyen-payment-error');
    if (existingError) {
        existingError.remove();
    }
}

export const createUrlWithToken = (url, token) => {
    if (!token) return url;
    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}tokenValue=${token}`;
}
