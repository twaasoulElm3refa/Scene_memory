<?php

namespace App\Services;

use RuntimeException;

/** Provider data has not arrived in a complete form yet; it is not proof of a mismatch. */
class IncompletePayPalEvidenceException extends RuntimeException {}
