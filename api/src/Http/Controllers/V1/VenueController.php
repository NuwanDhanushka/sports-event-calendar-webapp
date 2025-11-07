<?php

namespace App\Http\Controllers\V1;

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Models\venue;

/**
 * Venue controller
 */
class VenueController
{
    /**
     * List venues
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function index(Request $req, array $params): Response
    {

        /** check if all venues are requested */
        $all = (int)$req->query('all', 0) === 1;


        /** if all venues are requested, return all venues */
        if ($all) {
            $items = Venue::all();
            return new Response(200, 'venues list', true, [
                'venues' => array_map(fn(Venue $venue) => $venue->toArray(), $items),
                'meta'   => ['mode' => 'all', 'count' => count($items)],
            ]);
        }

        /** get limit and page from query params and set defaults and limits */
        $limit  = max(1, min(200, (int)$req->query('limit', 100)));
        $page   = max(1, (int)$req->query('page', 1));
        $offset = ($page - 1) * $limit;

        /** get the list of venues */
        $res = Venue::list($limit, $offset);
        [$items, $total] = [ $res['data'], $res['total'] ];

        /** return the response with the filtered venues and pagination info */
        return new Response(200, 'venues list', true, [
            'venues' => array_map(fn(Venue $venue) => $venue->toArray(), $items),
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