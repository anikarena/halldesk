<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule was broken (seat already taken, meals locked, amount too high...).
 * The message is safe to show to the user. bootstrap/app.php turns it into a
 * redirect back with an error message.
 */
class HallException extends RuntimeException
{
}
