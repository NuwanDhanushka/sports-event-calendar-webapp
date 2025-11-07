import {http} from "./http.js";

/**
 * List events
 * @param params
 * @returns {Promise<*>}
 */
export async function listEvents(params = {}) {
    return await http.get('/event', {query: params});
}

/**
 * Create an event
 * @param data
 * @returns {Promise<*>}
 */
export async function createEvent(data = {}) {
    return await http.post('/event', data);
}