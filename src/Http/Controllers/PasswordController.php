<?php

namespace Libinkk\OneAuth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Libinkk\OneAuth\Actions\ChangePasswordAction;
use Libinkk\OneAuth\Actions\RequestPasswordResetAction;
use Libinkk\OneAuth\Actions\ResetPasswordAction;

class PasswordController
{
    public function __construct(
        private RequestPasswordResetAction $requestReset,
        private ResetPasswordAction $reset,
        private ChangePasswordAction $changePassword
    ) {
    }

    public function forgot(Request $request): JsonResponse
    {
        return response()->json(['status' => $this->requestReset->execute($request->all())]);
    }

    public function reset(Request $request): JsonResponse
    {
        return response()->json(['status' => $this->reset->execute($request->all())]);
    }

    public function change(Request $request): JsonResponse
    {
        return response()->json(['changed' => $this->changePassword->execute($request->all())]);
    }
}
