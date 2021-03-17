<?php

namespace App\Http\Controllers\API;

use App\Actions\Fortify\PasswordValidationRules;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Dotenv\Validator;
use Exception;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator as FacadesValidator;

class UserController extends Controller
{
    use PasswordValidationRules;
    public function login(Request $request)
    {
        try {
            // Validasi input
            $this->validate($request, [
                'email' => 'email|required',
                'password' => 'required'
            ]);

            // Mengecek credential login
            $credentials = request(['email', 'password']);
            if(!Auth::attempt($credentials)) {
                return ResponseFormatter::error([
                    'message' => 'Unauthorized'
                ], 'Authentication Failed', 500);
            }

            // Jika Hash tidak sesuai
            $user = User::where('email', $request->email)->first();
            if(!Hash::check($request->email, $user->email, [])) {
                throw new \Exception('Invalid Credentials');
            }

            // Jika berhasil maka loginkan
            $tokenResult = $user->createToken('authToken')->plainTextToken;
            return ResponseFormatter::success([
                'access_token' => $tokenResult,
                'token_type' => 'Bearer',
                'user' => $user
            ], 'Authenticated');

        } catch(Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error,
            ], 'Authentication failed', 500);
        }
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules()
        ]);
        try {
            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'address' => $request->address,
                'houseNumber' => $request->houseNumber,
                'phoneNumber' => $request->phoneNumber,
                'city' => $request->city,
                'password' => Hash::make($request->password),
            ]);

            $user = User::where('email', $request->email)->first();

            $tokenResult = $user->createToken('authToken')->plainTextToken;

            return ResponseFormatter::success([
                'access_token' => $tokenResult,
                'token_type' => 'Bearer',
                'user' => $user
            ]);

        } catch (Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error
            ], 'Authentication failed', 500);
        }
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken()->delete();
        return ResponseFormatter::success($token, 'Token Revoked');
    }

    public function fatch(Request $request) 
    {
        return ResponseFormatter::success(
            $request->user(), 
            'get user profile success'
        );
    }

    public function updateProfile(Request $request) 
    {
        $data = $request->all();

        $user = Auth::user();
        $user->update($data);
        return ResponseFormatter::success($user, 'Profile Updated');
    }

    public function updatePhoto(Request $request)
    {
        $validator = FacadesValidator::make($request->all(), [
            'file' => 'required|image|max:2048'
        ]);

        if($validator->fails()) {
            return ResponseFormatter::error(
                ['error' => $validator->errors()],
                'Update photo fails',
                401
            );
        }

        if($request->file('file')) {
            $file = $request->file->store('assets/user', 'public');
            
            $user = Auth::user();
            $user->profile_photo_path = $file;
            $user->udpate();

            return ResponseFormatter::success([$file], 'file successfully updated');
        }
    }
}
