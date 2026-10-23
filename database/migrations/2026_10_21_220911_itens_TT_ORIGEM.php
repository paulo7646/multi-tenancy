<?php

// Gerado pelo dicionário de dados: itens da tabela TT_ORIGEM.

use App\Sistema\Schema\ItensDaTabela;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ItensDaTabela::sincronizar('TT_ORIGEM', 'VALOR', array (
  0 => 
  array (
    'VALOR' => 'WEB',
    'DESCRICAO' => 'Site',
  ),
  1 => 
  array (
    'VALOR' => 'LOJA',
    'DESCRICAO' => 'Loja física',
  ),
));
    }

    public function down(): void
    {
        ItensDaTabela::sincronizar('TT_ORIGEM', 'VALOR', array (
));
    }
};
