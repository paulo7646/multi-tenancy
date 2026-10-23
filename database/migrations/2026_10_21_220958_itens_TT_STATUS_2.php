<?php

// Gerado pelo dicionário de dados: itens da tabela TT_STATUS.

use App\Sistema\Schema\ItensDaTabela;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ItensDaTabela::sincronizar('TT_STATUS', 'CODIGO', array (
  0 => 
  array (
    'CODIGO' => 2,
    'DESCRICAO' => 'Em análise',
  ),
  1 => 
  array (
    'CODIGO' => 3,
    'DESCRICAO' => 'Fechado',
  ),
));
    }

    public function down(): void
    {
        ItensDaTabela::sincronizar('TT_STATUS', 'CODIGO', array (
  0 => 
  array (
    'CODIGO' => 1,
    'DESCRICAO' => 'Aberto',
  ),
  1 => 
  array (
    'CODIGO' => 2,
    'DESCRICAO' => 'Em análise',
  ),
));
    }
};
