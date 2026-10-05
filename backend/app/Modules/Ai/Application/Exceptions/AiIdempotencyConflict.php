<?php

namespace App\Modules\Ai\Application\Exceptions;

use RuntimeException;

final class AiIdempotencyConflict extends RuntimeException {}
