<?php

namespace App\Services\Assistant;

use RuntimeException;

/**
 * The AI service cannot be reached or is switched off. The message is safe to show.
 */
class AssistantUnavailable extends RuntimeException {}
