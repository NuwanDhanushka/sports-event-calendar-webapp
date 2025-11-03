<?php

namespace App\Http\Controllers\V1;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;
use App\Core\Storage;
use App\Models\Event;

class EventController
{
    public function index(Request $req, array $params): Response
    {
        $limit  = max(1, min(100, (int)$req->query('limit', 20)));
        $page   = max(1, (int)$req->query('page', 1));
        $offset = ($page - 1) * $limit;
        $storagePath = Env::get('STORAGE_PUBLIC_BASE','/storage/');

        $result = Event::list($limit, $offset);
        [$items, $total] = [ $result['data'], $result['total'] ];

        return new Response(200, 'Event list', true, [
            'events' => array_map(fn(Event $e) => $e->toArray(), $items),
            'meta' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / max(1,$limit)),
                'storagePublicBase' => $storagePath,
            ],
        ]);
    }

    public function show(Request $req, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $eventData = $id ? Event::find($id) : null;
        $storagePath = Env::get('STORAGE_PUBLIC_BASE','/storage/');
        return $eventData
            ? new Response(200, 'Event Data', true,
                ['data' =>
                        [
                            'id'=>$eventData->getId(),
                            'title'=>$eventData->getTitle(),
                            'event_banner'=>$eventData->getBannerPath(),
                            'meta' => [
                                'storage_public_base' => $storagePath,
                            ]
                        ]
                ])
            : new Response(404, 'Not Found', false, ['error' => 'not_found']);
    }

    public function store(Request $req, array $params): Response
    {
        $requestData = $req->getData();
        $bannerRelative = null;

        if (!Permission::has('event_create')) {
            return new Response(403, 'Permission denied', false);
        }

        if (empty($requestData['title'])) {
            return new Response(422, 'Validation failed', false, ['missing'=>['title']]);
        }

        $file  = $req->file('banner') ?? null;

        if ($file) {
            try {
                $subdir = 'events/' . date('Y') . '/' . date('m');
                $bannerRelative = Storage::saveUploaded($file, $subdir);

            } catch (\Throwable $e) {
                return new Response(400, 'Upload failed', false, ['error' => $e->getMessage()]);
            }
        }

        $id = Event::create($requestData,$bannerRelative);
        return (new Response(201, 'Event created', true, ['id' => $id, 'title' => $requestData['title']]));
    }

    public function update(Request $req, array $params): Response
    {
        if (!Permission::has('event_update')) {
            return new Response(403, 'Permission denied', false);
        }

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
        if (!Permission::has('event_delete')) {
            return new Response(403, 'Permission denied', false);
        }

        $id = (int)($params['id'] ?? 0);

        if ($id <= 0) {
            return new Response(400, 'Bad Request', false, ['error'=>'invalid_id']);
        }

        $deletedResult = Event::delete($id);
        return $deletedResult
            ? new Response(200, 'Event deleted', true, ['deleted' => $id])
            : new Response(404, 'Not Found', false, ['error' => 'not_found']);
    }

    public function uploadBanner(Request $req, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) return new Response(400, 'Bad Request', false, ['error'=>'invalid_id']);

        $file = $req->file('banner') ?? null;
        if (!$file) return new Response(422, 'Validation failed', false, ['missing'=>['banner']]);

        // Save new banner
        try {
            $subdir = 'events/' . date('Y') . '/' . date('m');
            $relative = Storage::saveUploaded($file, $subdir);
        } catch (\Throwable $e) {
            return new Response(400, 'Upload failed', false, ['error' => $e->getMessage()]);
        }

        // Delete old banner if any
        if ($old = Event::getBannerPathById($id)) {
            Storage::delete($old);
        }

        Event::setBanner($id, $relative);

        return new Response(201, 'Uploaded', true, [
            'path' => $relative,
            'url'  => Storage::url($relative),
        ]);
    }

    public function deleteBanner(Request $req, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) return new Response(400, 'Bad Request', false, ['error'=>'invalid_id']);

        if ($old = Event::getBannerPathById($id)) {
            Storage::delete($old);
            Event::setBanner($id, null);
        }

        return new Response(200, 'OK', true, ['deleted' => (bool)$old]);
    }

}