<?php

namespace App\Plataforma\Documentos;

use RuntimeException;

/**
 * Erro previsto ao guardar um arquivo (extensão bloqueada, tamanho, cota...).
 */
class DocumentoException extends RuntimeException {}
