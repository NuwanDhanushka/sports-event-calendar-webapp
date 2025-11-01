<?php

namespace App\Http\Controllers\V1;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\ApiToken;

class ApiTokenController {

    private function requireLogin(): int
    {
        return (int) Session::get('user_id', 0);
    }

    public function store(Request $req, array $params): Response
    {
        $uid = $this->requireLogin();
        if ($uid <= 0) {
            return new Response(401, 'Unauthorized', false, ['error' => 'login_required']);
        }

        $res = ApiToken::create($uid);
        return new Response(201, 'Created', true, [
            'id'    => $res['id'],
            'token' => $res['token'],
        ]);
    }

    public function index(Request $req, array $params): Response
    {
        $uid = $this->requireLogin();
        if ($uid <= 0) {
            return new Response(401, 'Unauthorized', false, ['error' => 'login_required']);
        }

        $rows = ApiToken::listAll();
        return new Response(200, 'Api entries', true, ['data' => $rows]);
    }

    public function destroy(Request $req, array $params): Response
    {
        $uid = $this->requireLogin();
        if ($uid <= 0) {
            return new Response(401, 'Unauthorized', false, ['error' => 'login_required']);
        }

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            return new Response(400, 'Bad Request', false, ['error' => 'invalid_id']);
        }

        ApiToken::delete($id);
        return new Response(200, 'Deleted api key', true, []);
    }

}
