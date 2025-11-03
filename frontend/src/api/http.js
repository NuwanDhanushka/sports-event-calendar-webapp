// Base path for API, e.g. "/api/v1"
const API_BASE_PATH = import.meta.env.VITE_API_BASE || '/api/v1'

let runtimeAuthToken = null

/**
 * Get the current auth token.
 * - If set at runtime, use that.
 * - Otherwise, use the VITE_API_TOKEN env var.
 * - Otherwise, return an empty string.
 */
function getAuthToken() {
    return runtimeAuthToken || import.meta.env.VITE_API_TOKEN || ''
}

/**
 * Append plain data to FormData.
 * @param form
 * @param key
 * @param value
 */
function appendValue(form, key, value) {
    if (value instanceof File || value instanceof Blob) {
        form.append(key, value)
    } else if (Array.isArray(value)) {
        value.forEach((item, index) => {
            appendValue(form, `${key}[${index}]`, item)
        })
    } else if (value !== null && typeof value === 'object') {
        form.append(key, JSON.stringify(value))
    } else {
        form.append(key, value ?? '')
    }
}

/**
 * Convert a plain object into FormData
 * @param object
 * @returns {FormData}
 */
function buildFormDataFrom(object = {}) {
    const form = new FormData()
    Object.entries(object).forEach(([field, value]) => appendValue(form, field, value))
    return form
}


/**
 * Send a request to the API.
 * @param path
 * @param options
 * @returns {Promise<any>}
 */
async function sendRequest(
    path,
    {
        method = 'GET',
        query = undefined,       // query params
        data = undefined,        // form data
        extraHeaders = {},       // any additional headers
        includeCredentials = true, // send cookies for PHP session
    } = {}
) {

    // Build the absolute URL with the proxy-friendly base path
    const url = new URL((API_BASE_PATH + path).replace(/\/{2,}/g, '/'), window.location.origin)

    if (query && typeof query === 'object') {
        for (const [key, val] of Object.entries(query)) {
            if (val !== undefined && val !== null) url.searchParams.append(key, String(val))
        }
    }

    // Base headers
    const headers = {
        Accept: 'application/json',
        ...extraHeaders,
    }

    // Bearer authentication
    const token = getAuthToken()
    if (token) headers.Authorization = `Bearer ${token}`

    // Get method
    if (method.toUpperCase() === 'GET') {
        const response = await fetch(url.toString(), {
            method: 'GET',
            credentials: includeCredentials ? 'include' : 'same-origin',
            headers,
        })
        return parseResponse(response)
    }

    // POST: form data
    const intendedMethod = method.toUpperCase()
    const formData = buildFormDataFrom(data || {})

    // Method override for PUT/PATCH/DELETE
    if (intendedMethod === 'PUT' || intendedMethod === 'PATCH' || intendedMethod === 'DELETE') {
        formData.append('_method', intendedMethod)
    }

    const response = await fetch(url.toString(), {
        method: 'POST',
        credentials: includeCredentials ? 'include' : 'same-origin',
        headers,
        body: formData,
    })

    return parseResponse(response)
}

/**
 * Parse the response from the API.
 * @param response
 * @returns {Promise<awaited Promise<Result<RootNode>> | Promise<Result<Root>> | Promise<any>>}
 */
async function parseResponse(response) {

    const body = await response.json().catch(() => null);

    if (!response.ok) {
        const err = new Error(body?.message || 'Request failed')
        err.status = response.status
        err.data = body
        throw err
    }
    return body
}

export const http = {

    setToken(token) {
        runtimeAuthToken = token || null
    },

    get:   (path, options)                => sendRequest(path, { ...options, method: 'GET' }),
    post:  (path, data = {}, options = {})=> sendRequest(path, { ...options, method: 'POST',  data }),
    put:   (path, data = {}, options = {})=> sendRequest(path, { ...options, method: 'PUT',   data }),
    patch: (path, data = {}, options = {})=> sendRequest(path, { ...options, method: 'PATCH', data }),
    delete:   (path, data = {}, options = {})=> sendRequest(path, { ...options, method: 'DELETE',data }),
}
