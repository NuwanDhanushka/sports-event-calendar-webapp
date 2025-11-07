import {http} from "./http.js";

/**
 * List competitions
 * @param params
 * @returns {Promise<*>}
 */
export async function listCompetitions(params = {}) {
    return await http.get('/competition', {query: params});
}

/**
 * List teams that participate competition
 * @param competitionId
 * @param params
 * @returns {Promise<*>}
 */
export async function listCompetitionTeams(competitionId, params = {}) {
    return http.get(`/competition/${competitionId}/teams`, { query: params });
}