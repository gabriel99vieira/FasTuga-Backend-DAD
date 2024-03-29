<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\CustomersController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\StoreCustomerRequest;

class AuthenticationController extends Controller
{

    private string $tokenName = "FasTugaToken";

    /**
     * Registers new customer
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function register(StoreCustomerRequest $request)
    {
        DB::transaction(function () use ($request) {
            CustomersController::createCustomer($request);
        });

        return response()->json(['message' => 'Register successful'], 200);
    }

    /**
     * Provides user login token
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        if (auth()->attempt([
            'email' => $request->email,
            'password' => $request->password
        ])) {
            /** @var User $user */
            $user = auth()->user();
            $token = $user->createToken($this->tokenName)->accessToken;
            return (new UserResource($user))->additional(['token' => $token]);
        }

        return response()->json(['message' => 'Authentication has failed!'], 401);
    }

    /**
     * Logout the user
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        DB::transaction(function () use ($request) {
            $token = $request->user()->token();
            $token->revoke();
            $token->delete();
        });

        return response()->json(['message' => 'You have been successfully logged out!'], 200);
    }
}
