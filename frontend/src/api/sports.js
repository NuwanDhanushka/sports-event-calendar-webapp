import {http} from "./http.js";

/**
 * List sports
 * @param params
 * @returns {Promise<*>}
 */
export async function listSports(params = {}) {
    return await http.get('/sport', { params });
}