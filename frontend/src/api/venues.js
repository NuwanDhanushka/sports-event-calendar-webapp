import {http} from "./http.js";

/**
 * List venues
 * @param params
 * @returns {Promise<*>}
 */
export async function listVenues(params = {}) {
    return await http.get('/venue', {query: params});
}