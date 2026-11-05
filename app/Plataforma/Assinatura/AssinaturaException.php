<?php

namespace App\Plataforma\Assinatura;

use RuntimeException;

/**
 * Erro previsto no fluxo de assinatura (a mensagem pode ser mostrada ao assinante).
 */
class AssinaturaException extends RuntimeException {}
