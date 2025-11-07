import {http} from "./http.js";

/**
 * List teams
 * @param params
 * @returns {Promise<*>}
 */
export async function listTeams(params = {}) {
    return await http.get('/team', {query: params});
}