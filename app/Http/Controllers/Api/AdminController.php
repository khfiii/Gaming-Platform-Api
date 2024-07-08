<?php

namespace App\Http\Controllers\Api;

use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Administrator;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function index(Request $request)
    {

        if ($request->user()->currentAccessToken()->name !== 'admin') {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ], 403);
        }

        $admins = Administrator::select(['username', 'last_login_at', 'created_at', 'updated_at'])->get();
        $countAdmins = Administrator::all()->count();

        return response()->json([
            'totalElements' => $countAdmins,
            'content' => $admins
        ], 200);
    }

    public function createUser(Request $request)
    {

        if ($request->user()->currentAccessToken()->name !== 'admin') {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ], 403);
        }

        $credentials = $request->only('username', 'password');

        $validate = Validator::make($credentials, [
            'username' => 'required|unique:users,username|min:4|max:60',
            'password' => 'required|min:5|max:20',
        ]);

        if ($validate->fails()) {
            foreach ($validate->errors()->toArray() as $key => $error) {
                $violations[$key] = [
                    'message' => implode(',', $error)
                ];
            }

            return response()->json([
                "status" => 'invalid',
                "message" => "Request body is not valid.",
                "violations" => $violations

            ], 400);
        }

        $user = User::create($credentials);

        return response()->json([
            'status' => 'success',
            'usuername' => $user->username
        ], 201);
    }

    public function getUsers(Request $request)
    {
        if ($request->user()->currentAccessToken()->name !== 'admin') {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ], 403);
        }

        $users = User::select('id','username', 'last_login_at', 'created_at', 'updated_at')->get();
        $countUsers = User::all()->count();

        return response()->json([
            'totalElements' => $countUsers,
            'content' => $users
        ], 200);
    }
    public function updateUser(Request $request, string $id)
    {

        if ($request->user()->currentAccessToken()->name !== 'admin') {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ], 403);
        }


        $user = User::find($id);

        if (is_null($user)) {
            return response()->json([
                "status" => "not-found",
                "message" => "User Not found"

            ], 400);
        }

        $credentials = $request->only('username', 'email');

        $user->update($credentials);

        return response()->json([
            'status' => 'success',
            'message' => 'user updated'
        ], 200);
    }
    public function deleteUser(Request $request, string $id)
    {

        if ($request->user()->currentAccessToken()->name !== 'admin') {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ], 403);
        }


        $user = User::find($id);

        if (is_null($user)) {
            return response()->json([
                "status" => "not-found",
                "message" => "User Not found"

            ], 400);
        }

        Game::where('created_by', $user->id)->get()->each(function (Game $game) {
            $game->delete();
        });

        $user->delete();

        return response()->json(status: 204);
    }

}
