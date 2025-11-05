<?php

namespace App\Http\Controllers\V1;

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Models\Sport;

/**
 * Sport controller
 */
class SportController
{
    /**
     * List sports
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function index(Request $req, array $params): Response
    {

        /** check if all sports are requested */
        $all = (int)$req->query('all', 0) === 1;

        /** get query params and team only data from the request */
        $filters = [
            'q'         => (string)$req->query('q', ''),
            'team_only' => $req->query('team_only', null),
        ];

        /** if all sports are requested, return all sports */
        if ($all) {
            $items = Sport::all($filters);
            return new Response(200, 'Sports list', true, [
                'sports' => array_map(fn(Sport $sport) => $sport->toArray(), $items),
                'meta'   => ['mode' => 'all', 'count' => count($items)],
            ]);
        }

        /** get limit and page from query params and set defaults and limits */
        $limit  = max(1, min(200, (int)$req->query('limit', 100)));
        $page   = max(1, (int)$req->query('page', 1));
        $offset = ($page - 1) * $limit;

        /** get the list of sports */
        $res = Sport::list($limit, $offset, $filters);
        [$items, $total] = [ $res['data'], $res['total'] ];

        /** return the response with the filtered sports and pagination info */
        return new Response(200, 'Sports list', true, [
            'sports' => array_map(fn(Sport $sport) => $sport->toArray(), $items),
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