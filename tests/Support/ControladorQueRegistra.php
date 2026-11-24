<?php

namespace Tests\Support;

use App\Sistema\Base\BaseController;
use Illuminate\Database\Eloquent\Model;

/**
 * Controller de teste: anota a ordem em que os ganchos rodam e cancela quando o registro pede.
 */
class ControladorQueRegistra extends BaseController
{
    /** @var list<string> */
    public static array $log = [];

    protected function beforeBeforePost(Model $registro, array $dados): ?bool
    {
        static::$log[] = 'beforeBeforePost';

        return null;
    }

    protected function beforePost(Model $registro): ?bool
    {
        static::$log[] = 'beforePost';

        return null;
    }

    protected function beforeInsert(Model $registro): ?bool
    {
        static::$log[] = 'beforeInsert';

        return $registro->getAttribute('NOME') === 'CANCELAR' ? false : null;
    }

    protected function beforeUpdate(Model $registro): ?bool
    {
        static::$log[] = 'beforeUpdate';

        return null;
    }

    protected function beforeBeforeDelete(Model $registro): ?bool
    {
        static::$log[] = 'beforeBeforeDelete';

        return null;
    }

    protected function beforeDelete(Model $registro): ?bool
    {
        static::$log[] = 'beforeDelete';

        return null;
    }

    protected function afterInsert(Model $registro): void
    {
        static::$log[] = 'afterInsert';
    }

    protected function afterUpdate(Model $registro): void
    {
        static::$log[] = 'afterUpdate';
    }

    protected function afterPost(Model $registro): void
    {
        static::$log[] = 'afterPost';
    }

    protected function afterDelete(Model $registro): void
    {
        static::$log[] = 'afterDelete';
    }

    protected function afterAfterInsert(Model $registro): void
    {
        static::$log[] = 'afterAfterInsert';
    }

    protected function afterAfterUpdate(Model $registro): void
    {
        static::$log[] = 'afterAfterUpdate';
    }

    protected function afterAfterPost(Model $registro): void
    {
        static::$log[] = 'afterAfterPost';
    }

    protected function afterAfterDelete(Model $registro): void
    {
        static::$log[] = 'afterAfterDelete';
    }
}
