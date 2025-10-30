<?php

namespace App\Http\Controllers\V1;
use App\Core\Request;
use App\Core\Response;

class EventController
{
    public function index(Request $req, array $params): Response
    {
        $rows = [];
        return new Response(200, 'Event Data', true, ['data' => $rows]);
    }
}