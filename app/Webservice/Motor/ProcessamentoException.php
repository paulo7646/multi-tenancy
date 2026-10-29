<?php

namespace App\Webservice\Motor;

use RuntimeException;

/**
 * Erro previsto no processamento (objeto obrigatório ausente, registro não encontrado...).
 */
class ProcessamentoException extends RuntimeException {}
