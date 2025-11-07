<?php

namespace App\Http\Controllers\V1;

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Models\team;

/**
 * Team controller
 */
class TeamController
{
    /**
     * List teams
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function index(Request $req, array $params): Response
    {

        /** check if all teams are requested */
        $all = (int)$req->query('all', 0) === 1;

        /** get query params from the request */
        $filters = [
            'sport_id' => $req->query('sport_id', null),
        ];

        /** if all teams are requested, return all teams */
        if ($all) {
            $items = Team::all($filters);
            return new Response(200, 'teams list', true, [
                'teams' => array_map(fn(Team $team) => $team->toArray(), $items),
                'meta'   => ['mode' => 'all', 'count' => count($items)],
            ]);
        }

        /** get limit and page from query params and set defaults and limits */
        $limit  = max(1, min(200, (int)$req->query('limit', 100)));
        $page   = max(1, (int)$req->query('page', 1));
        $offset = ($page - 1) * $limit;

        /** get the list of teams */
        $res = Team::list($limit, $offset, $filters);
        [$items, $total] = [ $res['data'], $res['total'] ];

        /** return the response with the filtered teams and pagination info */
        return new Response(200, 'teams list', true, [
            'teams' => array_map(fn(Team $team) => $team->toArray(), $items),
            'meta'   => [
                'mode'  => 'paged',
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / max(1, $limit)),
            ],
        ]);
    }
}