<?php

namespace App\Http\Controllers\V1;

use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;

class AuthController {

    public function login(Request $req, array $params):Response {
        $payload = $req->getData();

        $email = strtolower(trim($payload['email'] ?? ''));
        $password = trim($payload['password'] ?? '');

        if (empty($email) || empty($password)) {
            return new Response(422, 'Validation failed', false, ['missing'=>['email','password']]);
        }

        $user = User::validateCredentials($email, $password);
        if (!$user) {
            return new Response(401, 'Invalid credentials', false, ['error'=>'invalid_login']);
        }

        Session::set('user_id', $user->getId());
        $permissionList = Permission::getPermissionsForUser($user->getId());

        return new Response(200, 'Login successful', true,[
            'user' => ['id'=>$user->getId(), 'name'=>$user->getName(), 'email'=>$user->getEmail(),'permissions'=>$permissionList]
        ]);

    }

    public function me(Request $req, array $params):Response {
        $userId= (int)Session::get('user_id', 0);
        if (!$userId) {
            return new Response(401, 'Unauthorized', false, ['error'=>'unauthorized']);
        }

        $user = User::find($userId);
        if (!$user) return new Response(401, 'Unauthorized', false, []);

        return new Response(200, 'User details', true,[
            'user' => ['id'=>$user->getId(), 'name'=>$user->getName(), 'email'=>$user->getEmail()]
        ]);
    }

    public function logout(Request $req, array $params):Response {
        Permission::clear();
        Session::destroy();
        return new Response(200, 'Logout successful', true);
    }

}