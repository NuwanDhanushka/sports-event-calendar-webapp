<?php

namespace App\Http\Controllers\V1;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Role;
use App\Models\User;

/**
 * User controller
 */
class UserController {

    /**
     * Create a new user
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function store(Request $request, array $params):Response{

        /** get the request data */
        $payload = $request->getData();

        /** get the user data trim and lowercase data */
        $name = trim($payload['name'] ?? '');
        $email = strtolower(trim($payload['email'] ?? ''));
        $password = trim($payload['password'] ?? '');
        $roleId = (int)($payload['roleId'] ?? 0);

        /** validate the user data */
        if (empty($name) || empty($email) || empty($password) || empty($roleId)) {
            return new Response(422, 'Validation failed', false, ['missing'=>['name','email','password','roleId']]);
        }

        /** check if the email already exists */
        if (User::findByEmail($email)) {
            return new Response(409, 'Email exists', false, ['error'=>'email_taken']);
        }

        /** check if the roleId exists */
        if(!Role::exists($roleId)){
            return new Response(422, 'Invalid roleId', false, ['error'=>'role_not_found']);
        }

        /** create the user */
        $id = User::create($name, $email, $password, $roleId);
        if($id <= 0){
            return new Response(500, 'Internal Server Error', false, ['error'=>'user_creation_failed']);
        }

        /** return the response with the user data */
        return (new Response(200, 'User created', true, ['id' => $id, 'name' => $name, 'email' => $email]));
    }

    /**
     * Update a user's profile
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function update(Request $request, array $params):Response
    {
        /** check if the user is logged in */
        $userId = Session::get('user_id', 0);
        if($userId <= 0){
            return new Response(401, 'Unauthorized', false, ['error'=>'unauthorized']);
        }

        /** check if the user has sent the id  */
        $id = (int)($params['id'] ?? 0);
        if($id <= 0){
            return new Response(400, 'Bad Request', false, ['error'=>'invalid_id']);
        }

        /** check if the user is trying to update another user */
        if($userId !== $id){
            return new Response(403, 'Forbidden', false, ['error'=>'forbidden']);
        }

        /** update the user */
        $result = User::updateProfile($id, $request->getData());
        if(!$result){
            return new Response(400, 'User data update failed', false, ['error'=>'update_failed']);
        }

        /** return the response with the user data */
        return new Response(200, 'User data updated', true);

    }

    /**
     * Change a user's password
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function changePassword(Request $request, array $params):Response{

        /** check if the user is logged in */
        $userId = Session::get('user_id', 0);
        if($userId <= 0){
            return new Response(401, 'Unauthorized', false, ['error'=>'unauthorized']);
        }

        /** check if the user has sent the id  */
        $id = (int)($params['id'] ?? 0);
        if($id <= 0) {
            return new Response(400, 'Bad Request', false, ['error' => 'invalid_id']);
        }

        /** check if the user is trying to update another user */
        if($userId !== $id){
            return new Response(403, 'Forbidden', false, ['error'=>'forbidden']);
        }

        /** get the request data */
        $payload = $request->getData();

        /** get the user data trim and lowercase data */
        $currentPassword = trim($payload['current_password'] ?? '');
        $newPassword = trim($payload['new_password'] ?? '');
        if(empty($currentPassword) || empty($newPassword)){
            return new Response(422, 'Validation failed', false, ['missing'=>['current_password','new_password']]);
        }

        /** check if the current password is correct */
        $user = User::find($id);
        if(!$user || !password_verify($currentPassword, $user->getPasswordHash())){
            return new Response(401, 'Invalid credentials', false, ['error'=>'wrong_password']);
        }

        /** change the password */
        User::changePassword($id, $newPassword);
        return new Response(200, 'Password changed', true);

    }

    /**
     * Deactivate a user
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function deactivate(Request $request, array $params):Response{

        /** check if the user id is logged in */
        $userId = Session::get('user_id', 0);
        if($userId <= 0){
            return new Response(401, 'Unauthorized', false, ['error'=>'unauthorized']);
        }

        /** check if the user has sent the user id  */
        $id = (int)($params['id'] ?? 0);
        if($id <= 0) {
            return new Response(400, 'Bad Request', false, ['error' => 'invalid_id']);
        }

        /** check if the user is trying to deactivate another user */
        if($userId !== $id){
            return new Response(403, 'Forbidden', false, ['error'=>'forbidden']);
        }

        /** deactivate the user */
        User::deactivate($id);

        return new Response(200, 'User deactivated', true);
    }



}