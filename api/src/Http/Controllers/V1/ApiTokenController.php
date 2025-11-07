<?php

namespace App\Http\Controllers\V1;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\ApiToken;

/**
 * API token controller
 */
class ApiTokenController {

    /**
     * Get the user ID from the session
     * @return int
     */
    private function requireLogin(): int
    {
        /** get user ID from session */
        return (int) Session::get('user_id', 0);
    }

    /**
     * Create a new API token
     * @param Request $req
     * @param array $params
     * @return Response
     * @throws \Random\RandomException
     */
    public function store(Request $req, array $params): Response
    {
        /** check user ID from a session */
        $uid = $this->requireLogin();
        if ($uid <= 0) {
            return new Response(401, 'Unauthorized', false, ['error' => 'login_required']);
        }

        /** create a new API token */
        $res = ApiToken::create($uid);

        /** return the response with the token */
        return new Response(201, 'Created', true, [
            'id'    => $res['id'],
            'token' => $res['token'],
        ]);
    }

    /**
     * List all API tokens
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function index(Request $req, array $params): Response
    {
        /** check user ID from a session */
        $uid = $this->requireLogin();
        if ($uid <= 0) {
            return new Response(401, 'Unauthorized', false, ['error' => 'login_required']);
        }
        /** list all API tokens */
        $rows = ApiToken::listAll();
        return new Response(200, 'Api entries', true, ['data' => $rows]);
    }

    /**
     * Delete an API token
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function destroy(Request $req, array $params): Response
    {
        /** check user ID from a session */
        $uid = $this->requireLogin();
        if ($uid <= 0) {
            return new Response(401, 'Unauthorized', false, ['error' => 'login_required']);
        }
        /** check if the id is passed by the request as a parameter */
        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            return new Response(400, 'Bad Request', false, ['error' => 'invalid_id']);
        }
        /** delete the API token */
        ApiToken::delete($id);
        return new Response(200, 'Deleted api key', true, []);
    }

}
