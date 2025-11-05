<?php

namespace App\Http\Controllers\V1;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;
use App\Core\Storage;
use App\Models\Event;

/**
 * Event controller
 */
class EventController
{
    /**
     * Get a list of events
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function index(Request $req, array $params): Response
    {
        /** get the storage path from env */
        $storagePath = Env::get('STORAGE_PUBLIC_BASE','/storage/');

        /** get the date range from query params */
        $dateFrom = trim((string)$req->query('date_from', ''));
        $dateTo   = trim((string)$req->query('date_to', ''));

       /** get the sport ids and names from query params */
        $sportsParam = $req->query('sports', []);

        /** check if sports is a string and split it */
        if (is_string($sportsParam)) {

            /** if the string is comma separated, split it and trim space around and remove empty values*/
            $sportsParam = array_filter(array_map('trim', explode(',', $sportsParam)), fn($value)=>$value!=='');

        /** if sports is not array or string, wrap it in an array */
        } elseif (!is_array($sportsParam)) {
            $sportsParam = [$sportsParam];
        }

        /** get the query string from query params */
        $q = trim((string)$req->query('q', ''));

        /** check if date range or filter params are present */
        $hasRangeParams  = ($dateFrom !== '' || $dateTo !== '');
        $hasFilterParams = ($q !== '' || !empty($sportsParam));

