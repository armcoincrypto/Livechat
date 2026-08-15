<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function __construct()
    {
        // Resend remains authenticated. Signed verify callback must work cold
        // (email clients open links without a session).
        $this->middleware('auth')->only('resendMail');
        $this->middleware('throttle:6,1')->only('verify', 'resendMail');
    }

    public function verify(Request $request)
    {
        $id = $request->query('id');
        $hash = $request->query('hash');
        $expires = $request->query('expires');
        $signature = $request->query('signature');

        if (
            !is_string($id) ||
            !is_string($hash) ||
            !is_string($expires) ||
            !is_string($signature) ||
            $id === '' ||
            $hash === '' ||
            $expires === '' ||
            $signature === '' ||
            str_contains($hash, '?') ||
            str_contains($signature, '?')
        ) {
            return new JsonResponse([
                'status' => 1,
                'code' => 'INVALID_SIGNATURE',
                'message' => 'Invalid verification link.',
            ], 403);
        }

        $params = [
            'id' => $id,
            'hash' => $hash,
            'expires' => $expires,
        ];

        ksort($params);

        $expectedSignature = hash_hmac('sha256', http_build_query($params), config('app.key'));

        if (!is_string($signature) || !hash_equals($expectedSignature, $signature)) {
            return new JsonResponse([
                'status' => 1,
                'code' => 'INVALID_SIGNATURE',
                'message' => 'Invalid verification link.',
            ], 403);
        }

        if (!ctype_digit((string) $expires) || now()->timestamp > (int) $expires) {
            return new JsonResponse([
                'status' => 1,
                'code' => 'EXPIRED',
                'message' => 'This verification link has expired.',
            ], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return new JsonResponse([
                'status' => 1,
                'code' => 'INVALID_SIGNATURE',
                'message' => 'Invalid verification link.',
            ], 403);
        }

        if (!hash_equals(sha1($user->email), $hash)) {
            return new JsonResponse([
                'status' => 1,
                'code' => 'INVALID_SIGNATURE',
                'message' => 'Invalid verification link.',
            ], 403);
        }

        // Session may exist after registration; never allow verifying a different user.
        $authUser = $request->user();
        if ($authUser && (int) $authUser->getKey() !== (int) $user->getKey()) {
            return new JsonResponse([
                'status' => 1,
                'code' => 'INVALID_SIGNATURE',
                'message' => 'Invalid verification link.',
            ], 403);
        }

        $alreadyVerified = $user->hasVerifiedEmail();
        if (!$alreadyVerified) {
            $user->markEmailAsVerified();
        }

        return new JsonResponse([
            'status' => 0,
            'already_verified' => $alreadyVerified,
        ], 202);
    }

    public function resendMail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return new JsonResponse([], 204);
        }

        $request->user()->sendEmailVerificationNotification();

        return new JsonResponse([], 202);
    }
}
