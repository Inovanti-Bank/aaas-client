<?php

namespace App\Exceptions;

use Exception;

/**
 * Erro esperado de regra de negócio: o usuário tentou usar o IAaas
 * sem ter configurado a API Key e a chave privada pela interface.
 */
class MissingIaaasCredentialsException extends Exception {}
