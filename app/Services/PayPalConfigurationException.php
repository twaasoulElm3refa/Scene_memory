<?php

namespace App\Services;

use RuntimeException;

/** Required local PayPal configuration is missing or invalid. */
class PayPalConfigurationException extends RuntimeException {}
