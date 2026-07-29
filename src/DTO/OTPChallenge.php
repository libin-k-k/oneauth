<?php

namespace Libinkk\OneAuth\DTO;

class OTPChallenge
{
    public function __construct(
        public string $purpose,
        public string $channel,
        public string $target
    ) {
    }
}
