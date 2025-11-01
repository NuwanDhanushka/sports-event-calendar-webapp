<?php

namespace App\Http\Controllers\V1;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Role;
use App\Models\User;

class UserController {

    public function store(Request $request, array $params):Response{

        $payload = $request->getData();

        $name = trim($payload['name'] ?? '');
        $email = strtolower(trim($payload['email'] ?? ''));
        $password = trim($payload['password'] ?? '');
        $roleId = (int)($payload['roleId'] ?? 0);

        if (empty($name) || empty($email) || empty($password) || empty($roleId)) {
            return new Response(422, 'Validation failed', false, ['missing'=>['name','email','password','roleId']]);
        }

        if (User::findByEmail($email)) {
            return new Response(409, 'Email exists', false, ['error'=>'email_taken']);
        }

        if(!Role::exists($roleId)){
            return new Response(422, 'Invalid roleId', false, ['error'=>'role_not_found']);
        }

        $id = User::create($name, $email, $password, $roleId);

        if($id <= 0){
            return new Response(500, 'Internal Server Error', false, ['error'=>'user_creation_failed']);
        }

        return (new Response(200, 'User created', true, ['id' => $id, 'name' => $name, 'email' => $email]));
    }

    public function update(Request $request, array $params):Response
    {
        $userId = Session::get('user_id', 0);
        if($userId <= 0){
            return new Response(401, 'Unauthorized', false, ['error'=>'unauthorized']);
        }

        $id = (int)($params['id'] ?? 0);
        if($id <= 0){
            return new Response(400, 'Bad Request', false, ['error'=>'invalid_id']);
        }

        if($userId !== $id){
            return new Response(403, 'Forbidden', false, ['error'=>'forbidden']);
        }

        $result = User::updateProfile($id, $request->getData());

        if(!$result){
            return new Response(400, 'User data update failed', false, ['error'=>'update_failed']);
        }

        return new Response(200, 'User data updated', true);

    }

    public function changePassword(Request $request, array $params):Response{
        $userId = Session::get('user_id', 0);
        if($userId <= 0){
            return new Response(401, 'Unauthorized', false, ['error'=>'unauthorized']);
        }

        $id = (int)($params['id'] ?? 0);
        if($id <= 0) {
            return new Response(400, 'Bad Request', false, ['error' => 'invalid_id']);
        }

        if($userId !== $id){
            return new Response(403, 'Forbidden', false, ['error'=>'forbidden']);
        }

        $payload = $request->getData();

        $currentPassword = trim($payload['current_password'] ?? '');
        $newPassword = trim($payload['new_password'] ?? '');

        if(empty($currentPassword) || empty($newPassword)){
            return new Response(422, 'Validation failed', false, ['missing'=>['current_password','new_password']]);
        }

        $user = User::find($id);
        if(!$user || !password_verify($currentPassword, $user->getPasswordHash())){
            return new Response(401, 'Invalid credentials', false, ['error'=>'wrong_password']);
        }

        User::changePassword($id, $newPassword);
        return new Response(200, 'Password changed', true);

    }

    public function deactivate(Request $request, array $params):Response{

        $userId = Session::get('user_id', 0);
        if($userId <= 0){
            return new Response(401, 'Unauthorized', false, ['error'=>'unauthorized']);
        }

        $id = (int)($params['id'] ?? 0);
        if($id <= 0) {
            return new Response(400, 'Bad Request', false, ['error' => 'invalid_id']);
        }
        if($userId !== $id){
            return new Response(403, 'Forbidden', false, ['error'=>'forbidden']);
        }

        User::deactivate($id);

        return new Response(200, 'User deactivated', true);
    }



}