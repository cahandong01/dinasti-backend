<?php

namespace App\Modules\TenantRegion\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Login pakai 1 field "login" — bisa email ATAU username, dideteksi
     * otomatis lewat format input (riset: pola baku Laravel buat kasus
     * ini, bukan 2 field terpisah).
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validasi gagal.', 'errors' => $validator->errors()], 422);
        }

        $loginInput = $request->input('login');
        $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($field, $loginInput)->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Kredensial tidak cocok.'], 401);
        }

        $token = $user->createToken('api-token');

        return response()->json([
            'user' => $user->only(['id', 'name', 'username', 'email']),
            'token' => $token->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }
}