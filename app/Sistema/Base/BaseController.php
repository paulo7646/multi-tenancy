<?php

namespace App\Sistema\Base;

use App\Http\Controllers\Controller;
use App\Sistema\Base\Concerns\Utilidades;

/**
 * Base para controllers que usam as tabelas dos módulos
 * ($this->getModel('op', 'usuario'), $this->abortar(...), etc.).
 */
abstract class BaseController extends Controller
{
    use Utilidades;
}
