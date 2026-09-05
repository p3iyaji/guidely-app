<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditEventType;
use App\Domain\Audit\AuditWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IssueTokenRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    private const AUTHENTICATION_FAILED_MESSAGE = 'These credentials do not match our records.';

    private const AUTHENTICATION_FAILED_CODE = 'authentication_failed';

    public function __construct(private AuditWriter $audit) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = [
            'email' => Str::lower($request->validated('email')),
            'password' => $request->validated('password'),
        ];

        if (! Auth::guard('web')->attempt($credentials)) {
            $this->audit->record(AuditEventType::LoginFailed, $request);

            return $this->authenticationFailedResponse();
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if ($user->isDeactivated()) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $this->audit->record(AuditEventType::LoginFailed, $request);

            return $this->authenticationFailedResponse();
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $this->audit->record(AuditEventType::LoginSuccess, $request, $user);

        return response()->json([
            'message' => 'Authenticated.',
        ]);
    }

    public function token(IssueTokenRequest $request): JsonResponse
    {
        $email = Str::lower($request->validated('email'));
        $user = User::query()->where('email', $email)->first();

        if (
            $user === null
            || $user->isDeactivated()
            || ! Hash::check($request->validated('password'), $user->password)
        ) {
            $this->audit->record(AuditEventType::LoginFailed, $request);

            return $this->authenticationFailedResponse();
        }

        $deviceName = $request->validated('device_name') ?: 'hybrid';
        $accessToken = $user->createToken($deviceName);

        $this->audit->record(AuditEventType::LoginSuccess, $request, $user);

        return response()->json([
            'token' => $accessToken->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->audit->record(AuditEventType::Logout, $request, $user);

        $currentToken = $user->currentAccessToken();

        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    private function authenticationFailedResponse(): JsonResponse
    {
        return response()->json([
            'message' => self::AUTHENTICATION_FAILED_MESSAGE,
            'code' => self::AUTHENTICATION_FAILED_CODE,
        ], 401);
    }
}
