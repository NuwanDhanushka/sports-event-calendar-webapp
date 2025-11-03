import {http} from "./http.js";

export async function listEvents(params = {}) {
    return await http.get('/event', {query: params});
}
