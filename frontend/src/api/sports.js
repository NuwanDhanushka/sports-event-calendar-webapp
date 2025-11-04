import {http} from "./http.js";

export async function listSports(params = {}) {
    return await http.get('/sport', { params });
}