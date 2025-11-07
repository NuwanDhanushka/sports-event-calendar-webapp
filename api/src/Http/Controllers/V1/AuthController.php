<?php

namespace App\Http\Controllers\V1;

use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;

/**
 * Authentication controller
 */
class AuthController {

    /**
     * Login the user
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function login(Request $req, array $params):Response {
        /** get the request payload */
        $payload = $req->getData();

        /** get the email and password */
        $email = strtolower(trim($payload['email'] ?? ''));
        $password = trim($payload['password'] ?? '');

        /** validate email and password */
        if (empty($email) || empty($password)) {
            return new Response(422, 'Validation failed', false, ['missing'=>['email','password']]);
        }

        /** validate credentials */
        $user = User::validateCredentials($email, $password);

        /** if credentials are invalid, return error */
        if (!$user) {
            return new Response(401, 'Invalid credentials', false, ['error'=>'invalid_login']);
        }

        /** set permissions in session */
        Session::set('user_id', $user->getId());
        $permissionList = Permission::getPermissionsForUser($user->getId());

        /** return the user details as response*/
        return new Response(200, 'Login successful', true,[
            'user' => ['id'=>$user->getId(), 'name'=>$user->getName(), 'email'=>$user->getEmail(),'permissions'=>$permissionList]
        ]);

    }

    /**
     * Get the current logged user's details
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function me(Request $req, array $params):Response {

        /** get user ID from session */
        $userId= (int)Session::get('user_id', 0);

        /** if no user ID, return unauthorized */
        if (!$userId) {
            return new Response(401, 'Unauthorized', false, ['error'=>'unauthorized']);
        }

        /** find the user */
        $user = User::find($userId);

        /** if no user, return unauthorized */
        if (!$user) return new Response(401, 'Unauthorized', false, []);

        /** return the user details as response*/
        return new Response(200, 'User details', true,[
            'user' => ['id'=>$user->getId(), 'name'=>$user->getName(), 'email'=>$user->getEmail()]
        ]);
    }

    /**
     * Logout the user
     * @param Request $req
     * @param array $params
     * @return Response
     */
    public function logout(Request $req, array $params):Response {
        /** clear permissions in session */
        Permission::clear();
        /** destroy session */
        Session::destroy();
        return new Response(200, 'Logout successful', true);
    }

}