        /** If range or filter params are present */
        if ($hasRangeParams || $hasFilterParams) {

            /** if the one of date range is missing, set it to open-ended */
            if ($dateFrom === '') $dateFrom = '0001-01-01 00:00:00';
            if ($dateTo   === '') $dateTo   = '9999-12-31 23:59:59';

            /** if date range is not in the correct format, add time */
            if (strlen($dateFrom) === 10) $dateFrom .= ' 00:00:00';
            if (strlen($dateTo)   === 10) $dateTo   .= ' 23:59:59';

            /** if date range is not in the correct order, swap them */
            if ($dateFrom > $dateTo) {
                [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            }

            /** create the filter array with search text and sports */
            $filters = [
                'q'      => $q,
                'sports' => $sportsParam,  // array can be of id and/or names
            ];

            /** get filter events by date range, sports and search text */
            $items = Event::filterEvents($dateFrom, $dateTo, $filters);

            /** return the response with the filtered events */
            return new Response(200, 'Events in range', true, [
                'events' => array_map(fn(Event $event) => $event->toArray(), $items),
                'meta' => [
                    'mode'  => 'range',
                    'date_from' => $dateFrom,
                    'date_to'   => $dateTo,
                    'count' => count($items),
                    'storagePublicBase' => $storagePath,
                ],
            ]);
        }

        /** get limit and page from query params and set defaults and limits */
        $limit  = max(1, min(100, (int)$req->query('limit', 20)));
        $page   = max(1, (int)$req->query('page', 1));
        $offset = ($page - 1) * $limit;

        /** get the list of events */
        $result = Event::list($limit, $offset);
        [$items, $total] = [ $result['data'], $result['total'] ];

        /** return the response with the filtered events and pagination info */
        return new Response(200, 'Event list', true, [
            'events' => array_map(fn(Event $event) => $event->toArray(), $items),
            'meta' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / max(1,$limit)),
                'storagePublicBase' => $storagePath,
            ],
        ]);
    }

    /**
     * Get an event by id
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function show(Request $req, array $params): Response
    {
       /** get the event id from the request */
        $id = (int)($params['id'] ?? 0);

        /** get the event data from id */
        $eventData = $id ? Event::find($id) : null;
        /** get the storage path from env */
        $storagePath = Env::get('STORAGE_PUBLIC_BASE','/storage/');

        /** return the response with the event data */
        return $eventData
            ? new Response(200, 'Event Data', true,
                ['data' =>
                        [
                            'event'=>$eventData->toArray(),
                            'meta' => [
                                'storage_public_base' => $storagePath,
                            ]
                        ]
                ])
            : new Response(404, 'Not Found', false, ['error' => 'not_found']);
    }

    /**
     * Create a new event
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function store(Request $req, array $params): Response
    {
        /** get the data form the request */
        $requestData = $req->getData();
        $bannerRelative = null;

        /** check if user has permission to create */
        if (!Permission::has('event_create')) {
            return new Response(403, 'Permission denied', false);
        }

        if (empty($requestData['title'])) {
            return new Response(422, 'Validation failed', false, ['missing'=>['title']]);
        }

        /** check if a banner file is sent with the request */
        $file  = $req->file('banner') ?? null;
        if ($file) {
            try {
                /** create the path for the banner and store the file in the path */
                $subdir = 'events/' . date('Y') . '/' . date('m');

                /** save the file and get the relative path */
                $bannerRelative = Storage::saveUploaded($file, $subdir);

            } catch (\Throwable $e) {
                return new Response(400, 'Upload failed', false, ['error' => $e->getMessage()]);
            }
        }

        /** create the event */
        $id = Event::create($requestData,$bannerRelative);
        return (new Response(201, 'Event created', true, ['id' => $id, 'title' => $requestData['title']]));
    }

    /**
     * Update an event
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function update(Request $req, array $params): Response
    {
        /** check if user has permission to update */
        if (!Permission::has('event_update')) {
            return new Response(403, 'Permission denied', false);
        }

        /** check if user has sent id with the request */
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            return new Response(400, 'Bad Request', false, ['error'=>'invalid_id']);
        }

        /** update the event */
        $updateResult = Event::update($id, $req->getData());
        return $updateResult
            ? new Response(200, 'Event data updated', true, ['updated' => $id])
            : new Response(200, 'Nothing to update', true);
    }

    /**
     * Delete an event
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function destroy(Request $req, array $params): Response
    {
        /** check if a user has permission to delete an event */
        if (!Permission::has('event_delete')) {
            return new Response(403, 'Permission denied', false);
        }

        /** check if a user has sent id with the request */
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            return new Response(400, 'Bad Request', false, ['error'=>'invalid_id']);
        }

        /** delete the event */
        $deletedResult = Event::delete($id);
        return $deletedResult
            ? new Response(200, 'Event deleted', true, ['deleted' => $id])
            : new Response(404, 'Not Found', false, ['error' => 'not_found']);
    }

    /**
     * Upload banner for event
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function uploadBanner(Request $req, array $params): Response
    {
        /** check if a user has sent id with the request */
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) return new Response(400, 'Bad Request', false, ['error'=>'invalid_id']);

        /** check if a banner file is sent with the request */
        $file = $req->file('banner') ?? null;
        if (!$file) return new Response(422, 'Validation failed', false, ['missing'=>['banner']]);


        try {
            /** create the path for the banner and store the file in the path */
            $subdir = 'events/' . date('Y') . '/' . date('m');
            $relative = Storage::saveUploaded($file, $subdir);
        } catch (\Throwable $e) {
            return new Response(400, 'Upload failed', false, ['error' => $e->getMessage()]);
        }

        /** delete the old banner if it exists */
        if ($old = Event::getBannerPathById($id)) {
            Storage::delete($old);
        }

        /** set the new banner's relative path for the event */
        Event::setBanner($id, $relative);

        /** return the response with the uploaded file's path */
        return new Response(201, 'Uploaded', true, [
            'path' => $relative,
            'url'  => Storage::url($relative),
        ]);
    }

    /**
     * Delete banner for event
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function deleteBanner(Request $req, array $params): Response
    {
        /** check if a user has sent id with the request */
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) return new Response(400, 'Bad Request', false, ['error'=>'invalid_id']);

        /** get the old banner's id */
        if ($old = Event::getBannerPathById($id)) {
            /** delete the old banner from storage */
            Storage::delete($old);
            /** set the banner to null */
            Event::setBanner($id, null);
        }

        return new Response(200, 'OK', true, ['deleted' => (bool)$old]);
    }

}