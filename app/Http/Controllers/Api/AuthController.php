<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function signup(Request $request){

        $validate = Validator::make($request->all(), [
            'username' => 'required|unique:users,username|min:4|max:60',
            'password' => 'required|min:5|max:20',
        ]);

        if($validate->fails()){
            $violations = [];

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

        $user = User::create([
            'username' => $request->username,
            'password' => $request->password

        ]);

        $token = $user->createToken('auth');

        return response()->json([
            'status' => 'success',
            'token' => $token->plainTextToken
        ]);

    }

    public function sign(Request $request){

        $credentials = $request->only('username', 'password');

        $validate = Validator::make($credentials, [
            'username' => 'required',
            'password' => 'required',
        ]);

        if($validate->fails()){
            $violations = [];

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


        if(!Auth::attempt($credentials) && !Auth::guard('admins')->attempt($credentials)){
            return response()->json([
                'status' => 'invalid',
                'message' => 'invalid username or password'
            ], 401);

        }

        if(Auth::guard('admins')->attempt($credentials)){
            $admin = $request->user('admins');

            return response()->json([
                'status' => 'success',
                'token' => $admin->createToken('admin')->plainTextToken,
                'role' => 'admin',
                'username' => $admin->username
            ], 200);
        }

        if(Auth::guard('web')->attempt($credentials)){
            $user = $request->user();

            return response()->json([
                'status' => 'success',
                'token' => $user->createToken('user')->plainTextToken,
                'role' => 'user',
                'username' => $user->username
            ], 200);
        }

    }

    public function logout(Request $request){

        $request->user()->currentAccessToken()->delete();

        return response()->json(['status' => 'success'], 200);

    }
}
