<?php

namespace App\Models\Md;

use App\Sistema\Enums\Cor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MdStatus extends Model
{
    protected $connection = 'central';

    protected $table = 'md_status';

    protected $fillable = ['nome', 'nome_fisico', 'icone', 'cor', 'descricao'];

    public function tabelas(): HasMany
    {
        return $this->hasMany(MdTabelaStatus::class);
    }

    public function corFilament(): string|array
    {
        return Cor::resolver($this->cor);
    }
}
