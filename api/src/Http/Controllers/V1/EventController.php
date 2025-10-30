<?php

namespace App\Http\Controllers\V1;
use App\Core\Request;
use App\Core\Response;
use App\Models\Event;

class EventController
{
    public function index(Request $req, array $params): Response
    {
        $limit  = max(1, min(100, (int)$req->query('limit', 20)));
        $page   = max(1, (int)$req->query('page', 1));
        $offset = ($page - 1) * $limit;

        $result = Event::list($limit, $offset);

        return new Response(200, 'Event list', true, [
            'events' => $result['data'],
            'meta' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'pages' => (int)ceil($result['total'] / max(1,$limit)),
            ],
        ]);
    }

    public function show(Request $req, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $eventData = $id ? Event::find($id) : null;
        return $eventData
            ? new Response(200, 'Event Data', true, ['data' => ['id'=>$eventData->id, 'title'=>$eventData->title]])
            : new Response(404, 'Not Found', false, ['error' => 'not_found']);
    }

    public function store(Request $req, array $params): Response
    {
        $requestData = $req->getData();

        if (empty($requestData['title'])) {
            return new Response(422, 'Validation failed', false, ['missing'=>['title']]);
        }

        $id = Event::create($requestData);
        return (new Response(201, 'Event created', true, ['id' => $id, 'title' => $requestData['title']]));
    }

    public function update(Request $req, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);

        if ($id <= 0) {
            return new Response(400, 'Bad Request', false, ['error'=>'invalid_id']);
        }

        $updateResult = Event::update($id, $req->getData());
        return $updateResult
            ? new Response(200, 'Event data updated', true, ['updated' => $id])
            : new Response(200, 'Nothing to update', true);
    }

    public function destroy(Request $req, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);

        if ($id <= 0) {
            return new Response(400, 'Bad Request', false, ['error'=>'invalid_id']);
        }

        $deletedResult = Event::delete($id);
        return $deletedResult
            ? new Response(200, 'Event deleted', true, ['deleted' => $id])
            : new Response(404, 'Not Found', false, ['error' => 'not_found']);
    }

}