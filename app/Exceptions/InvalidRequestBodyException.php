<?php

namespace App\Exceptions;

use Exception;

/**
 * Erro esperado de regra de negócio: o body informado pelo usuário não é um JSON válido.
 */
class InvalidRequestBodyException extends Exception {}
