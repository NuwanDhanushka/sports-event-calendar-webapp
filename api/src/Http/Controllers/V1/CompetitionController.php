<?php

namespace App\Http\Controllers\V1;

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Models\Competition;

/**
 * Competition controller
 */
class CompetitionController
{
    /**
     * List competitions
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function index(Request $req, array $params): Response
    {

        /** check if all competitions are requested */
        $all = (int)$req->query('all', 0) === 1;

        /** get query params from the request */
        $filters = [
            'sport_id' => $req->query('sport_id', null),
        ];

        /** if all competitions are requested, return all competitions */
        if ($all) {
            $items = Competition::all($filters);
            return new Response(200, 'competitions list', true, [
                'competitions' => array_map(fn(Competition $competition) => $competition->toArray(), $items),
                'meta'   => ['mode' => 'all', 'count' => count($items)],
            ]);
        }

        /** get limit and page from query params and set defaults and limits */
        $limit  = max(1, min(200, (int)$req->query('limit', 100)));
        $page   = max(1, (int)$req->query('page', 1));
        $offset = ($page - 1) * $limit;

        /** get the list of competitions */
        $res = Competition::list($limit, $offset, $filters);
        [$items, $total] = [ $res['data'], $res['total'] ];

        /** return the response with the filtered competitions and pagination info */
        return new Response(200, 'competitions list', true, [
            'competitions' => array_map(fn(Competition $competition) => $competition->toArray(), $items),
            'meta'   => [
                'mode'  => 'paged',
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / max(1, $limit)),
            ],
        ]);
    }

    /**
     * List teams that participate in a given competition
     *
     * GET /competition/{competitionId}/teams
     *
     * @param Request $req
     * @param array   $params
     * @return Response
     */
    public function teams(Request $req, array $params): Response
    {
        $competitionId = (int)($params['competitionId'] ?? 0);

        if ($competitionId <= 0) {
            return new Response(
                400,
                'Invalid competition id',
                false,
                ['error' => 'invalid_competition_id']
            );
        }

        // Fetch teams for this competition
        $teams = Competition::teams($competitionId);

        return new Response(200, 'competition teams', true, [
            'competition_id' => $competitionId,
            'teams' => array_map(
                fn($team) => $team->toArray(),
                $teams
            ),
            'meta' => [
                'count' => count($teams),
            ],
        ]);
    }

}